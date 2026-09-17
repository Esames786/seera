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
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financial reports. Every report honours the same filters (quick date preset or
 * custom range, cost center, project, site) and can be exported as CSV with the
 * exact values shown on screen (client change requests NR-20 and NR-21).
 */
class FinancialReportController extends Controller
{
    public function __construct(private readonly PostingService $posting) {}

    public function index(): View
    {
        return view('admin.accounting.reports.index');
    }

    public function balanceSheet(Request $request): View|StreamedResponse
    {
        $balances = $this->balances($request);

        $assets = $this->section($balances, 'asset');
        $liabilities = $this->section($balances, 'liability');
        $equity = $this->section($balances, 'equity');

        // Current-period profit belongs to equity until it is closed out.
        $netProfit = round($this->total($this->section($balances, 'revenue')) - $this->total($this->section($balances, 'expense')), 2);

        if ($this->wantsCsv($request)) {
            $rows = [];
            foreach (['Assets' => $assets, 'Liabilities' => $liabilities, 'Equity' => $equity] as $section => $items) {
                foreach ($items as $row) {
                    $rows[] = [$section, $row['account_code'], $row['account_name'], $row['balance']];
                }
            }
            $rows[] = ['Equity', '-', 'Current Period Profit / Loss', $netProfit];
            $rows[] = ['Totals', '', 'Total Assets', $this->total($assets)];
            $rows[] = ['Totals', '', 'Total Liabilities', $this->total($liabilities)];
            $rows[] = ['Totals', '', 'Total Equity', round($this->total($equity) + $netProfit, 2)];

            return $this->csv('balance-sheet', ['Section', 'Code', 'Account', 'Balance (SAR)'], $rows, $request);
        }

        return view('admin.accounting.reports.balance-sheet', $this->filterOptions($request) + [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $this->total($assets),
            'totalLiabilities' => $this->total($liabilities),
            'totalEquity' => $this->total($equity),
            'netProfit' => $netProfit,
        ]);
    }

    public function profitLoss(Request $request): View|StreamedResponse
    {
        $balances = $this->balances($request);

        $revenue = $this->section($balances, 'revenue');
        $expenses = $this->section($balances, 'expense');
        $netProfit = round($this->total($revenue) - $this->total($expenses), 2);

        if ($this->wantsCsv($request)) {
            $rows = [];
            foreach (['Revenue' => $revenue, 'Expenses' => $expenses] as $section => $items) {
                foreach ($items as $row) {
                    $rows[] = [$section, $row['account_code'], $row['account_name'], $row['balance']];
                }
            }
            $rows[] = ['Totals', '', 'Total Revenue', $this->total($revenue)];
            $rows[] = ['Totals', '', 'Total Expenses', $this->total($expenses)];
            $rows[] = ['Totals', '', 'Net Profit / Loss', $netProfit];

            return $this->csv('profit-loss', ['Section', 'Code', 'Account', 'Amount (SAR)'], $rows, $request);
        }

        return view('admin.accounting.reports.profit-loss', $this->filterOptions($request) + [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totalRevenue' => $this->total($revenue),
            'totalExpenses' => $this->total($expenses),
            'netProfit' => $netProfit,
        ]);
    }

    public function trialBalance(Request $request): View|StreamedResponse
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

        $totalDebit = round($rows->sum('debit_balance'), 2);
        $totalCredit = round($rows->sum('credit_balance'), 2);

        if ($this->wantsCsv($request)) {
            $lines = $rows->map(fn ($row) => [$row['account_code'], $row['account_name'], ucfirst($row['account_type']), $row['debit_balance'], $row['credit_balance']])->all();
            $lines[] = ['', 'Totals', '', $totalDebit, $totalCredit];

            return $this->csv('trial-balance', ['Code', 'Account', 'Type', 'Debit Balance (SAR)', 'Credit Balance (SAR)'], $lines, $request);
        }

