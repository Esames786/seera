<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReopenLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Build a real eligible posted invoice instead of relying on or skipping a demo seed. */
    private function eligibleInvoice(User $admin): CustomerInvoice
    {
        $this->actingAs($admin)
            ->post(route('admin.accounting.accounts-receivable.store'), [
                'customer_id' => Customer::firstOrFail()->id,
                'project_id' => Project::firstOrFail()->id,
                'invoice_date' => now()->toDateString(),
                'supply_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'invoice_type' => 'standard',
                'vat_rate' => 15,
                'lines' => [['description' => 'Reopen dialog QA invoice', 'quantity' => 2, 'unit_price' => 100]],
            ])->assertSessionHasNoErrors();

        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.accounting.accounts-receivable.approve', $invoice))
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertFalse($invoice->isZatcaLocked());
        $this->assertFalse($invoice->receipts()->exists());

        return $invoice;
    }

    public function test_arabic_dialog_reopens_only_after_a_recorded_reason(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $invoice = $this->eligibleInvoice($admin);

        $response = $this->actingAs($admin)
            ->get(route('admin.accounting.accounts-receivable.show', $invoice))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('id="invoice-reopen-dialog"', false)
            ->assertSee('data-invoice-reopen-form', false)
            ->assertSee('تأكيد إعادة فتح الفاتورة')
            ->assertSee('أدخل سبب إعادة الفتح قبل المتابعة.');
        $response->assertDontSee("prompt('Reason for reopening", false);
        $response->assertSee('name="reason"', false);

        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.reopen', $invoice))
            ->assertSessionHasErrors('reason');
        $this->assertSame(__('invoice_reopen.reason_required', locale: 'ar'), session('errors')->first('reason'));
        $this->assertSame('unpaid', $invoice->fresh()->payment_status);

        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.reopen', $invoice), [
            'reason' => 'Authorized reconciliation adjustment',
        ])->assertSessionHasNoErrors();
        $this->assertStringContainsString('أُعيدت الفاتورة إلى مسودة', (string) session('status'));
        $this->assertSame('draft', $invoice->fresh()->payment_status);
        $this->assertStringContainsString('Authorized reconciliation adjustment', (string) $invoice->fresh()->notes);
    }

    public function test_english_dialog_and_super_admin_guard_remain_intact(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);
        $invoice = $this->eligibleInvoice($admin);

        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.show', $invoice))
            ->assertOk()
            ->assertSee('Confirm invoice reopening')
            ->assertSee('Reason for reopening this invoice (kept on the record):')
            ->assertSee('data-invoice-reopen-confirm', false)
            ->assertDontSee("prompt('Reason for reopening", false);

        $manager = Role::where('code', 'FINANCE_MANAGER')->firstOrFail()->users()->firstOrFail();
        $this->actingAs($manager)->get(route('admin.accounting.accounts-receivable.show', $invoice))
            ->assertOk()
            ->assertDontSee('id="invoice-reopen-dialog"', false)
            ->assertDontSee('data-invoice-reopen-form', false);
        $this->actingAs($manager)->post(route('admin.accounting.accounts-receivable.reopen', $invoice), [
            'reason' => 'Not permitted',
        ])->assertForbidden();
        $this->assertSame('unpaid', $invoice->fresh()->payment_status);
    }

    public function test_zatca_validation_labels_are_translated_without_changing_stored_values(): void
    {
        $this->assertSame('اجتاز التحقق', __('zatca.validation_passed', locale: 'ar'));
        $this->assertSame('فشل التحقق', __('zatca.validation_failed', locale: 'ar'));
        $this->assertSame('Passed', __('zatca.validation_passed', locale: 'en'));
        $this->assertSame('Failed', __('zatca.validation_failed', locale: 'en'));

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $invoice = $this->eligibleInvoice($admin);
        $record = $invoice->fresh()->zatcaRecord;
        $this->assertNotNull($record);

        $record->update(['validation_status' => 'passed']);
        $admin->update(['language' => 'Arabic']);
        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.show', $invoice))
            ->assertOk()
            ->assertSee('اجتاز التحقق');
        $this->assertSame('passed', $record->fresh()->validation_status);

        $record->update(['validation_status' => 'failed']);
        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.show', $invoice))
            ->assertOk()
            ->assertSee('فشل التحقق');
        $this->assertSame('failed', $record->fresh()->validation_status);
    }
    public function test_reopen_server_messages_have_exact_english_arabic_pairs(): void
    {
        foreach ([
            'only_super_admin',
            'reason_required',
            'unpaid_without_receipts',
            'success_with_reversal',
            'success_without_reversal',
        ] as $key) {
            $this->assertIsString(__("invoice_reopen.{$key}", locale: 'en'));
            $this->assertIsString(__("invoice_reopen.{$key}", locale: 'ar'));
            $this->assertNotSame(__("invoice_reopen.{$key}", locale: 'en'), __("invoice_reopen.{$key}", locale: 'ar'));
        }
    }

}
