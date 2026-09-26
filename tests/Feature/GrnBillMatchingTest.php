<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * F04: a goods receipt accrues Dr Inventory / Cr Goods Received Not Invoiced;
 * the matched supplier bill clears GRNI and records input VAT and the supplier
 * payable once. Receipts against an order cannot over-deliver, and a received
 * quantity cannot be invoiced twice.
 */
class GrnBillMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Item $item;

    private int $billSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->supplier = Supplier::orderBy('id')->firstOrFail();
        $this->warehouse = Warehouse::orderBy('id')->firstOrFail();
        $this->item = $this->newItem('ITM-F04');
    }

    // ---------------------------------------------------------------- helpers

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function newItem(string $code): Item
    {
        return Item::create([
            'item_code' => $code, 'name' => $code.' cement bag', 'unit_id' => Unit::first()?->id, 'valuation_method' => 'average',
            'inventory_account_id' => $this->account(PostingService::INVENTORY_ASSET)->id,
            'expense_account_id' => $this->account(PostingService::MATERIAL_EXPENSE)->id, 'vat_applicable' => true, 'status' => 'active',
        ]);
    }

    private function approvedOrder(float $qty = 10, float $price = 100, ?Supplier $supplier = null, ?Item $item = null, string $status = 'approved'): PurchaseOrder
    {
        $supplier ??= $this->supplier;
        $item ??= $this->item;
        $money = PurchaseOrderLine::calculate($qty, $price, 0, 15);

        $order = PurchaseOrder::create([
            'po_number' => PurchaseOrder::nextNumber(2026), 'supplier_id' => $supplier->id, 'po_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id, 'taxable_amount' => $money['taxable_amount'], 'vat_rate' => 15,
            'vat_amount' => $money['vat_amount'], 'total_amount' => $money['total_amount'], 'status' => $status,
            'approved_by' => $status === 'draft' ? null : $this->admin()->id, 'approved_at' => $status === 'draft' ? null : now(),
        ]);
        $order->lines()->create([
            'item_id' => $item->id, 'quantity' => $qty, 'unit_price' => $price, 'taxable_amount' => $money['taxable_amount'],
            'vat_rate' => 15, 'vat_amount' => $money['vat_amount'], 'total_amount' => $money['total_amount'],
        ]);

        return $order->fresh('lines');
    }

    private function storeReceipt(?PurchaseOrder $order, float $received, float $cost = 100, array $overrides = [], ?float $accepted = null): TestResponse
    {
        $line = ['item_id' => ($overrides['item'] ?? $this->item)->id, 'received_quantity' => $received, 'accepted_quantity' => $accepted ?? $received, 'unit_cost' => $cost];
        if ($order && ! array_key_exists('purchase_order_line_id', $overrides)) {
            $line['purchase_order_line_id'] = $order->lines->first()->id;
        } elseif (array_key_exists('purchase_order_line_id', $overrides)) {
            $line['purchase_order_line_id'] = $overrides['purchase_order_line_id'];
        }

        return $this->actingAs($overrides['as'] ?? $this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'purchase_order_id' => $order?->id, 'supplier_id' => ($overrides['supplier'] ?? $this->supplier)->id,
            'warehouse_id' => ($overrides['warehouse'] ?? $this->warehouse)->id,
            'received_date' => $overrides['date'] ?? now()->toDateString(), 'delivery_note_number' => $overrides['dn'] ?? 'DN-'.uniqid(),
            'vat_rate' => 15, 'lines' => [$line],
        ]);
    }

    private function postedReceipt(?PurchaseOrder $order, float $received, float $cost = 100, array $overrides = []): GoodsReceipt
    {
        $dn = 'DN-'.uniqid();
        $this->storeReceipt($order, $received, $cost, $overrides + ['dn' => $dn])->assertSessionHasNoErrors();
        $grn = GoodsReceipt::withoutGlobalScopes()->where('delivery_note_number', $dn)->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))->assertSessionHasNoErrors();

        return $grn->fresh(['lines', 'journalEntry']);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function storeBill(array $lines, array $extra = [], ?User $as = null): TestResponse
    {
        $this->billSeq++;

        return $this->actingAs($as ?? $this->admin())->post(route('admin.accounting.accounts-payable.store'), $extra + [
            'supplier_id' => $this->supplier->id, 'bill_number' => 'BILL-F04-'.$this->billSeq, 'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15, 'lines' => $lines,
        ]);
    }

    private function matched(GoodsReceipt $grn, float $qty, ?float $price = null, string $description = 'Received goods'): array
    {
        return ['description' => $description, 'goods_receipt_line_id' => $grn->lines->first()->id, 'matched_quantity' => $qty, 'quantity' => $qty, 'unit_price' => $price ?? (float) $grn->lines->first()->unit_cost];
    }

    private function lastBill(): SupplierBill
    {
        return SupplierBill::withoutGlobalScopes()->where('bill_number', 'BILL-F04-'.$this->billSeq)->firstOrFail();
    }

    private function approve(SupplierBill $bill): TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill));
    }

    /** Journal lines as [code => [debit, credit]]. */
    private function linesOf(JournalEntry $entry): array
    {
        $out = [];
        foreach ($entry->lines()->with('account')->get() as $line) {
            $code = $line->account->account_code;
            $out[$code] = [round(($out[$code][0] ?? 0) + (float) $line->debit, 2), round(($out[$code][1] ?? 0) + (float) $line->credit, 2)];
        }
        ksort($out);

        return $out;
    }

    private function vatRowsFor(string $module, int $id): int
    {
        return VatTransaction::where('source_module', $module)->where('source_id', $id)->count();
    }

    // ------------------------------------------------------------------ tests

    public function test_receipt_against_an_order_accrues_inventory_to_grni_without_vat_or_payable(): void
    {
        $order = $this->approvedOrder(10, 100);
        $grn = $this->postedReceipt($order, 6);   // partial receipt

        $this->assertSame('posted', $grn->status);
        $this->assertTrue((bool) $grn->accounting_posted);
        $this->assertSame(['1400' => [600.0, 0.0], '2150' => [0.0, 600.0]], $this->linesOf($grn->journalEntry));
        $this->assertSame(0, $this->vatRowsFor('Goods Receipt', $grn->id), 'no VAT transaction on a receipt');
        $this->assertArrayNotHasKey('2100', $this->linesOf($grn->journalEntry), 'no supplier payable on a receipt');
        $this->assertSame(1, StockLedgerEntry::where('reference_number', $grn->grn_number)->count());

        $order->refresh();
        $this->assertSame('partially_received', $order->status);
        $this->assertSame(6.0, (float) $order->lines->first()->received_quantity);
        $this->assertSame($order->lines->first()->id, $grn->lines->first()->purchase_order_line_id);
        $this->assertSame(6.0, $grn->lines->first()->uninvoicedQuantity());
    }

    public function test_matched_bill_clears_grni_and_records_input_vat_and_payable_once(): void
    {
        $grn = $this->postedReceipt($this->approvedOrder(10, 100), 6);

        $this->storeBill([$this->matched($grn, 6)])->assertSessionHasNoErrors();
        $bill = $this->lastBill();
        $this->assertSame(1, $bill->grnMatches()->count());
        $this->assertNull($bill->grnMatches()->first()->committed_at, 'a draft bill does not consume the receipt');
        $this->assertSame(6.0, $grn->lines->first()->fresh()->uninvoicedQuantity());

        $this->approve($bill)->assertSessionHasNoErrors();
        $bill->refresh();

        $this->assertSame('unpaid', $bill->status);
        $this->assertSame(690.0, (float) $bill->balance_amount);
        $this->assertSame(['1300' => [90.0, 0.0], '2100' => [0.0, 690.0], '2150' => [600.0, 0.0]], $this->linesOf($bill->journalEntry));
        $this->assertSame(1, $this->vatRowsFor('Supplier Bill', $bill->id), 'input VAT once');
        $this->assertSame(0, $this->vatRowsFor('Goods Receipt', $grn->id));
        $this->assertSame(6.0, (float) $grn->lines->first()->fresh()->invoiced_quantity);
        $this->assertNotNull($bill->grnMatches()->first()->committed_at);

        // GRNI nets to zero and accounts payable carries the purchase exactly once.
        $grni = JournalEntry::whereIn('id', [$grn->journal_entry_id, $bill->journal_entry_id])->get()
            ->sum(fn ($e) => ($this->linesOf($e)['2150'][0] ?? 0) - ($this->linesOf($e)['2150'][1] ?? 0));
        $this->assertSame(0.0, round($grni, 2));
        $this->assertArrayNotHasKey('5200', $this->linesOf($bill->journalEntry), 'goods already in inventory are not expensed again');
    }

    public function test_partial_invoices_and_several_receipts_on_one_bill_never_invoice_a_quantity_twice(): void
    {
        $grnA = $this->postedReceipt($this->approvedOrder(10, 100), 10);
        $grnB = $this->postedReceipt($this->approvedOrder(5, 80), 5, 80);

        // Partial invoice: 4 of the 10 received on A.
        $this->storeBill([$this->matched($grnA, 4)])->assertSessionHasNoErrors();
        $this->approve($this->lastBill())->assertSessionHasNoErrors();
        $this->assertSame(['1300' => [60.0, 0.0], '2100' => [0.0, 460.0], '2150' => [400.0, 0.0]], $this->linesOf($this->lastBill()->journalEntry));
        $this->assertSame(6.0, $grnA->lines->first()->fresh()->uninvoicedQuantity());

        // One invoice covering the rest of A and all of B.
        $this->storeBill([$this->matched($grnA, 6, null, 'Rest of A'), $this->matched($grnB, 5, null, 'All of B')])->assertSessionHasNoErrors();
        $bill = $this->lastBill();
        $this->assertSame(2, $bill->grnMatches()->count());
        $this->approve($bill)->assertSessionHasNoErrors();
        $this->assertSame(['1300' => [150.0, 0.0], '2100' => [0.0, 1150.0], '2150' => [1000.0, 0.0]], $this->linesOf($bill->fresh()->journalEntry));
        $this->assertSame(0.0, $grnA->lines->first()->fresh()->uninvoicedQuantity());
        $this->assertSame(0.0, $grnB->lines->first()->fresh()->uninvoicedQuantity());

        // Nothing is left to invoice on either receipt.
        $this->storeBill([$this->matched($grnA, 1)])->assertSessionHasErrors('matching');
        $this->assertSame(0, SupplierBill::where('bill_number', 'BILL-F04-'.$this->billSeq)->count(), 'the refused bill was not saved');

        // Asking for more than is left, split over two lines of the same bill, is refused too.
        $grnC = $this->postedReceipt($this->approvedOrder(10, 100), 10);
        $this->storeBill([$this->matched($grnC, 7, null, 'part one'), $this->matched($grnC, 4, null, 'part two')])->assertSessionHasErrors('matching');
    }

    public function test_over_receipt_supplier_mismatch_closed_order_and_foreign_item_are_rejected(): void
    {
        $order = $this->approvedOrder(10, 100);

        $this->storeReceipt($order, 11)->assertSessionHasErrors('lines');
        $other = Supplier::create(['name' => 'Other Vendor', 'code' => 'SUP-F04-X', 'status' => 'active']);
        $this->storeReceipt($order, 5, 100, ['supplier' => $other])->assertSessionHasErrors('lines');
        $this->storeReceipt($order, 5, 100, ['item' => $this->newItem('ITM-F04-B')])->assertSessionHasErrors('lines');
        $this->storeReceipt($this->approvedOrder(10, 100, null, null, 'draft'), 5)->assertSessionHasErrors('lines');
        $this->storeReceipt($this->approvedOrder(10, 100, null, null, 'received'), 5)->assertSessionHasErrors('lines');
        $this->assertSame(0, GoodsReceipt::where('purchase_order_id', $order->id)->count());

        // Within the outstanding quantity, split over two receipts.
        $this->postedReceipt($order, 6);
        $this->storeReceipt($order, 5)->assertSessionHasErrors('lines');      // 6 + 5 > 10
        $this->postedReceipt($order, 4);
        $this->assertSame('received', $order->fresh()->status);
    }

    public function test_two_receipts_saved_against_the_same_outstanding_quantity_cannot_both_post(): void
    {
        $order = $this->approvedOrder(10, 100);

        // Both drafts are valid when saved; only the first can post (stale/concurrent receipt).
        $this->storeReceipt($order, 10, 100, ['dn' => 'DN-FIRST'])->assertSessionHasNoErrors();
        $this->storeReceipt($order, 10, 100, ['dn' => 'DN-SECOND'])->assertSessionHasNoErrors();
        $first = GoodsReceipt::where('delivery_note_number', 'DN-FIRST')->firstOrFail();
        $second = GoodsReceipt::where('delivery_note_number', 'DN-SECOND')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $first))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $second))->assertSessionHasErrors('grn');

        $this->assertSame('draft', $second->fresh()->status);
        $this->assertNull($second->fresh()->journal_entry_id);
        $this->assertSame(0, StockLedgerEntry::where('reference_number', $second->grn_number)->count(), 'nothing moved for the refused receipt');
        $this->assertSame(10.0, (float) $order->fresh()->lines->first()->received_quantity);
    }

    public function test_direct_service_bill_still_posts_expense_vat_and_payable(): void
    {
        $this->storeBill([['description' => 'Crane hire', 'quantity' => 1, 'unit_price' => 1000]])->assertSessionHasNoErrors();
        $bill = $this->lastBill();
        $this->assertSame(0, $bill->grnMatches()->count());

        $this->approve($bill)->assertSessionHasNoErrors();
        $this->assertSame(['1300' => [150.0, 0.0], '2100' => [0.0, 1150.0], '5200' => [1000.0, 0.0]], $this->linesOf($bill->fresh()->journalEntry));
        $this->assertSame(1, $this->vatRowsFor('Supplier Bill', $bill->id));
    }

    public function test_mixed_bill_with_received_goods_and_a_service_line(): void
    {
        $grn = $this->postedReceipt($this->approvedOrder(10, 100), 6);

        $this->storeBill([
            $this->matched($grn, 6, 100, 'Cement delivered'),
            ['description' => 'Delivery charge', 'quantity' => 1, 'unit_price' => 200],
        ])->assertSessionHasNoErrors();
        $bill = $this->lastBill();
        $this->approve($bill)->assertSessionHasNoErrors();

        $this->assertSame(['1300' => [120.0, 0.0], '2100' => [0.0, 920.0], '2150' => [600.0, 0.0], '5200' => [200.0, 0.0]], $this->linesOf($bill->fresh()->journalEntry));
        $this->assertSame(1, $this->vatRowsFor('Supplier Bill', $bill->id), 'VAT recorded once for the whole bill');
        $this->assertSame(120.0, (float) VatTransaction::where('source_module', 'Supplier Bill')->where('source_id', $bill->id)->value('vat_amount'));
    }

    public function test_a_price_difference_between_the_invoice_and_the_receipt_is_a_variance_not_a_second_asset(): void
    {
        $grn = $this->postedReceipt($this->approvedOrder(10, 100), 6);

        $this->storeBill([$this->matched($grn, 6, 110)])->assertSessionHasNoErrors();   // invoiced at 110, received at 100
        $bill = $this->lastBill();
        $this->approve($bill)->assertSessionHasNoErrors();

        $this->assertSame(['1300' => [99.0, 0.0], '2100' => [0.0, 759.0], '2150' => [600.0, 0.0], '5200' => [60.0, 0.0]], $this->linesOf($bill->fresh()->journalEntry));
        $this->assertSame(600.0, (float) $bill->grnMatches()->first()->matched_taxable_amount, 'the accrual cleared is the receipt value');
    }

    public function test_a_stale_duplicate_match_is_refused_at_approval_and_rolls_everything_back(): void
    {
        $grn = $this->postedReceipt($this->approvedOrder(10, 100), 6);

        // Two clerks draft a bill for the same delivery while it is still uninvoiced.
        $this->storeBill([$this->matched($grn, 6)])->assertSessionHasNoErrors();
        $first = $this->lastBill();
        $this->storeBill([$this->matched($grn, 6)])->assertSessionHasNoErrors();
        $second = $this->lastBill();
        $journals = JournalEntry::count();
        $vatRows = VatTransaction::count();

        $this->approve($first)->assertSessionHasNoErrors();
        $this->approve($second)->assertSessionHasErrors('matching');

        $second->refresh();
        $this->assertSame('draft', $second->status);
        $this->assertNull($second->journal_entry_id);
        $this->assertNull($second->grnMatches()->first()->committed_at);
        $this->assertSame($journals + 1, JournalEntry::count(), 'only the first approval posted');
        $this->assertSame($vatRows + 1, VatTransaction::count());
        $this->assertSame(6.0, (float) $grn->lines->first()->fresh()->invoiced_quantity, 'the receipt was consumed once');
    }

    public function test_matching_an_unposted_receipt_or_another_suppliers_receipt_is_rejected(): void
    {
        $this->storeReceipt($this->approvedOrder(10, 100), 5, 100, ['dn' => 'DN-DRAFT'])->assertSessionHasNoErrors();
        $draft = GoodsReceipt::where('delivery_note_number', 'DN-DRAFT')->firstOrFail();
        $this->storeBill([$this->matched($draft->load('lines'), 5)])->assertSessionHasErrors('matching');

        $other = Supplier::create(['name' => 'Other Vendor', 'code' => 'SUP-F04-Y', 'status' => 'active']);
        $theirs = $this->postedReceipt($this->approvedOrder(5, 50, $other), 5, 50, ['supplier' => $other]);
        $this->storeBill([$this->matched($theirs, 5)])->assertSessionHasErrors('matching');
        $this->assertSame(5.0, $theirs->lines->first()->fresh()->uninvoicedQuantity());
    }

    public function test_a_project_scoped_user_cannot_match_a_receipt_outside_their_project(): void
    {
        $mine = Project::create(['name' => 'Mine', 'code' => 'PRJ-F04-A', 'status' => 'active']);
        $theirs = Project::create(['name' => 'Theirs', 'code' => 'PRJ-F04-B', 'status' => 'active']);
        $myWarehouse = Warehouse::create(['name' => 'My store', 'code' => 'WH-F04-A', 'project_id' => $mine->id, 'status' => 'active']);
        $theirWarehouse = Warehouse::create(['name' => 'Their store', 'code' => 'WH-F04-B', 'project_id' => $theirs->id, 'status' => 'active']);

        $role = Role::create(['name' => 'Project AP clerk', 'code' => 'PROJECT_AP_F04', 'level' => 3, 'access_scope' => 'Project Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where(fn ($q) => $q->where('module', 'Accounts Payable')->whereIn('action', ['view', 'create', 'edit', 'approve'])
            ->orWhere(fn ($q) => $q->where('module', 'Goods Receipts')->where('action', 'view')))->pluck('id'));
        $clerk = User::create(['name' => 'Project Clerk', 'email' => 'project-clerk@example.test', 'username' => 'project.clerk', 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $mine->id]);
        $clerk->roles()->attach($role, ['is_primary' => true]);
        $clerk = $clerk->fresh();

        $visible = $this->postedReceipt(null, 3, 100, ['warehouse' => $myWarehouse]);
        $hidden = $this->postedReceipt(null, 3, 100, ['warehouse' => $theirWarehouse]);

        $this->storeBill([$this->matched($hidden, 3)], ['project_id' => $mine->id], $clerk)->assertSessionHasErrors('matching');
        $this->assertSame(3.0, $hidden->lines->first()->fresh()->uninvoicedQuantity());

        $this->storeBill([$this->matched($visible, 3)], ['project_id' => $mine->id], $clerk)->assertSessionHasNoErrors();
        $bill = $this->lastBill();
        $this->actingAs($clerk)->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->assertSame(3.0, (float) $visible->lines->first()->fresh()->invoiced_quantity);
    }

    public function test_reopening_a_matched_bill_releases_the_received_quantity_and_reverses_the_clearing(): void
    {
        $grn = $this->postedReceipt($this->approvedOrder(10, 100), 6);
        $this->storeBill([$this->matched($grn, 6)])->assertSessionHasNoErrors();
        $bill = $this->lastBill();
        $this->approve($bill)->assertSessionHasNoErrors();
        $original = $bill->fresh()->journalEntry;

        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'Wrong price'])->assertSessionHasNoErrors();

        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertSame(0.0, (float) $grn->lines->first()->fresh()->invoiced_quantity, 'quantity released');
        $this->assertNull($bill->grnMatches()->first()->committed_at);
        $reversal = JournalEntry::where('source_module', 'Manual')->where('source_id', $original->id)->firstOrFail();
        $this->assertSame(['1300' => [0.0, 90.0], '2100' => [690.0, 0.0], '2150' => [0.0, 600.0]], $this->linesOf($reversal));
        $this->assertSame(0, $this->vatRowsFor('Supplier Bill', $bill->id));

        // Corrected and approved again: the receipt is consumed exactly once more.
        $this->approve($bill->fresh())->assertSessionHasNoErrors();
        $this->assertSame(6.0, (float) $grn->lines->first()->fresh()->invoiced_quantity);
        $this->assertSame(1, $this->vatRowsFor('Supplier Bill', $bill->id));
    }

    public function test_receipt_in_a_sealed_vat_period_posts_but_its_bill_dated_there_is_refused(): void
    {
        $sealed = VatPeriod::where('status', 'finalized')->orderBy('start_date')->firstOrFail();
        $sealedDate = $sealed->start_date->copy()->addDays(3)->toDateString();

        $grn = $this->postedReceipt($this->approvedOrder(10, 100), 6, 100, ['date' => $sealedDate]);
        $this->assertSame('posted', $grn->status, 'a receipt carries no VAT, so the sealed period does not block it');

        $this->storeBill([$this->matched($grn, 6)], ['bill_date' => $sealedDate, 'due_date' => now()->toDateString()])->assertSessionHasNoErrors();
        $bill = $this->lastBill();
        $this->approve($bill)->assertSessionHasErrors('vat');

        $bill->refresh();
        $this->assertSame('draft', $bill->status);
        $this->assertNull($bill->journal_entry_id);
        $this->assertSame(0.0, (float) $grn->lines->first()->fresh()->invoiced_quantity, 'the refused approval consumed nothing');
    }

    public function test_bill_form_offers_only_the_suppliers_uninvoiced_receipt_lines_and_prefills_from_a_receipt(): void
    {
        $grn = $this->postedReceipt($this->approvedOrder(10, 100), 6);

        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-payable.create', ['goods_receipt' => $grn->id]))->assertOk();
        $page->assertSee($grn->grn_number);
        $page->assertSee('data-supplier="'.$this->supplier->id.'"', false);
        $this->assertSame($grn->lines->first()->id, $page->viewData('grnPrefill')[0]['goods_receipt_line_id']);
        $this->assertSame(6.0, $page->viewData('grnPrefill')[0]['matched_quantity']);

        $this->actingAs($this->admin())->get(route('admin.inventory.goods-receipts.show', $grn))->assertOk()->assertSee('Create Supplier Bill');
    }
}
