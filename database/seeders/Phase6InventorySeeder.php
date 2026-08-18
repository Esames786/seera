<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Accounting\PostingService;
use App\Services\Inventory\StockService;
use Illuminate\Database\Seeder;

class Phase6InventorySeeder extends Seeder
{
    private StockService $stock;

    private PostingService $posting;

    private ?int $storeKeeperId = null;

    private ?int $supervisorId = null;

    public function run(): void
    {
        $this->stock = app(StockService::class);
        $this->posting = app(PostingService::class);
        $this->storeKeeperId = User::where('email', 'zubair@example.com')->value('id');
        $this->supervisorId = User::where('email', 'nabeel@example.com')->value('id');

        $this->seedUnits();
        $this->seedCategories();
        $this->seedItems();
        $this->seedOpeningStock();
        $this->seedPurchaseRequests();
        $this->seedPurchaseOrders();
        $this->seedGoodsReceipts();
        $this->seedStockIssues();
        $this->seedStockTransfers();
        $this->seedStockAdjustments();
    }

    private function seedUnits(): void
    {
        $units = [
            ['BAG', 'Bag', false], ['TON', 'Ton', true], ['M3', 'Cubic Meter', true],
            ['PCS', 'Piece', false], ['MTR', 'Meter', true], ['LTR', 'Litre', true],
            ['KG', 'Kilogram', true], ['ROLL', 'Roll', false],
        ];

        foreach ($units as [$code, $name, $decimal]) {
            Unit::create(['code' => $code, 'name' => $name, 'allows_decimal' => $decimal, 'status' => 'active']);
        }
    }

    private function seedCategories(): void
    {
        $accounts = ChartOfAccount::pluck('id', 'account_code');

        $categories = [
            ['CAT-CEM', 'Cement and Concrete'], ['CAT-STL', 'Steel and Rebar'],
            ['CAT-BLK', 'Blocks and Masonry'], ['CAT-ELE', 'Electrical'],
            ['CAT-PLM', 'Plumbing'], ['CAT-FIN', 'Finishing'],
            ['CAT-SAF', 'Safety and PPE'], ['CAT-CON', 'Consumables'],
        ];

        foreach ($categories as [$code, $name]) {
            ItemCategory::create([
                'code' => $code,
                'name' => $name,
                'inventory_account_id' => $accounts['1400'] ?? null,
                'expense_account_id' => $accounts['5200'] ?? null,
                'status' => 'active',
            ]);
        }
    }

