<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\SupplierBill;
use App\Models\VatTransaction;
use App\Models\ZatcaInvoiceRecord;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AccountingDashboardController extends Controller
{
    public function __construct(private readonly PostingService $posting)
    {
    }

    public function index(): View
    {
        $outputVat = (float) VatTransaction::where('vat_type', 'output')->sum('vat_amount');
        $inputVat = (float) VatTransaction::where('vat_type', 'input')->sum('vat_amount');

        return view('admin.accounting.dashboard', [
            'cashBalance' => $this->balanceOf(PostingService::CASH) + $this->balanceOf(PostingService::BANK),
            'payableBalance' => $this->balanceOf(PostingService::PAYABLE),
            'receivableBalance' => $this->balanceOf(PostingService::RECEIVABLE),
            'vatPayable' => round($outputVat - $inputVat, 2),
            'outputVat' => round($outputVat, 2),
            'inputVat' => round($inputVat, 2),
            'unpostedJournals' => JournalEntry::whereIn('status', ['draft', 'approved'])->count(),
            'zatcaFailed' => ZatcaInvoiceRecord::where('clearance_status', 'failed')->count(),
            'monthlyRevenue' => $this->monthlyMovement('revenue'),
            'monthlyExpenses' => $this->monthlyMovement('expense'),
            'payableAging' => $this->aging(SupplierBill::class, 'due_date', ['unpaid', 'partially_paid', 'approved']),
            'receivableAging' => $this->aging(CustomerInvoice::class, 'due_date', ['unpaid', 'partially_paid'], 'payment_status'),
            'recentJournals' => JournalEntry::with('costCenter')->latest('journal_date')->latest('id')->limit(8)->get(),
            'zatcaSummary' => ZatcaInvoiceRecord::selectRaw('clearance_status, COUNT(*) as total')
                ->groupBy('clearance_status')
                ->pluck('total', 'clearance_status')
                ->all(),
            'draftBills' => SupplierBill::where('status', 'draft')->count(),
            'draftInvoices' => CustomerInvoice::where('payment_status', 'draft')->count(),
            'overdueBills' => SupplierBill::whereIn('status', ['unpaid', 'partially_paid'])
                ->whereDate('due_date', '<', now())
                ->count(),
            'overdueInvoices' => CustomerInvoice::whereIn('payment_status', ['unpaid', 'partially_paid'])
                ->whereDate('due_date', '<', now())
                ->count(),
        ]);
    }

    private function balanceOf(string $code): float
    {
        return round($this->posting->account($code)?->postedBalance() ?? 0, 2);
    }

    /**
     * Signed movement of an account type inside the current calendar month.
     */
    private function monthlyMovement(string $accountType): float
    {
        $accountIds = ChartOfAccount::where('account_type', $accountType)->pluck('id');

        if ($accountIds->isEmpty()) {
            return 0.0;
        }

        $totals = JournalEntryLine::whereIn('chart_of_account_id', $accountIds)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')
                ->whereDate('journal_date', '>=', now()->startOfMonth())
                ->whereDate('journal_date', '<=', now()->endOfMonth()))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        return $accountType === 'revenue'
            ? round((float) $totals->credit - (float) $totals->debit, 2)
            : round((float) $totals->debit - (float) $totals->credit, 2);
    }

    /**
     * Outstanding balances bucketed by how overdue they are.
     *
     * @return array<string, float>
     */
    private function aging(string $model, string $dateColumn, array $statuses, string $statusColumn = 'status'): array
    {
        $today = Carbon::today();

        $rows = $model::whereIn($statusColumn, $statuses)
            ->get(['id', $dateColumn, 'balance_amount']);

        $buckets = ['Current' => 0.0, '1-30 days' => 0.0, '31-60 days' => 0.0, '60+ days' => 0.0];

        foreach ($rows as $row) {
            $due = $row->{$dateColumn};
            $daysLate = $due ? $due->diffInDays($today, false) : 0;

            $bucket = match (true) {
                $daysLate <= 0 => 'Current',
                $daysLate <= 30 => '1-30 days',
                $daysLate <= 60 => '31-60 days',
                default => '60+ days',
            };

            $buckets[$bucket] += (float) $row->balance_amount;
        }

        return array_map(fn ($value) => round($value, 2), $buckets);
    }
}
