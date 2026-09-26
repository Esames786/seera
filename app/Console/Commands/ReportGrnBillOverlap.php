<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\SupplierBill;
use App\Services\Accounting\PostingService;
use Illuminate\Console\Command;

/**
 * Read-only: lists goods receipts posted under the pre-F04 model (their journal
 * credits accounts payable) and the supplier bills that look like the invoice
 * for the same delivery, so the accountant can decide on corrections. Nothing
 * is changed by this command.
 */
class ReportGrnBillOverlap extends Command
{
    protected $signature = 'finance:grn-bill-overlap {--days=45 : Bill date window around the receipt date}';

    protected $description = 'List historical goods receipts that credited accounts payable and the supplier bills that may duplicate them (read-only)';

    public function handle(): int
    {
        $payableIds = ChartOfAccount::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('account_code', PostingService::PAYABLE)
                ->orWhereIn('id', \App\Models\Supplier::whereNotNull('linked_account_id')->pluck('linked_account_id')))
            ->pluck('id');
        $days = (int) $this->option('days');

        $receipts = GoodsReceipt::withoutGlobalScopes()
            ->with(['supplier', 'journalEntry.lines'])
            ->where('status', 'posted')
            ->whereNotNull('journal_entry_id')
            ->orderBy('received_date')
            ->get()
            ->filter(fn (GoodsReceipt $grn) => $grn->journalEntry
                && $grn->journalEntry->lines->contains(fn ($line) => $payableIds->contains($line->chart_of_account_id) && (float) $line->credit > 0));

        if ($receipts->isEmpty()) {
            $this->info('No posted goods receipt credits accounts payable. Nothing to review.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($receipts as $grn) {
            $candidates = SupplierBill::withoutGlobalScopes()
                ->where('supplier_id', $grn->supplier_id)
                ->where('status', '!=', 'draft')
                ->whereBetween('bill_date', [$grn->received_date->copy()->subDays($days), $grn->received_date->copy()->addDays($days)])
                ->get()
                ->filter(fn (SupplierBill $bill) => abs((float) $bill->taxable_amount - (float) $grn->taxable_amount) < 0.01
                    || ($grn->invoice_number && strcasecmp($grn->invoice_number, $bill->bill_number) === 0));

            $rows[] = [
                $grn->grn_number,
                $grn->received_date->toDateString(),
                $grn->supplier?->name,
                number_format((float) $grn->taxable_amount, 2),
                number_format((float) $grn->vat_amount, 2),
                $grn->journalEntry->journal_number,
                $candidates->isEmpty() ? '-' : $candidates->map(fn ($b) => $b->bill_number.' ('.$b->bill_date->toDateString().', '.number_format((float) $b->taxable_amount, 2).')')->implode('; '),
            ];
        }

        $this->warn(count($rows).' posted goods receipt(s) credited accounts payable under the previous model. Bills listed beside them may double-count AP and input VAT.');
        $this->table(['GRN', 'Received', 'Supplier', 'Taxable', 'VAT', 'Journal', 'Possible duplicate bills'], $rows);
        $this->line('No data was changed. Corrections need a separate, approved reversing-entry plan.');

        return self::SUCCESS;
    }
}
