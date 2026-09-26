<?php

namespace Tests\Feature;

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
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Finance correctness sprint F03: a finalized or submitted VAT period is
 * sealed. Every VAT-producing path (AP, AR, goods receipts, manual journals on
 * the VAT accounts, corrections) refuses a transaction dated inside it, and
 * the period itself cannot be recalculated or finalized twice.
 *
 * The demo data seeds the previous quarter as finalized and the current one
 * as draft; the tests use synthetic documents dated into each.
 */
class VatPeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private VatPeriod $sealed;

    private VatPeriod $open;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->sealed = VatPeriod::where('status', 'finalized')->orderBy('start_date')->firstOrFail();
        $this->open = VatPeriod::where('status', 'draft')->orderBy('start_date')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function sealedDate(): string
    {
        return $this->sealed->start_date->addDays(9)->toDateString();
    }

    private function openDate(): string
    {
        return $this->open->start_date->addDays(9)->toDateString();
    }

    private function bill(string $date, float $vatRate = 15, string $number = 'BILL-F03'): SupplierBill
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => $number, 'bill_date' => $date,
            'due_date' => $date, 'vat_rate' => $vatRate,
            'lines' => [['description' => 'Cement', 'quantity' => 10, 'unit_price' => 100]],
        ])->assertSessionHasNoErrors();

        return SupplierBill::where('bill_number', $number)->firstOrFail();
    }

    private function invoice(string $date): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => Customer::firstOrFail()->id, 'invoice_date' => $date, 'due_date' => $date, 'vat_rate' => 15,
            'lines' => [['description' => 'Progress claim', 'quantity' => 1, 'unit_price' => 2000]],
        ])->assertSessionHasNoErrors();

        return CustomerInvoice::latest('id')->firstOrFail();
    }

    public function test_a_bill_dated_in_a_sealed_period_cannot_be_approved(): void
    {
        $bill = $this->bill($this->sealedDate());
        $journals = JournalEntry::count();
        $vatRows = VatTransaction::count();

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))
            ->assertSessionHasErrors('vat');

        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertSame($journals, JournalEntry::count(), 'the whole approval rolled back');
        $this->assertSame($vatRows, VatTransaction::count());
        $this->assertStringContainsString($this->sealed->period_name, session('errors')->first('vat'));
        $this->assertSame($this->sealed->id, VatPeriod::find($this->sealed->id)->id);
        $this->assertSame('finalized', $this->sealed->fresh()->status, 'the period was not reopened');

        // The same bill dated in the open period approves normally.
        $openBill = $this->bill($this->openDate(), 15, 'BILL-F03-OPEN');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $openBill))->assertSessionHasNoErrors();
        $this->assertSame($this->open->id, VatTransaction::where('source_module', 'Supplier Bill')->where('source_id', $openBill->id)->value('vat_period_id'));
    }

    public function test_a_zero_vat_bill_in_a_sealed_period_is_not_a_vat_event(): void
    {
        // The VAT seal protects the return; the general ledger has no period close yet.
        $bill = $this->bill($this->sealedDate(), 0, 'BILL-F03-ZERO');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertSame(0, VatTransaction::where('source_module', 'Supplier Bill')->where('source_id', $bill->id)->count());
    }

    public function test_an_invoice_dated_in_a_sealed_period_cannot_be_approved(): void
    {
        $invoice = $this->invoice($this->sealedDate());

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))
            ->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertSame('draft', $invoice->payment_status);
        $this->assertNull($invoice->journal_entry_id);
        $this->assertNull($invoice->zatcaRecord);
    }

    public function test_a_goods_receipt_dated_in_a_sealed_period_cannot_be_posted(): void
    {
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'warehouse_id' => Warehouse::firstOrFail()->id,
            'received_date' => $this->sealedDate(), 'delivery_note_number' => 'DN-F03', 'vat_rate' => 15,
            'lines' => [['item_id' => Item::firstOrFail()->id, 'ordered_quantity' => 5, 'received_quantity' => 5, 'accepted_quantity' => 5, 'unit_cost' => 100]],
        ])->assertSessionHasNoErrors();
        $grn = GoodsReceipt::where('delivery_note_number', 'DN-F03')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))
            ->assertSessionHasErrors('vat');

        $grn->refresh();
        $this->assertSame('draft', $grn->status);
        $this->assertSame(0, StockLedgerEntry::where('reference_number', $grn->grn_number)->count(), 'stock did not move either');
    }

    public function test_a_manual_journal_on_a_vat_account_cannot_be_dated_into_a_sealed_period(): void
    {
        $inputVat = ChartOfAccount::where('account_code', PostingService::INPUT_VAT)->firstOrFail();
        $bank = ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail();
        $expense = ChartOfAccount::where('account_code', PostingService::MATERIAL_EXPENSE)->firstOrFail();

        $payload = fn (string $date, ChartOfAccount $debit) => [
            'journal_date' => $date, 'source_module' => 'Manual', 'status' => 'draft', 'description' => 'VAT adjustment',
            'lines' => [
                ['chart_of_account_id' => $debit->id, 'debit' => 100, 'credit' => 0],
                ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 100],
            ],
        ];

        $journals = JournalEntry::count();
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $payload($this->sealedDate(), $inputVat))
            ->assertSessionHasErrors('vat');
        $this->assertSame($journals, JournalEntry::count());

        // The same date without a VAT account, and a VAT line in the open period, are fine.
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $payload($this->sealedDate(), $expense))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $payload($this->openDate(), $inputVat))->assertSessionHasNoErrors();

        // A draft VAT journal cannot be posted into a period that was sealed after it was saved.
        $draft = JournalEntry::latest('id')->firstOrFail();
        $this->open->update(['status' => 'finalized']);
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.post', $draft))->assertSessionHasErrors('vat');
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_a_correction_cannot_withdraw_vat_from_a_sealed_period(): void
    {
        $invoice = $this->invoice($this->openDate());
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $this->open->update(['status' => 'finalized']);

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.reopen', $invoice), ['reason' => 'Late correction'])
            ->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->payment_status, 'the invoice stays approved');
        $this->assertNotNull($invoice->journal_entry_id);
        $this->assertSame(1, VatTransaction::where('source_module', 'Customer Invoice')->where('source_id', $invoice->id)->count());
    }

    public function test_stale_draft_model_cannot_recalculate_a_period_finalized_since_it_was_loaded(): void
    {
        $stale = $this->open->fresh();
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.finalize', $this->open))->assertSessionHasNoErrors();
        $before = $this->open->fresh()->getAttributes();
        try {
            $stale->recalculate();
            $this->fail('A stale draft instance must not bypass the period lock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vat', $exception->errors());
        }
        $this->assertSame($before, $this->open->fresh()->getAttributes());
    }

    public function test_withdrawal_checks_sealed_dates_even_if_a_legacy_vat_row_has_no_period_link(): void
    {
        $row = VatTransaction::create(['transaction_date' => $this->sealedDate(), 'source_module' => 'Review',
            'source_id' => 991, 'taxable_amount' => 100, 'vat_amount' => 15, 'vat_rate' => 15,
            'vat_type' => 'input', 'status' => 'active', 'party_type' => 'supplier']);
        try {
            app(PostingService::class)->withdrawVat('Review', 991);
            $this->fail('Legacy rows in sealed dates must not be withdrawn.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vat', $exception->errors());
        }
        $this->assertDatabaseHas('vat_transactions', ['id' => $row->id]);
    }

    public function test_a_period_cannot_be_recalculated_or_finalized_twice_once_sealed(): void
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.recalculate', $this->sealed))->assertSessionHasErrors('vat');
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.finalize', $this->sealed))->assertSessionHasErrors('vat');

        $this->actingAs($this->admin())->post(route('admin.accounting.vat.finalize', $this->open))->assertSessionHasNoErrors();
        $this->assertSame('finalized', $this->open->fresh()->status);
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.finalize', $this->open))->assertSessionHasErrors('vat');
    }
}
