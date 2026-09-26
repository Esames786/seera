<?php

namespace App\Support\Workspace;

use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\SupplierBill;
use App\Models\SupplierBillGrnMatch;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Purchase Order connected document workspace (procure-to-pay).
 *
 * The purchase order in the URL is the authority for every section: goods
 * receipts are the order's own receipts, bills are the bills whose F04 GRN
 * matches point at those receipts, journals are the journals those documents
 * posted. Every query starts from a model that carries the user's access scope,
 * so a receipt in another project's warehouse or a bill outside the user's
 * scope is neither listed nor counted. Nothing is written from here.
 */
class PurchaseOrderWorkspacePanels implements WorkspacePanels
{
    use PanelSet;

    public const PAGED = ['goods-receipts', 'billing', 'accounting', 'activity'];

    public static function panels(): array
    {
        return [
            'overview' => new PanelDefinition('overview', 'Overview', 'Purchase Orders'),
            'lines' => new PanelDefinition('lines', 'Order Lines', 'Purchase Orders'),
            'purchase-request' => new PanelDefinition('purchase-request', 'Source Purchase Request', 'Purchase Requests'),
            'supplier' => new PanelDefinition('supplier', 'Supplier & Commercial', 'Suppliers'),
            'attachments' => new PanelDefinition('attachments', 'Quotations', 'Purchase Orders'),
            'goods-receipts' => new PanelDefinition('goods-receipts', 'Goods Receipts', 'Goods Receipts'),
            'billing' => new PanelDefinition('billing', 'Billing & GRN Matching', 'Accounts Payable'),
            'accounting' => new PanelDefinition('accounting', 'Accounting', 'Journal Entries'),
            'activity' => new PanelDefinition('activity', 'Activity', 'Activity Logs'),
        ];
    }

    /** Posted receipts of THIS order only (the order id is the authority, never the browser). */
    public static function receipts(PurchaseOrder $order): Builder
    {
        return GoodsReceipt::query()->where('purchase_order_id', $order->id);
    }

    /** Bills that hold an F04 match against a receipt line of this order, for this order's supplier. */
    public static function bills(PurchaseOrder $order): Builder
    {
        return SupplierBill::query()
            ->where('supplier_id', $order->supplier_id)
            ->whereIn('id', SupplierBillGrnMatch::query()
                ->whereIn('goods_receipt_id', self::receipts($order)->select('id'))
                ->select('supplier_bill_id'));
    }

    /** The scoped query behind a paged panel. */
    public static function query(PurchaseOrder $order, string $panel, User $user): Builder
    {
        return match ($panel) {
            'goods-receipts' => self::receipts($order)
                ->with(['warehouse', 'receiver', 'lines', 'journalEntry'])->latest('received_date')->latest('id'),
            'billing' => self::bills($order)
                ->with(['project', 'site', 'journalEntry', 'grnMatches.goodsReceipt'])->latest('bill_date')->latest('id'),
            'accounting' => JournalEntry::query()
                ->whereIn('id', self::journalIds($order))
                ->with('lines.account')->latest('journal_date')->latest('id'),
            'activity' => self::activityQuery($order, $user),
        };
    }

    /**
     * Header and section figures, all from scoped queries. Money about bills is
     * only computed for users with the Accounts Payable view right.
     *
     * @return array<string, mixed>
     */
    public static function summary(PurchaseOrder $order, User $user): array
    {
        $lines = self::lineMatrix($order);
        $ordered = round($lines->sum('ordered'), 3);
        $received = round($lines->sum('received'), 3);
        $invoiced = round($lines->sum('invoiced'), 3);
        $uninvoiced = round($lines->sum('uninvoiced'), 3);

        $summary = [
            'ordered' => $ordered,
            'received' => $received,
            'outstanding' => round(max($ordered - $received, 0), 3),
            'invoiced' => $invoiced,
            'uninvoiced' => $uninvoiced,
            'receipts' => (clone self::receipts($order))->count(),
            'draft_receipts' => (clone self::receipts($order))->where('status', 'draft')->count(),
            'received_state' => match (true) {
                $ordered <= 0 => 'No lines',
                $received <= 0 => 'Nothing received yet',
                $received + 0.0005 >= $ordered => 'Fully received',
                default => 'Partially received',
            },
            'billing_state' => match (true) {
                $received <= 0 => 'Nothing to invoice yet',
                $uninvoiced <= 0.0005 => 'Fully invoiced',
                $invoiced > 0 => 'Partly invoiced',
                default => 'Received but not invoiced',
            },
            'can_create_receipt' => $order->canReceive()
                && $ordered - $received > 0.0005
                && ($user->hasPermission('Goods Receipts', 'create') || $user->hasPermission('Goods Receipts', 'receive')),
        ];

        if ($user->hasPermission('Accounts Payable', 'view')) {
            $bills = self::bills($order);
            $summary += [
                'bills' => (clone $bills)->count(),
                'billed' => round((float) (clone $bills)->whereIn('status', SupplierWorkspacePanels::APPROVED_BILL_STATUSES)->sum('total_amount'), 2),
                'paid' => round((float) (clone $bills)->whereIn('status', SupplierWorkspacePanels::APPROVED_BILL_STATUSES)->sum('paid_amount'), 2),
                'outstanding_payment' => round((float) (clone $bills)->whereIn('status', SupplierWorkspacePanels::OPEN_BILL_STATUSES)->sum('balance_amount'), 2),
            ];
        }

        return $summary;
    }

