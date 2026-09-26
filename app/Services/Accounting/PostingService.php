<?php

namespace App\Services\Accounting;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Models\ZatcaInvoiceRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns approved finance documents into balanced journal entries, VAT
 * transactions and ZATCA records, following the Phase 4 posting rules.
 *
 * Finance correctness sprint F01: a posting that cannot be recorded correctly
 * is refused with a ValidationException instead of returning null or a
 * one-sided entry. Every caller runs inside a database transaction, so the
 * refusal rolls the whole business event back (no half-approved bill, no
 * payment without its journal, no stock movement without its accounting).
 */
class PostingService
{
    /** Well-known account codes seeded by the standard chart of accounts. */
    public const CASH = '1110';

    public const BANK = '1120';

    public const RECEIVABLE = '1200';

    public const INPUT_VAT = '1300';

    public const INVENTORY_ASSET = '1400';

    public const PAYABLE = '2100';

    /** Goods Received Not Invoiced: the accrual a posted GRN carries until its supplier bill is matched (F04). */
    public const GRNI = '2150';

    public const OUTPUT_VAT = '2210';

    public const SALARY_PAYABLE = '2300';

    public const REVENUE = '4100';

    public const SALARY_EXPENSE = '5100';

    public const MATERIAL_EXPENSE = '5200';

    public const INVENTORY_ADJUSTMENT_EXPENSE = '5600';

