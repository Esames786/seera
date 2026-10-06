<?php

namespace App\Support\Workspace;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\Warehouse;

class SiteWorkspacePanels implements WorkspacePanels
{
    use PanelSet;

    public static function panels(): array
    {
        $panels = [];
        foreach (['project' => 'Projects', 'location' => 'Sites', 'staff' => 'HR', 'warehouses' => 'Warehouses', 'requests' => 'Purchase Requests', 'orders' => 'Purchase Orders', 'receipts' => 'Goods Receipts', 'materials' => 'Stock Issues', 'expenses' => 'Site Expenses', 'attendance' => 'Attendance', 'activity' => 'Activity Logs'] as $key => $module) {
            $panels[$key] = new PanelDefinition($key, 'workspace.'.$key, $module);
        }

        return $panels;
    }

    public static function query(Site $site, string $panel, User $actor)
    {
        $scoped = fn ($q) => $q->where('site_id', $site->id)->where('project_id', $site->project_id);

        return match ($panel) {
            'project' => Project::whereKey($site->project_id ?? 0)->with(['manager'])->when($actor->hasPermission('Customers', 'view'), fn ($q) => $q->with('customer')),
            'staff' => $scoped(Employee::query())->with(['department', 'designation', 'project']),
            'warehouses' => $scoped(Warehouse::query())->with(['incharge', 'project'])->when($actor->hasPermission('Warehouse Stock', 'view'), fn ($q) => $q->withCount(['stocks as stocked_items' => fn ($s) => $s->where('quantity', '>', 0)])->withSum(['stocks' => fn ($s) => $s->where('quantity', '>', 0)], 'total_value')),
            'requests' => $scoped(PurchaseRequest::query()),
            'orders' => $scoped(PurchaseOrder::query()),
            'receipts' => GoodsReceipt::whereHas('purchaseOrder', $scoped),
            'materials' => $scoped(StockLedgerEntry::query())->where('movement_type', 'issue')->where('reference_type', StockIssue::class)->whereIn('reference_id', $scoped(StockIssue::query())->where('status', 'posted')->select('id'))->with(['item', 'warehouse']),
            'expenses' => $scoped(SiteExpense::query())->with(['category', 'submitter']),
            'attendance' => $scoped(AttendanceRecord::query())->with('employee'),
            'activity' => ActivityLog::visibleTo($actor)->where('module', 'Sites')->where('description', 'like', '[Site #'.$site->id.'] %'),
            default => abort(404),
        };
    }

    public static function data(Site $site, string $panel, User $actor): array
    {
        return compact('site', 'panel', 'actor') + ['rows' => $panel === 'location' ? null : self::query($site, $panel, $actor)->latest('id')->paginate(10)->withQueryString()];
    }

    public static function cells($row, string $panel, User $actor): array
    {
        return match ($panel) {
            'project' => [$row->code, $row->name, $actor->hasPermission('Customers', 'view') ? $row->customer?->name : null, $row->manager?->name, $row->status],
            'staff' => [$row->employee_code, $row->name, $row->department?->name, $row->designation?->name, $row->project?->name, $row->status, $row->mobile_access ? __('workspace.yes') : __('workspace.no')],
            'warehouses' => [$row->code, $row->name, $row->incharge?->name, $row->project?->name, $row->status, ...($actor->hasPermission('Warehouse Stock', 'view') ? [$row->stocked_items, number_format((float) $row->stocks_sum_total_value, 2)] : [])],
            'requests' => [$row->pr_number, $row->request_date?->format('Y-m-d'), $row->status],
            'orders' => [$row->po_number, $row->po_date?->format('Y-m-d'), $row->status],
            'receipts' => [$row->grn_number, $row->received_date?->format('Y-m-d'), $row->status],
            'materials' => [$row->reference_number, $row->movement_date?->format('Y-m-d'), $row->item?->name, $row->out_quantity, number_format((float) $row->value, 2)],
            'expenses' => [$row->expense_number, $row->expense_date?->format('Y-m-d'), $row->category?->name, $row->submitter?->name, $row->payment_type, number_format((float) $row->total_amount, 2), $row->status, $row->accounting_posted ? __('workspace.posted') : __('workspace.not_posted')],
            'attendance' => [$row->attendance_date?->format('Y-m-d'), $row->employee?->name, $row->status, $row->source, $row->geofence_status],
            'activity' => [$row->created_at, $row->action, $row->user_name],
        };
    }

    public static function columns(string $panel, User $actor): array
    {
        return match ($panel) {
            'project' => ['code', 'name', 'customer', 'manager', 'status'], 'staff' => ['code', 'name', 'department', 'designation', 'project', 'status', 'mobile'],
            'warehouses' => ['code', 'name', 'manager', 'project', 'status', ...($actor->hasPermission('Warehouse Stock', 'view') ? ['stocked_items', 'stock_value'] : [])],
            'requests','orders','receipts' => ['number', 'date', 'status'], 'materials' => ['number', 'date', 'item', 'quantity', 'value'],
            'expenses' => ['number', 'date', 'category', 'submitter', 'payment_type', 'amount', 'approval_state', 'accounting_state'],
            'attendance' => ['date', 'employee', 'status', 'source', 'geofence_record'], 'activity' => ['date', 'action', 'actor'],
        };
    }

    public static function route(string $panel): ?string
    {
        return match ($panel) {
            'project' => 'admin.master.projects.show', 'staff' => 'admin.hr.employees.show', 'warehouses' => 'admin.master.warehouses.show',
            'requests' => 'admin.inventory.purchase-requests.show','orders' => 'admin.inventory.purchase-orders.show','receipts' => 'admin.inventory.goods-receipts.show',
            'materials' => 'admin.inventory.stock-issues.show','expenses' => 'admin.site-expenses.show', default => null,
        };
    }
}