    private function seedItems(): void
    {
        $categories = ItemCategory::pluck('id', 'code');
        $units = Unit::pluck('id', 'code');
        $accounts = ChartOfAccount::pluck('id', 'account_code');
        $suppliers = Supplier::orderBy('id')->get();

        // category, name, unit, average cost, reorder level
        $items = [
            ['CAT-CEM', 'Ordinary Portland Cement 50kg', 'BAG', 18.50, 400],
            ['CAT-CEM', 'Sulphate Resistant Cement 50kg', 'BAG', 21.00, 200],
            ['CAT-CEM', 'Ready Mix Concrete C30', 'M3', 240.00, 25],
            ['CAT-CEM', 'White Cement 25kg', 'BAG', 34.00, 60],
            ['CAT-CEM', 'Concrete Admixture', 'LTR', 12.00, 100],
            ['CAT-STL', 'Rebar 12mm', 'TON', 2650.00, 8],
            ['CAT-STL', 'Rebar 16mm', 'TON', 2620.00, 8],
            ['CAT-STL', 'Rebar 20mm', 'TON', 2600.00, 6],
            ['CAT-STL', 'Binding Wire', 'KG', 6.50, 150],
            ['CAT-STL', 'Steel Mesh A142', 'PCS', 145.00, 40],
            ['CAT-BLK', 'Hollow Block 200mm', 'PCS', 3.20, 2000],
            ['CAT-BLK', 'Solid Block 100mm', 'PCS', 2.40, 1500],
            ['CAT-BLK', 'Building Sand', 'M3', 55.00, 30],
            ['CAT-BLK', 'Aggregate 20mm', 'M3', 62.00, 30],
            ['CAT-BLK', 'Mortar Mix 40kg', 'BAG', 14.00, 200],
            ['CAT-ELE', 'Cable 3x2.5mm', 'MTR', 7.80, 500],
            ['CAT-ELE', 'Cable 3x4mm', 'MTR', 11.40, 400],
            ['CAT-ELE', 'Conduit Pipe 20mm', 'MTR', 2.90, 600],
            ['CAT-ELE', 'Distribution Board 12 Way', 'PCS', 320.00, 10],
            ['CAT-ELE', 'LED Flood Light 100W', 'PCS', 95.00, 25],
            ['CAT-PLM', 'PPR Pipe 25mm', 'MTR', 9.20, 300],
            ['CAT-PLM', 'PVC Drain Pipe 110mm', 'MTR', 16.50, 250],
            ['CAT-PLM', 'Gate Valve 50mm', 'PCS', 78.00, 20],
            ['CAT-PLM', 'Water Tank 1000L', 'PCS', 540.00, 5],
            ['CAT-PLM', 'Pipe Fittings Assorted', 'PCS', 12.00, 200],
            ['CAT-FIN', 'Ceramic Floor Tile 60x60', 'PCS', 42.00, 100],
            ['CAT-FIN', 'Wall Putty 20kg', 'BAG', 46.00, 80],
            ['CAT-FIN', 'Emulsion Paint 18L', 'LTR', 190.00, 40],
            ['CAT-FIN', 'Primer 18L', 'LTR', 155.00, 30],
            ['CAT-FIN', 'Gypsum Board 12mm', 'PCS', 28.00, 150],
            ['CAT-SAF', 'Safety Helmet', 'PCS', 24.00, 100],
            ['CAT-SAF', 'Safety Boots', 'PCS', 85.00, 60],
            ['CAT-SAF', 'High Visibility Vest', 'PCS', 18.00, 120],
            ['CAT-SAF', 'Safety Harness', 'PCS', 210.00, 20],
            ['CAT-SAF', 'Work Gloves', 'PCS', 9.50, 300],
            ['CAT-CON', 'Cutting Disc 4in', 'PCS', 6.00, 200],
            ['CAT-CON', 'Welding Rod 2.5mm', 'KG', 14.50, 80],
            ['CAT-CON', 'Plastic Sheet Roll', 'ROLL', 88.00, 15],
            ['CAT-CON', 'Shuttering Oil', 'LTR', 8.40, 150],
            ['CAT-CON', 'Wire Nails Assorted', 'KG', 7.20, 200],
        ];

        foreach ($items as $index => [$categoryCode, $name, $unitCode, $cost, $reorder]) {
            Item::create([
                'item_code' => sprintf('ITM-%04d', $index + 1),
                'name' => $name,
                'item_category_id' => $categories[$categoryCode] ?? null,
                'unit_id' => $units[$unitCode] ?? null,
                'valuation_method' => 'average',
                'reorder_level' => $reorder,
                'minimum_stock' => round($reorder * 0.5, 3),
                'maximum_stock' => round($reorder * 4, 3),
                'preferred_supplier_id' => $suppliers[$index % max($suppliers->count(), 1)]->id ?? null,
                'inventory_account_id' => $accounts['1400'] ?? null,
                'expense_account_id' => $accounts['5200'] ?? null,
                'vat_applicable' => true,
                'average_cost' => $cost,
                'status' => 'active',
            ]);
        }
    }

    /**
     * Opening balances, deliberately leaving every fifth item below its
     * reorder level so the low-stock screens have something real to show.
     */
    private function seedOpeningStock(): void
    {
        $warehouses = Warehouse::orderBy('id')->get();
        $items = Item::orderBy('id')->get();

        foreach ($items as $index => $item) {
            $warehouse = $warehouses[$index % max($warehouses->count(), 1)];
            $reorder = (float) $item->reorder_level;

            $quantity = $index % 5 === 0
                ? round(max($reorder * 0.4, 1), 3)
                : round(max($reorder, 20) * 3, 3);

            $this->stock->receive($item, $warehouse->id, $quantity, (float) $item->average_cost, [
                'movement_type' => 'grn',
                'reference_number' => 'OPENING',
                'movement_date' => now()->startOfYear()->toDateString(),
                'project_id' => $warehouse->project_id,
                'site_id' => $warehouse->site_id,
                'created_by' => $this->storeKeeperId,
            ]);
        }
    }

