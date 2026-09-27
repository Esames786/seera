<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillGrnMatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Procure-to-pay connected document workspace: the Purchase Order View shows
 * its source request, supplier, receipts, F04 billing state, journals and
 * activity, each section behind its own permission and access scope; the
 * order in the URL is authoritative; receiving, billing and payment stay on
 * their own pages and return to the context they were started from.
 */
class PurchaseOrderWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;

    protected Supplier $otherSupplier;

    protected Project $mine;

    protected Project $theirs;

    protected Warehouse $myStore;

    protected Warehouse $theirStore;

    protected Item $item;

    private int $billSeq = 0;

    private int $userSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->supplier = Supplier::orderBy('id')->firstOrFail();
        $this->otherSupplier = Supplier::orderBy('id')->skip(1)->first() ?? Supplier::create(['name' => 'Other Trading', 'code' => 'SUP-OTHER', 'status' => 'active']);
        $this->mine = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-P2P-A', 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-P2P-B', 'status' => 'active']);
        $this->myStore = Warehouse::create(['name' => 'Riyadh Site Warehouse', 'code' => 'WH-P2P-A', 'project_id' => $this->mine->id, 'status' => 'active']);
        $this->theirStore = Warehouse::create(['name' => 'Jeddah Site Warehouse', 'code' => 'WH-P2P-B', 'project_id' => $this->theirs->id, 'status' => 'active']);
        $this->item = Item::create([
            'item_code' => 'STL-16', 'name' => 'Reinforcement Steel 16mm', 'unit_id' => Unit::first()?->id, 'valuation_method' => 'average',
            'inventory_account_id' => ChartOfAccount::where('account_code', PostingService::INVENTORY_ASSET)->value('id'),
            'expense_account_id' => ChartOfAccount::where('account_code', PostingService::MATERIAL_EXPENSE)->value('id'), 'vat_applicable' => true, 'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------- helpers

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants module => actions */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'a'): User
    {
        $suffix .= '-'.++$this->userSeq;
        $role = Role::create(['name' => 'P2P role '.$suffix, 'code' => 'P2P_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'P2P '.$suffix, 'email' => 'p2p-'.$suffix.'@example.test', 'username' => 'p2p.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    /** A buyer who may read the whole procure-to-pay chain but change nothing. */
    protected function reader(?Project $project = null, string $suffix = 'r'): User
    {
        return $this->user([
            'Purchase Requests' => ['view'], 'Purchase Orders' => ['view'], 'Goods Receipts' => ['view'], 'Accounts Payable' => ['view'],
            'Journal Entries' => ['view'], 'Suppliers' => ['view'], 'Projects' => ['view'], 'Warehouses' => ['view'], 'Activity Logs' => ['view'],
        ], $project, $suffix);
    }

    protected function request(Project $project, Warehouse $warehouse, float $qty = 10000): PurchaseRequest
    {
        $pr = PurchaseRequest::create([
            'pr_number' => PurchaseRequest::nextNumber(2026), 'request_date' => now()->toDateString(), 'requested_by' => $this->admin()->id,
            'project_id' => $project->id, 'warehouse_id' => $warehouse->id, 'required_date' => now()->addDays(7)->toDateString(),
            'priority' => 'high', 'reason' => 'Slab reinforcement', 'estimated_total' => $qty * 3, 'status' => 'approved',
            'approved_by' => $this->admin()->id, 'approved_at' => now(),
        ]);
        $pr->lines()->create(['item_id' => $this->item->id, 'quantity' => $qty, 'unit_id' => $this->item->unit_id, 'estimated_unit_cost' => 3, 'estimated_total' => $qty * 3]);

        return $pr->fresh();
    }

    protected function order(Project $project, Warehouse $warehouse, float $qty = 10000, float $price = 3, ?PurchaseRequest $pr = null, ?Supplier $supplier = null, string $status = 'approved'): PurchaseOrder
    {
        $money = PurchaseOrderLine::calculate($qty, $price, 0, 15);
        $order = PurchaseOrder::create([
            'po_number' => PurchaseOrder::nextNumber(2026), 'purchase_request_id' => $pr?->id, 'supplier_id' => ($supplier ?? $this->supplier)->id, 'po_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(10)->toDateString(), 'project_id' => $project->id, 'warehouse_id' => $warehouse->id,
            'taxable_amount' => $money['taxable_amount'], 'vat_rate' => 15, 'vat_amount' => $money['vat_amount'], 'total_amount' => $money['total_amount'],
            'status' => $status, 'approved_by' => $status === 'draft' ? null : $this->admin()->id, 'approved_at' => $status === 'draft' ? null : now(),
        ]);
        $order->lines()->create(['item_id' => $this->item->id, 'quantity' => $qty, 'unit_price' => $price, 'taxable_amount' => $money['taxable_amount'], 'vat_rate' => 15, 'vat_amount' => $money['vat_amount'], 'total_amount' => $money['total_amount']]);

        return $order->fresh('lines');
    }

    protected function postedReceipt(PurchaseOrder $order, Warehouse $warehouse, float $qty, bool $post = true): GoodsReceipt
    {
        $dn = 'DN-'.uniqid();
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id, 'warehouse_id' => $warehouse->id,
            'received_date' => now()->toDateString(), 'delivery_note_number' => $dn, 'vat_rate' => 15,
            'lines' => [['item_id' => $this->item->id, 'purchase_order_line_id' => $order->lines->first()->id, 'received_quantity' => $qty, 'accepted_quantity' => $qty, 'unit_cost' => (float) $order->lines->first()->unit_price]],
        ])->assertSessionHasNoErrors();
        $grn = GoodsReceipt::where('delivery_note_number', $dn)->firstOrFail();
        if ($post) {
            $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn))->assertSessionHasNoErrors();
        }

        return $grn->fresh('lines');
    }

    /** An approved bill that invoices part of a posted receipt (F04 match). */
    protected function matchedBill(GoodsReceipt $grn, float $qty, ?string $number = null): SupplierBill
    {
        $number ??= 'GST-INV-'.(1045 + $this->billSeq++);
        $line = $grn->lines->first();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => $grn->supplier_id, 'bill_number' => $number, 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'project_id' => $grn->warehouse->project_id, 'vat_rate' => 15,
            'lines' => [['description' => 'Reinforcement Steel 16mm', 'goods_receipt_line_id' => $line->id, 'matched_quantity' => $qty, 'quantity' => $qty, 'unit_price' => (float) $line->unit_cost]],
        ])->assertSessionHasNoErrors();
        $bill = SupplierBill::where('bill_number', $number)->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();

        return $bill->fresh();
    }

    protected function panel(User $user, PurchaseOrder $order, string $panel, array $query = []): TestResponse
    {
        return $this->actingAs($user)->getJson(route('admin.inventory.purchase-orders.workspace.panel', [$order, $panel] + $query));
    }

    protected function open(User $user, PurchaseOrder $order): TestResponse
    {
        return $this->actingAs($user)->get(route('admin.inventory.purchase-orders.show', $order));
    }

    // ------------------------------------------------------------------ tests

    public function test_purchase_order_view_loads_every_section_for_a_full_reader(): void
    {
        $pr = $this->request($this->mine, $this->myStore);
        $order = $this->order($this->mine, $this->myStore, pr: $pr);

        $page = $this->open($this->reader(), $order)->assertOk();

        foreach (['Overview', 'Order Lines', 'Source Purchase Request', 'Supplier &amp; Commercial', 'Quotations', 'Goods Receipts', 'Billing &amp; GRN Matching', 'Accounting', 'Activity'] as $section) {
            $page->assertSee($section, false);
        }
        foreach (['id="overview"', 'id="lines"', 'id="purchase-request"', 'id="supplier"', 'id="attachments"', 'id="goods-receipts"', 'id="billing"', 'id="accounting"', 'id="activity"'] as $anchor) {
            $page->assertSee($anchor, false);
        }
        $page->assertSee('Read-only document view');
        // A reader changes nothing: no approve form, no edit link, no receipt link.
        $page->assertDontSee('Approve Order')->assertDontSee('Create Goods Receipt')->assertDontSee(route('admin.inventory.purchase-orders.edit', $order));
    }

    public function test_header_shows_identity_received_and_billing_state(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $grn = $this->postedReceipt($order, $this->myStore, 6000);

        $page = $this->open($this->reader(), $order)->assertOk();

        $page->assertSee($order->po_number)->assertSee($this->supplier->name)->assertSee('Riyadh Commercial Tower')->assertSee('Riyadh Site Warehouse')
            ->assertSee('Partially received')->assertSee('6000 of 10000')->assertSee('still to receive 4000')
            ->assertSee('Received but not invoiced')->assertSee('SAR 34,500.00');

        $this->matchedBill($grn, 6000);
        $this->open($this->reader(), $order)->assertOk()->assertSee('Fully invoiced')->assertSee('outstanding payment SAR 20,700.00');
    }

    public function test_source_purchase_request_relation_is_shown_and_linked_by_permission(): void
    {
        $pr = $this->request($this->mine, $this->myStore);
        $order = $this->order($this->mine, $this->myStore, pr: $pr);

        $this->open($this->reader(), $order)->assertOk()
            ->assertSee(route('admin.inventory.purchase-requests.show', $pr))->assertSee('Slab reinforcement')->assertSee('Requested Lines');

        // Without Purchase Requests view the section and the link are gone; the number stays as plain text on the overview.
        $noPr = $this->user(['Purchase Orders' => ['view']], suffix: 'nopr');
        $this->open($noPr, $order)->assertOk()->assertDontSee('id="purchase-request"', false)->assertDontSee(route('admin.inventory.purchase-requests.show', $pr))->assertSee($pr->pr_number);

        $direct = $this->order($this->mine, $this->myStore);
        $this->open($this->reader(), $direct)->assertOk()->assertSee('raised directly, without a purchase request');
    }

    public function test_supplier_section_links_and_payable_account_follow_permissions(): void
    {
        $order = $this->order($this->mine, $this->myStore);

        $reader = $this->reader();
        $this->open($reader, $order)->assertOk()->assertSee('View Supplier')->assertDontSee('Manage Supplier')->assertSee('Payable Account');

        $manager = $this->user(['Purchase Orders' => ['view'], 'Suppliers' => ['view', 'edit']], suffix: 'mgr');
        $this->open($manager, $order)->assertOk()->assertSee('Manage Supplier')->assertDontSee('Payable Account');

        $noSupplier = $this->user(['Purchase Orders' => ['view']], suffix: 'nosup');
        $this->open($noSupplier, $order)->assertOk()->assertDontSee('id="supplier"', false)->assertDontSee('View Supplier')->assertSee($this->supplier->name);
    }

    public function test_order_lines_show_ordered_received_and_still_to_receive(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $this->postedReceipt($order, $this->myStore, 6000);

        $page = $this->open($this->reader(), $order)->assertOk();
        $page->assertSee('Still to receive')->assertSeeInOrder(['Reinforcement Steel 16mm', '10000', '6000', '4000']);
    }

    public function test_f04_invoiced_and_uninvoiced_quantities_come_from_the_receipt_lines(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $grn = $this->postedReceipt($order, $this->myStore, 6000);
        $this->matchedBill($grn, 4000);

        $panel = $this->panel($this->reader(), $order, 'billing')->assertOk();
        $html = $panel->json('html');
        $this->assertStringContainsString('Received but not invoiced', $html);
        // Ordered 10000 · received 6000 · still to receive 4000 · invoiced 4000 · received but not invoiced 2000.
        $this->assertMatchesRegularExpression('/<td>10000<\/td>\s*<td>6000<\/td>\s*<td>4000<\/td>\s*<td>4000<\/td>\s*<td><strong>2000<\/strong><\/td>/', $html);
        $this->assertStringContainsString(PostingService::GRNI, $html);
    }

    public function test_multiple_goods_receipts_are_listed_with_their_invoicing_state(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $first = $this->postedReceipt($order, $this->myStore, 6000);
        $second = $this->postedReceipt($order, $this->myStore, 4000);
        $this->matchedBill($first, 6000);

        $html = $this->panel($this->reader(), $order, 'goods-receipts')->assertOk()->json('html');
        $this->assertStringContainsString($first->grn_number, $html);
        $this->assertStringContainsString($second->grn_number, $html);
        $this->assertStringContainsString('<span class="badge green">Invoiced</span>', $html);
        $this->assertStringContainsString('<span class="badge yellow">Received but not invoiced</span>', $html);
        $this->assertStringNotContainsString('Create Supplier Bill', $html, 'a reader may not create bills');

        $this->assertSame('received', $order->fresh()->status);
        $this->open($this->reader(), $order)->assertOk()->assertSee('Fully received');
    }

    public function test_related_bills_are_listed_with_actions_by_permission(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $grn = $this->postedReceipt($order, $this->myStore, 6000);
        $bill = $this->matchedBill($grn, 6000, 'GST-INV-1045');

        $html = $this->panel($this->reader(), $order, 'billing')->assertOk()->json('html');
        $this->assertStringContainsString('GST-INV-1045', $html);
        $this->assertStringContainsString('View bill', $html);
        $this->assertStringNotContainsString('Record Payment', $html);

        $payer = $this->user(['Purchase Orders' => ['view'], 'Accounts Payable' => ['view', 'process']], suffix: 'pay');
        $html = $this->panel($payer, $order, 'billing')->assertOk()->json('html');
        $this->assertStringContainsString('Record Payment', $html);
        $this->assertStringContainsString(route('admin.accounting.accounts-payable.payment', ['accounts_payable' => $bill, 'return_to' => '/admin/inventory/purchase-orders/'.$order->id.'#billing']), html_entity_decode($html));
    }

    public function test_accounting_section_is_hidden_and_its_route_refused_without_journal_view(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $this->postedReceipt($order, $this->myStore, 6000);

        $noFinance = $this->user(['Purchase Orders' => ['view'], 'Goods Receipts' => ['view']], suffix: 'nofin');
        $this->open($noFinance, $order)->assertOk()->assertDontSee('id="accounting"', false)->assertDontSee('id="billing"', false)->assertSee('id="goods-receipts"', false);
        $this->panel($noFinance, $order, 'accounting')->assertForbidden();
        $this->panel($noFinance, $order, 'billing')->assertForbidden();

        $html = $this->panel($this->reader(), $order, 'accounting')->assertOk()->json('html');
        $this->assertStringContainsString('Goods Received Not Invoiced', $html);
        $this->assertStringContainsString($order->goodsReceipts()->first()->journalEntry->journal_number, $html);
    }

    public function test_activity_section_follows_the_activity_log_permission(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $grn = $this->postedReceipt($order, $this->myStore, 6000);

        $html = $this->panel($this->admin(), $order, 'activity')->assertOk()->json('html');
        $this->assertStringContainsString('Posted goods receipt', $html);
        $this->assertStringContainsString($grn->grn_number, $html);

        // A lower role reads the section but never the Super Admin's entries (NR-32 visibility rule).
        $html = $this->panel($this->reader(), $order, 'activity')->assertOk()->json('html');
        $this->assertStringNotContainsString('Posted goods receipt', $html);

        $noActivity = $this->user(['Purchase Orders' => ['view']], suffix: 'noact');
        $this->open($noActivity, $order)->assertOk()->assertDontSee('id="activity"', false);
        $this->panel($noActivity, $order, 'activity')->assertForbidden();
        $this->panel($noActivity, $order, 'lines')->assertNotFound();
    }

    public function test_create_goods_receipt_is_offered_only_when_permitted_and_something_is_outstanding(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $expected = route('admin.inventory.goods-receipts.create', ['purchase_order' => $order->id, 'return_to' => '/admin/inventory/purchase-orders/'.$order->id.'#goods-receipts']);

        $this->open($this->reader(), $order)->assertOk()->assertDontSee('Create Goods Receipt');

        $receiver = $this->user(['Purchase Orders' => ['view'], 'Goods Receipts' => ['view', 'create']], suffix: 'rcv');
        $page = $this->open($receiver, $order)->assertOk()->assertSee('Create Goods Receipt');
        $this->assertStringContainsString($expected, html_entity_decode($page->getContent()));

        $this->postedReceipt($order, $this->myStore, 10000);
        $this->open($receiver, $order)->assertOk()->assertDontSee('Create Goods Receipt');

        $draft = $this->order($this->mine, $this->myStore, status: 'draft');
        $this->open($receiver, $draft)->assertOk()->assertDontSee('Create Goods Receipt');
    }

    public function test_access_scope_hides_orders_and_receipts_outside_the_users_project(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $foreign = $this->order($this->theirs, $this->theirStore);
        $hidden = $this->postedReceipt($order, $this->theirStore, 1000, post: false);
        $visible = $this->postedReceipt($order, $this->myStore, 2000, post: false);

        $scoped = $this->reader($this->mine, 'scoped');
        $this->open($scoped, $foreign)->assertNotFound();
        $this->panel($scoped, $foreign, 'goods-receipts')->assertNotFound();

        $html = $this->panel($scoped, $order, 'goods-receipts')->assertOk()->json('html');
        $this->assertStringContainsString($visible->grn_number, $html);
        $this->assertStringNotContainsString($hidden->grn_number, $html);
        $this->assertStringContainsString('Showing the latest 1 of 1', $html);
    }

    public function test_forged_receipt_and_order_relations_are_rejected(): void
    {
        $orderA = $this->order($this->mine, $this->myStore);
        $orderB = $this->order($this->mine, $this->myStore, 500);
        $grnB = $this->postedReceipt($orderB, $this->myStore, 500);

        // A's workspace never shows B's receipt, whatever the browser asks for.
        $html = $this->panel($this->reader(), $orderA, 'goods-receipts')->assertOk()->json('html');
        $this->assertStringNotContainsString($grnB->grn_number, $html);

        // A receipt for order A cannot carry a line of order B.
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), [
            'purchase_order_id' => $orderA->id, 'supplier_id' => $orderA->supplier_id, 'warehouse_id' => $this->myStore->id,
            'received_date' => now()->toDateString(), 'vat_rate' => 15,
            'lines' => [['item_id' => $this->item->id, 'purchase_order_line_id' => $orderB->lines->first()->id, 'received_quantity' => 100, 'accepted_quantity' => 100, 'unit_cost' => 3]],
        ])->assertSessionHasErrors('lines');

        // An order outside the user's scope cannot be used to pre-fill or store a receipt.
        $foreign = $this->order($this->theirs, $this->theirStore);
        $receiver = $this->user(['Goods Receipts' => ['view', 'create'], 'Purchase Orders' => ['view']], $this->mine, 'frcv');
        $this->actingAs($receiver)->get(route('admin.inventory.goods-receipts.create', ['purchase_order' => $foreign->id]))->assertOk()->assertDontSee($foreign->po_number);
        $this->actingAs($receiver)->post(route('admin.inventory.goods-receipts.store'), [
            'purchase_order_id' => $foreign->id, 'supplier_id' => $foreign->supplier_id, 'warehouse_id' => $this->myStore->id,
            'received_date' => now()->toDateString(), 'vat_rate' => 15,
            'lines' => [['item_id' => $this->item->id, 'purchase_order_line_id' => $foreign->lines->first()->id, 'received_quantity' => 100, 'accepted_quantity' => 100, 'unit_cost' => 3]],
        ])->assertSessionHasErrors('purchase_order_id');
    }

    public function test_bill_relations_of_another_supplier_or_order_are_not_shown(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $grn = $this->postedReceipt($order, $this->myStore, 6000);
        $own = $this->matchedBill($grn, 3000, 'GST-INV-OWN');

        // A forged match row pointing a different supplier's bill at this receipt is ignored.
        $foreignBill = SupplierBill::create([
            'supplier_id' => $this->otherSupplier->id, 'bill_number' => 'OTHER-1', 'bill_date' => now()->toDateString(), 'project_id' => $this->mine->id,
            'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115, 'paid_amount' => 0, 'balance_amount' => 115, 'status' => 'draft',
        ]);
        $foreignLine = $foreignBill->lines()->create(['description' => 'Forged', 'quantity' => 1, 'unit_price' => 100, 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115]);
        SupplierBillGrnMatch::create(['supplier_bill_id' => $foreignBill->id, 'supplier_bill_line_id' => $foreignLine->id, 'goods_receipt_id' => $grn->id, 'goods_receipt_line_id' => $grn->lines->first()->id, 'matched_quantity' => 1, 'matched_taxable_amount' => 3]);

        // A bill of the same supplier matched to another order's receipt stays on that order.
        $otherOrder = $this->order($this->mine, $this->myStore, 500);
        $otherBill = $this->matchedBill($this->postedReceipt($otherOrder, $this->myStore, 500), 500, 'GST-INV-OTHER-PO');

        $html = $this->panel($this->reader(), $order, 'billing')->assertOk()->json('html');
        $this->assertStringContainsString($own->bill_number, $html);
        $this->assertStringNotContainsString('OTHER-1', $html);
        $this->assertStringNotContainsString($otherBill->bill_number, $html);

        $this->open($this->reader(), $order)->assertOk()->assertSee('1 bill,');
    }

    public function test_attachments_follow_order_permissions_and_belong_to_the_order(): void
    {
        Storage::fake(PurchaseOrderAttachment::DISK);
        $order = $this->order($this->mine, $this->myStore, status: 'draft');
        $other = $this->order($this->mine, $this->myStore, status: 'draft');
        $this->actingAs($this->admin())->post(route('admin.inventory.purchase-orders.attachments.store', $other), [
            'quotations' => [UploadedFile::fake()->create('quotation.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $attachment = PurchaseOrderAttachment::firstOrFail();

        $reader = $this->reader();
        $this->open($reader, $order)->assertOk()->assertDontSee('Upload')->assertDontSee('>Remove<', false)->assertSee('No quotation attached yet');
        // The file belongs to the other order: reading it through this order is not found.
        $this->actingAs($reader)->get(route('admin.inventory.purchase-orders.attachments.download', [$order, $attachment]))->assertNotFound();
        $this->actingAs($reader)->get(route('admin.inventory.purchase-orders.attachments.download', [$other, $attachment]))->assertOk();

        $buyer = $this->user(['Purchase Orders' => ['view', 'create', 'delete']], suffix: 'buyer');
        $this->open($buyer, $other)->assertOk()->assertSee('Upload')->assertSee('>Remove<', false)->assertSee('quotation.pdf');
    }

    public function test_goods_receipt_saved_from_the_order_returns_to_its_receipts_section(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $origin = '/admin/inventory/purchase-orders/'.$order->id.'#goods-receipts';
        $payload = fn (string $dn) => [
            'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id, 'warehouse_id' => $this->myStore->id,
            'received_date' => now()->toDateString(), 'delivery_note_number' => $dn, 'vat_rate' => 15,
            'lines' => [['item_id' => $this->item->id, 'purchase_order_line_id' => $order->lines->first()->id, 'received_quantity' => 1000, 'accepted_quantity' => 1000, 'unit_cost' => 3]],
        ];

        // The create page seeded from the order carries the origin in the form.
        $this->actingAs($this->admin())->get(route('admin.inventory.goods-receipts.create', ['purchase_order' => $order->id, 'return_to' => $origin]))
            ->assertOk()->assertSee('name="_return_to" value="'.$origin.'"', false)->assertSee('Save &amp; close', false);

        // Save & Close goes back to the order's Goods Receipts section.
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), $payload('DN-1') + ['_save_action' => 'close', '_return_to' => $origin])
            ->assertSessionHasNoErrors()->assertRedirect($origin);

        // Save stays on the new receipt (Post Stock lives there) and keeps the origin for the Back button.
        $response = $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), $payload('DN-2') + ['_save_action' => 'stay', '_return_to' => $origin])->assertSessionHasNoErrors();
        $grn = GoodsReceipt::where('delivery_note_number', 'DN-2')->firstOrFail();
        $response->assertRedirect(route('admin.inventory.goods-receipts.show', [$grn, 'return_to' => $origin]));
        $this->actingAs($this->admin())->get(route('admin.inventory.goods-receipts.show', [$grn, 'return_to' => $origin]))->assertOk()->assertSee('href="'.$origin.'"', false)->assertSee('Bill Matches');

        // Posting from that page returns to the receipt with the origin kept; an off-site origin is ignored.
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.post-stock', $grn), ['_return_to' => $origin])
            ->assertRedirect(route('admin.inventory.goods-receipts.show', [$grn, 'return_to' => $origin]));
        $this->actingAs($this->admin())->post(route('admin.inventory.goods-receipts.store'), $payload('DN-3') + ['_save_action' => 'close', '_return_to' => 'https://evil.example/phish'])
            ->assertRedirect(route('admin.inventory.goods-receipts.index'));
    }

    public function test_bill_and_payment_flows_return_to_the_context_they_started_from(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $grn = $this->postedReceipt($order, $this->myStore, 6000);
        $billing = '/admin/inventory/purchase-orders/'.$order->id.'#billing';
        $line = $grn->lines->first();

        // Create Supplier Bill from the order's receipts, Save & Close → back to the Billing section.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.store'), [
            'supplier_id' => $grn->supplier_id, 'bill_number' => 'GST-INV-1045', 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
            'project_id' => $this->mine->id, 'vat_rate' => 15, '_save_action' => 'close', '_return_to' => $billing,
            'lines' => [['description' => 'Steel', 'goods_receipt_line_id' => $line->id, 'matched_quantity' => 6000, 'quantity' => 6000, 'unit_price' => 3]],
        ])->assertSessionHasNoErrors()->assertRedirect($billing);
        $bill = SupplierBill::where('bill_number', 'GST-INV-1045')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();

        // The payment form opened from the order's Billing section cancels and records back to it.
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-payable.payment', ['accounts_payable' => $bill, 'return_to' => $billing]))
            ->assertOk()->assertSee('name="_return_to" value="'.$billing.'"', false)->assertSee('href="'.$billing.'"', false);
        $cash = ChartOfAccount::where('account_code', PostingService::CASH)->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
            'payment_date' => now()->toDateString(), 'payment_account_id' => $cash->id, 'amount' => 1000, 'purpose' => 'Bill payment', '_return_to' => $billing,
        ])->assertSessionHasNoErrors()->assertRedirect($billing);
        $this->assertSame(1000.0, (float) $bill->fresh()->paid_amount);

        // Without an origin the payment returns to the bill; an off-site origin is ignored.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
            'payment_date' => now()->toDateString(), 'payment_account_id' => $cash->id, 'amount' => 500, 'purpose' => 'Bill payment', '_return_to' => 'https://evil.example/',
        ])->assertRedirect(route('admin.accounting.accounts-payable.show', $bill));

        // The bill page shows its GRN matches, balance and a Back link when opened from the order.
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-payable.show', ['accounts_payable' => $bill, 'return_to' => $billing]))
            ->assertOk()->assertSee('GRN Matches')->assertSee($grn->grn_number)->assertSee('Outstanding payment')->assertSee('SAR 19,200.00')->assertSee('href="'.$billing.'"', false)->assertSee('Invoiced (bill approved)');
    }

    public function test_purchase_request_view_shows_ordered_quantities_orders_and_activity(): void
    {
        $pr = $this->request($this->mine, $this->myStore);
        $order = $this->order($this->mine, $this->myStore, 6000, pr: $pr);
        $this->postedReceipt($order, $this->myStore, 2000);

        $page = $this->actingAs($this->reader())->get(route('admin.inventory.purchase-requests.show', $pr))->assertOk();
        $page->assertSee('Ordered so far')->assertSee('Still to order')->assertSeeInOrder(['10000', '6000', '4000'])
            ->assertSee('Partly ordered')->assertSee($order->po_number)->assertSee('2000 of 6000')->assertSee('id="activity"', false)
            ->assertDontSee('Create Purchase Order')->assertDontSee('Reject Request');

        $noOrders = $this->user(['Purchase Requests' => ['view']], suffix: 'noord');
        $this->actingAs($noOrders)->get(route('admin.inventory.purchase-requests.show', $pr))->assertOk()
            ->assertDontSee(route('admin.inventory.purchase-orders.show', $order))->assertSee($order->po_number)->assertDontSee('id="activity"', false);
    }

    public function test_goods_receipt_view_shows_bill_matches_and_hides_finance_without_permission(): void
    {
        $order = $this->order($this->mine, $this->myStore);
        $grn = $this->postedReceipt($order, $this->myStore, 6000);
        $bill = $this->matchedBill($grn, 4000, 'GST-INV-1045');

        $this->actingAs($this->reader())->get(route('admin.inventory.goods-receipts.show', $grn))->assertOk()
            ->assertSee('Partly invoiced')->assertSee('invoiced 4000, received but not invoiced 2000')->assertSee('Bill Matches')->assertSee('GST-INV-1045')
            ->assertSee('Invoiced (bill approved)')->assertSee('Back to Purchase Order')->assertSee('Goods Received Not Invoiced')->assertSee('id="activity"', false)
            ->assertDontSee('Post Stock')->assertDontSee('Create Supplier Bill');

        $storekeeper = $this->user(['Goods Receipts' => ['view']], suffix: 'store');
        $this->actingAs($storekeeper)->get(route('admin.inventory.goods-receipts.show', $grn))->assertOk()
            ->assertDontSee('Bill Matches')->assertDontSee('GST-INV-1045')->assertDontSee('id="accounting"', false)->assertDontSee('id="activity"', false)
            ->assertDontSee(route('admin.inventory.purchase-orders.show', $order));
    }

    public function test_paged_sections_show_five_rows_and_page_through(): void
    {
        $order = $this->order($this->mine, $this->myStore, 12000);
        for ($i = 0; $i < 6; $i++) {
            $this->postedReceipt($order, $this->myStore, 1000, post: false);
        }

        $html = $this->panel($this->reader(), $order, 'goods-receipts')->assertOk()->json('html');
        $this->assertStringContainsString('Showing the latest 5 of 6', $html);
        $this->assertStringContainsString('page=2', $html);

        $html = $this->panel($this->reader(), $order, 'goods-receipts', ['page' => 2])->assertOk()->json('html');
        $this->assertStringContainsString('Showing the latest 1 of 6', $html);

        $this->open($this->reader(), $order)->assertOk()->assertSee('Showing the latest 5 of 6')->assertSee('page_goods-receipts=2');
    }
}
