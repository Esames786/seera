<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserAccessScopeService
{
    /** @var array<int, array<int, int>> Per-request cache of the projects each user may see. */
    private array $projectIds = [];

    public function apply(Builder $query, Model $model, User $user): void
    {
        $scope = $user->effectiveAccessScope();
        if ($scope === 'company') {
            return;
        }

        $table = $model->getTable();

        if (in_array($table, ['attendance_records', 'employee_documents', 'leave_requests', 'overtime_records', 'salary_structures', 'end_of_service_records', 'payroll_run_items'], true)) {
            $query->whereHas('employee');

            return;
        }

        if (in_array($table, ['purchase_request_lines', 'purchase_order_lines', 'goods_receipt_lines', 'stock_issue_lines', 'stock_transfer_lines'], true)) {
            $relation = match ($table) {
                'purchase_request_lines' => 'purchaseRequest',
                'purchase_order_lines' => 'purchaseOrder',
                'goods_receipt_lines' => 'goodsReceipt',
                'stock_issue_lines' => 'stockIssue',
                default => 'stockTransfer',
            };
            $query->whereHas($relation);

            return;
        }

        if ($table === 'supplier_payments') {
            $query->whereHas('bill');

            return;
        }
        if ($table === 'customer_receipts') {
            $query->whereHas('invoice');

            return;
        }
        if ($table === 'zatca_invoice_records') {
            $query->whereHas('customerInvoice');

            return;
        }

        match ($scope) {
            'project' => $this->projectScope($query, $table, $user),
            'site' => $this->siteScope($query, $table, $user),
            'warehouse' => $this->warehouseScope($query, $table, $user),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Projects a project-level user may work in: the project assigned on the
     * user record plus every project where they are the Project Manager
     * (client change request NR-34: a manager assigned on the project form
     * could not see that project).
     *
     * @return array<int, int>
     */
    public function projectIdsFor(User $user): array
    {
        return $this->projectIds[$user->id] ??= collect([$user->project_id])
            ->merge(Project::withoutGlobalScopes()->where('manager_id', $user->id)->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** The project used when a project-level user does not choose one explicitly. */
    public function defaultProjectIdFor(User $user): ?int
    {
        return $user->project_id ?: ($this->projectIdsFor($user)[0] ?? null);
    }

    private function projectScope(Builder $query, string $table, User $user): void
    {
        $projectIds = $this->projectIdsFor($user);

        if ($projectIds === []) {
            $query->whereRaw('1 = 0');
        } elseif ($table === 'projects') {
            $query->whereKey($projectIds);
        } elseif (in_array($table, ['sites', 'warehouses', 'employees', 'journal_entry_lines', 'supplier_bills', 'customer_invoices', 'purchase_requests', 'purchase_orders', 'stock_issues', 'stock_ledger_entries'], true)) {
            $query->whereIn($table.'.project_id', $projectIds);
        } elseif (in_array($table, ['goods_receipts', 'stock_adjustments', 'warehouse_stocks'], true)) {
            $query->whereHas('warehouse', fn ($warehouse) => $warehouse->whereIn('project_id', $projectIds));
        } elseif ($table === 'stock_transfers') {
            $query->whereHas('fromWarehouse', fn ($warehouse) => $warehouse->whereIn('project_id', $projectIds));
        }
    }

    private function siteScope(Builder $query, string $table, User $user): void
    {
        if (! $user->site_id) {
            $query->whereRaw('1 = 0');
        } elseif ($table === 'sites') {
            $query->whereKey($user->site_id);
        } elseif (in_array($table, ['warehouses', 'employees', 'journal_entry_lines', 'supplier_bills', 'purchase_requests', 'purchase_orders', 'stock_issues', 'stock_ledger_entries'], true)) {
            $query->where($table.'.site_id', $user->site_id);
        } elseif ($table === 'projects') {
            $query->whereKey($this->projectIdsFor($user) ?: [0]);
        } elseif (in_array($table, ['goods_receipts', 'stock_adjustments', 'warehouse_stocks'], true)) {
            $query->whereHas('warehouse', fn ($warehouse) => $warehouse->where('site_id', $user->site_id));
        } elseif ($table === 'stock_transfers') {
            $query->whereHas('fromWarehouse', fn ($warehouse) => $warehouse->where('site_id', $user->site_id));
        } elseif ($table === 'customer_invoices') {
            $query->whereIn('project_id', $this->projectIdsFor($user) ?: [0]);
        }
    }

    private function warehouseScope(Builder $query, string $table, User $user): void
    {
        if (! $user->warehouse_id) {
            $query->whereRaw('1 = 0');
        } elseif ($table === 'warehouses') {
            $query->whereKey($user->warehouse_id);
        } elseif (in_array($table, ['goods_receipts', 'stock_adjustments', 'warehouse_stocks', 'stock_issues', 'stock_ledger_entries', 'purchase_requests', 'purchase_orders'], true)) {
            $query->where($table.'.warehouse_id', $user->warehouse_id);
        } elseif ($table === 'stock_transfers') {
            $query->where(function ($transfer) use ($user) {
                $transfer->where('from_warehouse_id', $user->warehouse_id)
                    ->orWhere('to_warehouse_id', $user->warehouse_id);
            });
        } else {
            $query->whereRaw('1 = 0');
        }
    }
}
