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

class GeneralLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $account = $request->filled('account')
            ? ChartOfAccount::find($request->integer('account'))
            : null;

        $postedOnly = ! $request->has('posted_only') || $request->boolean('posted_only');

        $query = JournalEntryLine::with(['account', 'costCenter', 'journalEntry'])
            ->whereHas('journalEntry', function ($q) use ($request, $postedOnly) {
                $q->when($postedOnly, fn ($j) => $j->where('status', 'posted'))
                    ->when(! $postedOnly, fn ($j) => $j->where('status', '!=', 'cancelled'))
                    ->when($request->filled('source'), fn ($j) => $j->where('source_module', $request->string('source')))
                    ->when($request->filled('from'), fn ($j) => $j->whereDate('journal_date', '>=', $request->date('from')))
                    ->when($request->filled('to'), fn ($j) => $j->whereDate('journal_date', '<=', $request->date('to')));
            })
            ->when($account, fn ($q) => $q->where('chart_of_account_id', $account->id))
            ->when($request->filled('cost_center'), fn ($q) => $q->where('cost_center_id', $request->integer('cost_center')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', $request->integer('site')));

        $lines = (clone $query)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entries.journal_date')
            ->orderBy('journal_entry_lines.id')
            ->select('journal_entry_lines.*')
            ->paginate(20)
            ->withQueryString();

        // A running balance only means something when the report is scoped to one account.
        $openingBalance = $account ? (float) $account->opening_balance : 0.0;
        $running = $openingBalance;
        $signed = $account && $account->normal_balance === 'credit' ? -1 : 1;

        foreach ($lines as $line) {
            $running += $signed * ((float) $line->debit - (float) $line->credit);
            $line->running_balance = round($running, 2);
        }

        $totals = (clone $query)->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')->first();

        return view('admin.accounting.general-ledger.index', [
            'lines' => $lines,
            'account' => $account,
            'openingBalance' => $openingBalance,
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