    private function seedPurchaseRequests(): void
    {
        $items = Item::orderBy('id')->get();
        $projects = Project::orderBy('id')->get();
        $sites = Site::orderBy('id')->get();
        $warehouses = Warehouse::orderBy('id')->get();

        $statuses = ['approved', 'approved', 'pending', 'approved', 'pending', 'rejected', 'draft', 'approved'];

        foreach ($statuses as $index => $status) {
            $date = now()->subDays(40 - ($index * 4));
            $decided = in_array($status, ['approved', 'rejected'], true);

            $pr = PurchaseRequest::create([
                'pr_number' => PurchaseRequest::nextNumber($date->year),
                'request_date' => $date->toDateString(),
                'requested_by' => $this->supervisorId,
                'project_id' => $projects[$index % max($projects->count(), 1)]->id ?? null,
                'site_id' => $sites[$index % max($sites->count(), 1)]->id ?? null,
                'warehouse_id' => $warehouses[$index % max($warehouses->count(), 1)]->id ?? null,
                'required_date' => $date->copy()->addDays(14)->toDateString(),
                'priority' => ['normal', 'high', 'urgent', 'low'][$index % 4],
                'reason' => 'Material required for the next construction stage.',
                'status' => $status,
                'approved_by' => $decided ? $this->storeKeeperId : null,
                'approved_at' => $decided ? $date->copy()->addDay() : null,
                'rejection_reason' => $status === 'rejected' ? 'Stock already available at another warehouse.' : null,
            ]);

            $total = 0.0;

            for ($line = 0; $line < 3; $line++) {
                $item = $items[($index * 3 + $line) % $items->count()];
                $quantity = 20 + ($line * 15);
                $cost = (float) $item->average_cost;

                $pr->lines()->create([
                    'item_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => $quantity,
                    'unit_id' => $item->unit_id,
                    'estimated_unit_cost' => $cost,
                    'estimated_total' => round($quantity * $cost, 2),
                    'budget_line' => 'Materials',
                ]);

                $total += $quantity * $cost;
            }

            $pr->update(['estimated_total' => round($total, 2)]);
        }
    }

    private function seedPurchaseOrders(): void
    {
        $suppliers = Supplier::orderBy('id')->get();
        $items = Item::orderBy('id')->get();
        $warehouses = Warehouse::orderBy('id')->get();
        $projects = Project::orderBy('id')->get();
        $approvedRequests = PurchaseRequest::where('status', 'approved')->orderBy('id')->get()->values();

        $statuses = ['approved', 'approved', 'approved', 'approved', 'draft', 'approved'];

        foreach ($statuses as $index => $status) {
            $date = now()->subDays(30 - ($index * 4));
            $sourceRequest = $approvedRequests[$index] ?? null;

            $order = PurchaseOrder::create([
                'po_number' => PurchaseOrder::nextNumber($date->year),
                'purchase_request_id' => $sourceRequest?->id,
                'supplier_id' => $suppliers[$index % max($suppliers->count(), 1)]->id,
                'po_date' => $date->toDateString(),
                'expected_delivery_date' => $date->copy()->addDays(10)->toDateString(),
                'project_id' => $projects[$index % max($projects->count(), 1)]->id ?? null,
                'warehouse_id' => $warehouses[$index % max($warehouses->count(), 1)]->id,
                'vat_rate' => 15,
                'status' => $status,
                'approved_by' => $status === 'approved' ? $this->storeKeeperId : null,
                'approved_at' => $status === 'approved' ? $date->copy()->addDay() : null,
                'notes' => 'Seeded demo purchase order.',
            ]);

            $taxable = 0.0;
            $vat = 0.0;

            for ($line = 0; $line < 3; $line++) {
                $item = $items[($index * 4 + $line) % $items->count()];
                $quantity = 30 + ($line * 10);
                $price = (float) $item->average_cost;
                $lineTaxable = round($quantity * $price, 2);
                $lineVat = round($lineTaxable * 0.15, 2);

                $order->lines()->create([
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'received_quantity' => 0,
                    'unit_price' => $price,
                    'taxable_amount' => $lineTaxable,
                    'vat_rate' => 15,
                    'vat_amount' => $lineVat,
                    'total_amount' => round($lineTaxable + $lineVat, 2),
                ]);

                $taxable += $lineTaxable;
                $vat += $lineVat;
            }

            $order->update([
                'taxable_amount' => round($taxable, 2),
                'vat_amount' => round($vat, 2),
                'total_amount' => round($taxable + $vat, 2),
            ]);

            if ($sourceRequest && $status === 'approved') {
                $sourceRequest->update(['status' => 'converted']);
            }
        }
    }

