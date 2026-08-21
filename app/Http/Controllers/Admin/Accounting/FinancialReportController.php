<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\CustomerInvoice;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\Site;
use App\Models\SupplierBill;
use App\Models\VatPeriod;
use App\Services\Accounting\PostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    public function __construct(private readonly PostingService $posting) {}

    public function index(): View
    {
        return view('admin.accounting.reports.index');
    }

    public function balanceSheet(Request $request): View
    {
        $balances = $this->balances($request);

        $assets = $this->section($balances, 'asset');
        $liabilities = $this->section($balances, 'liability');
        $equity = $this->section($balances, 'equity');

        // Current-period profit belongs to equity until it is closed out.
        $netProfit = $this->total($this->section($balances, 'revenue')) - $this->total($this->section($balances, 'expense'));

        return view('admin.accounting.reports.balance-sheet', $this->filterOptions() + [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $this->total($assets),
            'totalLiabilities' => $this->total($liabilities),
            'totalEquity' => $this->total($equity),
            'netProfit' => round($netProfit, 2),
        ]);
    }

    public function profitLoss(Request $request): View
    {
        $balances = $this->balances($request);

        $revenue = $this->section($balances, 'revenue');
        $expenses = $this->section($balances, 'expense');

        return view('admin.accounting.reports.profit-loss', $this->filterOptions() + [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totalRevenue' => $this->total($revenue),
            'totalExpenses' => $this->total($expenses),
            'netProfit' => round($this->total($revenue) - $this->total($expenses), 2),
        ]);
    }

    public function trialBalance(Request $request): View
    {
        $rows = $this->movements($request)
            ->map(function ($row) {
                $net = (float) $row['debit'] - (float) $row['credit'];

                return $row + [
                    'debit_balance' => $net > 0 ? round($net, 2) : 0.0,
                    'credit_balance' => $net < 0 ? round(abs($net), 2) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['debit_balance'] > 0 || $row['credit_balance'] > 0)
            ->values();

        return view('admin.accounting.reports.trial-balance', $this->filterOptions() + [
            'rows' => $rows,
            'totalDebit' => round($rows->sum('debit_balance'), 2),
            'totalCredit' => round($rows->sum('credit_balance'), 2),
        ]);
    }

    public function cashFlow(Request $request): View
    {
        $cashAccountIds = ChartOfAccount::whereIn('account_code', [PostingService::CASH, PostingService::BANK])->pluck('id');

        $opening = ChartOfAccount::whereIn('id', $cashAccountIds)->sum('opening_balance');

        $totals = JournalEntryLine::whereIn('chart_of_account_id', $cashAccountIds)
            ->whereHas('journalEntry', fn ($q) => $this->applyEntryFilters($q, $request))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        $cashIn = round((float) $totals->debit, 2);
        $cashOut = round((float) $totals->credit, 2);

        return view('admin.accounting.reports.cash-flow', $this->filterOptions() + [
            'openingCash' => round((float) $opening, 2),
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'closingCash' => round((float) $opening + $cashIn - $cashOut, 2),
            'movements' => JournalEntryLine::with(['account', 'journalEntry'])
                ->whereIn('chart_of_account_id', $cashAccountIds)
                ->whereHas('journalEntry', fn ($q) => $this->applyEntryFilters($q, $request))
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function vatReport(Request $request): View
    {
        $periods = VatPeriod::orderByDesc('start_date')->get();

        return view('admin.accounting.reports.vat-report', $this->filterOptions() + [
            'periods' => $periods,
            'totalOutputVat' => round((float) $periods->sum('output_vat'), 2),
            'totalInputVat' => round((float) $periods->sum('input_vat'), 2),
            'totalVatPayable' => round((float) $periods->sum('vat_payable'), 2),
        ]);
    }

    public function projectCostReport(Request $request): View
    {
        $projects = Project::with('customer')->orderBy('name')->get();
        $expenseIds = ChartOfAccount::where('account_type', 'expense')->pluck('id');
        $revenueIds = ChartOfAccount::where('account_type', 'revenue')->pluck('id');
        $costs = JournalEntryLine::whereIn('chart_of_account_id', $expenseIds)
            ->whereHas('journalEntry', fn ($q) => $this->applyEntryFilters($q, $request))
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(debit), 0) as total')->pluck('total', 'project_id');
        $revenues = JournalEntryLine::whereIn('chart_of_account_id', $revenueIds)
            ->whereHas('journalEntry', fn ($q) => $this->applyEntryFilters($q, $request))
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(credit), 0) as total')->pluck('total', 'project_id');
        $bills = SupplierBill::where('status', '!=', 'draft')
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(total_amount), 0) as total')->pluck('total', 'project_id');
        $invoices = CustomerInvoice::where('payment_status', '!=', 'draft')
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(total_amount), 0) as total')->pluck('total', 'project_id');

        $rows = $projects->map(function (Project $project) use ($costs, $revenues, $bills, $invoices) {
            $cost = (float) ($costs[$project->id] ?? 0);
            $revenue = (float) ($revenues[$project->id] ?? 0);
            $billed = (float) ($bills[$project->id] ?? 0);
            $invoiced = (float) ($invoices[$project->id] ?? 0);

            return [
                'project' => $project,
                'budget' => (float) $project->budget,
                'cost' => round($cost, 2),
                'revenue' => round($revenue, 2),
                'billed' => round($billed, 2),
                'invoiced' => round($invoiced, 2),
                'margin' => round($revenue - $cost, 2),
                'budget_used' => (float) $project->budget > 0 ? round($cost / (float) $project->budget * 100, 1) : 0.0,
            ];
        });

        return view('admin.accounting.reports.project-cost-report', $this->filterOptions() + [
            'rows' => $rows,
            'totalCost' => round($rows->sum('cost'), 2),
            'totalRevenue' => round($rows->sum('revenue'), 2),
        ]);
    }

    /**
     * Posted debit/credit movement per account, honouring the report filters.
     */
    private function movements(Request $request): Collection
    {
        $totals = JournalEntryLine::query()
            ->whereHas('journalEntry', fn ($q) => $this->applyEntryFilters($q, $request))
            ->when($request->filled('cost_center'), fn ($q) => $q->where('journal_entry_lines.cost_center_id', $request->integer('cost_center')))
            ->when($request->filled('project'), fn ($q) => $q->where('journal_entry_lines.project_id', $request->integer('project')))
            ->when($request->filled('site'), fn ($q) => $q->where('journal_entry_lines.site_id', $request->integer('site')))
            ->groupBy('chart_of_account_id')
            ->selectRaw('chart_of_account_id, COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->get()
            ->keyBy('chart_of_account_id');

        return ChartOfAccount::orderBy('account_code')->get()->map(function (ChartOfAccount $account) use ($totals) {
            $movement = $totals->get($account->id);
            $opening = (float) $account->opening_balance;

            return [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'debit' => round((float) ($movement?->debit ?? 0) + ($account->normal_balance === 'debit' ? $opening : 0), 2),
                'credit' => round((float) ($movement?->credit ?? 0) + ($account->normal_balance === 'credit' ? $opening : 0), 2),
            ];
        });
    }

    /**
     * Account balances signed toward each account type's normal side.
     */
    private function balances(Request $request): Collection
    {
        return $this->movements($request)->map(function ($row) {
            $net = $row['debit'] - $row['credit'];

            return $row + [
                'balance' => in_array($row['account_type'], ChartOfAccount::DEBIT_TYPES, true)
                    ? round($net, 2)
                    : round(-$net, 2),
            ];
        });
    }

    private function section(Collection $balances, string $type): Collection
    {
        return $balances->where('account_type', $type)->values();
    }

    private function total(Collection $section): float
    {
        return round($section->sum('balance'), 2);
    }

    private function applyEntryFilters($query, Request $request)
    {
        return $query->where('status', 'posted')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('journal_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('journal_date', '<=', $request->date('to')));
    }

    private function filterOptions(): array
    {
        return [
            'branches' => Branch::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'costCenters' => CostCenter::orderBy('code')->get(),
        ];
    }
}