        return view('admin.accounting.reports.trial-balance', $this->filterOptions($request) + [
            'rows' => $rows,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }

    public function cashFlow(Request $request): View|StreamedResponse
    {
        $cashAccountIds = ChartOfAccount::whereIn('account_code', [PostingService::CASH, PostingService::BANK])->pluck('id');

        $opening = ChartOfAccount::whereIn('id', $cashAccountIds)->sum('opening_balance');

        $totals = JournalEntryLine::whereIn('chart_of_account_id', $cashAccountIds)
            ->whereHas('journalEntry', fn ($q) => $this->applyEntryFilters($q, $request))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        $cashIn = round((float) $totals->debit, 2);
        $cashOut = round((float) $totals->credit, 2);
        $closing = round((float) $opening + $cashIn - $cashOut, 2);

        $movements = JournalEntryLine::with(['account', 'journalEntry'])
            ->whereIn('chart_of_account_id', $cashAccountIds)
            ->whereHas('journalEntry', fn ($q) => $this->applyEntryFilters($q, $request))
            ->latest('id');

        if ($this->wantsCsv($request)) {
            $rows = [
                ['', '', 'Opening Cash', '', '', round((float) $opening, 2), ''],
                ['', '', 'Cash In', '', '', $cashIn, ''],
                ['', '', 'Cash Out', '', '', '', $cashOut],
                ['', '', 'Closing Cash', '', '', $closing, ''],
            ];
            foreach ($movements->get() as $line) {
                $rows[] = [
                    $line->journalEntry->journal_date->toDateString(), $line->journalEntry->journal_number, $line->account->label(),
                    $line->description ?? $line->journalEntry->description ?? '', $line->journalEntry->source_module,
                    (float) $line->debit > 0 ? round((float) $line->debit, 2) : '', (float) $line->credit > 0 ? round((float) $line->credit, 2) : '',
                ];
            }

            return $this->csv('cash-flow', ['Date', 'Journal', 'Account', 'Description', 'Source', 'Cash In (SAR)', 'Cash Out (SAR)'], $rows, $request);
        }

        return view('admin.accounting.reports.cash-flow', $this->filterOptions($request) + [
            'openingCash' => round((float) $opening, 2),
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'closingCash' => $closing,
            'movements' => $movements->limit(20)->get(),
        ]);
    }

    public function vatReport(Request $request): View|StreamedResponse
    {
        $period = $this->period($request);

        // A period is included when it overlaps the selected range (NR-21).
        $periods = VatPeriod::orderByDesc('start_date')
            ->when($period->from, fn ($q) => $q->whereDate('end_date', '>=', $period->from->toDateString()))
            ->when($period->to, fn ($q) => $q->whereDate('start_date', '<=', $period->to->toDateString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->get();

        $totals = [
            'totalOutputVat' => round((float) $periods->sum('output_vat'), 2),
            'totalInputVat' => round((float) $periods->sum('input_vat'), 2),
            'totalVatPayable' => round((float) $periods->sum('vat_payable'), 2),
        ];

        if ($this->wantsCsv($request)) {
            $rows = $periods->map(fn (VatPeriod $p) => [
                $p->period_name, $p->start_date->toDateString(), $p->end_date->toDateString(),
                round((float) $p->sales_taxable_amount, 2), round((float) $p->output_vat, 2),
                round((float) $p->purchase_taxable_amount, 2), round((float) $p->input_vat, 2),
                round((float) $p->vat_payable, 2), ucfirst($p->status),
            ])->all();
            $rows[] = ['Totals', '', '', '', $totals['totalOutputVat'], '', $totals['totalInputVat'], $totals['totalVatPayable'], ''];

            return $this->csv('vat-report', ['Period', 'Start', 'End', 'Sales Taxable', 'Output VAT', 'Purchase Taxable', 'Input VAT', 'VAT Payable', 'Status'], $rows, $request);
        }

        return view('admin.accounting.reports.vat-report', $this->filterOptions($request) + $totals + [
            'periods' => $periods,
            'vatStatuses' => VatPeriod::STATUSES,
        ]);
    }

    public function projectCostReport(Request $request): View|StreamedResponse
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

        if ($this->wantsCsv($request)) {
            $lines = $rows->map(fn ($row) => [
                $row['project']->name, $row['project']->customer?->name ?? '', $row['budget'], $row['cost'], $row['budget_used'],
                $row['revenue'], $row['billed'], $row['invoiced'], $row['margin'],
            ])->all();
            $lines[] = ['Totals', '', '', round($rows->sum('cost'), 2), '', round($rows->sum('revenue'), 2), '', '', round($rows->sum('revenue') - $rows->sum('cost'), 2)];

            return $this->csv('project-cost-report', ['Project', 'Client', 'Budget', 'Posted Cost', 'Budget Used %', 'Posted Revenue', 'Supplier Billed', 'Customer Invoiced', 'Margin'], $lines, $request);
        }

        return view('admin.accounting.reports.project-cost-report', $this->filterOptions($request) + [
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
        $period = $this->period($request);

        return $query->where('status', 'posted')
            ->when($period->from, fn ($q) => $q->whereDate('journal_date', '>=', $period->from->toDateString()))
            ->when($period->to, fn ($q) => $q->whereDate('journal_date', '<=', $period->to->toDateString()));
    }

    /**
     * The quick preset or custom range the request asked for, resolved once per
     * request. Kept on the request (not the controller) because the router reuses
     * controller instances within a process, e.g. across requests in tests.
     */
    private function period(Request $request): ReportPeriod
    {
        if (! $request->attributes->has('seera.report_period')) {
            $request->attributes->set('seera.report_period', ReportPeriod::fromRequest($request));
        }

        return $request->attributes->get('seera.report_period');
    }

    private function wantsCsv(Request $request): bool
    {
        return $request->query('export') === 'csv';
    }

    /**
     * Stream the report as UTF-8 CSV (with BOM so Excel keeps Arabic names intact).
     * The rows are the same values the screen shows for the same filters.
     */
    private function csv(string $name, array $headers, iterable $rows, Request $request): StreamedResponse
    {
        $filename = $name.'-'.$this->period($request)->slug().'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filterOptions(Request $request): array
    {
        return [
            'period' => $this->period($request),
            'presets' => ReportPeriod::PRESETS,
            'branches' => Branch::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'costCenters' => CostCenter::orderBy('code')->get(),
        ];
    }
}