    public function account(string $code): ?ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->first();
    }

    /**
     * A supplier may be linked to a sub-account of Accounts Payable (client
     * change request CR-18). Suppliers without a link, or linked to an inactive
     * account, post to the control account exactly as before.
     */
    public function payableAccountFor(?Supplier $supplier): ?ChartOfAccount
    {
        $linked = $supplier?->linked_account_id ? ChartOfAccount::find($supplier->linked_account_id) : null;

        return $linked && $linked->status === 'active' ? $linked : $this->account(self::PAYABLE);
    }

    /**
     * Debit expense/inventory accounts and input VAT, credit accounts payable.
     */
    public function postSupplierBill(SupplierBill $bill, ?int $userId = null): JournalEntry
    {
        $bill->loadMissing('lines.grnMatch', 'supplier');

        $payable = $this->activeOrRefuse($this->payableAccountFor($bill->supplier), 'Accounts Payable ('.self::PAYABLE.')');
        $inputVat = (float) $bill->vat_amount > 0 ? $this->requireAccount(self::INPUT_VAT, 'Input VAT') : null;
        $grni = $bill->lines->contains(fn ($line) => $line->grnMatch !== null)
            ? $this->requireAccount(self::GRNI, 'Goods Received Not Invoiced')
            : null;
        $dimensions = ['project_id' => $bill->project_id, 'site_id' => $bill->site_id];

        $lines = [];

        foreach ($bill->lines as $line) {
            $account = $this->lineAccount($line->chart_of_account_id, self::MATERIAL_EXPENSE, 'expense account on bill line "'.$line->description.'"');
            $costCenter = $line->cost_center_id ?? $bill->cost_center_id;

            // A line invoicing received goods clears the GRNI accrual at the receipt's cost (F04).
            // Any difference between the invoiced price and the receipt cost is a price
            // variance on the line's expense account; the inventory value is not restated.
            if ($match = $line->grnMatch) {
                $accrued = (float) $match->matched_taxable_amount;
                $variance = round((float) $line->taxable_amount - $accrued, 2);

                $lines[] = [
                    'chart_of_account_id' => $grni->id,
                    'description' => 'GRNI cleared: '.$line->description,
                    'debit' => $accrued,
                    'credit' => 0,
                    'cost_center_id' => $costCenter,
                ] + $dimensions;

                if (abs($variance) >= 0.01) {
                    $lines[] = [
                        'chart_of_account_id' => $account->id,
                        'description' => 'Price variance: '.$line->description,
                        'debit' => $variance > 0 ? $variance : 0,
                        'credit' => $variance < 0 ? -$variance : 0,
                        'cost_center_id' => $costCenter,
                    ] + $dimensions;
                }

                continue;
            }

            $lines[] = [
                'chart_of_account_id' => $account->id,
                'description' => $line->description,
                'debit' => (float) $line->taxable_amount,
                'credit' => 0,
                'cost_center_id' => $costCenter,
            ] + $dimensions;
        }

        if ($lines === []) {
            $lines[] = [
                'chart_of_account_id' => $this->requireAccount(self::MATERIAL_EXPENSE, 'Material Expense')->id,
                'description' => 'Supplier bill '.$bill->bill_number,
                'debit' => (float) $bill->taxable_amount,
                'credit' => 0,
                'cost_center_id' => $bill->cost_center_id,
            ] + $dimensions;
        }

        if ($inputVat) {
            $lines[] = [
                'chart_of_account_id' => $inputVat->id,
                'description' => 'Input VAT on '.$bill->bill_number,
                'debit' => (float) $bill->vat_amount,
                'credit' => 0,
                'cost_center_id' => $bill->cost_center_id,
            ] + $dimensions;
        }

        $lines[] = [
            'chart_of_account_id' => $payable->id,
            'description' => $bill->supplier->name.' - '.$bill->bill_number,
            'debit' => 0,
            'credit' => (float) $bill->total_amount,
            'cost_center_id' => $bill->cost_center_id,
        ] + $dimensions;

        $entry = $this->createEntry([
            'journal_date' => $bill->bill_date,
            'reference_number' => $bill->bill_number,
            'source_module' => 'Supplier Bill',
            'source_id' => $bill->id,
            'description' => 'Supplier bill '.$bill->bill_number.' - '.$bill->supplier->name,
            'cost_center_id' => $bill->cost_center_id,
        ], $lines, 'Supplier Bill', 'Bill Approved', $userId);

        $this->recordVat(
            'input', $bill->vat_amount, $bill->taxable_amount, $bill->vat_rate,
            $bill->bill_date, 'Supplier Bill', $bill->id, $bill->bill_number,
            'supplier', $bill->supplier_id, $bill->supplier->name
        );

        return $entry;
    }

    /**
     * Debit accounts payable, credit the cash/bank account used.
     */
    public function postSupplierPayment(SupplierPayment $payment, ?int $userId = null): JournalEntry
    {
        $payment->loadMissing('supplier', 'bill');

        $payable = $this->activeOrRefuse($this->payableAccountFor($payment->supplier), 'Accounts Payable ('.self::PAYABLE.')');
        $paymentAccount = $payment->payment_account_id
            ? $this->activeOrRefuse(ChartOfAccount::find($payment->payment_account_id), 'payment account')
            : $this->requireAccount(self::BANK, 'Bank');
        $dimensions = ['project_id' => $payment->bill?->project_id, 'site_id' => $payment->bill?->site_id];

        return $this->createEntry([
            'journal_date' => $payment->payment_date,
            'reference_number' => $payment->reference_number,
            'source_module' => 'Supplier Payment',
            'source_id' => $payment->id,
            'description' => 'Payment to '.$payment->supplier->name,
        ], [
            [
                'chart_of_account_id' => $payable->id,
                'description' => 'Payment to '.$payment->supplier->name,
                'debit' => (float) $payment->amount,
                'credit' => 0,
            ] + $dimensions,
            [
                'chart_of_account_id' => $paymentAccount->id,
                'description' => 'Payment to '.$payment->supplier->name,
                'debit' => 0,
                'credit' => (float) $payment->amount,
            ] + $dimensions,
        ], 'Supplier Payment', 'Payment Recorded', $userId);
    }

    /**
     * Debit accounts receivable, credit revenue and output VAT.
     */
    public function postCustomerInvoice(CustomerInvoice $invoice, ?int $userId = null): JournalEntry
    {
        $invoice->loadMissing('lines', 'customer');

        $receivable = $this->requireAccount(self::RECEIVABLE, 'Accounts Receivable');
        $outputVat = (float) $invoice->vat_amount > 0 ? $this->requireAccount(self::OUTPUT_VAT, 'Output VAT') : null;
        $dimensions = ['project_id' => $invoice->project_id];

        $lines = [[
            'chart_of_account_id' => $receivable->id,
            'description' => $invoice->customer->name.' - '.$invoice->invoice_number,
            'debit' => (float) $invoice->total_amount,
            'credit' => 0,
            'cost_center_id' => $invoice->cost_center_id,
        ] + $dimensions];

        foreach ($invoice->lines as $line) {
            $account = $this->lineAccount($line->revenue_account_id, self::REVENUE, 'revenue account on invoice line "'.$line->description.'"');

            $lines[] = [
                'chart_of_account_id' => $account->id,
                'description' => $line->description,
                'debit' => 0,
                'credit' => (float) $line->taxable_amount,
                'cost_center_id' => $line->cost_center_id ?? $invoice->cost_center_id,
            ] + $dimensions;
        }

        if ($invoice->lines->isEmpty()) {
            $lines[] = [
                'chart_of_account_id' => $this->requireAccount(self::REVENUE, 'Project Revenue')->id,
                'description' => 'Invoice '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => (float) $invoice->taxable_amount,
                'cost_center_id' => $invoice->cost_center_id,
            ] + $dimensions;
        }

        if ($outputVat) {
            $lines[] = [
                'chart_of_account_id' => $outputVat->id,
                'description' => 'Output VAT on '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => (float) $invoice->vat_amount,
                'cost_center_id' => $invoice->cost_center_id,
            ] + $dimensions;
        }

        $entry = $this->createEntry([
            'journal_date' => $invoice->invoice_date,
            'reference_number' => $invoice->invoice_number,
            'source_module' => 'Customer Invoice',
            'source_id' => $invoice->id,
            'description' => 'Customer invoice '.$invoice->invoice_number.' - '.$invoice->customer->name,
            'cost_center_id' => $invoice->cost_center_id,
        ], $lines, 'Customer Invoice', 'Invoice Approved', $userId);

        $this->recordVat(
            'output', $invoice->vat_amount, $invoice->taxable_amount, $invoice->vat_rate,
            $invoice->invoice_date, 'Customer Invoice', $invoice->id, $invoice->invoice_number,
            'customer', $invoice->customer_id, $invoice->customer->name
        );

        return $entry;
    }

    /**
     * Debit cash/bank, credit accounts receivable.
     */
    public function postCustomerReceipt(CustomerReceipt $receipt, ?int $userId = null): JournalEntry
    {
        $receipt->loadMissing('customer', 'invoice');

        $receivable = $this->requireAccount(self::RECEIVABLE, 'Accounts Receivable');
        $receiptAccount = $receipt->receipt_account_id
            ? $this->activeOrRefuse(ChartOfAccount::find($receipt->receipt_account_id), 'receipt account')
            : $this->requireAccount(self::BANK, 'Bank');
        $dimensions = ['project_id' => $receipt->invoice?->project_id];

        return $this->createEntry([
            'journal_date' => $receipt->receipt_date,
            'reference_number' => $receipt->reference_number,
            'source_module' => 'Customer Receipt',
            'source_id' => $receipt->id,
            'description' => 'Receipt from '.$receipt->customer->name,
        ], [
            [
                'chart_of_account_id' => $receiptAccount->id,
                'description' => 'Receipt from '.$receipt->customer->name,
                'debit' => (float) $receipt->amount,
                'credit' => 0,
            ] + $dimensions,
            [
                'chart_of_account_id' => $receivable->id,
                'description' => 'Receipt from '.$receipt->customer->name,
                'debit' => 0,
                'credit' => (float) $receipt->amount,
            ] + $dimensions,
        ], 'Customer Receipt', 'Receipt Recorded', $userId);
    }

    /**
     * Goods receipt: debit inventory asset and input VAT, credit accounts payable.
     * Returns null only when the receipt carries no value at all.
     */
    public function postGoodsReceipt(GoodsReceipt $grn, ?int $userId = null): ?JournalEntry
    {
        $grn->loadMissing('lines.item', 'supplier', 'warehouse');

        $dimensions = ['project_id' => $grn->warehouse?->project_id, 'site_id' => $grn->warehouse?->site_id];

        $lines = [];
        $accrued = 0.0;

        foreach ($grn->lines as $line) {
            if ((float) $line->total_cost <= 0) {
                continue;
            }

            $account = $this->lineAccount($line->item?->inventory_account_id, self::INVENTORY_ASSET, 'inventory account for '.($line->item?->label() ?? 'item'));

            $lines[] = [
                'chart_of_account_id' => $account->id,
                'description' => $line->item?->label(),
                'debit' => (float) $line->total_cost,
                'credit' => 0,
            ] + $dimensions;
            $accrued = round($accrued + (float) $line->total_cost, 2);
        }

        if ($accrued <= 0) {
            return null;
        }

        // The receipt accrues to Goods Received Not Invoiced. Supplier accounts payable and
        // input VAT are recognised once, on the matched supplier bill (F04).
        $grni = $this->requireAccount(self::GRNI, 'Goods Received Not Invoiced');

        $lines[] = [
            'chart_of_account_id' => $grni->id,
            'description' => 'GRNI accrual: '.$grn->supplier->name.' - '.$grn->grn_number,
            'debit' => 0,
            'credit' => $accrued,
        ] + $dimensions;

        return $this->createEntry([
            'journal_date' => $grn->received_date,
            'reference_number' => $grn->grn_number,
            'source_module' => 'Inventory',
            'source_id' => $grn->id,
            'description' => 'Goods receipt '.$grn->grn_number.' - '.$grn->supplier->name,
        ], $lines, 'Inventory', 'Inventory Purchase', $userId);
    }

    /**
     * Stock issue: debit project material expense, credit inventory asset.
     * Returns null only when the issue carries no value.
     */
    public function postStockIssue(StockIssue $issue, ?int $userId = null): ?JournalEntry
    {
        $issue->loadMissing('lines.item', 'warehouse');

        if ((float) $issue->total_cost <= 0) {
            return null;
        }

        $dimensions = ['project_id' => $issue->project_id, 'site_id' => $issue->site_id];
        $lines = [];

        foreach ($issue->lines as $line) {
            if ((float) $line->total_cost <= 0) {
                continue;
            }

            $label = $line->item?->label() ?? 'item';
            $expense = $this->lineAccount($line->item?->expense_account_id, self::MATERIAL_EXPENSE, 'expense account for '.$label);
            $inventory = $this->lineAccount($line->item?->inventory_account_id, self::INVENTORY_ASSET, 'inventory account for '.$label);

            $lines[] = [
                'chart_of_account_id' => $expense->id,
                'description' => $label,
                'debit' => (float) $line->total_cost,
                'credit' => 0,
            ] + $dimensions;

            $lines[] = [
                'chart_of_account_id' => $inventory->id,
                'description' => $label,
                'debit' => 0,
                'credit' => (float) $line->total_cost,
            ] + $dimensions;
        }

        return $this->createEntry([
            'journal_date' => $issue->issue_date,
            'reference_number' => $issue->issue_number,
            'source_module' => 'Inventory',
            'source_id' => $issue->id,
            'description' => 'Stock issue '.$issue->issue_number.' to '.($issue->project?->name ?? 'store'),
        ], $lines, 'Inventory', 'Stock Issued', $userId);
    }

    /**
     * Stock adjustment loss: debit inventory adjustment expense, credit
     * inventory asset. A gain reverses those two sides. Returns null only
     * when the adjustment has no value.
     */
    public function postStockAdjustment(StockAdjustment $adjustment, ?int $userId = null): ?JournalEntry
    {
        $adjustment->loadMissing('item', 'warehouse');

        $value = abs((float) $adjustment->adjustment_value);

        if ($value <= 0) {
            return null;
        }

        $inventory = $this->lineAccount($adjustment->item?->inventory_account_id, self::INVENTORY_ASSET, 'inventory account for '.($adjustment->item?->label() ?? 'item'));
        $adjustmentExpense = $this->requireAccount(self::INVENTORY_ADJUSTMENT_EXPENSE, 'Inventory Adjustment Expense');
        $dimensions = ['project_id' => $adjustment->warehouse?->project_id, 'site_id' => $adjustment->warehouse?->site_id];

        $isLoss = $adjustment->isLoss();

        $lines = [
            [
                'chart_of_account_id' => $isLoss ? $adjustmentExpense->id : $inventory->id,
                'description' => 'Stock adjustment '.$adjustment->adjustment_number,
                'debit' => $value,
                'credit' => 0,
            ] + $dimensions,
            [
                'chart_of_account_id' => $isLoss ? $inventory->id : $adjustmentExpense->id,
                'description' => 'Stock adjustment '.$adjustment->adjustment_number,
                'debit' => 0,
                'credit' => $value,
            ] + $dimensions,
        ];

        return $this->createEntry([
            'journal_date' => $adjustment->adjustment_date,
            'reference_number' => $adjustment->adjustment_number,
            'source_module' => 'Inventory',
            'source_id' => $adjustment->id,
            'description' => 'Stock adjustment '.$adjustment->adjustment_number.' ('.($isLoss ? 'loss' : 'gain').')',
        ], $lines, 'Inventory', 'Stock Adjusted', $userId);
    }

    /**
     * ZATCA foundation only: UUID, QR payload, XML path and a tamper-proof hash
     * are generated locally. Production clearance happens in the ZATCA phase.
     */
    public function createZatcaRecord(CustomerInvoice $invoice): ZatcaInvoiceRecord
    {
        $invoice->loadMissing('customer');
        $uuid = (string) Str::uuid();

        $qr = base64_encode(implode('|', [
            $invoice->customer->name,
            $invoice->invoice_number,
            $invoice->invoice_date?->toDateString(),
            (string) $invoice->total_amount,
            (string) $invoice->vat_amount,
        ]));

        $record = ZatcaInvoiceRecord::updateOrCreate(
            ['customer_invoice_id' => $invoice->id],
            [
                'uuid' => $invoice->zatcaRecord?->uuid ?? $uuid,
                'qr_code_data' => $qr,
                'xml_file_path' => 'zatca/xml/'.$invoice->invoice_number.'.xml',
                'digital_signature_status' => 'signed',
                'clearance_status' => 'pending',
                'retry_count' => 0,
                'tamper_proof_hash' => hash('sha256', $uuid.$invoice->invoice_number.$invoice->total_amount),
            ]
        );

        $invoice->update(['zatca_status' => 'pending_clearance']);

        return $record;
    }

    public function recordVat(
        string $type,
        float|string $vatAmount,
        float|string $taxableAmount,
        float|string $vatRate,
        $date,
        string $sourceModule,
        ?int $sourceId,
        ?string $reference,
        string $partyType,
        ?int $partyId,
        ?string $partyName
    ): ?VatTransaction {
        if ((float) $vatAmount <= 0) {
            return null;
        }

        // Keep the period lock until insertion commits, including direct service calls.
        return DB::transaction(function () use ($type, $vatAmount, $taxableAmount, $vatRate, $date, $sourceModule, $sourceId, $reference, $partyType, $partyId, $partyName) {
            $period = $this->assertVatPeriodOpen($date, $sourceModule.' '.($reference ?? ''));

            return VatTransaction::create([
                'transaction_date' => $date,
                'source_module' => $sourceModule,
                'source_id' => $sourceId,
                'source_reference' => $reference,
                'party_type' => $partyType,
                'party_id' => $partyId,
                'party_name' => $partyName,
                'taxable_amount' => $taxableAmount,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'vat_type' => $type,
                'vat_period_id' => $period?->id,
                'status' => 'active',
            ]);
        });
    }

    public function periodFor($date, bool $lock = false): ?VatPeriod
    {
        return VatPeriod::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /** VAT control accounts: a journal line on one of these changes a VAT return. */
    public function vatAccountIds(): array
    {
        return ChartOfAccount::whereIn('account_code', [self::INPUT_VAT, self::OUTPUT_VAT])->pluck('id')->all();
    }

    /**
     * Refuse any VAT-bearing transaction dated inside a finalized or submitted
     * VAT period (F03). The date decides the period; nothing is reopened
     * automatically, and correcting a sealed period needs a credit note.
     */
    public function assertVatPeriodOpen($date, string $subject): ?VatPeriod
    {
        // Outside a transaction this is only a form preflight. Every writer must
        // recheck inside its transaction, where this is a current, locking read.
        $period = $this->periodFor($date, DB::transactionLevel() > 0);

        if ($period && in_array($period->status, ['finalized', 'submitted'], true)) {
            throw ValidationException::withMessages([
                'vat' => trim($subject).' is dated '.Carbon::parse($date)->toDateString()
                    .', inside VAT period '.$period->period_name.' which is already '.$period->status
                    .'. Nothing was recorded. Use a date in an open period, or issue a credit note for a sealed one.',
            ]);
        }

        return $period;
    }

    /**
     * Post a balanced entry that undoes another one, debit for credit (client
     * change request NR-31). The original stays in the ledger untouched; the
     * reversal references its journal number so the audit trail is complete.
     */
    public function reverseEntry(JournalEntry $entry, string $description, ?int $userId = null): JournalEntry
    {
        $entry->loadMissing('lines');

        $lines = $entry->lines->map(fn ($line) => [
            'chart_of_account_id' => $line->chart_of_account_id,
            'description' => 'Reversal: '.$line->description,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'cost_center_id' => $line->cost_center_id,
            'project_id' => $line->project_id,
            'site_id' => $line->site_id,
        ])->all();

        return $this->createEntry([
            'journal_date' => now()->toDateString(),
            'reference_number' => $entry->journal_number,
            'source_module' => 'Manual',
            'source_id' => $entry->id,
            'description' => $description,
            'cost_center_id' => $entry->cost_center_id,
        ], $lines, 'Manual', 'Reversal', $userId, true);
    }

    /**
     * Take a document's VAT rows out of the return again when the document is
     * sent back to draft. Refused once the period has been finalized: that
     * needs a credit note, not a correction.
     */
    public function withdrawVat(string $sourceModule, int $sourceId): void
    {
        DB::transaction(function () use ($sourceModule, $sourceId) {
            $rows = VatTransaction::where('source_module', $sourceModule)->where('source_id', $sourceId)->get();
            if ($rows->isEmpty()) {
                return;
            }

            // Lock periods before changing their rows, in the same order as finalization.
            // Date matching also protects legacy VAT rows with a missing period link.
            $periods = VatPeriod::where(function ($query) use ($rows) {
                $query->whereIn('id', $rows->pluck('vat_period_id')->filter());
                foreach ($rows as $row) {
                    $query->orWhere(fn ($dates) => $dates->whereDate('start_date', '<=', $row->transaction_date)
                        ->whereDate('end_date', '>=', $row->transaction_date));
                }
            })->orderBy('id')->lockForUpdate()->get();

            foreach ($periods as $period) {
                if (in_array($period->status, ['finalized', 'submitted'], true)) {
                    throw ValidationException::withMessages([
                        'vat' => 'The VAT of this document belongs to '.$period->period_name.', which is already '.$period->status.'. It cannot be corrected; issue a credit note instead.',
                    ]);
                }
            }

            VatTransaction::whereIn('id', $rows->pluck('id'))->delete();
        });
    }

    /** Human wording for the flash message after a document was posted. */
    public function describe(JournalEntry $entry): string
    {
        return $entry->status === 'posted'
            ? 'journal entry '.$entry->journal_number.' posted'
            : 'journal entry '.$entry->journal_number.' created as a draft awaiting posting (review mode for this posting rule)';
    }

    /**
     * Build a balanced journal entry. The matching posting rule decides whether
     * it lands as a draft for review or is posted straight to the ledger. An
     * entry that is one-sided, unbalanced or uses a missing or inactive account
     * is refused; nothing is written.
     */
    private function createEntry(array $header, array $lines, string $module, string $event, ?int $userId, ?bool $forcePost = null): JournalEntry
    {
        $lines = array_values(array_filter(
            $lines,
            fn (array $line) => (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0
        ));

        if (count($lines) < 2) {
            $this->refuse('Nothing to post: the accounting entry for '.($header['reference_number'] ?? $module).' would have fewer than two lines.');
        }

        $accounts = ChartOfAccount::whereKey(array_unique(array_column($lines, 'chart_of_account_id')))->get()->keyBy('id');

        foreach ($lines as $line) {
            $account = $accounts->get($line['chart_of_account_id']);

            if (! $account) {
                $this->refuse('An account used by this entry no longer exists in the chart of accounts.');
            }

            if ($account->status !== 'active') {
                $this->refuse('Account '.$account->label().' is inactive. Activate it or map another account before posting.');
            }
        }

        $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            $this->refuse('The accounting entry would not balance (debit '.number_format($totalDebit, 2).', credit '.number_format($totalCredit, 2).'). Nothing was posted.');
        }

        $rule = AutomaticPostingRule::where('source_module', $module)
            ->where('trigger_event', $event)
            ->where('status', 'active')
            ->first();

        $autoPost = $forcePost ?? (bool) ($rule?->auto_post);

        return DB::transaction(function () use ($header, $lines, $totalDebit, $totalCredit, $autoPost, $userId) {
            // Includes automatic/reversal journals and custom lines mapped to a VAT
            // control account, even when no separate VAT transaction is generated.
            if (array_intersect(array_column($lines, 'chart_of_account_id'), $this->vatAccountIds()) !== []) {
                $this->assertVatPeriodOpen($header['journal_date'], 'This journal touches a VAT account and');
            }

            $entry = JournalEntry::create($header + [
                'journal_number' => JournalEntry::nextNumber((int) now()->year),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => $autoPost ? 'posted' : 'draft',
                'created_by' => $userId,
                'posted_by' => $autoPost ? $userId : null,
                'posted_at' => $autoPost ? now() : null,
            ]);

            $entry->lines()->createMany($lines);

            return $entry;
        });
    }

    /** The account chosen on a document line, or the standard fallback; both must be active. */
    private function lineAccount(?int $chosenId, string $fallbackCode, string $purpose): ChartOfAccount
    {
        if ($chosenId) {
            return $this->activeOrRefuse(ChartOfAccount::find($chosenId), $purpose);
        }

        return $this->requireAccount($fallbackCode, $purpose);
    }

    private function requireAccount(string $code, string $purpose): ChartOfAccount
    {
        $account = $this->account($code);

        if (! $account) {
            $this->refuse('The chart of accounts has no '.$purpose.' account ('.$code.'). Set it up before posting.');
        }

        return $this->activeOrRefuse($account, $purpose);
    }

    private function activeOrRefuse(?ChartOfAccount $account, string $purpose): ChartOfAccount
    {
        if (! $account) {
            $this->refuse('The '.$purpose.' is missing from the chart of accounts. Set it up before posting.');
        }

        if ($account->status !== 'active') {
            $this->refuse('The '.$purpose.' '.$account->label().' is inactive. Activate it or map another account before posting.');
        }

        return $account;
    }

    /**
     * Stop the business transaction. Callers run inside DB::transaction, so the
     * document, its settlement rows and any stock movement roll back together.
     */
    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['posting' => $message]);
    }
}