    /**
     * Per order line: ordered, received, still to receive, invoiced and
     * received-but-not-invoiced. Received comes from the order line itself
     * (updated by Post Stock); invoiced comes from the F04 invoiced quantity on
     * the posted receipt lines of this order.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function lineMatrix(PurchaseOrder $order): Collection
    {
        $order->loadMissing('lines.item.unit');

        $receiptLines = GoodsReceiptLine::query()
            ->whereIn('goods_receipt_id', self::receipts($order)->where('status', 'posted')->select('id'))
            ->get(['purchase_order_line_id', 'accepted_quantity', 'invoiced_quantity'])
            ->groupBy('purchase_order_line_id');

        return $order->lines->map(function ($line) use ($receiptLines) {
            $posted = $receiptLines->get($line->id, collect());
            $invoiced = round((float) $posted->sum('invoiced_quantity'), 3);
            $uninvoiced = round($posted->sum(fn ($grnLine) => $grnLine->uninvoicedQuantity()), 3);

            return [
                'line' => $line,
                'ordered' => (float) $line->quantity,
                'received' => (float) $line->received_quantity,
                'outstanding' => $line->outstandingQuantity(),
                'invoiced' => $invoiced,
                'uninvoiced' => $uninvoiced,
            ];
        });
    }

    /**
     * Everything a paged section view needs, for the View page and the panel endpoint.
     *
     * @return array<string, mixed>
     */
    public static function data(PurchaseOrder $order, string $panel, User $user, int $perPage = 5, ?int $page = null, bool $inline = true): array
    {
        $definition = self::definition($panel);
        $rows = self::query($order, $panel, $user)
            ->paginate($perPage, ['*'], $inline ? 'page_'.$panel : 'page', $page)
            ->fragment($panel);

        return [
            'order' => $order,
            'panel' => $panel,
            'title' => $definition->title,
            'rows' => $rows,
            'inline' => $inline,
            'viewAll' => self::viewAllUrl($order, $panel),
            'returnTo' => route('admin.inventory.purchase-orders.show', $order, false).'#'.$panel,
        ] + match ($panel) {
            'goods-receipts' => [
                'canViewReceipts' => $user->hasPermission('Goods Receipts', 'view'),
                'canCreateBill' => $user->hasPermission('Accounts Payable', 'create'),
                'canViewJournals' => $user->hasPermission('Journal Entries', 'view'),
            ],
            'billing' => [
                'matrix' => self::lineMatrix($order),
                'canEditBill' => $user->hasPermission('Accounts Payable', 'edit'),
                'canPay' => $user->hasPermission('Accounts Payable', 'process'),
                'canViewReceipts' => $user->hasPermission('Goods Receipts', 'view'),
            ],
            'accounting' => ['grniAccount' => \App\Services\Accounting\PostingService::GRNI],
            default => [],
        };
    }

    public static function viewAllUrl(PurchaseOrder $order, string $panel): ?string
    {
        return match ($panel) {
            'goods-receipts' => route('admin.inventory.goods-receipts.index', ['search' => $order->po_number]),
            'billing' => route('admin.accounting.accounts-payable.index', ['supplier' => $order->supplier_id]),
            'accounting' => route('admin.accounting.journal-entries.index', ['search' => $order->po_number]),
            'activity' => route('admin.activity-logs.index', ['search' => $order->po_number]),
            default => null,
        };
    }

    /** Journals posted by this order's receipts, by the bills matched to them, and by payments of those bills. */
    private static function journalIds(PurchaseOrder $order): Collection
    {
        $bills = self::bills($order);

        return collect()
            ->merge((clone self::receipts($order))->whereNotNull('journal_entry_id')->pluck('journal_entry_id'))
            ->merge((clone $bills)->whereNotNull('journal_entry_id')->pluck('journal_entry_id'))
            ->merge(SupplierPayment::query()->whereIn('supplier_bill_id', (clone $bills)->select('id'))->whereNotNull('journal_entry_id')->pluck('journal_entry_id'))
            ->unique()->values();
    }

    private static function activityQuery(PurchaseOrder $order, User $user): Builder
    {
        // The order's own entries plus those of its receipts and matched bills. Documents come
        // from scoped queries, so a hidden receipt never surfaces through its activity.
        $references = collect([$order->po_number])
            ->merge((clone self::receipts($order))->latest('id')->limit(20)->pluck('grn_number'))
            ->merge((clone self::bills($order))->latest('id')->limit(20)->pluck('bill_number'))
            ->filter()->unique()->values();

        return ActivityLog::query()->visibleTo($user)
            ->whereIn('module', ['Inventory', 'Accounting'])
            ->where(function ($q) use ($references) {
                foreach ($references as $reference) {
                    $q->orWhere('description', 'like', '%'.addcslashes($reference, '%_').'%');
                }
            })
            ->latest('created_at')->latest('id');
    }
}
