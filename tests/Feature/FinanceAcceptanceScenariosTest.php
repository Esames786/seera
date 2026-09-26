<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockIssue;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Finance acceptance pack FIN-01 … FIN-10 (docs/finance-readiness-audit-2026-09-24.md §7),
 * executed on synthetic data over the demo seed. Every scenario states its input,
 * the action taken, the expected status, debit/credit lines, VAT impact, ledger
 * and report impact and the audit log entry, and asserts each of them.
 *
 * FIN-08 follows the F04 GRNI model: the receipt accrues to Goods Received Not
 * Invoiced and the matched supplier bill clears it with one payable and one VAT
 * event. FIN-09 covers permission and scope only: a multi-approver runtime does
 * not exist yet (BLOCKED).
 * These tests prove system behaviour on synthetic data; they are not a client
 * acceptance.
 */
class FinanceAcceptanceScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------- helpers

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function today(): string
    {
        return now()->toDateString();
    }

    /** Net posted movement (debit − credit) of an account on today's date. */
    private function movementToday(string $code): float
    {
        $row = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.journal_date', $this->today())
            ->where('journal_entry_lines.chart_of_account_id', $this->account($code)->id)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) AS debit, COALESCE(SUM(journal_entry_lines.credit), 0) AS credit')
            ->first();

        return round((float) $row->debit - (float) $row->credit, 2);
    }

    /** The journal lines of an entry as [account code => [debit, credit]]. */
    private function linesOf(JournalEntry $entry): array
    {
        $out = [];
        foreach ($entry->lines()->with('account')->get() as $line) {
            $code = $line->account->account_code;
            $out[$code] = [round(($out[$code][0] ?? 0) + (float) $line->debit, 2), round(($out[$code][1] ?? 0) + (float) $line->credit, 2)];
        }
        ksort($out);

        return $out;
    }

    private function vatRows(string $module, int $id): \Illuminate\Support\Collection
    {
        return VatTransaction::where('source_module', $module)->where('source_id', $id)->get();
    }

    private function logged(string $action, string $needle): void
    {
        $this->assertTrue(
            ActivityLog::where('module', 'Accounting')->where('action', $action)->where('description', 'like', "%{$needle}%")->exists(),
            "expected an audit log '{$action}' mentioning '{$needle}'"
        );
    }

    private function draftBill(string $number = 'BILL-FIN', float $net = 1000, float $vatRate = 15): SupplierBill
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => $number, 'bill_date' => $this->today(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => $vatRate,
            'lines' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => $net]],
        ])->assertSessionHasNoErrors();

        return SupplierBill::where('bill_number', $number)->firstOrFail();
    }

    private function approvedBill(string $number = 'BILL-FIN', float $net = 1000): SupplierBill
    {
        $bill = $this->draftBill($number, $net);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    private function draftInvoice(float $net = 2000, ?Customer $customer = null, ?string $dueDate = null): CustomerInvoice
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => ($customer ?? Customer::firstOrFail())->id, 'invoice_date' => $this->today(),
            'due_date' => $dueDate ?? now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Progress claim', 'quantity' => 1, 'unit_price' => $net]],
        ])->assertSessionHasNoErrors();

        return CustomerInvoice::latest('id')->firstOrFail();
    }

    private function approvedInvoice(float $net = 2000, ?Customer $customer = null, ?string $dueDate = null): CustomerInvoice
    {
        $invoice = $this->draftInvoice($net, $customer, $dueDate);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    private function pay(SupplierBill $bill, float $amount, string $key, string $accountCode = PostingService::CASH): TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
            'payment_date' => $this->today(), 'amount' => $amount, 'purpose' => 'Bill payment',
            'payment_method' => $accountCode === PostingService::CASH ? 'Cash' : 'Bank Transfer',
            'payment_account_id' => $this->account($accountCode)->id, 'idempotency_key' => $key,
        ]);
    }

    private function receive(CustomerInvoice $invoice, float $amount, string $key, string $accountCode = PostingService::BANK): TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
            'receipt_date' => $this->today(), 'amount' => $amount,
            'payment_method' => $accountCode === PostingService::CASH ? 'Cash' : 'Bank Transfer',
            'receipt_account_id' => $this->account($accountCode)->id, 'idempotency_key' => $key,
        ]);
    }

    private function journalPayload(array $lines, string $status = 'draft', string $description = 'FIN journal'): array
    {
        return [
            'journal_date' => $this->today(), 'source_module' => 'Manual', 'status' => $status, 'description' => $description,
            'lines' => array_map(fn ($l) => ['chart_of_account_id' => $this->account($l[0])->id, 'debit' => $l[1], 'credit' => $l[2]], $lines),
        ];
    }

    private function report(string $name, array $query = []): TestResponse
    {
        return $this->actingAs($this->admin())->get(route('admin.accounting.reports.'.$name, ['from' => $this->today(), 'to' => $this->today()] + $query))->assertOk();
    }

    // ------------------------------------------------------------------ FIN-01

    public function test_fin01_opening_journal_is_excluded_until_posted_and_reports_stay_balanced(): void
    {
        $bankBefore = $this->movementToday(PostingService::BANK);
        $equityBefore = $this->movementToday('3100');

        // Input: a balanced opening journal Dr Bank 10,000 / Cr Owner Equity 10,000, saved as a draft.
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $this->journalPayload([
            [PostingService::BANK, 10000, 0], ['3100', 0, 10000],
        ], 'draft', 'FIN-01 opening'))->assertSessionHasNoErrors();
        $entry = JournalEntry::where('description', 'FIN-01 opening')->firstOrFail();

        // Expected: draft, and nothing in the posted ledger yet.
        $this->assertSame('draft', $entry->status);
        $this->assertSame($bankBefore, $this->movementToday(PostingService::BANK), 'a draft journal is not in the ledger');

        // Action: explicit posting adds exactly those amounts.
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.post', $entry))->assertSessionHasNoErrors();
        $this->assertSame('posted', $entry->fresh()->status);
        $this->assertSame(['1120' => [10000.0, 0.0], '3100' => [0.0, 10000.0]], $this->linesOf($entry));
        $this->assertSame(round($bankBefore + 10000, 2), $this->movementToday(PostingService::BANK));
        $this->assertSame(round($equityBefore - 10000, 2), $this->movementToday('3100'));

        // Ledger/report impact: trial balance Dr = Cr, balance sheet A = L + E + result.
        $trial = $this->report('trial-balance');
        $this->assertSame(round($trial->viewData('totalDebit'), 2), round($trial->viewData('totalCredit'), 2));
        $sheet = $this->report('balance-sheet');
        $this->assertSame(
            round($sheet->viewData('totalAssets'), 2),
            round($sheet->viewData('totalLiabilities') + $sheet->viewData('totalEquity') + $sheet->viewData('netProfit'), 2)
        );

        // Rejections: an unbalanced journal and a journal on an inactive account are refused as a whole.
        $count = JournalEntry::count();
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $this->journalPayload([
            [PostingService::BANK, 10000, 0], ['3100', 0, 9000],
        ]))->assertSessionHasErrors();
        $inactive = ChartOfAccount::create(['account_code' => '3198', 'account_name' => 'Closed reserve', 'account_type' => 'equity', 'normal_balance' => 'credit', 'opening_balance' => 0, 'status' => 'inactive']);
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $this->journalPayload([
            [PostingService::BANK, 100, 0], ['3198', 0, 100],
        ]))->assertSessionHasErrors();
        $this->assertSame($count, JournalEntry::count());

        // Audit log.
        $this->logged('Posted journal entry', $entry->journal_number);
    }

    // ------------------------------------------------------------------ FIN-02

    public function test_fin02_standalone_ap_bill_posts_expense_input_vat_and_payable_only_on_approval(): void
    {
        $expenseBefore = $this->movementToday(PostingService::MATERIAL_EXPENSE);
        $inputVatBefore = $this->movementToday(PostingService::INPUT_VAT);
        $payableBefore = $this->movementToday(PostingService::PAYABLE);

        // Input: draft bill net 1,000, VAT 15 % = 150, gross 1,150.
        $bill = $this->draftBill('BILL-FIN02');
        $this->assertSame('draft', $bill->status);
        $this->assertSame(1150.0, (float) $bill->total_amount);
        $this->assertNull($bill->journal_entry_id, 'a draft bill has no GL');
        $this->assertCount(0, $this->vatRows('Supplier Bill', $bill->id), 'a draft bill has no VAT');
        $this->assertSame($expenseBefore, $this->movementToday(PostingService::MATERIAL_EXPENSE));

        // Action: approve.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $bill->refresh();

        // Expected: status unpaid, outstanding 1,150, Dr Expense 1,000 / Dr Input VAT 150 / Cr AP 1,150, posted.
        $this->assertSame('unpaid', $bill->status);
        $this->assertSame(1150.0, (float) $bill->balance_amount);
        $entry = $bill->journalEntry;
        $this->assertSame('posted', $entry->status);
        $this->assertSame('Supplier Bill', $entry->source_module);
        $this->assertSame(['1300' => [150.0, 0.0], '2100' => [0.0, 1150.0], '5200' => [1000.0, 0.0]], $this->linesOf($entry));

        // VAT impact: exactly one input VAT row of 150 in the open period.
        $vat = $this->vatRows('Supplier Bill', $bill->id);
        $this->assertCount(1, $vat);
        $this->assertSame('input', $vat->first()->vat_type);
        $this->assertSame(150.0, (float) $vat->first()->vat_amount);
        $this->assertSame('draft', VatPeriod::find($vat->first()->vat_period_id)->status);

        // Ledger impact.
        $this->assertSame(round($expenseBefore + 1000, 2), $this->movementToday(PostingService::MATERIAL_EXPENSE));
        $this->assertSame(round($inputVatBefore + 150, 2), $this->movementToday(PostingService::INPUT_VAT));
        $this->assertSame(round($payableBefore - 1150, 2), $this->movementToday(PostingService::PAYABLE));

        // Audit log.
        $this->logged('Approved supplier bill', 'BILL-FIN02');
    }

    // ------------------------------------------------------------------ FIN-03

    public function test_fin03_ap_partial_and_full_payment_settle_the_bill_without_new_vat(): void
    {
        $bill = $this->approvedBill('BILL-FIN03');
        $cashBefore = $this->movementToday(PostingService::CASH);
        $payableAfterBill = $this->movementToday(PostingService::PAYABLE);
        $vatCount = VatTransaction::count();

        // Action: pay 400 in cash.
        $this->pay($bill, 400, 'fin03-first')->assertSessionHasNoErrors();
        $bill->refresh();
        $payment = $bill->payments()->firstOrFail();
        $this->assertSame('partially_paid', $bill->status);
        $this->assertSame(750.0, (float) $bill->balance_amount);
        $this->assertSame(['1110' => [0.0, 400.0], '2100' => [400.0, 0.0]], $this->linesOf($payment->journalEntry));

        // Replay of the same operation key has no second effect.
        $this->pay($bill, 400, 'fin03-first')->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame(1, $bill->payments()->count());
        $this->assertSame(750.0, (float) $bill->fresh()->balance_amount);

        // Overpayment is refused atomically: no payment row, no journal.
        $journals = JournalEntry::count();
        $this->pay($bill, 751, 'fin03-over')->assertSessionHasErrors('amount');
        $this->assertSame(1, $bill->payments()->count());
        $this->assertSame($journals, JournalEntry::count());

        // Action: pay the remaining 750.
        $this->pay($bill, 750, 'fin03-second')->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertSame(0.0, (float) $bill->balance_amount);

        // Ledger: AP nets to zero for this bill, cash down by 1,150; VAT untouched by settlement.
        $this->assertSame($payableAfterBill + 1150, $this->movementToday(PostingService::PAYABLE));
        $this->assertSame(round($cashBefore - 1150, 2), $this->movementToday(PostingService::CASH));
        $this->assertSame($vatCount, VatTransaction::count(), 'no VAT on settlement');

        // A paid bill accepts no further payment.
        $this->pay($bill, 1, 'fin03-third')->assertSessionHasErrors();

        // Audit log.
        $this->logged('Recorded supplier payment', 'BILL-FIN03');
    }

    // ------------------------------------------------------------------ FIN-04

    public function test_fin04_ar_invoice_and_receipts_post_revenue_output_vat_and_clear_the_receivable(): void
    {
        $receivableBefore = $this->movementToday(PostingService::RECEIVABLE);
        $revenueBefore = $this->movementToday(PostingService::REVENUE);
        $outputVatBefore = $this->movementToday(PostingService::OUTPUT_VAT);
        $bankBefore = $this->movementToday(PostingService::BANK);

        // Input: invoice net 2,000, VAT 300, gross 2,300; approve.
        $invoice = $this->approvedInvoice(2000);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame(2300.0, (float) $invoice->balance_amount);
        $this->assertSame(['1200' => [2300.0, 0.0], '2210' => [0.0, 300.0], '4100' => [0.0, 2000.0]], $this->linesOf($invoice->journalEntry));

        $vat = $this->vatRows('Customer Invoice', $invoice->id);
        $this->assertCount(1, $vat);
        $this->assertSame('output', $vat->first()->vat_type);
        $this->assertSame(300.0, (float) $vat->first()->vat_amount);

        // The local ZATCA record is a foundation only: it exists but is not cleared by a live gateway.
        $this->assertSame(1, $invoice->zatcaRecord()->count());
        $this->assertNotSame('cleared', $invoice->zatcaRecord()->value('clearance_status'));

        // Action: receive 500 then 1,800 by bank.
        $this->receive($invoice, 500, 'fin04-first')->assertSessionHasNoErrors();
        $this->assertSame(1800.0, (float) $invoice->fresh()->balance_amount);
        $this->assertSame('partially_paid', $invoice->fresh()->payment_status);
        $this->assertSame(['1120' => [500.0, 0.0], '1200' => [0.0, 500.0]], $this->linesOf($invoice->receipts()->first()->journalEntry));

        // Replay has one effect.
        $this->receive($invoice, 500, 'fin04-first')->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->receipts()->count());

        $this->receive($invoice, 1800, 'fin04-second')->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame(0.0, (float) $invoice->balance_amount);

        // Ledger: AR nets to zero, bank up 2,300, revenue 2,000, output VAT 300; no further VAT rows.
        $this->assertSame($receivableBefore, $this->movementToday(PostingService::RECEIVABLE));
        $this->assertSame(round($bankBefore + 2300, 2), $this->movementToday(PostingService::BANK));
        $this->assertSame(round($revenueBefore - 2000, 2), $this->movementToday(PostingService::REVENUE));
        $this->assertSame(round($outputVatBefore - 300, 2), $this->movementToday(PostingService::OUTPUT_VAT));
        $this->assertCount(1, $this->vatRows('Customer Invoice', $invoice->id));

        // A cash-only customer rejects a bank receipt at the endpoint.
        $cashOnly = Customer::create(['name' => 'Cash Only Client', 'code' => 'CUST-FIN04', 'status' => 'active', 'allowed_payment_types' => 'Cash']);
        $cashInvoice = $this->approvedInvoice(100, $cashOnly);
        $this->receive($cashInvoice, 115, 'fin04-bank', PostingService::BANK)->assertSessionHasErrors('receipt_account_id');
        $this->assertSame(0, $cashInvoice->receipts()->count());
        $this->receive($cashInvoice, 115, 'fin04-cash', PostingService::CASH)->assertSessionHasNoErrors();
        $this->assertSame('paid', $cashInvoice->fresh()->payment_status);

        // Audit log.
        $this->logged('Approved customer invoice', $invoice->invoice_number);
        $this->logged('Recorded customer receipt', $invoice->invoice_number);
    }

    // ------------------------------------------------------------------ FIN-05

    public function test_fin05_cash_trial_balance_and_reports_tie_out_after_fin02_to_fin04(): void
    {
        $plBefore = $this->report('profit-loss');
        $cashBefore = $this->report('cash-flow');

        $bill = $this->approvedBill('BILL-FIN05');
        $this->pay($bill, 1150, 'fin05-pay', PostingService::BANK)->assertSessionHasNoErrors();
        $invoice = $this->approvedInvoice(2000);
        $this->receive($invoice, 2300, 'fin05-rcpt', PostingService::BANK)->assertSessionHasNoErrors();

        // Profit & loss for the period: revenue +2,000, expenses +1,000, profit +1,000.
        $pl = $this->report('profit-loss');
        $this->assertSame(2000.0, round($pl->viewData('totalRevenue') - $plBefore->viewData('totalRevenue'), 2));
        $this->assertSame(1000.0, round($pl->viewData('totalExpenses') - $plBefore->viewData('totalExpenses'), 2));
        $this->assertSame(1000.0, round($pl->viewData('netProfit') - $plBefore->viewData('netProfit'), 2));

        // Cash flow: bank +2,300 − 1,150 = +1,150 net, and closing = opening + in − out.
        $cash = $this->report('cash-flow');
        $this->assertSame(2300.0, round($cash->viewData('cashIn') - $cashBefore->viewData('cashIn'), 2));
        $this->assertSame(1150.0, round($cash->viewData('cashOut') - $cashBefore->viewData('cashOut'), 2));
        $this->assertSame(
            round($cash->viewData('openingCash') + $cash->viewData('cashIn') - $cash->viewData('cashOut'), 2),
            round($cash->viewData('closingCash'), 2)
        );

        // Trial balance debits = credits; balance sheet identity holds; AP and AR net zero for these documents.
        $trial = $this->report('trial-balance');
        $this->assertSame(round($trial->viewData('totalDebit'), 2), round($trial->viewData('totalCredit'), 2));
        $sheet = $this->report('balance-sheet');
        $this->assertSame(
            round($sheet->viewData('totalAssets'), 2),
            round($sheet->viewData('totalLiabilities') + $sheet->viewData('totalEquity') + $sheet->viewData('netProfit'), 2)
        );
        $this->assertSame(0.0, (float) $bill->fresh()->balance_amount);
        $this->assertSame(0.0, (float) $invoice->fresh()->balance_amount);

        // General ledger for the bank account: opening + movements = closing and the export carries the figures.
        $ledger = $this->actingAs($this->admin())->get(route('admin.accounting.general-ledger', ['from' => $this->today(), 'to' => $this->today(), 'account' => $this->account(PostingService::BANK)->id]))->assertOk();
        $lines = $ledger->viewData('lines')->getCollection();
        $this->assertGreaterThanOrEqual(2, $lines->count());
        $expected = round($ledger->viewData('openingBalance') + $lines->sum(fn ($l) => (float) $l->debit) - $lines->sum(fn ($l) => (float) $l->credit), 2);
        $this->assertSame($expected, round((float) $lines->last()->running_balance, 2), 'opening + page movements = last running balance (single page)');

        $csv = $this->actingAs($this->admin())->get(route('admin.accounting.reports.profit-loss', ['from' => $this->today(), 'to' => $this->today(), 'export' => 'csv']))->assertOk();
        $this->assertStringContainsString('"Net Profit / Loss",'.round((float) $pl->viewData('netProfit'), 2), $csv->streamedContent());
        $this->assertStringContainsString('"Total Revenue",'.round((float) $pl->viewData('totalRevenue'), 2), $csv->streamedContent());
    }

    // ------------------------------------------------------------------ FIN-06

    public function test_fin06_vat_return_counts_each_document_once_and_a_finalized_period_refuses_late_entries(): void
    {
        $period = VatPeriod::where('status', 'draft')->orderBy('start_date')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.recalculate', $period))->assertSessionHasNoErrors();
        $before = $period->fresh();

        // Input: FIN-02 bill (input 150) and FIN-04 invoice (output 300).
        $bill = $this->approvedBill('BILL-FIN06');
        $invoice = $this->approvedInvoice(2000);
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.recalculate', $period))->assertSessionHasNoErrors();
        $after = $period->fresh();
        $this->assertSame(300.0, round((float) $after->output_vat - (float) $before->output_vat, 2));
        $this->assertSame(150.0, round((float) $after->input_vat - (float) $before->input_vat, 2));
        $this->assertSame(150.0, round((float) $after->vat_payable - (float) $before->vat_payable, 2));

        // Correction in the open period: reopen the unpaid bill. The original journal stays,
        // a reversal neutralises it, the VAT row is withdrawn, and re-approval counts VAT once.
        $original = $bill->journalEntry;
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'Wrong amount'])->assertSessionHasNoErrors();
        $this->assertSame('posted', $original->fresh()->status, 'posted history is immutable');
        $reversal = JournalEntry::where('source_module', 'Manual')->where('source_id', $original->id)->where('reference_number', $original->journal_number)->firstOrFail();
        $this->assertSame(['1300' => [0.0, 150.0], '2100' => [1150.0, 0.0], '5200' => [0.0, 1000.0]], $this->linesOf($reversal));
        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertCount(0, $this->vatRows('Supplier Bill', $bill->id), 'VAT withdrawn with the reopen');

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->assertCount(1, $this->vatRows('Supplier Bill', $bill->id), 'VAT counted exactly once after re-approval');
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.recalculate', $period))->assertSessionHasNoErrors();
        $this->assertSame(150.0, round((float) $period->fresh()->input_vat - (float) $before->input_vat, 2));

        // Zero-rated bill: no VAT row, still posts.
        $zero = $this->draftBill('BILL-FIN06-Z', 100, 0);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $zero))->assertSessionHasNoErrors();
        $this->assertCount(0, $this->vatRows('Supplier Bill', $zero->id));

        // Finalize, then a late bill, a late correction and a second finalize are all refused.
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.finalize', $period))->assertSessionHasNoErrors();
        $this->assertSame('finalized', $period->fresh()->status);
        $late = $this->draftBill('BILL-FIN06-LATE');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $late))->assertSessionHasErrors('vat');
        $this->assertSame('draft', $late->fresh()->status);
        $this->assertNull($late->fresh()->journal_entry_id);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.reopen', $invoice), ['reason' => 'Late'])->assertSessionHasErrors('vat');
        $this->assertSame('unpaid', $invoice->fresh()->payment_status);
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.finalize', $period))->assertSessionHasErrors('vat');
        $this->actingAs($this->admin())->post(route('admin.accounting.vat.recalculate', $period))->assertSessionHasErrors('vat');
        $this->assertSame((float) $after->output_vat, (float) $period->fresh()->output_vat, 'finalized totals are frozen');

        // Audit log.
        $this->logged('Finalized VAT period', $period->period_name);
        $this->logged('Reopened supplier bill', 'BILL-FIN06');
    }

    // ------------------------------------------------------------------ FIN-07

    public function test_fin07_receivable_ageing_places_open_invoices_in_the_agreed_buckets(): void
    {
        $before = $this->actingAs($this->admin())->get(route('admin.accounting.dashboard'))->assertOk()->viewData('receivableAging');

        // Input: four open invoices (gross 1,150 each) due today, 31, 61 and 91 days ago, one settled, one draft.
        $dueToday = $this->approvedInvoice(1000, null, $this->today());
        $late31 = $this->approvedInvoice(1000);
        $late61 = $this->approvedInvoice(1000);
        $late91 = $this->approvedInvoice(1000);
        // Time passes: the due dates are now in the past (the invoice date stays valid, only the term expired).
        $late31->update(['due_date' => now()->subDays(31)->toDateString()]);
        $late61->update(['due_date' => now()->subDays(61)->toDateString()]);
        $late91->update(['due_date' => now()->subDays(91)->toDateString()]);
        $settled = $this->approvedInvoice(1000, null, $this->today());
        $this->receive($settled, 1150, 'fin07-settled')->assertSessionHasNoErrors();
        $this->draftInvoice(1000, null, $this->today());

        $after = $this->actingAs($this->admin())->get(route('admin.accounting.dashboard'))->assertOk()->viewData('receivableAging');
        $delta = fn (string $bucket) => round($after[$bucket] - $before[$bucket], 2);

        // Expected: due today = Current; 31 days = 31–60; 61 and 91 days = 60+. Settled and draft excluded.
        $this->assertSame(1150.0, $delta('Current'));
        $this->assertSame(0.0, $delta('1-30 days'));
        $this->assertSame(1150.0, $delta('31-60 days'));
        $this->assertSame(2300.0, $delta('60+ days'));

        // A partial receipt reduces the residual in its bucket, not the whole invoice.
        $this->receive($late31, 150, 'fin07-partial')->assertSessionHasNoErrors();
        $again = $this->actingAs($this->admin())->get(route('admin.accounting.dashboard'))->viewData('receivableAging');
        $this->assertSame(1000.0, round($again['31-60 days'] - $before['31-60 days'], 2));
    }

    // ------------------------------------------------------------------ FIN-08

    public function test_fin08_goods_receipt_issue_and_matched_bill_carry_one_liability_and_one_vat(): void
    {
        $inventoryBefore = $this->movementToday(PostingService::INVENTORY_ASSET);
        $payableBefore = $this->movementToday(PostingService::PAYABLE);
        $inputVatBefore = $this->movementToday(PostingService::INPUT_VAT);
        $expenseBefore = $this->movementToday(PostingService::MATERIAL_EXPENSE);

        $warehouse = Warehouse::firstOrFail();
        $item = Item::create([
            'item_code' => 'ITM-FIN08', 'name' => 'FIN-08 cement bag', 'unit_id' => Unit::first()?->id, 'valuation_method' => 'average',
            'inventory_account_id' => $this->account(PostingService::INVENTORY_ASSET)->id,
            'expense_account_id' => $this->account(PostingService::MATERIAL_EXPENSE)->id, 'vat_applicable' => true, 'status' => 'active',
        ]);

        // Input: GRN 10 units at 100, VAT 15 %.
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'warehouse_id' => $warehouse->id, 'received_date' => $this->today(),
            'delivery_note_number' => 'DN-FIN08', 'vat_rate' => 15,
            'lines' => [['item_id' => $item->id, 'ordered_quantity' => 10, 'received_quantity' => 10, 'accepted_quantity' => 10, 'unit_cost' => 100]],
        ])->assertSessionHasNoErrors();
        $grn = GoodsReceipt::where('delivery_note_number', 'DN-FIN08')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))->assertSessionHasNoErrors();

        // Expected (F04 GRNI model): stock 10 / 1,000; Dr Inventory 1,000 / Cr GRNI 1,000;
        // no supplier payable and no VAT row at receipt stage.
        $grniBefore = $this->movementToday(PostingService::GRNI);
        $stock = WarehouseStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->firstOrFail();
        $this->assertSame(10.0, (float) $stock->quantity);
        $this->assertSame(1000.0, (float) $stock->total_value);
        $this->assertSame(round($inventoryBefore + 1000, 2), $this->movementToday(PostingService::INVENTORY_ASSET));
        $this->assertSame(['1400' => [1000.0, 0.0], '2150' => [0.0, 1000.0]], $this->linesOf($grn->fresh()->journalEntry));
        $this->assertSame($payableBefore, $this->movementToday(PostingService::PAYABLE), 'no payable on a receipt');
        $this->assertSame($inputVatBefore, $this->movementToday(PostingService::INPUT_VAT), 'no input VAT on a receipt');
        $this->assertCount(0, $this->vatRows('Goods Receipt', $grn->id));
        $vatCount = VatTransaction::count();

        // Action: issue 3 units to a project.
        $this->actingAs($this->admin())->post(route('admin.inventory.stock-issues.store'), [
            'warehouse_id' => $warehouse->id, 'issue_date' => $this->today(), 'purpose' => 'FIN-08 issue',
            'lines' => [['item_id' => $item->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();
        $issue = StockIssue::where('purpose', 'FIN-08 issue')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.inventory.stock-issues.post', $issue))->assertSessionHasNoErrors();

        // Expected: Dr Material Expense 300 / Cr Inventory 300; stock 7 / 700; no VAT on an issue.
        $stock->refresh();
        $this->assertSame(7.0, (float) $stock->quantity);
        $this->assertSame(700.0, (float) $stock->total_value);
        $this->assertSame(round($inventoryBefore + 700, 2), $this->movementToday(PostingService::INVENTORY_ASSET));
        $this->assertSame(round($expenseBefore + 300, 2), $this->movementToday(PostingService::MATERIAL_EXPENSE));
        $this->assertSame($vatCount, VatTransaction::count(), 'no VAT on an issue');

        // Action: the supplier's invoice for the same delivery, matched to the receipt line (F04).
        $grnLine = $grn->lines()->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => 'BILL-FIN08', 'bill_date' => $this->today(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'Cement bags', 'goods_receipt_line_id' => $grnLine->id, 'matched_quantity' => 10, 'quantity' => 10, 'unit_price' => 100]],
        ])->assertSessionHasNoErrors();
        $bill = SupplierBill::where('bill_number', 'BILL-FIN08')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();

        // Expected: Dr GRNI 1,000 / Dr Input VAT 150 / Cr AP 1,150; GRNI nets to zero; the
        // liability and the VAT exist exactly once; inventory value is untouched by the bill.
        $this->assertSame(['1300' => [150.0, 0.0], '2100' => [0.0, 1150.0], '2150' => [1000.0, 0.0]], $this->linesOf($bill->fresh()->journalEntry));
        $this->assertSame(round($grniBefore + 1000, 2), $this->movementToday(PostingService::GRNI), 'the accrual is cleared: GRNI is back where it stood before the receipt');
        $this->assertSame(round($payableBefore - 1150, 2), $this->movementToday(PostingService::PAYABLE), 'one payable for the purchase');
        $this->assertSame(round($inputVatBefore + 150, 2), $this->movementToday(PostingService::INPUT_VAT), 'one input VAT for the purchase');
        $this->assertCount(1, $this->vatRows('Supplier Bill', $bill->id));
        $this->assertSame($vatCount + 1, VatTransaction::count());
        $this->assertSame(round($inventoryBefore + 700, 2), $this->movementToday(PostingService::INVENTORY_ASSET));
        $this->assertSame(10.0, (float) $grnLine->fresh()->invoiced_quantity);
        $this->assertSame(1150.0, (float) $bill->fresh()->balance_amount);

        // The same received quantity cannot be invoiced twice.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'bill_number' => 'BILL-FIN08-DUP', 'bill_date' => $this->today(), 'vat_rate' => 15,
            'lines' => [['description' => 'Cement bags again', 'goods_receipt_line_id' => $grnLine->id, 'matched_quantity' => 1, 'quantity' => 1, 'unit_price' => 100]],
        ])->assertSessionHasErrors('matching');

        // Audit log.
        $this->assertTrue(ActivityLog::where('module', 'Inventory')->where('action', 'Posted goods receipt')->where('description', $grn->grn_number)->exists());
        $this->logged('Approved supplier bill', 'BILL-FIN08');
    }

    // ------------------------------------------------------------------ FIN-09

    public function test_fin09_an_actor_without_approve_rights_or_outside_scope_cannot_finalize_a_bill(): void
    {
        $bill = $this->draftBill('BILL-FIN09');
        $journals = JournalEntry::count();

        // A clerk with view/create/edit on Accounts Payable, but no approve.
        $role = Role::create(['name' => 'AP clerk', 'code' => 'AP_CLERK_FIN09', 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where('module', 'Accounts Payable')->whereIn('action', ['view', 'create', 'edit'])->pluck('id'));
        $clerk = User::create(['name' => 'AP Clerk', 'email' => 'ap-clerk@example.test', 'username' => 'ap.clerk', 'password' => 'a-strong-password-123', 'status' => 'active']);
        $clerk->roles()->attach($role, ['is_primary' => true]);

        $this->actingAs($clerk->fresh())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertForbidden();
        $this->actingAs($clerk->fresh())->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'x'])->assertForbidden();
        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertSame($journals, JournalEntry::count(), 'no GL effect from a refused approval');

        // The clerk may still read and edit the draft.
        $this->actingAs($clerk->fresh())->get(route('admin.accounting.accounts-payable.show', $bill))->assertOk();

        // The authorised approver finalises it once; a second approval is refused (F11).
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasErrors('bill');
        $this->assertSame($journals + 1, JournalEntry::count());

        // BLOCKED: a two-step "first approval leaves pending, final approval posts" flow needs the
        // multi-approver runtime, which does not exist yet. Single-actor approval is what runs today.
    }

    // ------------------------------------------------------------------ FIN-10

    public function test_fin10_cancel_reversal_and_immutability_rules_hold(): void
    {
        // Cancelling a draft journal has no GL effect.
        $bankBefore = $this->movementToday(PostingService::BANK);
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.store'), $this->journalPayload([
            [PostingService::BANK, 500, 0], ['3100', 0, 500],
        ], 'draft', 'FIN-10 draft'))->assertSessionHasNoErrors();
        $draft = JournalEntry::where('description', 'FIN-10 draft')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.cancel', $draft))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $draft->fresh()->status);
        $this->assertSame($bankBefore, $this->movementToday(PostingService::BANK));
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.post', $draft))->assertSessionHasErrors();
        $this->assertSame('cancelled', $draft->fresh()->status, 'a cancelled journal cannot be posted');

        // A permitted reversal of an unpaid bill nets to zero and keeps the posted history.
        $payableBefore = $this->movementToday(PostingService::PAYABLE);
        $expenseBefore = $this->movementToday(PostingService::MATERIAL_EXPENSE);
        $bill = $this->approvedBill('BILL-FIN10');
        $original = $bill->journalEntry;
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'Duplicate entry'])->assertSessionHasNoErrors();
        $this->assertSame($payableBefore, $this->movementToday(PostingService::PAYABLE));
        $this->assertSame($expenseBefore, $this->movementToday(PostingService::MATERIAL_EXPENSE));
        $this->assertSame('posted', $original->fresh()->status);
        $this->assertSame(2, JournalEntry::where('status', 'posted')->where(fn ($q) => $q->whereKey($original->id)->orWhere('source_id', $original->id))->count(), 'original and reversal both kept');

        // Posted history is immutable through every route.
        $this->actingAs($this->admin())->delete(route('admin.accounting.journal-entries.destroy', $original))->assertSessionHasErrors('journal');
        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.cancel', $original))->assertSessionHasErrors('journal');
        $this->actingAs($this->admin())->put(route('admin.accounting.journal-entries.update', $original), $this->journalPayload([
            [PostingService::BANK, 1, 0], ['3100', 0, 1],
        ]))->assertSessionHasErrors('journal');
        $this->assertSame(3, $original->fresh()->lines()->count());

        // A settled bill cannot be reopened; a payment cannot be reversed until a correction process exists.
        $paid = $this->approvedBill('BILL-FIN10-PAID');
        $this->pay($paid, 1150, 'fin10-paid')->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.reopen', $paid), ['reason' => 'x'])->assertSessionHasErrors('bill');
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.accounting.accounts-payable.payment.reverse'), 'no payment reversal route exists yet (known gap, documented)');

        // Audit log.
        $this->logged('Reopened supplier bill', 'BILL-FIN10');
        $this->logged('Cancelled journal entry', $draft->journal_number);
    }
}
