<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance correctness sprint F02: the same payment or receipt submitted twice
 * (double click, refresh, retry, replay) is recorded once; separate partial
 * settlements still work.
 */
class SettlementIdempotencyTest extends TestCase
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

    private function approvedBill(): SupplierBill
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => 'BILL-F02',
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Cement', 'quantity' => 10, 'unit_price' => 100]],
        ])->assertSessionHasNoErrors();
        $bill = SupplierBill::where('bill_number', 'BILL-F02')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    private function approvedInvoice(): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => Customer::firstOrFail()->id, 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Progress claim', 'quantity' => 1, 'unit_price' => 2000]],
        ])->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    private function paymentPayload(string $key, float $amount = 400): array
    {
        return [
            'payment_date' => now()->toDateString(), 'amount' => $amount, 'payment_method' => 'Cash', 'purpose' => 'Bill payment',
            'payment_account_id' => ChartOfAccount::where('account_code', PostingService::CASH)->firstOrFail()->id,
            'idempotency_key' => $key,
        ];
    }

    public function test_the_forms_carry_a_one_time_key(): void
    {
        $bill = $this->approvedBill();
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-payable.payment', $bill))
            ->assertOk()->assertSee('name="idempotency_key" value="', false);
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.receipt', $invoice))
            ->assertOk()->assertSee('name="idempotency_key" value="', false);
    }

    public function test_the_same_payment_submitted_twice_is_recorded_once(): void
    {
        $bill = $this->approvedBill();
        $journals = JournalEntry::count();
        $key = 'pay-'.str_repeat('a', 30);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), $this->paymentPayload($key))
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Payment recorded successfully.');

        // Refresh / double click / retry: same key, same answer, nothing added.
        $replay = $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), $this->paymentPayload($key));
        $replay->assertSessionHasNoErrors();
        $this->assertStringContainsString('already recorded', session('status'));

        $bill->refresh();
        $this->assertSame(1, $bill->payments()->count());
        $this->assertSame($journals + 1, JournalEntry::count(), 'exactly one payment journal');
        $this->assertSame(750.0, (float) $bill->balance_amount);
        $this->assertSame('partially_paid', $bill->status);
        $this->assertSame(1, \App\Models\ActivityLog::where('action', 'Recorded supplier payment')->where('description', 'like', 'BILL-F02%')->count());

        // A genuinely new operation with the same amount is a second partial payment.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), $this->paymentPayload('pay-'.str_repeat('b', 30)))
            ->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame(2, $bill->payments()->count());
        $this->assertSame(350.0, (float) $bill->balance_amount);

        // The database refuses a duplicate key even if the application check were bypassed.
        $this->expectException(QueryException::class);
        SupplierPayment::create($this->paymentPayload($key) + ['supplier_id' => $bill->supplier_id, 'supplier_bill_id' => $bill->id]);
    }

    public function test_a_payment_without_a_key_still_works_for_older_clients(): void
    {
        $bill = $this->approvedBill();
        $payload = $this->paymentPayload('unused');
        unset($payload['idempotency_key']);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), $payload)->assertSessionHasNoErrors();

        $this->assertSame(2, $bill->payments()->count(), 'without a key the two requests are two payments, as before');
        $this->assertNull($bill->payments()->first()->idempotency_key);
    }

    public function test_the_same_receipt_submitted_twice_is_recorded_once(): void
    {
        $invoice = $this->approvedInvoice();
        $journals = JournalEntry::count();
        $key = 'rcpt-'.str_repeat('c', 30);
        $payload = [
            'receipt_date' => now()->toDateString(), 'amount' => 500, 'payment_method' => 'Bank Transfer',
            'receipt_account_id' => ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail()->id,
            'idempotency_key' => $key,
        ];

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertStringContainsString('already recorded', session('status'));

        $invoice->refresh();
        $this->assertSame(1, $invoice->receipts()->count());
        $this->assertSame($journals + 1, JournalEntry::count());
        $this->assertSame(1800.0, (float) $invoice->balance_amount);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), ['idempotency_key' => 'rcpt-'.str_repeat('d', 30)] + $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $invoice->fresh()->receipts()->count());
        $this->assertSame(1300.0, (float) $invoice->fresh()->balance_amount);

        $this->expectException(QueryException::class);
        CustomerReceipt::create($payload + ['customer_id' => $invoice->customer_id, 'customer_invoice_id' => $invoice->id]);
    }
}
