<?php

namespace App\Services\Projects;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetLine;
use App\Models\PurchaseOrderLine;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Collection;

/**
 * Budget vs Actual for one budget version. Actual cost is the Project Cost
 * Report's own semantics (net debit of posted expense-account journal lines
 * carrying the project), grouped by account; committed cost is the unreceived
 * value of approved purchase orders, grouped by the ordered item's expense
 * account. Budget lines are compared through their mapped account, so one
 * account feeds exactly one cost code and nothing is counted twice; posted
 * cost on accounts no line maps to is shown as its own row, which keeps the
 * total actual equal to the Project Cost Report. Constant number of queries.
 */
class ProjectBudgetReport
{
    /** @return array<string, mixed> */
    public function build(Project $project, ?ProjectBudget $budget, ?int $siteId = null): array
    {
        $lines = $budget ? $budget->lines()->with(['account', 'item.unit', 'site', 'unit'])->when($siteId, fn ($q) => $q->where('site_id', $siteId))->get() : collect();
        $accounts = ChartOfAccount::where('account_type', 'expense')->get()->keyBy('id');
        $actual = $this->actualByAccount($project, $accounts->keys()->all(), $siteId);
        $committed = $this->committedByAccount($project, $accounts, $siteId);
        $issued = $this->issuedByItem($project, $lines->pluck('item_id')->filter()->unique()->values()->all(), $siteId);

        $groups = [];
        $mapped = [];
        foreach ($lines as $line) {
            $key = $line->category.'|'.$line->cost_code;
            $groups[$key] ??= ['category' => $line->category, 'cost_code' => $line->cost_code, 'descriptions' => [], 'budget' => 0.0, 'accounts' => [], 'lines' => 0, 'sites' => []];
            $groups[$key]['budget'] += (float) $line->amount;
            $groups[$key]['lines']++;
            $groups[$key]['descriptions'][$line->description] = true;
            if ($line->site_id) {
                $groups[$key]['sites'][$line->site_id] = $line->site?->name;
            }
            if ($line->chart_of_account_id) {
                $groups[$key]['accounts'][$line->chart_of_account_id] = $line->account?->label();
                $mapped[$line->chart_of_account_id] = true;
            }
        }
        $rows = [];
        foreach ($groups as $group) {
            $hasAccounts = $group['accounts'] !== [];
            $groupActual = $hasAccounts ? array_sum(array_map(fn ($id) => $actual[$id] ?? 0.0, array_keys($group['accounts']))) : null;
            $groupCommitted = $hasAccounts ? array_sum(array_map(fn ($id) => $committed[$id] ?? 0.0, array_keys($group['accounts']))) : null;
            $rows[] = $this->row($group['category'], $group['cost_code'], implode('; ', array_keys($group['descriptions'])), $group['budget'], $groupCommitted, $groupActual, array_values($group['accounts']), $group['lines'], array_values($group['sites']));
        }
        usort($rows, fn ($a, $b) => [array_search($a['category'], array_keys(ProjectBudgetLine::CATEGORIES)), $a['cost_code']] <=> [array_search($b['category'], array_keys(ProjectBudgetLine::CATEGORIES)), $b['cost_code']]);

        // Posted cost / commitments on accounts no budget line maps to: visible, never hidden, so totals reconcile.
        $unmappedActual = 0.0;
        $unmappedCommitted = 0.0;
        $unmappedAccounts = [];
        foreach ($actual as $accountId => $value) {
            if (! isset($mapped[$accountId]) && abs($value) >= 0.005) {
                $unmappedActual += $value;
                $unmappedAccounts[$accountId] = $accounts[$accountId]?->label();
            }
        }
        foreach ($committed as $accountId => $value) {
            if (! isset($mapped[$accountId]) && abs($value) >= 0.005) {
                $unmappedCommitted += $value;
                $unmappedAccounts[$accountId] = $accounts[$accountId]?->label();
            }
        }

        $categories = [];
        foreach (ProjectBudgetLine::CATEGORIES as $key => $label) {
            $categoryRows = array_filter($rows, fn ($r) => $r['category'] === $key);
            if ($categoryRows === []) {
                continue;
            }
            $hasActual = array_filter($categoryRows, fn ($r) => $r['actual'] !== null) !== [];
            $categories[$key] = $this->row($key, null, $label, array_sum(array_column($categoryRows, 'budget')),
                $hasActual ? array_sum(array_map(fn ($r) => $r['committed'] ?? 0.0, $categoryRows)) : null,
                $hasActual ? array_sum(array_map(fn ($r) => $r['actual'] ?? 0.0, $categoryRows)) : null,
                [], array_sum(array_column($categoryRows, 'lines')), []);
            $categories[$key]['integration'] = $key === 'equipment'
                ? 'Equipment actual-cost integration is NOT YET OPERATIONAL; only cost posted to the mapped accounts is shown.'
                : null;
        }

        $totalBudget = round((float) array_sum(array_column($rows, 'budget')), 2);
        $totalActual = round((float) array_sum($actual), 2);          // == Project Cost Report cost for the same filter
        $totalCommitted = round((float) array_sum($committed), 2);

        return [
            'budget' => $budget,
            'rows' => $rows,
            'categories' => array_values($categories),
            'unmapped' => ['actual' => round($unmappedActual, 2), 'committed' => round($unmappedCommitted, 2), 'accounts' => array_values(array_filter($unmappedAccounts))],
            'totals' => [
                'budget' => $totalBudget,
                'committed' => $totalCommitted,
                'actual' => $totalActual,
                'remaining' => round($totalBudget - $totalActual, 2),
                'variance' => round($totalBudget - $totalActual - $totalCommitted, 2),
                'utilization' => $totalBudget > 0 ? round($totalActual / $totalBudget * 100, 1) : null,
                'over_budget' => $totalBudget - $totalActual < -0.005,
            ],
            'issued' => $issued,
            'line_count' => $lines->count(),
        ];
    }

