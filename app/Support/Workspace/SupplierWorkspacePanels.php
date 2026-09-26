<?php

namespace App\Support\Workspace;

use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supplier connected workspace (pilot of the Connected Workspace Standard).
 *
 * Every panel query starts from a model that carries the user's access scope
 * (project / site / warehouse) as a global scope, so a supplier's documents
 * outside the user's scope are neither listed nor counted. Totals are computed
 * from the same scoped queries the rows come from.
 */
class SupplierWorkspacePanels implements WorkspacePanels
{
    use PanelSet;

    public const OPEN_BILL_STATUSES = ['unpaid', 'partially_paid'];

    public const APPROVED_BILL_STATUSES = ['unpaid', 'partially_paid', 'paid'];

    public static function panels(): array
    {
        return [
            'projects' => new PanelDefinition('projects', 'Projects', 'Suppliers', 'view', 'edit'),
            'purchase-orders' => new PanelDefinition('purchase-orders', 'Purchase Orders', 'Purchase Orders'),
            'goods-receipts' => new PanelDefinition('goods-receipts', 'Goods Receipts', 'Goods Receipts'),
            'bills' => new PanelDefinition('bills', 'Supplier Bills', 'Accounts Payable'),
            'payments' => new PanelDefinition('payments', 'Payments & Balance', 'Accounts Payable'),
            'accounting' => new PanelDefinition('accounting', 'Accounting', 'Journal Entries'),
            'activity' => new PanelDefinition('activity', 'Activity', 'Activity Logs'),
        ];
    }

    /** The scoped query behind a panel. */
    public static function query(Supplier $supplier, string $panel, User $user): Builder
    {
        return match ($panel) {
            'projects' => $supplier->projects()->with('customer')->orderBy('projects.name')->getQuery(),
            'purchase-orders' => PurchaseOrder::query()->where('supplier_id', $supplier->id)
                ->with(['project', 'site', 'lines'])->latest('po_date')->latest('id'),
            'goods-receipts' => GoodsReceipt::query()->where('supplier_id', $supplier->id)
                ->with(['purchaseOrder', 'warehouse.project', 'warehouse.site', 'lines'])->latest('received_date')->latest('id'),
            'bills' => SupplierBill::query()->where('supplier_id', $supplier->id)
                ->with(['project', 'site'])->withCount('grnMatches')->latest('bill_date')->latest('id'),
            'payments' => SupplierPayment::query()->where('supplier_id', $supplier->id)
                ->with(['bill', 'paymentAccount', 'journalEntry'])->latest('payment_date')->latest('id'),
            'accounting' => JournalEntry::query()
                ->where(fn ($q) => $q
                    ->where(fn ($bills) => $bills->where('source_module', 'Supplier Bill')
                        ->whereIn('source_id', SupplierBill::query()->where('supplier_id', $supplier->id)->select('id')))
                    ->orWhere(fn ($payments) => $payments->where('source_module', 'Supplier Payment')
                        ->whereIn('source_id', SupplierPayment::query()->where('supplier_id', $supplier->id)->select('id'))))
                ->latest('journal_date')->latest('id'),
            'activity' => self::activityQuery($supplier, $user),
        };
    }

    /**
     * Header figures, from the bills the user may see. Null when the user has no
     * Accounts Payable view right, so nothing about money is leaked through the header.
     *
     * @return array<string, mixed>|null
     */
    public static function summary(Supplier $supplier, User $user): ?array
    {
        if (! $user->hasPermission('Accounts Payable', 'view')) {
            return null;
        }

        $bills = SupplierBill::query()->where('supplier_id', $supplier->id);

        return [
            'approved' => round((float) (clone $bills)->whereIn('status', self::APPROVED_BILL_STATUSES)->sum('total_amount'), 2),
            'paid' => round((float) (clone $bills)->whereIn('status', self::APPROVED_BILL_STATUSES)->sum('paid_amount'), 2),
            'outstanding' => round((float) (clone $bills)->whereIn('status', self::OPEN_BILL_STATUSES)->sum('balance_amount'), 2),
            'open_bills' => (clone $bills)->whereIn('status', self::OPEN_BILL_STATUSES)->count(),
            'draft_bills' => (clone $bills)->where('status', 'draft')->count(),
            'last_payment' => SupplierPayment::query()->where('supplier_id', $supplier->id)->latest('payment_date')->latest('id')->first(),
        ];
    }