    /**
     * Four posted receipts that really move stock and post to accounting,
     * plus one left in draft so the posting flow stays visible.
     */
    private function seedGoodsReceipts(): void
    {
        $orders = PurchaseOrder::where('status', 'approved')->with('lines.item')->orderBy('id')->get()->values();

        foreach ($orders->take(5)->values() as $index => $order) {
            $date = $order->po_date->copy()->addDays(8);

            $grn = GoodsReceipt::create([
                'grn_number' => GoodsReceipt::nextNumber($date->year),
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'warehouse_id' => $order->warehouse_id,
                'received_date' => $date->toDateString(),
                'received_by' => $this->storeKeeperId,
                'delivery_note_number' => 'DN-'.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT),
                'invoice_number' => 'SINV-'.str_pad((string) (3000 + $index), 4, '0', STR_PAD_LEFT),
                'vat_rate' => 15,
                'status' => 'draft',
                'notes' => 'Seeded demo goods receipt.',
            ]);

            $taxable = 0.0;
            $firstLineId = $order->lines->first()?->id;

            foreach ($order->lines as $line) {
                $received = (float) $line->quantity;
                // One line on the third receipt arrives partly rejected.
                $accepted = ($index === 2 && $line->id === $firstLineId)
                    ? round($received * 0.9, 3)
                    : $received;

                $cost = (float) $line->unit_price;

                $grn->lines()->create([
                    'item_id' => $line->item_id,
                    'ordered_quantity' => $line->quantity,
                    'received_quantity' => $received,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => round($received - $accepted, 3),
                    'unit_cost' => $cost,
                    'total_cost' => round($accepted * $cost, 2),
                ]);

                $taxable += $accepted * $cost;
            }

            $vat = round($taxable * 0.15, 2);

            $grn->update([
                'taxable_amount' => round($taxable, 2),
                'vat_amount' => $vat,
                'total_amount' => round($taxable + $vat, 2),
            ]);

            if ($index === 4) {
                continue;
            }

            $grn->refresh()->load('lines.item');

            foreach ($grn->lines as $line) {
                if (! $line->item || (float) $line->accepted_quantity <= 0) {
                    continue;
                }

                $this->stock->receive($line->item, $grn->warehouse_id, (float) $line->accepted_quantity, (float) $line->unit_cost, [
                    'movement_type' => 'grn',
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $grn->id,
                    'reference_number' => $grn->grn_number,
                    'movement_date' => $grn->received_date->toDateString(),
                    'created_by' => $this->storeKeeperId,
                ]);

                $order->lines()->where('item_id', $line->item_id)->first()?->increment('received_quantity', (float) $line->accepted_quantity);
            }

            $entry = $this->posting->postGoodsReceipt($grn, $this->storeKeeperId);

            $grn->update([
                'status' => 'posted',
                'stock_updated' => true,
                'accounting_posted' => (bool) $entry,
                'journal_entry_id' => $entry?->id,
            ]);

            $order->refresh()->refreshReceiptStatus();
        }
    }

    private function seedStockIssues(): void
    {
        $warehouses = Warehouse::orderBy('id')->get();
        $projects = Project::orderBy('id')->get();
        $sites = Site::orderBy('id')->get();

        for ($index = 0; $index < 10; $index++) {
            $warehouse = $warehouses[$index % max($warehouses->count(), 1)];
            $date = now()->subDays(20 - $index);

            $stocked = WarehouseStock::with('item')
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity', '>', 5)
                ->orderByDesc('quantity')
                ->limit(2)
                ->get();

            if ($stocked->isEmpty()) {
                continue;
            }

            $issue = StockIssue::create([
                'issue_number' => StockIssue::nextNumber($date->year),
                'warehouse_id' => $warehouse->id,
                'project_id' => $warehouse->project_id ?? ($projects[$index % max($projects->count(), 1)]->id ?? null),
                'site_id' => $warehouse->site_id ?? ($sites[$index % max($sites->count(), 1)]->id ?? null),
                'requested_by' => $this->supervisorId,
                'issue_date' => $date->toDateString(),
                'purpose' => 'Material issued for site works.',
                'status' => 'draft',
            ]);

            foreach ($stocked as $row) {
                $issue->lines()->create([
                    'item_id' => $row->item_id,
                    'quantity' => round((float) $row->quantity * 0.1, 3),
                    'unit_cost' => 0,
                    'total_cost' => 0,
                ]);
            }

            // The last two stay in draft so the posting flow stays visible.
            if ($index >= 8) {
                continue;
            }

            $issue->refresh()->load('lines.item');
            $total = 0.0;

            foreach ($issue->lines as $line) {
                $entry = $this->stock->issue($line->item, $issue->warehouse_id, (float) $line->quantity, [
                    'movement_type' => 'issue',
                    'reference_type' => StockIssue::class,
                    'reference_id' => $issue->id,
                    'reference_number' => $issue->issue_number,
                    'movement_date' => $issue->issue_date->toDateString(),
                    'project_id' => $issue->project_id,
                    'site_id' => $issue->site_id,
                    'created_by' => $this->storeKeeperId,
                ]);

                $line->update([
                    'unit_cost' => $entry->unit_cost,
                    'total_cost' => round((float) $line->quantity * (float) $entry->unit_cost, 2),
                ]);

                $total += (float) $line->total_cost;
            }

            $issue->update([
                'total_cost' => round($total, 2),
                'status' => 'posted',
                'approved_by' => $this->storeKeeperId,
            ]);

            $journal = $this->posting->postStockIssue($issue->fresh('lines.item'), $this->storeKeeperId);

            $issue->update([
                'accounting_posted' => (bool) $journal,
                'journal_entry_id' => $journal?->id,
            ]);
        }
    }

    private function seedStockTransfers(): void
    {
        $warehouses = Warehouse::orderBy('id')->get();

        if ($warehouses->count() < 2) {
            return;
        }

        for ($index = 0; $index < 6; $index++) {
            $from = $warehouses[$index % $warehouses->count()];
            $to = $warehouses[($index + 1) % $warehouses->count()];
            $date = now()->subDays(15 - $index);

            $stocked = WarehouseStock::with('item')
                ->where('warehouse_id', $from->id)
                ->where('quantity', '>', 10)
                ->orderByDesc('quantity')
                ->limit(2)
                ->get();

            if ($stocked->isEmpty()) {
                continue;
            }

            $transfer = StockTransfer::create([
                'transfer_number' => StockTransfer::nextNumber($date->year),
                'transfer_date' => $date->toDateString(),
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'requested_by' => $this->supervisorId,
                'status' => 'draft',
                'notes' => 'Seeded demo stock transfer.',
            ]);

            foreach ($stocked as $row) {
                $transfer->lines()->create([
                    'item_id' => $row->item_id,
                    'quantity' => round((float) $row->quantity * 0.05, 3),
                    'unit_cost' => 0,
                    'total_cost' => 0,
                ]);
            }

            // Two stay draft, two stop at dispatched, two complete.
            if ($index < 2) {
                continue;
            }

            $transfer->refresh()->load('lines.item');
            $total = 0.0;

            foreach ($transfer->lines as $line) {
                $entry = $this->stock->issue($line->item, $from->id, (float) $line->quantity, [
                    'movement_type' => 'transfer_out',
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'reference_number' => $transfer->transfer_number,
                    'movement_date' => $transfer->transfer_date->toDateString(),
                    'created_by' => $this->storeKeeperId,
                ]);

                $line->update([
                    'unit_cost' => $entry->unit_cost,
                    'total_cost' => round((float) $line->quantity * (float) $entry->unit_cost, 2),
                ]);

                $total += (float) $line->total_cost;
            }

            $transfer->update([
                'status' => 'dispatched',
                'dispatched_by' => $this->storeKeeperId,
                'dispatch_date' => $date->copy()->addDay()->toDateString(),
                'total_cost' => round($total, 2),
            ]);

            if ($index < 4) {
                continue;
            }

            foreach ($transfer->fresh('lines.item')->lines as $line) {
                $this->stock->receive($line->item, $to->id, (float) $line->quantity, (float) $line->unit_cost, [
                    'movement_type' => 'transfer_in',
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'reference_number' => $transfer->transfer_number,
                    'movement_date' => $date->copy()->addDays(2)->toDateString(),
                    'created_by' => $this->storeKeeperId,
                ]);
            }

            $transfer->update([
                'status' => 'received',
                'received_by' => $this->storeKeeperId,
                'receive_date' => $date->copy()->addDays(2)->toDateString(),
            ]);
        }
    }

    /**
     * One draft, one approved-not-posted, three posted (two losses, one gain)
     * so every adjustment state and both accounting directions are visible.
     */
    private function seedStockAdjustments(): void
    {
        $rows = WarehouseStock::with('item')
            ->where('quantity', '>', 20)
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();

        foreach ($rows as $index => $row) {
            $date = now()->subDays(10 - $index);
            $current = (float) $row->quantity;
            $counted = $index < 3 ? round($current * 0.97, 3) : round($current * 1.02, 3);
            $difference = round($counted - $current, 3);

            if ($difference == 0.0) {
                continue;
            }

            $adjustment = StockAdjustment::create([
                'adjustment_number' => StockAdjustment::nextNumber($date->year),
                'warehouse_id' => $row->warehouse_id,
                'item_id' => $row->item_id,
                'adjustment_date' => $date->toDateString(),
                'current_quantity' => $current,
                'adjusted_quantity' => $counted,
                'difference_quantity' => $difference,
                'unit_cost' => (float) $row->average_cost,
                'adjustment_value' => round(abs($difference) * (float) $row->average_cost, 2),
                'adjustment_type' => $difference < 0 ? 'decrease' : 'increase',
                'reason' => $difference < 0 ? 'Physical count variance and site wastage.' : 'Physical count found extra stock.',
                'status' => 'draft',
            ]);

            if ($index === 0) {
                continue;
            }

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $this->storeKeeperId,
                'approved_at' => $date->copy()->addHours(4),
            ]);

            if ($index === 1) {
                continue;
            }

            $entry = $this->stock->adjust($adjustment->item, $adjustment->warehouse_id, $counted, [
                'movement_type' => 'adjustment',
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
                'reference_number' => $adjustment->adjustment_number,
                'movement_date' => $adjustment->adjustment_date->toDateString(),
                'created_by' => $this->storeKeeperId,
            ]);

            $adjustment->update([
                'unit_cost' => $entry->unit_cost,
                'adjustment_value' => round(abs($difference) * (float) $entry->unit_cost, 2),
                'status' => 'posted',
            ]);

            $journal = $this->posting->postStockAdjustment($adjustment->fresh('item'), $this->storeKeeperId);

            $adjustment->update([
                'accounting_posted' => (bool) $journal,
                'journal_entry_id' => $journal?->id,
            ]);
        }
    }
}