    /**
     * Net posted expense per account for the project (and site): the Project
     * Cost Report semantics, grouped by account instead of summed.
     *
     * @param  array<int, int>  $expenseIds
     * @return array<int, float>
     */
    public function actualByAccount(Project $project, array $expenseIds, ?int $siteId = null): array
    {
        return JournalEntryLine::where('project_id', $project->id)->whereIn('chart_of_account_id', $expenseIds)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->groupBy('chart_of_account_id')->selectRaw('chart_of_account_id, COALESCE(SUM(debit - credit), 0) as total')
            ->pluck('total', 'chart_of_account_id')->map(fn ($v) => round((float) $v, 2))->all();
    }

    /**
     * Open purchase commitments: for every line of an approved / partially
     * received project PO, the unreceived quantity at the net unit price
     * (excluding VAT). Received quantities are stock / GRNI and reach cost only
     * when issued, so they are not commitments. Bucketed by the item's expense
     * account (Material Expense when the item has none).
     *
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @return array<int, float>
     */
    public function committedByAccount(Project $project, Collection $accounts, ?int $siteId = null): array
    {
        $materialId = $accounts->firstWhere('account_code', PostingService::MATERIAL_EXPENSE)?->id;
        $lines = PurchaseOrderLine::with('item:id,expense_account_id')
            ->whereHas('purchaseOrder', fn ($q) => $q->where('project_id', $project->id)->whereIn('status', ['approved', 'partially_received'])->when($siteId, fn ($s) => $s->where('site_id', $siteId)))
            ->get();
        $committed = [];
        foreach ($lines as $line) {
            $outstanding = $line->outstandingQuantity();
            if ($outstanding <= 0) {
                continue;
            }
            $accountId = $line->item?->expense_account_id ?: $materialId;
            if (! $accountId || ! $accounts->has($accountId)) {
                continue;   // no expense account to compare against: not a project cost bucket
            }
            $committed[$accountId] = round(($committed[$accountId] ?? 0.0) + $outstanding * $line->netUnitPrice(), 2);
        }

        return $committed;
    }

