<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserAccessScopeService
{
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

    private function projectScope(Builder $query, string $table, User $user): void
    {
        if (! $user->project_id) {
            $query->whereRaw('1 = 0');
        } elseif ($table === 'projects') {
            $query->whereKey($user->project_id);
        } elseif (in_array($table, ['sites', 'warehouses', 'employees', 'journal_entry_lines', 'supplier_bills', 'customer_invoices', 'purchase_requests', 'purchase_orders', 'stock_issues', 'stock_ledger_entries'], true)) {
            $query->where($table.'.project_id', $user->project_id);
        } elseif (in_array($table, ['goods_receipts', 'stock_adjustments', 'warehouse_stocks'], true)) {
            $query->whereHas('warehouse', fn ($warehouse) => $warehouse->where('project_id', $user->project_id));
        } elseif ($table === 'stock_transfers') {
            $query->whereHas('fromWarehouse', fn ($warehouse) => $warehouse->where('project_id', $user->project_id));
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
            $query->whereKey($user->project_id ?? 0);
        } elseif (in_array($table, ['goods_receipts', 'stock_adjustments', 'warehouse_stocks'], true)) {
            $query->whereHas('warehouse', fn ($warehouse) => $warehouse->where('site_id', $user->site_id));
        } elseif ($table === 'stock_transfers') {
            $query->whereHas('fromWarehouse', fn ($warehouse) => $warehouse->where('site_id', $user->site_id));
        } elseif ($table === 'customer_invoices') {
            $query->where('project_id', $user->project_id ?? 0);
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