    /**
     * Everything a panel view needs, for the AJAX workspace and for the read-only View page.
     *
     * @return array<string, mixed>
     */
    public static function data(Supplier $supplier, string $panel, User $user, bool $readonly = false, int $perPage = 10, ?int $page = null): array
    {
        $definition = self::definition($panel);
        $rows = self::query($supplier, $panel, $user)->paginate($perPage, ['*'], $readonly ? 'page_'.$panel : 'page', $page);

        $data = [
            'supplier' => $supplier,
            'panel' => $panel,
            'title' => $definition->title,
            'module' => $definition->module,
            'rows' => $rows,
            'readonly' => $readonly,
            'canEdit' => ! $readonly && $definition->canWrite($user),
            'hasNext' => ! $readonly && self::hasNext($user, $panel),
            'viewAll' => self::viewAllUrl($supplier, $panel),
        ];

        return $data + match ($panel) {
            'projects' => [
                'canViewProjects' => $user->hasPermission('Projects', 'view'),
                'assignable' => $data['canEdit'] ? self::assignableProjects($supplier) : collect(),
            ],
            'purchase-orders' => ['canViewOrders' => $user->hasPermission('Purchase Orders', 'view')],
            'goods-receipts' => ['canCreateBill' => $user->hasPermission('Accounts Payable', 'create')],
            'bills' => [
                'canEditBill' => $user->hasPermission('Accounts Payable', 'edit'),
                'canPay' => $user->hasPermission('Accounts Payable', 'process'),
            ],
            'payments' => ['summary' => self::summary($supplier, $user)],
            'accounting' => ['linkedAccount' => $supplier->linkedAccount],
            default => [],
        };
    }

    /** Projects the user may see that are not linked to this supplier yet. */
    public static function assignableProjects(Supplier $supplier)
    {
        return Project::query()->whereNotIn('id', $supplier->projects()->select('projects.id'))->orderBy('name')->get(['id', 'code', 'name']);
    }

    public static function viewAllUrl(Supplier $supplier, string $panel): ?string
    {
        return match ($panel) {
            'purchase-orders' => route('admin.inventory.purchase-orders.index', ['search' => $supplier->name]),
            'goods-receipts' => route('admin.inventory.goods-receipts.index', ['search' => $supplier->name]),
            'bills', 'payments' => route('admin.accounting.accounts-payable.index', ['supplier' => $supplier->id]),
            'accounting' => route('admin.accounting.journal-entries.index', ['search' => $supplier->name]),
            'activity' => route('admin.activity-logs.index', ['search' => $supplier->name]),
            default => null,
        };
    }

    private static function activityQuery(Supplier $supplier, User $user): Builder
    {
        // Supplier master entries plus finance/inventory entries that name this supplier's
        // recent documents. Scoped documents only, so hidden bills never surface here.
        $references = collect([$supplier->name])
            ->merge(SupplierBill::query()->where('supplier_id', $supplier->id)->latest('id')->limit(20)->pluck('bill_number'))
            ->merge(PurchaseOrder::query()->where('supplier_id', $supplier->id)->latest('id')->limit(20)->pluck('po_number'))
            ->merge(GoodsReceipt::query()->where('supplier_id', $supplier->id)->latest('id')->limit(20)->pluck('grn_number'))
            ->filter()->unique()->values();

        return ActivityLog::query()->visibleTo($user)
            ->whereIn('module', ['Suppliers', 'Accounting', 'Inventory'])
            ->where(function ($q) use ($references) {
                foreach ($references as $reference) {
                    $q->orWhere('description', 'like', '%'.addcslashes($reference, '%_').'%');
                }
            })
            ->latest('created_at')->latest('id');
    }
}
