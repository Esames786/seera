<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * General ledger. Finance correctness sprint F06: the running balance starts
 * from the account's opening balance plus every posted movement before the
 * selected range, and continues correctly across pages.
 */
class GeneralLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $account = $request->filled('account')
            ? ChartOfAccount::find($request->integer('account'))
            : null;

        $postedOnly = ! $request->has('posted_only') || $request->boolean('posted_only');

        // Everything except the date range: reused for the pre-range opening.
        $base = JournalEntryLine::with(['account', 'costCenter', 'journalEntry'])
            ->when($account, fn ($q) => $q->where('chart_of_account_id', $account->id))
            ->when($request->filled('cost_center'), fn ($q) => $q->where('cost_center_id', $request->integer('cost_center')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', $request->integer('site')));

        $journalFilter = function ($j) use ($request, $postedOnly) {
            $j->when($postedOnly, fn ($q) => $q->where('status', 'posted'))
                ->when(! $postedOnly, fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->when($request->filled('source'), fn ($q) => $q->where('source_module', $request->string('source')));
        };

        $query = (clone $base)->whereHas('journalEntry', function ($j) use ($request, $journalFilter) {
            $journalFilter($j);
            $j->when($request->filled('from'), fn ($q) => $q->whereDate('journal_date', '>=', $request->date('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('journal_date', '<=', $request->date('to')));
        });

        $ordered = fn () => (clone $query)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entries.journal_date')
            ->orderBy('journal_entry_lines.id')
            ->select('journal_entry_lines.*');

        $lines = $ordered()->paginate(20)->withQueryString();

        // A running balance only means something when the report is scoped to one account.
        $signed = $account && $account->normal_balance === 'credit' ? -1 : 1;
        $openingBalance = $account ? (float) $account->opening_balance : 0.0;

        if ($account && $request->filled('from')) {
            // Movement before the range belongs to the opening, not to the page.
            $prior = (clone $base)->whereHas('journalEntry', function ($j) use ($request, $journalFilter) {
                $journalFilter($j);
                $j->whereDate('journal_date', '<', $request->date('from'));
            })->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')->first();

            $openingBalance += $signed * ((float) $prior->debit - (float) $prior->credit);
        }

        $running = $openingBalance;

        if ($account && $lines->currentPage() > 1) {
            // Lines on the earlier pages are already in the balance the page starts from.
            $earlier = $ordered()->limit(($lines->currentPage() - 1) * $lines->perPage())->get(['journal_entry_lines.debit', 'journal_entry_lines.credit']);
            $running += $signed * ((float) $earlier->sum('debit') - (float) $earlier->sum('credit'));
        }

        foreach ($lines as $line) {
            $running += $signed * ((float) $line->debit - (float) $line->credit);
            $line->running_balance = round($running, 2);
        }

        $totals = (clone $query)->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')->first();

        return view('admin.accounting.general-ledger.index', [
            'lines' => $lines,
            'account' => $account,
            'openingBalance' => round($openingBalance, 2),
            'totalDebit' => round((float) $totals->debit, 2),
            'totalCredit' => round((float) $totals->credit, 2),
            'postedOnly' => $postedOnly,
            'accounts' => ChartOfAccount::orderBy('account_code')->get(),
            'costCenters' => CostCenter::orderBy('code')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'sourceModules' => JournalEntry::SOURCE_MODULES,
        ]);
    }
}
