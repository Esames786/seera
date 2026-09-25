<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\VatTransaction;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Finance correctness sprint F01: a business event is either fully recorded
 * (document + journal + VAT + stock) or refused as a whole. Missing or
 * inactive accounts and unbalanced entries can no longer leave a half-state,
 * and a review-mode (draft) journal is reported as such.
 */
class FinancePostingIntegrityTest extends TestCase
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

    private function deactivate(string $code): void
    {
        ChartOfAccount::where('account_code', $code)->update(['status' => 'inactive']);
    }

    private function draftBill(array $line = [], string $number = 'BILL-F01'): SupplierBill
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => $number,
            'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [$line + ['description' => 'Steel bars', 'quantity' => 10, 'unit_price' => 100]],
        ])->assertSessionHasNoErrors();

        return SupplierBill::where('bill_number', $number)->firstOrFail();
    }

    private function draftInvoice(): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => Customer::firstOrFail()->id, 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Progress claim', 'quantity' => 1, 'unit_price' => 2000]],
        ])->assertSessionHasNoErrors();

        return CustomerInvoice::latest('id')->firstOrFail();
    }

    private function draftGrn(): GoodsReceipt
    {
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'warehouse_id' => Warehouse::firstOrFail()->id,
            'received_date' => now()->toDateString(), 'delivery_note_number' => 'DN-F01', 'vat_rate' => 15,
            'lines' => [['item_id' => Item::firstOrFail()->id, 'ordered_quantity' => 10, 'received_quantity' => 10, 'accepted_quantity' => 10, 'unit_cost' => 100]],
        ])->assertSessionHasNoErrors();

        return GoodsReceipt::where('delivery_note_number', 'DN-F01')->firstOrFail();
    }

    public function test_bill_approval_is_refused_as_a_whole_when_the_input_vat_account_is_inactive(): void
    {
        $bill = $this->draftBill();
        $journals = JournalEntry::count();
        $vatRows = VatTransaction::count();
        $this->deactivate(PostingService::INPUT_VAT);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))
            ->assertSessionHasErrors('posting');

        $bill->refresh();
        $this->assertSame('draft', $bill->status, 'the bill must not be half-approved');
        $this->assertNull($bill->journal_entry_id);
        $this->assertSame($journals, JournalEntry::count(), 'no journal, not even a draft one');
        $this->assertSame($vatRows, VatTransaction::count(), 'no VAT row without its journal');
        $this->assertStringContainsString('Input VAT', session('errors')->first('posting'));
    }

    public function test_bill_approval_is_refused_when_a_line_uses_an_inactive_account(): void
    {
        $fuel = ChartOfAccount::where('account_code', '5300')->firstOrFail();
        $bill = $this->draftBill(['chart_of_account_id' => $fuel->id]);
        $fuel->update(['status' => 'inactive']);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))
            ->assertSessionHasErrors('posting');

        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertStringContainsString('5300', session('errors')->first('posting'));
    }

    public function test_a_payment_without_its_journal_is_refused_and_rolled_back(): void
    {
        $bill = $this->draftBill();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->deactivate(PostingService::PAYABLE);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
            'payment_date' => now()->toDateString(), 'amount' => 500, 'payment_method' => 'Cash', 'purpose' => 'Bill payment',
            'payment_account_id' => ChartOfAccount::where('account_code', PostingService::CASH)->firstOrFail()->id,
        ])->assertSessionHasErrors('posting');

        $bill->refresh();
        $this->assertSame(0, $bill->payments()->count(), 'the payment row was rolled back with its journal');
        $this->assertSame('unpaid', $bill->status);
        $this->assertSame(1150.0, (float) $bill->balance_amount);
    }

    public function test_invoice_approval_is_refused_as_a_whole_when_the_output_vat_account_is_inactive(): void
    {
        $invoice = $this->draftInvoice();
        $this->deactivate(PostingService::OUTPUT_VAT);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))
            ->assertSessionHasErrors('posting');

        $invoice->refresh();
        $this->assertSame('draft', $invoice->payment_status);
        $this->assertNull($invoice->journal_entry_id);
        $this->assertNull($invoice->zatcaRecord, 'no e-invoice record for an unapproved invoice');
        $this->assertSame(0, VatTransaction::where('source_module', 'Customer Invoice')->where('source_id', $invoice->id)->count());
    }

    public function test_goods_receipt_posting_rolls_the_stock_movement_back_when_accounting_is_refused(): void
    {
        Item::query()->update(['inventory_account_id' => null]);
        $grn = $this->draftGrn();
        $this->deactivate(PostingService::INVENTORY_ASSET);

        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))
            ->assertSessionHasErrors('posting');

        $grn->refresh();
        $this->assertSame('draft', $grn->status, 'stock and accounting are one event');
        $this->assertFalse((bool) $grn->stock_updated);
        $this->assertSame(0, StockLedgerEntry::where('reference_number', $grn->grn_number)->count(), 'no stock ledger movement survived the rollback');
        $this->assertNull(JournalEntry::where('reference_number', $grn->grn_number)->first(), 'no journal for the refused receipt');
    }

    public function test_review_mode_rules_report_a_draft_journal_truthfully(): void
    {
        AutomaticPostingRule::where('source_module', 'Supplier Bill')->where('trigger_event', 'Bill Approved')->update(['auto_post' => false]);
        AutomaticPostingRule::where('source_module', 'Inventory')->where('trigger_event', 'Inventory Purchase')->update(['auto_post' => false]);

        $bill = $this->draftBill();
        $response = $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill));
        $response->assertSessionHasNoErrors();
        $this->assertStringContainsString('draft', session('status'));
        $this->assertSame('draft', $bill->fresh()->journalEntry->status);

        $grn = $this->draftGrn();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))->assertSessionHasNoErrors();
        $grn->refresh();
        $this->assertSame('posted', $grn->status);
        $this->assertFalse((bool) $grn->accounting_posted, 'a draft journal is not "accounting posted"');
        $this->assertSame('draft', $grn->journalEntry->status);
    }

    public function test_an_entry_that_would_not_balance_is_refused_before_anything_is_written(): void
    {
        $bill = $this->draftBill();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $entry = $bill->fresh()->journalEntry;

        // Tamper with one stored line so a reversal of it could not balance.
        DB::table('journal_entry_lines')->where('journal_entry_id', $entry->id)->where('debit', '>', 0)->limit(1)->update(['debit' => 999999]);
        $before = JournalEntry::count();

        try {
            app(PostingService::class)->reverseEntry($entry->fresh(), 'Tampered reversal');
            $this->fail('an unbalanced reversal must be refused');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('would not balance', $exception->errors()['posting'][0]);
        }

        $this->assertSame($before, JournalEntry::count());
    }
}
