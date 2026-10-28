<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\JournalEntryLine;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\VatTransaction;
use App\Services\Accounting\CustomerNoteService;
use App\Services\Accounting\SalesVatPolicy;
use App\Services\Zatca\ZatcaSubmissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\OnboardsZatcaUnit;
use Tests\TestCase;

/**
 * Sales VAT authorization (client requirement R22-02, sales side): 15 % by
 * default, any other rate only by a Super Admin with a logged reason, forged
 * line rates refused, posted invoices immutable, notes and GL / VAT consistent.
 */
class SalesVatAuthorizationTest extends TestCase
{
    use OnboardsZatcaUnit;
    use RefreshDatabase;

    private Project $project;

    private Project $otherProject;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        config(['zatca.environment' => 'sandbox', 'zatca.production_enabled' => false, 'zatca.endpoints.sandbox' => self::SANDBOX]);
        CompanyProfile::query()->update(['name' => 'Seera Construction Co.', 'name_ar' => 'شركة سيرا', 'vat_number' => '300123456700003', 'cr_number' => '1010123456',
            'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya', 'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN']);
        $this->project = Project::create(['name' => 'VAT Tower', 'code' => 'PRJ-SVAT', 'status' => 'active']);
        $this->otherProject = Project::create(['name' => 'Other Site', 'code' => 'PRJ-SVAT-O', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Riyadh Developers LLC', 'code' => 'CUS-SVAT', 'type' => 'Company', 'vat_number' => '300987654300003', 'cr_number' => '1010654321',
            'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
    }

    /** An ordinary AR user (not Super Admin) with every Accounts Receivable action. */
    private function arUser(?Project $project = null): User
    {
        $suffix = 'svat-'.++$this->seq;
        $role = Role::create(['name' => 'Role '.$suffix, 'code' => 'SVAT_'.$this->seq, 'level' => 3, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', ['Accounts Receivable', 'Customers', 'Projects'])->pluck('id')->all());
        $user = User::create(['name' => 'User '.$suffix, 'email' => $suffix.'@example.test', 'username' => $suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'supply_date' => now()->toDateString(), 'project_id' => $this->project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'invoice_type' => 'standard',
            'lines' => [['description' => 'Concrete works', 'quantity' => 10, 'unit_price' => 100], ['description' => 'Steel works', 'quantity' => 5, 'unit_price' => 200]],
        ];
    }

    public function test_ordinary_sales_user_saves_the_standard_rate_with_or_without_sending_it(): void
    {
        $user = $this->arUser();
        $this->assertFalse($user->isSuperAdmin());
        $this->actingAs($user)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 15]))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('admin.accounting.accounts-receivable.store'), $this->payload())->assertSessionHasNoErrors();
        foreach (CustomerInvoice::latest('id')->limit(2)->get() as $invoice) {
            $this->assertSame('15.00', $invoice->vat_rate);
            $this->assertSame('300.00', $invoice->vat_amount);
            $this->assertSame('2300.00', $invoice->total_amount);
            $this->assertTrue($invoice->lines->every(fn ($l) => $l->vat_rate === '15.00'));
        }
        $this->assertDatabaseMissing('activity_logs', ['action' => SalesVatPolicy::ACTION]);
    }

