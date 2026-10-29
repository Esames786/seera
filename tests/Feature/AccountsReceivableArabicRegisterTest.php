<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CustomerInvoice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsReceivableArabicRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_arabic_receivables_register_translates_navigation_and_posting_explanation(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.index'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('المحاسبة / الحسابات المدينة')
            ->assertSee('الذمم المدينة المستحقة')
            ->assertSee('فواتير العملاء')
            ->assertSee('اعتماد الفاتورة يُرحّل')
            ->assertSee('name="status"', false)
            ->assertSee('name="zatca"', false)
            // Column label through zatca.label (a bare __('ZATCA') resolves to the lang group on a case-insensitive filesystem).
            ->assertSee('<th>زاتكا</th>', false)
            // Shared filter / row action controls and the status option labels are translated; option values unchanged.
            ->assertSee('تصفية')
            ->assertSee('>عرض<', false)
            ->assertDontSee('>View<', false)
            ->assertDontSee('>Filter<', false)
            ->assertSee('value="partially_paid"', false)
            ->assertSee('مدفوع جزئياً')
            ->assertSee('value="submission_pending"', false)
            ->assertSee('بانتظار الرد');
    }

    public function test_english_receivables_register_retains_existing_labels(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);

        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.index'))->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Outstanding Receivable')
            ->assertSee('Approving an invoice posts:')
            ->assertSee('name="status"', false);
    }

    public function test_invoice_detail_translates_read_only_labels_without_changing_amounts(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $invoice = CustomerInvoice::firstOrFail();

        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('المحاسبة / الحسابات المدينة / فاتورة العميل')
            ->assertSee('المبلغ الخاضع للضريبة')
            ->assertSee('نسبة الضريبة')
            ->assertSee('<th>زاتكا</th>', false)
            ->assertDontSee(' receipt)')
            ->assertDontSee(' receipts)')
            ->assertSee($invoice->invoice_number)
            ->assertSee(number_format($invoice->total_amount, 2));
    }

    public function test_english_invoice_detail_retains_labels_and_receipt_count(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);
        $invoice = CustomerInvoice::firstOrFail();

        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk()
            ->assertSee('<th>ZATCA</th>', false)
            ->assertSee('Subtotal (taxable)')
            ->assertSee('Total incl. VAT')
            ->assertSee(number_format($invoice->total_amount, 2))
            ->assertSee($invoice->receipts()->count() === 1 ? '(1 receipt)' : '('.$invoice->receipts()->count().' receipts)');
    }
}
