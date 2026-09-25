<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance correctness sprint F11: every financial transition re-reads the
 * record under lock inside its transaction, so a stale edit, a stale delete
 * or a repeated approve/post cannot overwrite or duplicate a finalized state.
 */
class FinanceStateTransitionTest extends TestCase
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

    private function billPayload(array $extra = []): array
    {
        return $extra + [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => 'BILL-F11', 'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Cement', 'quantity' => 10, 'unit_price' => 100]],
        ];
    }

    private function invoicePayload(array $extra = []): array
    {
        return $extra + [
            'customer_id' => Customer::firstOrFail()->id, 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Progress claim', 'quantity' => 1, 'unit_price' => 2000]],
        ];
    }

    public function test_an_approved_bill_cannot_be_edited_deleted_or_approved_again(): void
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), $this->billPayload())->assertSessionHasNoErrors();
        $bill = SupplierBill::where('bill_number', 'BILL-F11')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $journal = $bill->fresh()->journal_entry_id;

        // A form opened while the bill was still a draft comes back after the approval.
        $this->actingAs($this->admin())->put(route('admin.accounting.accounts-payable.update', $bill), $this->billPayload([
            'lines' => [['description' => 'Cement', 'quantity' => 99, 'unit_price' => 100]],
        ]))->assertSessionHasErrors('bill');
        $this->actingAs($this->admin())->delete(route('admin.accounting.accounts-payable.destroy', $bill))->assertSessionHasErrors('bill');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasErrors('bill');

        $bill->refresh();
        $this->assertSame('unpaid', $bill->status);
        $this->assertSame(1150.0, (float) $bill->total_amount, 'the stale edit did not land');
        $this->assertSame($journal, $bill->journal_entry_id, 'no second journal');
        $this->assertSame(1, JournalEntry::where('source_module', 'Supplier Bill')->where('source_id', $bill->id)->count());
    }

    public function test_an_approved_invoice_cannot_be_edited_deleted_or_approved_again(): void
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), $this->invoicePayload())->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();

        $this->actingAs($this->admin())->put(route('admin.accounting.accounts-receivable.update', $invoice), $this->invoicePayload([
            'lines' => [['description' => 'Progress claim', 'quantity' => 1, 'unit_price' => 1]],
        ]))->assertSessionHasErrors('invoice');
        $this->actingAs($this->admin())->delete(route('admin.accounting.accounts-receivable.destroy', $invoice))->assertSessionHasErrors('invoice');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasErrors('invoice');

        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame(2300.0, (float) $invoice->total_amount);
        $this->assertSame(1, JournalEntry::where('source_module', 'Customer Invoice')->where('source_id', $invoice->id)->count());
        $this->assertSame(1, $invoice->zatcaRecord()->count());
    }

    public function test_a_posted_journal_cannot_be_edited_deleted_cancelled_or_posted_again(): void
    {
        $bank = ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail();
        $expense = ChartOfAccount::where('account_code', PostingService::MATERIAL_EXPENSE)->firstOrFail();
        $payload = [
            'journal_date' => now()->toDateString(), 'source_module' => 'Manual', 'status' => 'draft', 'description' => 'F11',
            'lines' => [
                ['chart_of_account_id' => $expense->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 100],
            ],
        ];

        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $payload)->assertSessionHasNoErrors();
        $entry = JournalEntry::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.post', $entry))->assertSessionHasNoErrors();
        $postedAt = $entry->fresh()->posted_at;

        $this->actingAs($this->admin())->put(route('admin.accounting.journal-entries.update', $entry), ['description' => 'changed'] + $payload)->assertSessionHasErrors('journal');
        $this->actingAs($this->admin())->delete(route('admin.accounting.journal-entries.destroy', $entry))->assertSessionHasErrors('journal');
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.cancel', $entry))->assertSessionHasErrors('journal');
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.post', $entry))->assertSessionHasErrors('journal');

        $entry->refresh();
        $this->assertSame('posted', $entry->status);
        $this->assertSame('F11', $entry->description);
        $this->assertEquals($postedAt, $entry->posted_at, 'a repeated post does not re-stamp the entry');
        $this->assertSame(2, $entry->lines()->count());
    }
}