    public function test_ordinary_user_cannot_save_five_percent_or_zero_even_with_a_reason_or_forged_flags(): void
    {
        $user = $this->arUser();
        $before = CustomerInvoice::count();
        foreach ([5, 0, 14.99] as $rate) {
            $this->actingAs($user)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => $rate, 'vat_override_reason' => 'Client agreed', 'is_super_admin' => true, 'super_admin' => 1]))
                ->assertSessionHasErrors('vat_rate');
        }
        $this->assertSame($before, CustomerInvoice::count());
        $this->assertDatabaseMissing('activity_logs', ['action' => SalesVatPolicy::ACTION]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Created customer invoice']);
    }

    public function test_forged_line_vat_rates_and_excess_precision_are_refused_for_everyone(): void
    {
        foreach ([$this->arUser(), $this->admin()] as $actor) {
            $payload = $this->payload(['vat_rate' => 15]);
            $payload['lines'][0]['vat_rate'] = 5;
            $this->actingAs($actor)->post(route('admin.accounting.accounts-receivable.store'), $payload)->assertSessionHasErrors('lines.0.vat_rate');
            $this->actingAs($actor)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => '15.0001']))->assertSessionHasErrors('vat_rate');
        }
        // A line rate that merely repeats the header is accepted.
        $before = CustomerInvoice::count();
        $payload = $this->payload(['vat_rate' => 15]);
        $payload['lines'][1]['vat_rate'] = '15.00';
        $this->actingAs($this->arUser())->post(route('admin.accounting.accounts-receivable.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame($before + 1, CustomerInvoice::count());
    }

    public function test_super_admin_override_needs_a_reason_and_is_audited_on_create_and_update(): void
    {
        $admin = $this->admin();
        $this->assertTrue($admin->isSuperAdmin());
        $before = CustomerInvoice::count();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 5]))->assertSessionHasErrors('vat_override_reason');
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 5, 'vat_override_reason' => '   ']))->assertSessionHasErrors('vat_override_reason');
        $this->assertSame($before, CustomerInvoice::count());
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 5, 'vat_override_reason' => 'Transitional rate agreed in contract C-17']))->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->assertSame(['5.00', '100.00', '2100.00'], [$invoice->vat_rate, $invoice->vat_amount, $invoice->total_amount]);
        $log = ActivityLog::where('action', SalesVatPolicy::ACTION)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertStringContainsString($invoice->invoice_number, $log->description);
        $this->assertStringContainsString('Transitional rate agreed in contract C-17', $log->description);
        $this->assertStringContainsString('"requested":5', $log->description);
        // Editing the draft: an ordinary user cannot keep the 5 % (the policy runs again); the Super Admin needs the reason again.
        $this->actingAs($this->arUser())->put(route('admin.accounting.accounts-receivable.update', $invoice), $this->payload(['vat_rate' => 5, 'vat_override_reason' => 'x']))->assertSessionHasErrors('vat_rate');
        $this->actingAs($admin)->put(route('admin.accounting.accounts-receivable.update', $invoice), $this->payload(['vat_rate' => 5]))->assertSessionHasErrors('vat_override_reason');
        $this->actingAs($admin)->put(route('admin.accounting.accounts-receivable.update', $invoice), $this->payload(['vat_rate' => 5, 'vat_override_reason' => 'Still per contract C-17']))->assertSessionHasNoErrors();
        $this->assertSame(2, ActivityLog::where('action', SalesVatPolicy::ACTION)->count());
        $this->assertSame('5.00', $invoice->fresh()->vat_rate);
        // Returning to the standard rate needs no reason and leaves no override row.
        $this->actingAs($this->arUser())->put(route('admin.accounting.accounts-receivable.update', $invoice), $this->payload(['vat_rate' => 15]))->assertSessionHasNoErrors();
        $this->assertSame('15.00', $invoice->fresh()->vat_rate);
        $this->assertSame(2, ActivityLog::where('action', SalesVatPolicy::ACTION)->count());
        // The reason is never stored on the invoice itself.
        $this->assertArrayNotHasKey('vat_override_reason', $invoice->fresh()->getAttributes());
    }

    public function test_sales_has_no_tax_classification_so_zero_rate_is_an_override_and_cannot_be_e_invoiced_as_standard_category(): void
    {
        // Documented limitation: no customer / line classification (zero-rated, exempt, out of scope) exists on the sales side.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('customers', 'tax_treatment'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('customer_invoice_lines', 'vat_category'));
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 0, 'vat_override_reason' => 'Export supply — classification pending Finance']))->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->assertSame(['0.00', '0.00', '2000.00'], [$invoice->vat_rate, $invoice->vat_amount, $invoice->total_amount]);
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(0, VatTransaction::where('source_module', 'Customer Invoice')->where('source_id', $invoice->id)->count(), 'no VAT row for a zero-rated invoice');
        $this->unit = $this->onboardedUnit();
        $record = app(ZatcaSubmissionService::class)->generate($invoice->fresh(['lines', 'zatcaRecord']), $admin);
        $this->assertSame('validation_failed', $record->status, 'category S at 0% is refused locally (BR-S-05); a proper category model is a separate decision');
        $this->assertSame('standard_rate_zero', $record->validation_errors[0]['code']);
    }

    public function test_posted_invoice_is_immutable_and_its_notes_inherit_the_authorized_rate(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 5, 'vat_override_reason' => 'Contract C-17']))->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.accounting.accounts-receivable.update', $invoice), $this->payload(['vat_rate' => 15]))->assertSessionHasErrors('invoice');
        $this->assertSame('5.00', $invoice->fresh()->vat_rate);
        // A credit note inherits 5 % without any override input; totals and VAT row follow it.
        $notes = app(CustomerNoteService::class);
        $note = $notes->post($notes->createDraft($invoice->fresh(['lines']), 'credit', ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'Returned goods'], [['invoice_line_id' => $invoice->lines[0]->id, 'quantity' => 2, 'unit_price' => 100]], $admin), $admin);
        $this->assertSame(['5.00', '200.00', '10.00', '210.00'], [$note->vat_rate, $note->taxable_amount, $note->vat_amount, $note->total_amount]);
        $row = VatTransaction::where('source_module', 'Customer Credit Note')->where('source_id', $note->id)->firstOrFail();
        $this->assertSame([-10.0, 5.0], [(float) $row->vat_amount, (float) $row->vat_rate]);
        $this->assertSame(1, ActivityLog::where('action', SalesVatPolicy::ACTION)->count(), 'the note needs no new override');
    }

    public function test_gl_and_vat_register_reconcile_for_an_authorized_override(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 5, 'vat_override_reason' => 'Contract C-17']))->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $entry = $invoice->fresh()->journalEntry;
        $this->assertSame('posted', $entry->status);
        $this->assertSame(2100.0, (float) $entry->total_debit);
        $this->assertSame((float) $entry->total_debit, (float) $entry->total_credit);
        $vatLine = JournalEntryLine::where('journal_entry_id', $entry->id)->whereHas('account', fn ($q) => $q->where('account_code', '2210'))->firstOrFail();
        $this->assertSame(100.0, (float) $vatLine->credit);
        $row = VatTransaction::where('source_module', 'Customer Invoice')->where('source_id', $invoice->id)->firstOrFail();
        $this->assertSame([100.0, 2000.0, 5.0, 'output'], [(float) $row->vat_amount, (float) $row->taxable_amount, (float) $row->vat_rate, $row->vat_type]);
    }

    public function test_project_scope_and_permissions_still_apply_to_the_vat_path(): void
    {
        $before = CustomerInvoice::count();
        $outsider = $this->arUser($this->otherProject);
        $this->actingAs($outsider)->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 15]))->assertForbidden();
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer-svat@example.test', 'username' => 'viewer-svat', 'password' => 'a-strong-password-123', 'status' => 'active']);
        $role = Role::create(['name' => 'Viewer role', 'code' => 'SVAT_VIEW', 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where('module', 'Accounts Receivable')->where('action', 'view')->pluck('id')->all());
        $viewer->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($viewer->fresh())->post(route('admin.accounting.accounts-receivable.store'), $this->payload(['vat_rate' => 15]))->assertForbidden();
        $this->assertSame($before, CustomerInvoice::count());
    }
}
