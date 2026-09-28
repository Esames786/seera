<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\SaveAction;
use App\Support\Workspace\ProjectWorkspacePanels as Panels;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Project $other;

    private User $admin;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->project = Project::create(['code' => 'PW-A', 'name' => 'Riyadh Commercial Tower', 'customer_id' => Customer::first()->id, 'budget' => 10000, 'status' => 'active']);
        $this->other = Project::create(['code' => 'PW-B', 'name' => 'Other private project', 'status' => 'active']);
    }

    private function actor(array $modules, string $scope = 'Company Level', ?Site $site = null): User
    {
        $n = ++$this->sequence;
        $role = Role::create(['name' => 'PW role '.$n, 'code' => 'PW_ROLE_'.$n, 'level' => 4, 'access_scope' => $scope, 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', $modules)->where('action', 'view')->pluck('id'));
        $user = User::create(['name' => 'PW actor '.$n, 'email' => 'pw'.$n.'@example.test', 'password' => 'secret', 'status' => 'active', 'project_id' => $this->project->id, 'site_id' => $site?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    private function panel(string $key, ?User $user = null, array $query = [])
    {
        return $this->actingAs($user ?? $this->admin)->getJson(route('admin.master.projects.workspace.panel', [$this->project, $key, ...$query]));
    }

    private function site(Project $project, string $code): Site
    {
        return Site::create(['name' => $code, 'code' => $code, 'project_id' => $project->id, 'status' => 'active']);
    }

    private function warehouse(Project $project, string $code, ?Site $site = null): Warehouse
    {
        return Warehouse::create(['name' => $code, 'code' => $code, 'project_id' => $project->id, 'site_id' => $site?->id, 'status' => 'active']);
    }

    private function order(Project $project, string $number, ?Supplier $supplier = null, ?Site $site = null): PurchaseOrder
    {
        return PurchaseOrder::create(['po_number' => $number, 'supplier_id' => ($supplier ?? Supplier::first())->id, 'project_id' => $project->id, 'site_id' => $site?->id, 'po_date' => '2026-09-28', 'status' => 'approved', 'total_amount' => 230]);
    }

    private function invoice(Project $project, string $number): CustomerInvoice
    {
        return CustomerInvoice::create(['customer_id' => $this->project->customer_id, 'project_id' => $project->id, 'invoice_number' => $number, 'invoice_date' => '2026-09-28', 'taxable_amount' => 200, 'vat_amount' => 30, 'total_amount' => 230, 'balance_amount' => 230, 'payment_status' => 'unpaid']);
    }

    public function test_list_view_manage_identity_and_read_only_get_have_no_business_writes(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.projects.index'))->assertOk()->assertSee('Edit / Manage')->assertSee('>View<', false);
        $writes = [];
        DB::listen(function ($event) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create)\b/i', $event->sql)) {
                $writes[] = $event->sql;
            }
        });
        $this->get(route('admin.master.projects.show', $this->project))->assertOk()->assertSee('PW-A')->assertSee('Riyadh Commercial Tower')
            ->assertSee('data-project-identity', false)->assertDontSee('name="budget"', false)->assertDontSee('name="_save_action"', false);
        foreach (array_keys(Panels::panels()) as $key) {
            $this->panel($key)->assertOk()->assertJsonStructure(['html']);
        }
        $this->assertSame([], $writes);
        $this->get(route('admin.master.projects.edit', $this->project))->assertOk()->assertSee('name="_save_action"', false)->assertSee('data-project-identity', false);
    }

    public function test_profile_save_close_new_and_validation_preserve_business_documents(): void
    {
        $payload = ['name' => 'Updated tower', 'code' => 'PW-A', 'status' => 'active', 'budget' => 10000];
        $url = route('admin.master.projects.update', $this->project);
        $this->actingAs($this->admin)->put($url, $payload + ['_save_action' => 'stay'])->assertRedirect(route('admin.master.projects.edit', $this->project));
        $this->put($url, $payload + ['_save_action' => 'close'])->assertRedirect(route('admin.master.projects.index'));
        $this->put($url, $payload + ['_save_action' => 'new'])->assertRedirect(route('admin.master.projects.create'));
        $this->put($url, $payload + ['_save_action' => 'close', '_return_to' => '/admin/master/customers'])->assertRedirect('/admin/master/customers');
        $this->put($url, $payload + ['_return_to' => 'https://evil.test'])->assertRedirect(route('admin.master.projects.index'));
        $this->put($url, ['name' => '', 'code' => 'PW-A', 'status' => 'active'])->assertSessionHasErrors('name');
        $this->assertSame('Updated tower', $this->project->fresh()->name);
        $this->assertDatabaseHas('activity_logs', ['description' => '[Project #'.$this->project->id.'] PW-A / Updated tower']);
    }

    public function test_create_save_stays_and_new_starts_fresh(): void
    {
        $this->actingAs($this->admin)->post(route('admin.master.projects.store'), ['name' => 'Fresh tower', 'code' => 'PW-C', 'status' => 'active', '_save_action' => 'stay'])
            ->assertRedirect(route('admin.master.projects.edit', Project::where('code', 'PW-C')->firstOrFail()));
        $this->post(route('admin.master.projects.store'), ['name' => 'Next tower', 'code' => 'PW-D', 'status' => 'active', '_save_action' => 'new'])->assertRedirect(route('admin.master.projects.create'));
    }

    public function test_every_panel_is_hidden_and_directly_denied_without_its_permission(): void
    {
        $viewer = $this->actor(['Projects']);
        $page = $this->actingAs($viewer)->get(route('admin.master.projects.show', $this->project))->assertOk();
        foreach (array_keys(Panels::panels()) as $key) {
            $page->assertDontSee('data-workspace-related="'.$key.'"', false);
            $this->panel($key, $viewer)->assertForbidden();
        }
        $page->assertDontSee('Posted cost (SAR)')->assertDontSee('Material used (SAR)');
        $this->get(route('admin.master.projects.index'))->assertDontSee('Edit / Manage')->assertDontSee('+ Create Project');
        $this->get(route('admin.master.projects.edit', $this->project))->assertForbidden();
        $this->panel('unknown', $this->admin)->assertNotFound();
        $this->panel('customer', $this->actor(['Customers']))->assertForbidden();
    }

    public function test_customer_permission_does_not_grant_ar_and_links_are_authorized(): void
    {
        $viewer = $this->actor(['Projects', 'Customers']);
        $html = $this->panel('customer', $viewer)->assertOk()->json('html');
        $this->assertStringContainsString(Customer::find($this->project->customer_id)->name, $html);
        $this->assertStringNotContainsString('Edit / Manage', $html);
        $this->assertStringNotContainsString('Amount still to receive', $html);
        $this->invoice($this->project, 'PW-INV-CUSTOMER');
        $this->panel('customer')->assertOk();
    }

    public function test_sites_are_scoped_and_route_parent_rejects_foreign_site_and_project_payload(): void
    {
        $mine = $this->site($this->project, 'SITE-PW-MINE');
        $theirs = $this->site($this->other, 'SITE-PW-SECRET');
        $html = $this->panel('sites')->assertOk()->json('html');
        $this->assertStringContainsString($mine->code, $html);
        $this->assertStringNotContainsString($theirs->code, $html);
        $this->get(route('admin.master.sites.project.edit', [$this->project, $theirs]))->assertNotFound();
        $this->put(route('admin.master.sites.project.update', [$this->project, $theirs]), ['name' => 'stolen', 'code' => 'stolen', 'status' => 'active'])->assertNotFound();
        $this->putJson(route('admin.master.sites.project.update', [$this->project, $mine]), ['project_id' => $this->other->id, 'name' => $mine->name, 'code' => $mine->code, 'status' => 'active'])->assertUnprocessable();
        $this->postJson(route('admin.master.sites.project.store', $this->project), ['project_id' => $this->other->id, 'name' => 'bad', 'code' => 'bad', 'status' => 'active'])->assertUnprocessable();
        $this->assertSame($this->project->id, $mine->fresh()->project_id);
    }

    public function test_site_child_save_returns_to_project_and_cannot_create_with_only_view(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.sites.project.create', $this->project))->assertOk()->assertSee('name="_save_action"', false);
        $this->post(route('admin.master.sites.project.store', $this->project), ['name' => 'New location', 'code' => 'PW-NEW', 'status' => 'active', '_save_action' => 'close'])
            ->assertRedirect(route('admin.master.projects.show', $this->project, false).'#sites');
        $site = Site::where('code', 'PW-NEW')->firstOrFail();
        $this->assertSame($this->project->id, $site->project_id);
        $this->put(route('admin.master.sites.project.update', [$this->project, $site]), ['name' => 'New location', 'code' => 'PW-NEW', 'status' => 'active', '_save_action' => 'close', '_return_to' => '//evil.test'])
            ->assertRedirect(route('admin.master.projects.show', $this->project, false).'#sites');
        $viewer = $this->actor(['Projects', 'Sites']);
        $this->actingAs($viewer)->post(route('admin.master.sites.project.store', $this->project), [])->assertForbidden();
    }

    public function test_staff_and_warehouses_are_project_and_site_scoped_with_stock_permission_separate(): void
    {
        $site = $this->site($this->project, 'SITE-STAFF');
        $hiddenSite = $this->site($this->project, 'SITE-HIDDEN');
        foreach ([[$this->project, $site, 'Visible worker'], [$this->project, $hiddenSite, 'Hidden worker'], [$this->other, null, 'Other worker']] as [$project, $s, $name]) {
            Employee::create(['employee_code' => 'PW-'.str_replace(' ', '-', $name), 'first_name' => $name, 'project_id' => $project->id, 'site_id' => $s?->id, 'status' => 'active']);
        }
        $visible = $this->warehouse($this->project, 'WH-PW-VISIBLE', $site);
        $hidden = $this->warehouse($this->project, 'WH-PW-HIDDEN', $hiddenSite);
        WarehouseStock::create(['warehouse_id' => $visible->id, 'item_id' => Item::first()->id, 'quantity' => 3, 'average_cost' => 10, 'total_value' => 30]);
        WarehouseStock::create(['warehouse_id' => $hidden->id, 'item_id' => Item::first()->id, 'quantity' => 99, 'average_cost' => 10, 'total_value' => 990]);
        $viewer = $this->actor(['Projects', 'Sites', 'HR', 'Warehouses', 'Warehouse Stock'], 'Site Level', $site);
        $html = $this->panel('staff', $viewer)->assertOk()->json('html');
        $this->assertStringContainsString('Visible worker', $html);
        $this->assertStringNotContainsString('Hidden worker', $html);
        $this->assertStringNotContainsString('Other worker', $html);
        $html = $this->panel('warehouses', $viewer)->assertOk()->json('html');
        $this->assertStringContainsString('WH-PW-VISIBLE', $html);
        $this->assertStringNotContainsString('WH-PW-HIDDEN', $html);
        $this->assertStringContainsString('3.000', $html);
        $this->assertStringNotContainsString('99.000', $html);
        $data = Panels::data($this->project, 'stock', $viewer);
        $this->assertSame(30.0, $data['stockTotal']);
        $this->assertSame(1, $data['rows']->total());
        $this->panel('stock', $viewer)->assertOk();
        $limited = $this->actor(['Projects', 'Warehouses']);
        $this->assertStringContainsString('Restricted', $this->panel('warehouses', $limited)->assertOk()->json('html'));
        $this->panel('stock', $limited)->assertForbidden();
    }

    public function test_suppliers_include_pivot_and_authorized_project_documents_not_other_project_purchases(): void
    {
        $pivot = Supplier::create(['code' => 'PW-PIVOT', 'name' => 'Linked supplier', 'status' => 'active']);
        $document = Supplier::create(['code' => 'PW-DOC', 'name' => 'Document supplier', 'status' => 'active']);
        $private = Supplier::create(['code' => 'PW-PRIVATE', 'name' => 'Private supplier', 'status' => 'active']);
        $pivot->projects()->attach($this->project);
        $this->order($this->project, 'PW-PO-SUPPLIER', $document);
        $this->order($this->other, 'PW-PO-PRIVATE', $private);
        $html = $this->panel('suppliers')->assertOk()->json('html');
        foreach (['PW-PIVOT', 'PW-DOC', '230.00'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('PW-PRIVATE', $html);
        $viewer = $this->actor(['Projects', 'Suppliers']);
        $html = $this->panel('suppliers', $viewer)->assertOk()->json('html');
        $this->assertStringContainsString('PW-PIVOT', $html);
        $this->assertStringNotContainsString('PW-DOC', $html);
    }

    public function test_procurement_is_project_scoped_and_grn_uses_po_not_warehouse_alone(): void
    {
        $store = $this->warehouse($this->project, 'WH-PROC');
        foreach ([[$this->project, 'VISIBLE'], [$this->other, 'SECRET']] as [$project, $label]) {
            PurchaseRequest::create(['pr_number' => 'PW-PR-'.$label, 'project_id' => $project->id, 'request_date' => '2026-09-28', 'status' => 'approved']);
            $po = $this->order($project, 'PW-PO-'.$label);
            GoodsReceipt::create(['grn_number' => 'PW-GRN-'.$label, 'purchase_order_id' => $po->id, 'warehouse_id' => $store->id, 'supplier_id' => $po->supplier_id, 'received_date' => '2026-09-28', 'status' => 'draft']);
        }
        foreach (['requests' => 'PR', 'orders' => 'PO', 'receipts' => 'GRN'] as $key => $prefix) {
            $html = $this->panel($key)->assertOk()->json('html');
            $this->assertStringContainsString('PW-'.$prefix.'-VISIBLE', $html);
            $this->assertStringNotContainsString('PW-'.$prefix.'-SECRET', $html);
        }
    }

    public function test_materials_use_only_posted_project_issue_ledger_values_not_receipts(): void
    {
        $store = $this->warehouse($this->project, 'WH-MAT');
        foreach ([[$this->project, 'posted', 'issue', 60], [$this->project, 'draft', 'issue', 900], [$this->other, 'posted', 'issue', 700], [$this->project, 'posted', 'grn', 800]] as $i => [$project, $status, $movement, $value]) {
            $issue = StockIssue::create(['issue_number' => 'PW-ISS-'.$i, 'issue_date' => '2026-09-28', 'warehouse_id' => $store->id, 'project_id' => $project->id, 'status' => $status, 'total_cost' => $value]);
            StockLedgerEntry::create(['warehouse_id' => $store->id, 'item_id' => Item::first()->id, 'project_id' => $project->id, 'movement_type' => $movement, 'reference_type' => StockIssue::class, 'reference_id' => $issue->id, 'reference_number' => $issue->issue_number, 'movement_date' => '2026-09-28', 'out_quantity' => 2, 'value' => $value]);
        }
        $html = $this->panel('materials')->assertOk()->json('html');
        $this->assertStringContainsString('PW-ISS-0', $html);
        foreach ([1, 2, 3] as $i) {
            $this->assertStringNotContainsString('PW-ISS-'.$i, $html);
        }
        $this->assertSame(60.0, Panels::data($this->project, 'materials', $this->admin)['materialTotal']);
    }

    public function test_invoices_and_receipts_follow_project_and_visible_invoice_with_process_links_gated(): void
    {
        foreach ([[$this->project, 'VISIBLE'], [$this->other, 'SECRET']] as [$project, $label]) {
            $invoice = $this->invoice($project, 'PW-INV-'.$label);
            CustomerReceipt::create(['customer_id' => $invoice->customer_id, 'customer_invoice_id' => $invoice->id, 'receipt_date' => '2026-09-28', 'reference_number' => 'PW-PAY-'.$label, 'amount' => 20]);
        }
        $viewer = $this->actor(['Projects', 'Accounts Receivable'], 'Project Level');
        foreach (['invoices' => 'INV', 'payments' => 'PAY'] as $key => $prefix) {
            $html = $this->panel($key, $viewer)->assertOk()->json('html');
            $this->assertStringContainsString('PW-'.$prefix.'-VISIBLE', $html);
            $this->assertStringNotContainsString('PW-'.$prefix.'-SECRET', $html);
            $this->assertStringNotContainsString('Record Receipt', $html);
        }
        $this->assertStringContainsString('Record Receipt', $this->panel('invoices')->assertOk()->json('html'));
    }

    public function test_finance_matches_report_net_posted_semantics_and_selected_project_filter(): void
    {
        $expense = ChartOfAccount::where('account_type', 'expense')->firstOrFail();
        $revenue = ChartOfAccount::where('account_type', 'revenue')->firstOrFail();
        foreach ([['posted', $this->project, $expense, 120, 0], ['posted', $this->project, $expense, 0, 20], ['posted', $this->project, $revenue, 0, 300], ['draft', $this->project, $expense, 999, 0], ['posted', $this->other, $expense, 777, 0]] as $i => [$status, $project, $account, $debit, $credit]) {
            $entry = JournalEntry::create(['journal_number' => 'PW-JE-'.$i, 'journal_date' => '2026-09-28', 'source_module' => 'Manual', 'status' => $status]);
            $entry->lines()->create(['chart_of_account_id' => $account->id, 'project_id' => $project->id, 'debit' => $debit, 'credit' => $credit]);
        }
        $this->invoice($this->project, 'PW-INV-FIN');
        SupplierBill::create(['supplier_id' => Supplier::first()->id, 'project_id' => $this->project->id, 'bill_number' => 'PW-BILL', 'bill_date' => '2026-09-28', 'status' => 'cancelled', 'total_amount' => 50]);
        $this->actingAs($this->admin);
        $finance = Panels::finance($this->project);
        $this->assertSame(100.0, $finance['cost']);
        $this->assertSame(300.0, $finance['revenue']);
        $this->assertSame(200.0, $finance['margin']);
        $this->assertSame(1.0, $finance['budget_used']);
        $this->assertSame(50.0, $finance['billed']);
        $this->assertSame(230.0, $finance['invoiced']);
        $response = $this->get(route('admin.accounting.reports.project-cost-report', ['project' => $this->project->id]))->assertOk();
        $this->assertCount(1, $response->viewData('rows'));
        $reportRow = $response->viewData('rows')->first();
        $this->assertSame($this->project->id, $reportRow['project']->id);
        $this->assertSame(Arr::except($finance, 'project'), Arr::except($reportRow, 'project'));
        $this->panel('finance')->assertOk();
    }

    public function test_activity_uses_entity_token_and_role_visibility_not_ambiguous_names(): void
    {
        $viewer = $this->actor(['Projects', 'Activity Logs']);
        foreach ([[$viewer->id, '[Project #'.$this->project->id.'] PW-A / visible'], [$viewer->id, '[Project #'.$this->other->id.'] PW-B / hidden'], [$this->admin->id, '[Project #'.$this->project->id.'] PW-A / admin-private'], [$viewer->id, $this->project->name]] as [$actor, $description]) {
            ActivityLog::create(['user_id' => $actor, 'user_name' => 'Actor', 'module' => 'Projects', 'action' => 'Updated', 'description' => $description, 'status' => 'success']);
        }
        $html = $this->panel('activity', $viewer)->assertOk()->json('html');
        $this->assertStringContainsString('PW-A / visible', $html);
        $this->assertStringNotContainsString('PW-B / hidden', $html);
        $this->assertStringNotContainsString('admin-private', $html);
        $this->assertSame(1, Panels::query($this->project, 'activity', $viewer)->count());
    }

    public function test_forged_record_and_project_filters_cannot_override_route_parent(): void
    {
        foreach (array_keys(Panels::panels()) as $key) {
            $this->panel($key, null, ['record' => 999])->assertUnprocessable();
            $this->panel($key, null, ['project_id' => $this->other->id])->assertUnprocessable();
        }
        $viewer = $this->actor(['Projects', 'Sites'], 'Project Level');
        $this->actingAs($viewer)->get(route('admin.master.projects.show', $this->other))->assertNotFound();
        $this->getJson(route('admin.master.projects.workspace.panel', [$this->other, 'sites']))->assertNotFound();
    }

    public function test_panels_are_paged_and_not_eager_loaded_on_initial_view(): void
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->site($this->project, 'PW-PAGED-'.$i);
        }
        $this->actingAs($this->admin)->get(route('admin.master.projects.show', $this->project))->assertOk()->assertDontSee('PW-PAGED-');
        $data = Panels::data($this->project, 'sites', $this->admin);
        $this->assertCount(10, $data['rows']->items());
        $this->assertSame(23, $data['rows']->total());
        $this->assertCount(3, Panels::data($this->project, 'sites', $this->admin, 3)['rows']->items());
        $this->get(route('admin.master.projects.workspace.panel', [$this->project, 'sites']))->assertOk()->assertSee('Back to Project');
        $this->panel('sites', null, ['page' => 3])->assertOk();
    }

    public function test_return_paths_reject_external_admin_prefix_and_traversal(): void
    {
        foreach (['https://evil.test', '//evil.test', '/administrator', '/admin/../logout', '/admin/%2e%2e/logout', '/admin/%5cevil', '/admin/%252e%252e/logout'] as $path) {
            $this->assertFalse(SaveAction::isSafePath($path), $path);
        }
        $this->assertTrue(SaveAction::isSafePath('/admin/master/projects/1#sites'));
        $this->assertTrue(SaveAction::isSafePath('/admin/master/projects?search=tower'));
    }

    public function test_site_scoped_financial_and_header_totals_exclude_other_sites(): void
    {
        $site = $this->site($this->project, 'PW-FIN-SITE');
        $hidden = $this->site($this->project, 'PW-FIN-HIDDEN');
        $expense = ChartOfAccount::where('account_type', 'expense')->firstOrFail();
        foreach ([[$site, 25], [$hidden, 975]] as [$s, $value]) {
            $entry = JournalEntry::create(['journal_number' => 'PW-SITE-JE-'.$s->id, 'journal_date' => '2026-09-28', 'status' => 'posted', 'source_module' => 'Manual']);
            $entry->lines()->create(['chart_of_account_id' => $expense->id, 'project_id' => $this->project->id, 'site_id' => $s->id, 'debit' => $value]);
        }
        $viewer = $this->actor(['Projects', 'Financial Reports', 'Sites'], 'Site Level', $site);
        $this->actingAs($viewer);
        $this->assertSame(25.0, Panels::finance($this->project)['cost']);
        $summary = Panels::summary($this->project, $viewer);
        $this->assertSame('25.00', $summary['Posted cost (SAR)']);
        $this->assertSame(1, $summary['Sites']);
        $this->panel('finance', $viewer)->assertOk();
    }

    public function test_po_and_grn_billing_states_use_visible_posted_receipt_lines_and_return_links(): void
    {
        $store = $this->warehouse($this->project, 'PW-STATE-WH');
        $order = $this->order($this->project, 'PW-STATE-PO');
        $order->lines()->create(['item_id' => Item::first()->id, 'quantity' => 5, 'received_quantity' => 5, 'unit_price' => 10, 'total_amount' => 50]);
        $receipt = GoodsReceipt::create(['grn_number' => 'PW-STATE-GRN', 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id, 'warehouse_id' => $store->id, 'received_date' => '2026-09-28', 'status' => 'posted', 'stock_updated' => true]);
        $receipt->lines()->create(['item_id' => Item::first()->id, 'accepted_quantity' => 5, 'received_quantity' => 5, 'invoiced_quantity' => 2]);
        foreach (['orders', 'receipts'] as $panel) {
            $this->assertStringContainsString('Partly invoiced', $this->panel($panel)->assertOk()->json('html'));
        }
        $origin = route('admin.master.projects.show', $this->project, false).'#orders';
        $this->get(route('admin.inventory.purchase-orders.show', [$order, 'return_to' => $origin]))->assertOk()->assertSee($origin)->assertSee('Back to origin');
        $invoice = $this->invoice($this->project, 'PW-RETURN-INV');
        $this->get(route('admin.accounting.accounts-receivable.show', [$invoice, 'return_to' => $origin]))->assertOk()->assertSee($origin)->assertSee('Back to origin');
    }

    public function test_material_quantities_are_not_totalled_across_different_units(): void
    {
        $store = $this->warehouse($this->project, 'PW-UNITS-WH');
        $unitA = Unit::create(['code' => 'PWKG', 'name' => 'PW kilograms']);
        $unitB = Unit::create(['code' => 'PWL', 'name' => 'PW litres']);
        foreach ([$unitA, $unitB] as $i => $unit) {
            $item = Item::create(['item_code' => 'PW-UNIT-'.$i, 'name' => 'PW unit item '.$i, 'unit_id' => $unit->id, 'status' => 'active']);
            $issue = StockIssue::create(['issue_number' => 'PW-UNIT-ISS-'.$i, 'issue_date' => '2026-09-28', 'warehouse_id' => $store->id, 'project_id' => $this->project->id, 'status' => 'posted']);
            StockLedgerEntry::create(['warehouse_id' => $store->id, 'item_id' => $item->id, 'project_id' => $this->project->id, 'movement_type' => 'issue', 'reference_type' => StockIssue::class, 'reference_id' => $issue->id, 'movement_date' => '2026-09-28', 'out_quantity' => 5, 'value' => 10]);
        }
        $this->actingAs($this->admin);
        $this->assertArrayNotHasKey('materialQuantity', Panels::data($this->project, 'materials', $this->admin));
        $this->assertSame(20.0, Panels::data($this->project, 'materials', $this->admin)['materialTotal']);
    }
}
