<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
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

    public function test_arabic_invoice_dialog_labels_are_translated_without_browser_prompt(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $invoice = CustomerInvoice::where('payment_status', 'unpaid')->whereNotNull('journal_entry_id')->first();

        if (! $invoice) {
            $this->markTestSkipped('No eligible unpaid invoice in the seeded fixture.');
        }

        $response = $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk();
        $response->assertDontSee("prompt('Reason for reopening", false)
            ->assertSee('تأكيد إعادة فتح الفاتورة');
    }

    public function test_zatca_validation_labels_are_translated(): void
    {
        $this->assertSame('اجتاز التحقق', __('zatca.validation_passed', locale: 'ar'));
        $this->assertSame('فشل التحقق', __('zatca.validation_failed', locale: 'ar'));
        $this->assertSame('Passed', __('zatca.validation_passed', locale: 'en'));
        $this->assertSame('Failed', __('zatca.validation_failed', locale: 'en'));
    }
}
