<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\CustomerInvoice;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\SupplierBill;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Collection;

/** Shared, scoped Project Cost Report semantics; no ledger mutations. */
class ProjectCostReport
{
    public function rows(Collection $projects, ReportPeriod $period): \Illuminate\Support\Collection
    {
        $expenseIds = ChartOfAccount::where('account_type', 'expense')->pluck('id');
        $revenueIds = ChartOfAccount::where('account_type', 'revenue')->pluck('id');
        $inRange = fn ($query, string $column) => $query
            ->when($period->from, fn ($q) => $q->whereDate($column, '>=', $period->from->toDateString()))
            ->when($period->to, fn ($q) => $q->whereDate($column, '<=', $period->to->toDateString()));

        // Net of reversals (F06): a reversed cost or revenue no longer counts, and the
        // document totals follow the same date range as the ledger figures.
        $costs = JournalEntryLine::whereIn('project_id', $projects->modelKeys())->whereIn('chart_of_account_id', $expenseIds)
            ->whereHas('journalEntry', fn ($q) => $inRange($q->where('status', 'posted'), 'journal_date'))
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(debit - credit), 0) as total')->pluck('total', 'project_id');
        $revenues = JournalEntryLine::whereIn('project_id', $projects->modelKeys())->whereIn('chart_of_account_id', $revenueIds)
            ->whereHas('journalEntry', fn ($q) => $inRange($q->where('status', 'posted'), 'journal_date'))
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(credit - debit), 0) as total')->pluck('total', 'project_id');
        $bills = $inRange(SupplierBill::whereIn('project_id', $projects->modelKeys())->where('status', '!=', 'draft'), 'bill_date')
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(total_amount), 0) as total')->pluck('total', 'project_id');
        $invoices = $inRange(CustomerInvoice::whereIn('project_id', $projects->modelKeys())->where('payment_status', '!=', 'draft'), 'invoice_date')
            ->groupBy('project_id')->selectRaw('project_id, COALESCE(SUM(total_amount), 0) as total')->pluck('total', 'project_id');

        return $projects->map(function (Project $project) use ($costs, $revenues, $bills, $invoices) {
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

    }
}
