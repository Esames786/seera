<?php

namespace App\Services\Accounting;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\JournalEntry;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Models\ZatcaInvoiceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns approved finance documents into balanced journal entries, VAT
 * transactions and ZATCA records, following the Phase 4 posting rules.
 */
class PostingService
{
    /** Well-known account codes seeded by the standard chart of accounts. */
    public const CASH = '1110';

    public const BANK = '1120';

    public const RECEIVABLE = '1200';

    public const INPUT_VAT = '1300';

    public const PAYABLE = '2100';

    public const OUTPUT_VAT = '2210';

    public const SALARY_PAYABLE = '2300';

    public const REVENUE = '4100';

    public const SALARY_EXPENSE = '5100';

    public const MATERIAL_EXPENSE = '5200';

    public function account(string $code): ?ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->first();
    }

    /**
     * Debit expense/inventory accounts and input VAT, credit accounts payable.
     */
    public function postSupplierBill(SupplierBill $bill, ?int $userId = null): ?JournalEntry
    {
        $bill->loadMissing('lines', 'supplier');

        $payable = $this->account(self::PAYABLE);
        $inputVat = $this->account(self::INPUT_VAT);
        $fallbackExpense = $this->account(self::MATERIAL_EXPENSE);

        if (! $payable) {
            return null;
        }

        $lines = [];

        foreach ($bill->lines as $line) {
            $account = $line->chart_of_account_id ? ChartOfAccount::find($line->chart_of_account_id) : null;
            $account ??= $fallbackExpense;

            if (! $account) {
                continue;
            }

            $lines[] = [
                'chart_of_account_id' => $account->id,
                'description' => $line->description,
                'debit' => (float) $line->taxable_amount,
                'credit' => 0,
                'cost_center_id' => $line->cost_center_id ?? $bill->cost_center_id,
                'project_id' => $bill->project_id,
                'site_id' => $bill->site_id,
            ];
        }

        if ($lines === [] && $fallbackExpense) {
            $lines[] = [
                'chart_of_account_id' => $fallbackExpense->id,
                'description' => 'Supplier bill '.$bill->bill_number,
                'debit' => (float) $bill->taxable_amount,
                'credit' => 0,
                'cost_center_id' => $bill->cost_center_id,
                'project_id' => $bill->project_id,
                'site_id' => $bill->site_id,
            ];
        }

        if ($inputVat && (float) $bill->vat_amount > 0) {
            $lines[] = [
                'chart_of_account_id' => $inputVat->id,
                'description' => 'Input VAT on '.$bill->bill_number,
                'debit' => (float) $bill->vat_amount,
                'credit' => 0,
                'cost_center_id' => $bill->cost_center_id,
            ];
        }

        $lines[] = [
            'chart_of_account_id' => $payable->id,
            'description' => $bill->supplier->name.' - '.$bill->bill_number,
            'debit' => 0,
            'credit' => (float) $bill->total_amount,
            'cost_center_id' => $bill->cost_center_id,
        ];

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
    public function postSupplierPayment(SupplierPayment $payment, ?int $userId = null): ?JournalEntry
    {
        $payment->loadMissing('supplier');

        $payable = $this->account(self::PAYABLE);
        $paymentAccount = $payment->payment_account_id
            ? ChartOfAccount::find($payment->payment_account_id)
            : $this->account(self::BANK);

        if (! $payable || ! $paymentAccount) {
            return null;
        }

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
            ],
            [
                'chart_of_account_id' => $paymentAccount->id,
                'description' => 'Payment to '.$payment->supplier->name,
                'debit' => 0,
                'credit' => (float) $payment->amount,
            ],
        ], 'Supplier Payment', 'Payment Recorded', $userId);
    }

    /**
     * Debit accounts receivable, credit revenue and output VAT.
     */
    public function postCustomerInvoice(CustomerInvoice $invoice, ?int $userId = null): ?JournalEntry
    {
        $invoice->loadMissing('lines', 'customer');

        $receivable = $this->account(self::RECEIVABLE);
        $outputVat = $this->account(self::OUTPUT_VAT);
        $fallbackRevenue = $this->account(self::REVENUE);

        if (! $receivable) {
            return null;
        }

        $lines = [[
            'chart_of_account_id' => $receivable->id,
            'description' => $invoice->customer->name.' - '.$invoice->invoice_number,
            'debit' => (float) $invoice->total_amount,
            'credit' => 0,
            'cost_center_id' => $invoice->cost_center_id,
            'project_id' => $invoice->project_id,
        ]];

        foreach ($invoice->lines as $line) {
            $account = $line->revenue_account_id ? ChartOfAccount::find($line->revenue_account_id) : null;
            $account ??= $fallbackRevenue;

            if (! $account) {
                continue;
            }

            $lines[] = [
                'chart_of_account_id' => $account->id,
                'description' => $line->description,
                'debit' => 0,
                'credit' => (float) $line->taxable_amount,
                'cost_center_id' => $line->cost_center_id ?? $invoice->cost_center_id,
                'project_id' => $invoice->project_id,
            ];
        }

        if ($invoice->lines->isEmpty() && $fallbackRevenue) {
            $lines[] = [
                'chart_of_account_id' => $fallbackRevenue->id,
                'description' => 'Invoice '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => (float) $invoice->taxable_amount,
                'cost_center_id' => $invoice->cost_center_id,
                'project_id' => $invoice->project_id,
            ];
        }

        if ($outputVat && (float) $invoice->vat_amount > 0) {
            $lines[] = [
                'chart_of_account_id' => $outputVat->id,
                'description' => 'Output VAT on '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => (float) $invoice->vat_amount,
                'cost_center_id' => $invoice->cost_center_id,
            ];
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
    public function postCustomerReceipt(CustomerReceipt $receipt, ?int $userId = null): ?JournalEntry
    {
        $receipt->loadMissing('customer');

        $receivable = $this->account(self::RECEIVABLE);
        $receiptAccount = $receipt->receipt_account_id
            ? ChartOfAccount::find($receipt->receipt_account_id)
            : $this->account(self::BANK);

        if (! $receivable || ! $receiptAccount) {
            return null;
        }

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
            ],
            [
                'chart_of_account_id' => $receivable->id,
                'description' => 'Receipt from '.$receipt->customer->name,
                'debit' => 0,
                'credit' => (float) $receipt->amount,
            ],
        ], 'Customer Receipt', 'Receipt Recorded', $userId);
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
            'vat_period_id' => $this->periodFor($date)?->id,
            'status' => 'active',
        ]);
    }

    public function periodFor($date): ?VatPeriod
    {
        return VatPeriod::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * Build a balanced journal entry. The matching posting rule decides whether
     * it lands as a draft for review or is posted straight to the ledger.
     */
    private function createEntry(array $header, array $lines, string $module, string $event, ?int $userId): JournalEntry
    {
        $lines = array_values(array_filter(
            $lines,
            fn (array $line) => (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0
        ));

        $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        $rule = AutomaticPostingRule::where('source_module', $module)
            ->where('trigger_event', $event)
            ->where('status', 'active')
            ->first();

        $autoPost = (bool) ($rule?->auto_post) && abs($totalDebit - $totalCredit) < 0.01;

        return DB::transaction(function () use ($header, $lines, $totalDebit, $totalCredit, $autoPost, $userId) {
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
}
