<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\LookupValue;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFeedbackSeptember22Test extends TestCase
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

    private function payload(array $extra = []): array
    {
        return $extra + ['name' => 'September Customer', 'type' => 'Company', 'status' => 'active'];
    }

    public function test_locale_is_persisted_allowlisted_and_sets_arabic_direction(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->from(route('admin.master.customers.index'))
            ->post(route('admin.locale.update'), ['locale' => 'ar'])
            ->assertRedirect(route('admin.master.customers.index'));
        $this->assertSame('Arabic', $admin->fresh()->language);
        $this->get(route('admin.master.customers.create'))->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)->assertSee('جهات اتصال المكتب وموقعه');
        $this->post(route('admin.locale.update'), ['locale' => 'ur'])->assertSessionHasErrors('locale');
        $this->post(route('admin.locale.update'), ['locale' => 'en'])->assertRedirect();
        $this->get(route('admin.master.customers.create'))->assertOk()->assertSee('lang="en" dir="ltr"', false);
    }

    public function test_locale_does_not_redirect_to_external_referer(): void
    {
        $this->actingAs($this->admin())->from('https://outside.example/admin/users')
            ->post(route('admin.locale.update'), ['locale' => 'ar'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_colour_ratings_are_real_swatches_with_named_radio_options(): void
    {
        foreach (['customers', 'suppliers'] as $module) {
            $this->actingAs($this->admin())->get(route('admin.master.'.$module.'.create'))->assertOk()
                ->assertSee('rating-swatch rating-green', false)->assertSee('rating-swatch rating-amber', false)
                ->assertSee('rating-swatch rating-red', false)->assertSee('type="radio" name="rating" value="Green"', false);
        }
        $this->post(route('admin.master.customers.store'), $this->payload(['rating' => 'Amber']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'September Customer', 'rating' => 'Amber']);
    }

    public function test_customer_type_can_be_created_inline_and_used_in_create_edit_and_filters(): void
    {
        $this->actingAs($this->admin())->postJson(route('admin.master.lookup-values.store'), ['type' => 'customer_type', 'value' => 'Institution'])
            ->assertCreated()->assertJson(['id' => 'Institution', 'label' => 'Institution']);
        $this->post(route('admin.master.customers.store'), $this->payload(['type' => 'Institution']))->assertSessionHasNoErrors();
        $customer = Customer::where('name', 'September Customer')->firstOrFail();
        $this->get(route('admin.master.customers.edit', $customer))->assertOk()->assertSee('Institution');
        $this->get(route('admin.master.customers.index', ['type' => 'Institution']))->assertOk()->assertSee($customer->name);
        $this->postJson(route('admin.master.lookup-values.store'), ['type' => 'customer_type', 'value' => 'Institution'])->assertUnprocessable();
        $this->post(route('admin.master.customers.store'), $this->payload(['type' => 'Unregistered']))->assertSessionHasErrors('type');
    }

    public function test_supplier_permission_cannot_create_customer_types(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::create(['name' => 'Supplier Only', 'code' => 'SUPPLIER_ONLY_TEST', 'level' => 2, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where(fn ($q) => $q->where('module', 'Suppliers')->orWhere('module', 'Dashboard'))->pluck('id'));
        $user->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($user)->postJson(route('admin.master.lookup-values.store'), ['type' => 'customer_type', 'value' => 'Forbidden'])->assertForbidden();
        $this->assertFalse(LookupValue::where('type', 'customer_type')->where('value', 'Forbidden')->exists());
    }

    public function test_contacts_and_notes_save_with_customer_and_view_is_read_only(): void
    {
        $this->actingAs($this->admin())->post(route('admin.master.customers.store'), $this->payload([
            'new_contacts' => ['office' => ['name' => 'Office Person', 'address' => 'Office 12'], 'site' => ['name' => 'Site Person', 'address' => 'Gate 3']],
            'new_note' => 'Call before visiting', '_save_action' => 'stay',
        ]))->assertSessionHasNoErrors();
        $customer = Customer::where('name', 'September Customer')->firstOrFail();
        $this->assertSame(2, $customer->contacts()->count());
        $this->assertSame(1, $customer->notes()->count());
        $this->get(route('admin.master.customers.show', $customer))->assertOk()
            ->assertSee('Office Person')->assertSee('Call before visiting')
            ->assertDontSee('Add Contact')->assertDontSee('Add Note')
            ->assertDontSee(route('admin.master.customers.contacts.store', $customer), false)
            ->assertDontSee(route('admin.master.customers.notes.store', $customer), false);
        $this->get(route('admin.master.customers.edit', $customer))->assertOk()
            ->assertSee('name="new_contacts[office][name]"', false)
            ->assertSee('name="new_contacts[site][name]"', false)->assertSee('name="new_note"', false);
        $this->put(route('admin.master.customers.update', $customer), $this->payload(['_save_action' => 'stay']))
            ->assertRedirect(route('admin.master.customers.edit', $customer));
        $this->assertSame(2, $customer->contacts()->count());
        $this->assertSame(1, $customer->notes()->count());
    }

    public function test_invalid_child_data_does_not_partially_create_customer(): void
    {
        $this->actingAs($this->admin())->post(route('admin.master.customers.store'), $this->payload([
            'new_contacts' => ['office' => ['address' => 'Missing contact name']],
        ]))->assertSessionHasErrors('new_contacts.office.name');
        $this->assertDatabaseMissing('customers', ['name' => 'September Customer']);
    }

    public function test_contact_site_must_belong_to_selected_customer_on_both_paths(): void
    {
        $customer = Customer::create($this->payload(['name' => 'Unrelated Customer', 'code' => 'UNRELATED-22']));
        $site = Site::firstOrFail();
        $this->actingAs($this->admin())->put(route('admin.master.customers.update', $customer), $this->payload([
            'new_contacts' => ['site' => ['name' => 'Wrong site', 'site_id' => $site->id]],
        ]))->assertSessionHasErrors('new_contacts.site.site_id');
        $this->post(route('admin.master.customers.contacts.store', $customer), ['location_type' => 'site', 'name' => 'Wrong site', 'site_id' => $site->id])
            ->assertSessionHasErrors('site_id');
        $this->assertSame(0, $customer->contacts()->count());
    }

    public function test_cash_only_customer_rejects_bank_receipt_and_accepts_cash(): void
    {
        $customer = Customer::firstOrFail();
        $this->assertSame('Both', $customer->allowed_payment_types);
        $customer->update(['allowed_payment_types' => 'Cash']);
        $invoice = $this->invoice($customer);
        $bank = ChartOfAccount::where('account_code', '1120')->firstOrFail();
        $cash = ChartOfAccount::where('account_code', '1110')->firstOrFail();
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.receipt', $invoice))->assertOk()
            ->assertSee('value="'.$cash->id.'"', false)->assertDontSee('value="'.$bank->id.'"', false);
        $this->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
            'receipt_date' => today()->toDateString(), 'amount' => 10, 'receipt_account_id' => $bank->id, 'payment_method' => 'Bank Transfer',
        ])->assertSessionHasErrors('receipt_account_id');
        $this->assertSame(0, $invoice->receipts()->count());
        $this->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
            'receipt_date' => today()->toDateString(), 'amount' => 10, 'receipt_account_id' => $cash->id, 'payment_method' => 'Cash',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->receipts()->count());
        $this->assertNotNull($invoice->receipts()->first()->journal_entry_id);
    }

    public function test_bank_only_customer_rejects_cash_and_mismatched_method(): void
    {
        $customer = Customer::firstOrFail();
        $customer->update(['allowed_payment_types' => 'Bank']);
        $invoice = $this->invoice($customer);
        foreach ([['1110', 'Cash', 'receipt_account_id'], ['1120', 'Cash', 'payment_method']] as [$code, $method, $error]) {
            $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
                'receipt_date' => today()->toDateString(), 'amount' => 10,
                'receipt_account_id' => ChartOfAccount::where('account_code', $code)->firstOrFail()->id, 'payment_method' => $method,
            ])->assertSessionHasErrors($error);
        }
        $this->assertSame(0, $invoice->receipts()->count());
    }

    private function invoice(Customer $customer): CustomerInvoice
    {
        return CustomerInvoice::create([
            'customer_id' => $customer->id, 'invoice_number' => 'INV-SEPT22', 'invoice_date' => today(),
            'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115,
            'received_amount' => 0, 'balance_amount' => 115, 'payment_status' => 'unpaid', 'zatca_status' => 'pending',
        ]);
    }
}
