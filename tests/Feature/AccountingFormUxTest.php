<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use App\Support\SaveAction;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accounting UX batch 1: every draft CRUD form in Accounting uses the shared
 * save workflow (Save / Save & New / Save & Close / Cancel) through
 * App\Support\SaveAction and the form-actions component. Business actions
 * (approve, post, pay, receive, finalize) are untouched.
 */
class AccountingFormUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function origin(): string
    {
        return '/admin/accounting/dashboard';
    }

    // ------------------------------------------------------------- payloads

    private function accountPayload(array $extra = []): array
    {
        return $extra + ['account_code' => '5997', 'account_name' => 'UX Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'opening_balance' => 0, 'status' => 'active'];
    }

    private function costCenterPayload(array $extra = []): array
    {
        return $extra + ['code' => 'CC-UX', 'name' => 'UX cost center', 'type' => 'department', 'status' => 'active'];
    }

    private function rulePayload(array $extra = []): array
    {
        return $extra + ['source_module' => 'Manual', 'trigger_event' => 'UX Event', 'cost_center_rule' => 'None', 'auto_post' => 1, 'status' => 'active'];
    }

    private function journalPayload(array $extra = []): array
    {
        return $extra + [
            'journal_date' => now()->toDateString(), 'source_module' => 'Manual', 'status' => 'draft', 'description' => 'UX journal',
            'lines' => [
                ['chart_of_account_id' => $this->account(PostingService::MATERIAL_EXPENSE)->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $this->account(PostingService::BANK)->id, 'debit' => 0, 'credit' => 100],
            ],
        ];
    }

    private function billPayload(array $extra = []): array
    {
        return $extra + [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => 'BILL-UX', 'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100]],
        ];
    }

    private function invoicePayload(array $extra = []): array
    {
        return $extra + [
            'customer_id' => Customer::firstOrFail()->id, 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Claim', 'quantity' => 1, 'unit_price' => 100]],
        ];
    }

    /**
     * Each converted form: [store route, update route, payload builder, record finder, stay route for create, stay route for edit, index route, create route].
     *
     * @return array<string, array>
     */
    private function forms(): array
    {
        return [
            'chart-of-accounts' => ['admin.accounting.chart-of-accounts', fn () => $this->accountPayload(), fn () => ChartOfAccount::where('account_code', '5997')->firstOrFail(), 'edit'],
            'cost-centers' => ['admin.accounting.cost-centers', fn () => $this->costCenterPayload(), fn () => CostCenter::where('code', 'CC-UX')->firstOrFail(), 'edit'],
            'posting-rules' => ['admin.accounting.posting-rules', fn () => $this->rulePayload(), fn () => AutomaticPostingRule::where('trigger_event', 'UX Event')->firstOrFail(), 'edit'],
            'journal-entries' => ['admin.accounting.journal-entries', fn () => $this->journalPayload(), fn () => JournalEntry::where('description', 'UX journal')->latest('id')->firstOrFail(), 'show'],
            'accounts-payable' => ['admin.accounting.accounts-payable', fn () => $this->billPayload(), fn () => SupplierBill::where('bill_number', 'BILL-UX')->firstOrFail(), 'show'],
            'accounts-receivable' => ['admin.accounting.accounts-receivable', fn () => $this->invoicePayload(), fn () => CustomerInvoice::latest('id')->firstOrFail(), 'show'],
        ];
    }

    // ---------------------------------------------------------------- tests

    public function test_save_keeps_the_user_on_the_record_for_every_converted_form(): void
    {
        foreach ($this->forms() as $name => [$base, $payload, $find, $stayView]) {
            $this->actingAs($this->admin())->post(route($base.'.store'), $payload() + [SaveAction::FIELD => SaveAction::STAY])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route($base.'.'.$stayView, $find()));

            // Editing and saving again also stays on the record.
            $record = $find();
            $this->actingAs($this->admin())->put(route($base.'.update', $record), $payload() + [SaveAction::FIELD => SaveAction::STAY])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route($base.'.'.$stayView, $record), "{$name}: update + Save");
        }
    }

    public function test_save_and_close_returns_to_the_list_or_to_the_origin_the_user_came_from(): void
    {
        foreach ($this->forms() as $name => [$base, $payload, $find]) {
            // Default (no action, or explicit close) is the list.
            $this->actingAs($this->admin())->post(route($base.'.store'), $payload())
                ->assertSessionHasNoErrors()
                ->assertRedirect(route($base.'.index'));

            // With a known origin, close returns there.
            $record = $find();
            $this->actingAs($this->admin())->put(route($base.'.update', $record), $payload() + [SaveAction::FIELD => SaveAction::CLOSE, SaveAction::RETURN_FIELD => $this->origin()])
                ->assertSessionHasNoErrors()
                ->assertRedirect($this->origin());

            // An origin outside the admin area is ignored, never followed.
            foreach (['https://evil.example/x', '//evil.example', '/login', '/admin\\\\evil'] as $bad) {
                $this->actingAs($this->admin())->put(route($base.'.update', $record), $payload() + [SaveAction::FIELD => SaveAction::CLOSE, SaveAction::RETURN_FIELD => $bad])
                    ->assertRedirect(route($base.'.index'));
            }
        }
    }

    public function test_save_and_new_opens_a_fresh_create_form_and_keeps_the_origin(): void
    {
        foreach ($this->forms() as $name => [$base, $payload, $find]) {
            $this->actingAs($this->admin())->post(route($base.'.store'), $payload() + [SaveAction::FIELD => SaveAction::NEW])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route($base.'.create'));
            $find();   // the record was saved

            $record = $find();
            $this->actingAs($this->admin())->put(route($base.'.update', $record), $payload() + [SaveAction::FIELD => SaveAction::NEW, SaveAction::RETURN_FIELD => $this->origin()])
                ->assertRedirect(route($base.'.create', ['return_to' => $this->origin()]));
        }
    }

    public function test_validation_failure_preserves_the_entered_data_and_the_chosen_action(): void
    {
        $this->actingAs($this->admin())->from(route('admin.accounting.accounts-payable.create'))
            ->post(route('admin.accounting.accounts-payable.store'), $this->billPayload(['bill_number' => '', SaveAction::FIELD => SaveAction::STAY, SaveAction::RETURN_FIELD => $this->origin()]))
            ->assertRedirect(route('admin.accounting.accounts-payable.create'))
            ->assertSessionHasErrors('bill_number')
            ->assertSessionHasInput('lines.0.description', 'Service')
            ->assertSessionHasInput(SaveAction::RETURN_FIELD, $this->origin());

        $this->actingAs($this->admin())->from(route('admin.accounting.cost-centers.create'))
            ->post(route('admin.accounting.cost-centers.store'), $this->costCenterPayload(['type' => 'planet']))
            ->assertSessionHasErrors('type')
            ->assertSessionHasInput('name', 'UX cost center');

        // The re-rendered form carries the values and the origin back into the fields.
        $page = $this->actingAs($this->admin())
            ->withSession(['_old_input' => $this->costCenterPayload([SaveAction::RETURN_FIELD => $this->origin()])])
            ->get(route('admin.accounting.cost-centers.create'))->assertOk();
        $page->assertSee('value="UX cost center"', false);
        $page->assertSee('name="'.SaveAction::RETURN_FIELD.'" value="'.$this->origin().'"', false);
        $page->assertSee('href="'.$this->origin().'"', false);   // Cancel goes back to the origin
    }

    public function test_every_converted_form_renders_the_shared_actions_and_cancel_targets_the_list(): void
    {
        foreach ($this->forms() as $name => [$base]) {
            $page = $this->actingAs($this->admin())->get(route($base.'.create'))->assertOk();
            $page->assertSee('name="'.SaveAction::FIELD.'" value="stay"', false);
            $page->assertSee('name="'.SaveAction::FIELD.'" value="new"', false);
            $page->assertSee('name="'.SaveAction::FIELD.'" value="close"', false);
            $page->assertSee('data-save-default', false);
            $page->assertSee('href="'.route($base.'.index').'"', false);
        }

        // The reference Employee form gained Save & New as well.
        $this->actingAs($this->admin())->get(route('admin.hr.employees.create'))->assertOk()
            ->assertSee('name="_save_action" value="new"', false);
    }

    public function test_business_actions_are_unchanged_by_the_save_workflow(): void
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), $this->billPayload())->assertSessionHasNoErrors();
        $bill = SupplierBill::where('bill_number', 'BILL-UX')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill), [SaveAction::FIELD => SaveAction::NEW])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.accounting.accounts-payable.show', $bill));
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertNotNull($bill->fresh()->journal_entry_id);

        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $this->journalPayload())->assertSessionHasNoErrors();
        $entry = JournalEntry::where('description', 'UX journal')->latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.post', $entry), [SaveAction::FIELD => SaveAction::CLOSE])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.accounting.journal-entries.show', $entry));
        $this->assertSame('posted', $entry->fresh()->status);

        // An approved bill still cannot be edited, whatever save action is sent.
        $this->actingAs($this->admin())->put(route('admin.accounting.accounts-payable.update', $bill), $this->billPayload([SaveAction::FIELD => SaveAction::STAY]))
            ->assertSessionHasErrors('bill');
    }

    public function test_supplier_bill_created_from_a_goods_receipt_keeps_its_matching_and_closes_back_to_the_receipt(): void
    {
        $item = Item::create([
            'item_code' => 'ITM-UX', 'name' => 'UX item', 'valuation_method' => 'average',
            'inventory_account_id' => $this->account(PostingService::INVENTORY_ASSET)->id,
            'expense_account_id' => $this->account(PostingService::MATERIAL_EXPENSE)->id, 'status' => 'active',
        ]);
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'warehouse_id' => Warehouse::firstOrFail()->id, 'received_date' => now()->toDateString(),
            'delivery_note_number' => 'DN-UX', 'vat_rate' => 15,
            'lines' => [['item_id' => $item->id, 'received_quantity' => 4, 'accepted_quantity' => 4, 'unit_cost' => 50]],
        ])->assertSessionHasNoErrors();
        $grn = GoodsReceipt::where('delivery_note_number', 'DN-UX')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))->assertSessionHasNoErrors();
        $grnPage = route('admin.inventory.goods-receipts.show', $grn, false);

        // The receipt page links to a pre-filled bill that will close back to the receipt.
        $this->actingAs($this->admin())->get(route('admin.inventory.goods-receipts.show', $grn))->assertOk()
            ->assertSee('return_to='.rawurlencode($grnPage), false);
        $create = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-payable.create', ['goods_receipt' => $grn->id, 'return_to' => $grnPage]))->assertOk();
        $create->assertSee('name="'.SaveAction::RETURN_FIELD.'" value="'.$grnPage.'"', false);

        $line = $grn->lines()->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), $this->billPayload([
            SaveAction::FIELD => SaveAction::CLOSE, SaveAction::RETURN_FIELD => $grnPage,
            'lines' => [['description' => 'UX item', 'goods_receipt_line_id' => $line->id, 'matched_quantity' => 4, 'quantity' => 4, 'unit_price' => 50]],
        ]))->assertSessionHasNoErrors()->assertRedirect($grnPage);

        $bill = SupplierBill::where('bill_number', 'BILL-UX')->firstOrFail();
        $this->assertSame(1, $bill->grnMatches()->count());
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->assertSame(4.0, (float) $line->fresh()->invoiced_quantity);
        $codes = $bill->fresh()->journalEntry->lines()->with('account')->get()->pluck('account.account_code')->all();
        $this->assertContains(PostingService::GRNI, $codes);
        $this->assertNotContains(PostingService::MATERIAL_EXPENSE, $codes);
    }

    public function test_permissions_are_still_enforced_on_the_converted_forms(): void
    {
        $role = Role::create(['name' => 'Viewer', 'code' => 'AP_VIEWER_UX', 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', ['Accounts Payable', 'Cost Centers', 'Journal Entries'])->where('action', 'view')->pluck('id'));
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer-ux@example.test', 'username' => 'viewer.ux', 'password' => 'a-strong-password-123', 'status' => 'active']);
        $viewer->roles()->attach($role, ['is_primary' => true]);
        $viewer = $viewer->fresh();

        $this->actingAs($viewer)->post(route('admin.accounting.accounts-payable.store'), $this->billPayload([SaveAction::FIELD => SaveAction::STAY]))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.accounting.cost-centers.store'), $this->costCenterPayload([SaveAction::FIELD => SaveAction::NEW]))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.accounting.journal-entries.store'), $this->journalPayload())->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.accounting.chart-of-accounts.create'))->assertForbidden();
        $this->assertSame(0, SupplierBill::where('bill_number', 'BILL-UX')->count());
        $this->assertSame(0, CostCenter::where('code', 'CC-UX')->count());
    }
}