    /**
     * Posted issue quantities / values per item (drill-down information for
     * material lines; the cost itself is already in the ledger figures).
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array{quantity: float, value: float}>
     */
    public function issuedByItem(Project $project, array $itemIds, ?int $siteId = null): array
    {
        if ($itemIds === []) {
            return [];
        }

        return StockLedgerEntry::where('project_id', $project->id)->whereIn('item_id', $itemIds)->where('movement_type', 'issue')
            ->where('reference_type', StockIssue::class)
            ->whereIn('reference_id', StockIssue::where('project_id', $project->id)->where('status', 'posted')->select('id'))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->groupBy('item_id')->selectRaw('item_id, COALESCE(SUM(out_quantity), 0) as quantity, COALESCE(SUM(value), 0) as value')
            ->get()->mapWithKeys(fn ($r) => [(int) $r->item_id => ['quantity' => (float) $r->quantity, 'value' => round((float) $r->value, 2)]])->all();
    }

    /** @return array<int, array<string, mixed>> CSV rows (header first) for the Budget vs Actual table. */
    public function csvRows(array $report): array
    {
        $rows = [['Category', 'Cost Code', 'Description', 'Sites', 'Lines', 'Accounts', 'Budget', 'Committed', 'Actual', 'Remaining', 'Variance after commitments', 'Status']];
        foreach ($report['rows'] as $row) {
            $rows[] = [ProjectBudgetLine::categoryLabel($row['category']), $row['cost_code'], $row['description'], implode('; ', $row['sites']), $row['lines'], implode('; ', $row['accounts']),
                number_format($row['budget'], 2, '.', ''), $row['committed'] === null ? '' : number_format($row['committed'], 2, '.', ''), $row['actual'] === null ? '' : number_format($row['actual'], 2, '.', ''),
                $row['remaining'] === null ? '' : number_format($row['remaining'], 2, '.', ''), $row['variance'] === null ? '' : number_format($row['variance'], 2, '.', ''), $row['status']];
        }
        if ($report['unmapped']['actual'] != 0 || $report['unmapped']['committed'] != 0) {
            $rows[] = ['Other', '', 'Posted cost / commitments on accounts no budget line maps to', '', '', implode('; ', $report['unmapped']['accounts']), '0.00', number_format($report['unmapped']['committed'], 2, '.', ''), number_format($report['unmapped']['actual'], 2, '.', ''), '', '', 'Not budgeted'];
        }
        $t = $report['totals'];
        $rows[] = ['Total', '', '', '', $report['line_count'], '', number_format($t['budget'], 2, '.', ''), number_format($t['committed'], 2, '.', ''), number_format($t['actual'], 2, '.', ''), number_format($t['remaining'], 2, '.', ''), number_format($t['variance'], 2, '.', ''), $t['over_budget'] ? 'Over budget' : 'Within budget'];

        return $rows;
    }

    /** @return array<string, mixed> */
    private function row(string $category, ?string $costCode, string $description, float $budget, ?float $committed, ?float $actual, array $accounts, int $lines, array $sites): array
    {
        $budget = round($budget, 2);
        $remaining = $actual === null ? null : round($budget - $actual, 2);
        $variance = $actual === null ? null : round($budget - $actual - ($committed ?? 0.0), 2);

        return [
            'category' => $category,
            'cost_code' => $costCode,
            'description' => $description,
            'budget' => $budget,
            'committed' => $committed === null ? null : round($committed, 2),
            'actual' => $actual === null ? null : round($actual, 2),
            'remaining' => $remaining,
            'variance' => $variance,
            'utilization' => $actual !== null && $budget > 0 ? round($actual / $budget * 100, 1) : null,
            'status' => $actual === null ? 'No account mapped' : ($remaining < -0.005 ? 'Over budget' : ($variance < -0.005 ? 'Committed beyond budget' : 'Within budget')),
            'accounts' => $accounts,
            'lines' => $lines,
            'sites' => $sites,
        ];
    }
}
