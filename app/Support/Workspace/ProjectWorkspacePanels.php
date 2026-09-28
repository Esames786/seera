<?php

namespace App\Support\Workspace;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Accounting\ProjectCostReport;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Project Phase A: scoped read models only. Business writes stay with their owners. */
class ProjectWorkspacePanels implements WorkspacePanels
{
    use PanelSet;

    public static function panels(): array
    {
        $definitions = [
            ['customer', 'Customer', 'Customers'], ['sites', 'Sites / Locations', 'Sites'],
            ['staff', 'Project Team / Staff', 'HR'], ['warehouses', 'Warehouses', 'Warehouses'],
            ['stock', 'Stock On Hand', 'Warehouse Stock'], ['suppliers', 'Suppliers', 'Suppliers'],
            ['requests', 'Purchase Requests', 'Purchase Requests'], ['orders', 'Project Purchases', 'Purchase Orders'],
            ['receipts', 'Goods Receipts', 'Goods Receipts'], ['materials', 'Material Used', 'Stock Issues'],
            ['invoices', 'Customer Invoices', 'Accounts Receivable'], ['payments', 'Customer Receipts', 'Accounts Receivable'],
            ['finance', 'Financial / Cost Summary', 'Financial Reports'], ['activity', 'Activity', 'Activity Logs'],
        ];

        return collect($definitions)->mapWithKeys(fn ($d) => [$d[0] => new PanelDefinition(...$d)])->all();
    }

    public static function invoices(Project $project): Builder
    {
        return CustomerInvoice::where('project_id', $project->id);
    }

    public static function receipts(Project $project): Builder
    {
        // A receipt belongs to a project through its PO, not merely its warehouse.
        return GoodsReceipt::whereHas('purchaseOrder', fn ($q) => $q->where('project_id', $project->id));
    }

    public static function materials(Project $project): Builder
    {
        // Same issue-ledger values as Inventory Project Consumption; only posted, visible issues.
        return StockLedgerEntry::where('project_id', $project->id)->where('movement_type', 'issue')->where('reference_type', StockIssue::class)
            ->whereIn('reference_id', StockIssue::where('project_id', $project->id)->where('status', 'posted')->select('id'));
    }

    public static function query(Project $project, string $panel, User $user): Builder
    {
        return match ($panel) {
            'customer' => Customer::whereKey($project->customer_id ?? 0),
            'sites' => Site::where('project_id', $project->id)->with('supervisor'),
            'staff' => Employee::where('project_id', $project->id)->with(['department', 'designation', 'site', 'manager']),
            'warehouses' => Warehouse::where('project_id', $project->id)->with(['site', 'incharge'])
                ->when($user->hasPermission('Warehouse Stock', 'view'), fn ($q) => $q
                    ->withCount(['stocks as stocked_items' => fn ($s) => $s->where('quantity', '>', 0)])
                    ->withSum(['stocks' => fn ($s) => $s->where('quantity', '>', 0)], 'total_value')),
            'stock' => WarehouseStock::whereHas('warehouse', fn ($q) => $q->where('project_id', $project->id))
                ->where('quantity', '>', 0)->with(['warehouse', 'item.unit']),
            'suppliers' => Supplier::where(function ($q) use ($project, $user) {
                $q->whereHas('projects', fn ($p) => $p->where('projects.id', $project->id));
                if ($user->hasPermission('Purchase Orders', 'view')) {
                    $q->orWhereIn('id', PurchaseOrder::where('project_id', $project->id)->select('supplier_id'));
                }
                if ($user->hasPermission('Accounts Payable', 'view')) {
                    $q->orWhereIn('id', SupplierBill::where('project_id', $project->id)->select('supplier_id'));
                }
            }),
            'requests' => PurchaseRequest::where('project_id', $project->id)
                ->when($user->hasPermission('Purchase Orders', 'view'), fn ($q) => $q->withCount(['purchaseOrders' => fn ($p) => $p->where('project_id', $project->id)])),
            'orders' => PurchaseOrder::where('project_id', $project->id)->with('supplier'),
            'receipts' => self::receipts($project)->with(['purchaseOrder', 'supplier', 'warehouse'])
                ->withSum('lines', 'accepted_quantity')->withSum('lines', 'invoiced_quantity'),
            'materials' => self::materials($project)->with(['site', 'warehouse', 'item.unit']),
            'invoices' => self::invoices($project)->with('customer'),
            'payments' => CustomerReceipt::whereIn('customer_invoice_id', self::invoices($project)->select('id'))->with('invoice'),
            // New records use an immutable entity token. Legacy names are not a safe subject key.
            'activity' => ActivityLog::visibleTo($user)->where('module', 'Projects')
                ->where('description', 'like', '[Project #'.$project->id.'] %'),
            default => abort(404),
        };
    }

    public static function summary(Project $project, User $user): array
    {
        $summary = [];
        foreach (['sites' => 'Sites', 'staff' => 'Assigned staff', 'warehouses' => 'Warehouses'] as $key => $label) {
            if (self::definition($key)->canView($user)) {
                $summary[$label] = self::query($project, $key, $user)->count();
            }
        }
        if ($user->hasPermission('Purchase Orders', 'view')) {
            $summary['Open purchase orders'] = PurchaseOrder::where('project_id', $project->id)->whereIn('status', ['approved', 'partially_received'])->count();
        }
        if ($user->hasPermission('Stock Issues', 'view')) {
            $summary['Material used (SAR)'] = number_format((float) self::materials($project)->sum('value'), 2);
        }
        if ($user->hasPermission('Accounts Receivable', 'view')) {
            $summary['Amount still to receive (SAR)'] = number_format((float) self::invoices($project)->whereIn('payment_status', ['unpaid', 'partially_paid'])->sum('balance_amount'), 2);
        }
        if ($user->hasPermission('Financial Reports', 'view')) {
            $finance = self::finance($project);
            $summary['Posted cost (SAR)'] = number_format($finance['cost'], 2);
            $summary['Posted revenue (SAR)'] = number_format($finance['revenue'], 2);
        }

        return $summary;
    }

    public static function finance(Project $project): array
    {
        return app(ProjectCostReport::class)->rows(new Collection([$project]), new ReportPeriod(null, null, 'custom'))->first();
    }

    public static function data(Project $project, string $panel, User $user, int $page = 1): array
    {
        $rows = $panel === 'finance' ? null : self::query($project, $panel, $user)->latest('id')->paginate(10, ['*'], 'page', $page)->withQueryString();
        $extra = [];
        if ($panel === 'finance') {
            $extra['finance'] = self::finance($project);
        }
        if ($panel === 'materials') {
            $extra['materialTotal'] = (float) self::materials($project)->sum('value');
            // Only total quantities when every visible movement has the same known unit.
            $unitIds = self::materials($project)->join('items', 'items.id', '=', 'stock_ledger_entries.item_id')->distinct()->pluck('items.unit_id');
            if ($unitIds->count() === 1 && $unitIds->first()) {
                $extra['materialQuantity'] = (float) self::materials($project)->sum('out_quantity');
                $extra['materialUnit'] = Unit::find($unitIds->first())?->name;
            }
        }
        if ($panel === 'stock') {
            $extra['stockTotal'] = (float) self::query($project, $panel, $user)->sum('total_value');
        }
        if ($panel === 'warehouses' && $user->hasPermission('Warehouse Stock', 'view')) {
            $extra['warehouseQuantities'] = WarehouseStock::whereIn('warehouse_id', $rows->pluck('id'))->where('quantity', '>', 0)
                ->join('items', 'items.id', '=', 'warehouse_stocks.item_id')
                ->leftJoin('units', 'units.id', '=', 'items.unit_id')
                ->groupBy('warehouse_stocks.warehouse_id', 'items.unit_id', 'units.name')
                ->selectRaw('warehouse_stocks.warehouse_id, items.unit_id, units.name as unit_name, SUM(warehouse_stocks.quantity) as on_hand')
                ->get()->groupBy('warehouse_id')->map(fn ($units) => $units->map(fn ($unit) => $unit->unit_id
                    ? number_format((float) $unit->on_hand, 3).' '.$unit->unit_name
                    : 'Unit missing: see item balances')->implode(' / '));
        }
        if ($panel === 'customer' && $user->hasPermission('Accounts Receivable', 'view')) {
            $extra['arTotal'] = (float) self::invoices($project)->whereIn('payment_status', ['unpaid', 'partially_paid', 'paid'])->sum('total_amount');
            $extra['arOutstanding'] = (float) self::invoices($project)->whereIn('payment_status', ['unpaid', 'partially_paid'])->sum('balance_amount');
        }
        if ($panel === 'suppliers' && $user->hasPermission('Purchase Orders', 'view')) {
            $extra['supplierPurchases'] = PurchaseOrder::where('project_id', $project->id)->whereIn('supplier_id', $rows->pluck('id'))
                ->where('status', '!=', 'cancelled')->groupBy('supplier_id')->selectRaw('supplier_id, COUNT(*) as orders, SUM(total_amount) as value')->get()->keyBy('supplier_id');
        }
        if ($panel === 'orders' && $user->hasPermission('Goods Receipts', 'view')) {
            $extra['orderReceipts'] = GoodsReceiptLine::whereIn('goods_receipt_id', self::receipts($project)->whereIn('purchase_order_id', $rows->pluck('id'))->where('status', 'posted')->select('id'))
                ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
                ->groupBy('goods_receipts.purchase_order_id')->selectRaw('goods_receipts.purchase_order_id, SUM(accepted_quantity) as accepted, SUM(invoiced_quantity) as invoiced')->get()->keyBy('purchase_order_id');
        }

        return compact('project', 'panel', 'user', 'rows') + $extra + [
            'title' => self::definition($panel)->title,
            'returnTo' => route('admin.master.projects.show', $project, false).'#'.$panel,
        ];
    }
}
