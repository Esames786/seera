<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\StockIssue;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\Workspace\SiteWorkspacePanels as Panels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private Site $other;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $project = Project::create(['name' => 'Project A', 'code' => 'A']);
        $other = Project::create(['name' => 'Private project', 'code' => 'B']);
        $this->site = Site::create(['code' => 'SITE-A', 'name' => 'Public site', 'project_id' => $project->id, 'status' => 'active', 'latitude' => 24.5, 'longitude' => 46.5, 'geofence_radius' => 300, 'geofence_enabled' => true]);
        $this->other = Site::create(['code' => 'SITE-B', 'name' => 'Private site', 'project_id' => $other->id, 'status' => 'active']);
        $this->actor = $this->actor();
    }

    private function actor(?array $modules = null, string $scope = 'Company Level'): User
    {
        $role = Role::create(['name' => 'Viewer', 'code' => uniqid(), 'status' => 'active', 'access_scope' => $scope]);
        foreach ($modules ?? ['Sites', 'Projects', 'Customers', 'HR', 'Warehouses', 'Warehouse Stock', 'Stock Ledger', 'Purchase Requests', 'Purchase Orders', 'Goods Receipts', 'Stock Issues', 'Site Expenses', 'Attendance', 'Activity Logs'] as $module) {
            foreach (['view', 'edit', 'create'] as $action) {
                $role->permissions()->attach(Permission::firstOrCreate(compact('module', 'action'))->id);
            }
        }
        $user = User::factory()->create(['status' => 'active', 'project_id' => $this->site->project_id, 'site_id' => $this->site->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }

    private function panel(string $panel, array $query = [])
    {
        return $this->getJson(route('admin.master.sites.workspace.panel', [$this->site, $panel, ...$query]));
    }

    public function test_readonly_view_manage_identity_geofence_and_all_panel_endpoints(): void
    {
        $this->actingAs($this->actor)->get(route('admin.master.sites.show', $this->site))->assertOk()->assertSee('SITE-A')->assertSee('Project A')->assertDontSee('name="_save_action"', false)->assertSee('configuration only');
        $this->get(route('admin.master.sites.edit', $this->site))->assertOk()->assertSee('data-workspace-guard-tabs', false)->assertSee('name="latitude"', false);
        foreach (array_keys(Panels::panels()) as $panel) {
            $response = $this->panel($panel)->assertOk();
            $this->assertStringNotContainsString('data-related-save', $response->json('html'));
        }
        $this->panel('location')->assertSee('300')->assertSee('24.5')->assertSee('not implemented');
        $this->panel('attendance')->assertSee('manually recorded');
    }

    public function test_save_actions_safe_origin_and_project_nested_workspace(): void
    {
        $this->actingAs($this->actor);
        $data = ['name' => 'Updated site', 'code' => $this->site->code, 'status' => 'active', 'project_id' => $this->site->project_id];
        $url = route('admin.master.sites.update', $this->site);
        foreach (['stay' => route('admin.master.sites.edit', $this->site), 'close' => route('admin.master.sites.index'), 'new' => route('admin.master.sites.create')] as $action => $destination) {
            $this->put($url, $data + ['_save_action' => $action])->assertSessionHasNoErrors()->assertRedirect($destination);
        }
        $origin = '/admin/master/projects/'.$this->site->project_id.'#sites';
        $this->put($url, $data + ['_return_to' => $origin])->assertRedirect($origin);
        $this->put($url, $data + ['_return_to' => '//evil.example'])->assertRedirect(route('admin.master.sites.index'));
        $this->get(route('admin.master.sites.project.edit', [$this->site->project_id, $this->site]))->assertOk()->assertSee('data-workspace', false);
        $this->putJson($url, array_replace($data, ['project_id' => $this->other->project_id]))->assertUnprocessable();
        $this->assertSame($this->site->project_id, $this->site->fresh()->project_id);
        $supervisor = User::factory()->create(['project_id' => $this->other->project_id]);
        $this->site->update(['supervisor_id' => $supervisor->id]);
        $restricted = $this->actor(['Sites'], 'Project Level');
        $this->actingAs($restricted)->get(route('admin.master.sites.edit', $this->site))->assertOk()->assertSee('value="'.$supervisor->id.'" selected', false);
        $this->put($url, $data + ['supervisor_id' => $supervisor->id])->assertSessionHasNoErrors();
        $this->assertSame($supervisor->id, $this->site->fresh()->supervisor_id);
        $foreign = User::factory()->create(['project_id' => $this->other->project_id]);
        $this->putJson($url, $data + ['supervisor_id' => $foreign->id])->assertForbidden();
    }

    public function test_panels_hidden_and_direct_requests_denied_without_child_permissions(): void
    {
        $this->actingAs($this->actor(['Sites']));
        $view = $this->get(route('admin.master.sites.show', $this->site))->assertOk();
        foreach (array_keys(Panels::panels()) as $panel) {
            if ($panel === 'location') {
                continue;
            }
            $view->assertDontSee('data-workspace-related="'.$panel.'"', false);
            $this->panel($panel)->assertForbidden();
        }
        $this->get(route('admin.master.sites.expenses.create', $this->site))->assertForbidden();
        $this->postJson(route('admin.master.sites.expenses.store', $this->site), [])->assertForbidden();
    }

    public function test_staff_warehouses_counts_pagination_and_stock_permissions(): void
    {
        $item = Item::create(['item_code' => 'STOCK', 'name' => 'Stock item']);
        foreach ([$this->site, $this->other] as $site) {
            Employee::create(['employee_code' => $site->code, 'first_name' => $site->name, 'project_id' => $site->project_id, 'site_id' => $site->id]);
            $warehouse = Warehouse::create(['code' => $site->code, 'name' => $site->name, 'project_id' => $site->project_id, 'site_id' => $site->id]);
            WarehouseStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1, 'total_value' => $site->is($this->site) ? 123 : 98765]);
        }
        $this->actingAs($this->actor);
        foreach (['staff', 'warehouses'] as $panel) {
            $this->panel($panel)->assertOk()->assertSee('Public site')->assertDontSee('Private site');
        }
        $query = Panels::query($this->site, 'staff', $this->actor);
        $this->assertSame(1, $query->count());
        for ($i = 0; $i < 11; $i++) {
            Employee::create(['employee_code' => 'P'.$i, 'first_name' => 'Paged '.$i, 'project_id' => $this->site->project_id, 'site_id' => $this->site->id]);
        }
        $this->panel('staff')->assertOk()->assertSee('data-panel-load');
        $this->assertSame(10, Panels::data($this->site, 'staff', $this->actor)['rows']->count());
        $limited = $this->actor(['Sites', 'Warehouses']);
        $this->actingAs($limited);
        $this->panel('warehouses')->assertOk()->assertDontSee('Stock value')->assertDontSee('Stocked items');
        $restricted = $this->actor(scope: 'Site Level');
        $this->actingAs($restricted);
        $this->panel('warehouses')->assertOk()->assertSee('123.00')->assertDontSee('98,765.00');
        $visible = Panels::data($this->site, 'warehouses', $restricted)['rows'];
        $this->assertSame(1, $visible->total());
        $this->assertEquals(123, $visible->sum('stocks_sum_total_value'));
        $this->getJson(route('admin.master.sites.workspace.panel', [$this->other, 'staff']))->assertNotFound();
        $this->panel('staff', ['site_id' => $this->other->id])->assertUnprocessable();
    }

    public function test_procurement_uses_exact_site_and_receipts_use_order_not_warehouse_guess(): void
    {
        $supplier = Supplier::create(['code' => 'SUP', 'name' => 'Supplier']);
        $warehouse = Warehouse::create(['code' => 'WH', 'name' => 'WH', 'project_id' => $this->site->project_id, 'site_id' => $this->site->id]);
        foreach ([$this->site, $this->other] as $site) {
            PurchaseRequest::create(['pr_number' => 'PR-'.$site->code, 'request_date' => today(), 'project_id' => $site->project_id, 'site_id' => $site->id]);
            $order = PurchaseOrder::create(['po_number' => 'PO-'.$site->code, 'po_date' => today(), 'supplier_id' => $supplier->id, 'project_id' => $site->project_id, 'site_id' => $site->id]);
            GoodsReceipt::create(['grn_number' => 'GRN-'.$site->code, 'received_date' => today(), 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'purchase_order_id' => $order->id]);
        }
        $this->actingAs($this->actor);
        foreach (['requests', 'orders', 'receipts'] as $panel) {
            $this->panel($panel)->assertOk()->assertSee('SITE-A')->assertDontSee('SITE-B');
        }
    }

    public function test_materials_are_posted_issues_only_not_receipt_consumption(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH', 'name' => 'WH', 'project_id' => $this->site->project_id, 'site_id' => $this->site->id]);
        $item = Item::create(['item_code' => 'I', 'name' => 'Material']);
        foreach (['posted', 'draft'] as $status) {
            $issue = StockIssue::create(['issue_number' => 'ISS-'.$status, 'issue_date' => today(), 'warehouse_id' => $warehouse->id, 'project_id' => $this->site->project_id, 'site_id' => $this->site->id, 'status' => $status]);
            StockLedgerEntry::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'project_id' => $this->site->project_id, 'site_id' => $this->site->id, 'movement_type' => 'issue', 'reference_type' => StockIssue::class, 'reference_id' => $issue->id, 'reference_number' => $issue->issue_number, 'movement_date' => today(), 'out_quantity' => 2, 'value' => 20]);
        }
        $this->actingAs($this->actor);
        $this->panel('materials')->assertOk()->assertSee('ISS-posted')->assertDontSee('ISS-draft');
        $this->assertEquals(20, Panels::query($this->site, 'materials', $this->actor)->sum('value'));
    }

    public function test_expense_context_preselection_permission_and_forged_parent_block(): void
    {
        $this->actingAs($this->actor);
        $response = $this->get(route('admin.master.sites.expenses.create', $this->site))->assertOk();
        $this->assertSame($this->site->id, $response->viewData('expense')->site_id);
        $this->assertSame($this->site->project_id, $response->viewData('expense')->project_id);
        $this->assertSame(route('admin.master.sites.expenses.store', $this->site), $response->viewData('formAction'));
        $this->postJson(route('admin.master.sites.expenses.store', $this->site), ['site_id' => $this->other->id, 'project_id' => $this->other->project_id])->assertUnprocessable();
        $this->assertSame(0, SiteExpense::count());
        $category = ExpenseCategory::create(['code' => 'EX', 'name' => 'Expense']);
        foreach ([$this->site, $this->other] as $site) {
            SiteExpense::create(['expense_number' => 'SE-'.$site->code, 'expense_date' => today(), 'submitted_by_user_id' => $this->actor->id, 'project_id' => $site->project_id, 'site_id' => $site->id, 'expense_category_id' => $category->id, 'payment_type' => 'Supplier Credit', 'description' => 'context', 'taxable_amount' => 100, 'total_amount' => 100]);
        }
        $this->panel('expenses')->assertOk()->assertSee('SE-SITE-A')->assertDontSee('SE-SITE-B');
    }

    public function test_attendance_and_activity_use_visible_site_records_not_legacy_names(): void
    {
        foreach ([$this->site, $this->other] as $site) {
            $employee = Employee::create(['employee_code' => $site->code, 'first_name' => $site->name, 'project_id' => $site->project_id, 'site_id' => $site->id]);
            AttendanceRecord::create(['employee_id' => $employee->id, 'project_id' => $site->project_id, 'site_id' => $site->id, 'attendance_date' => today(), 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'unknown']);
            ActivityLog::create(['user_id' => $this->actor->id, 'user_name' => 'Actor', 'module' => 'Sites', 'action' => $site->name, 'description' => '[Site #'.$site->id.'] '.$site->code]);
        }
        ActivityLog::create(['user_id' => $this->actor->id, 'user_name' => 'Actor', 'module' => 'Sites', 'action' => 'LEGACY UNSAFE', 'description' => $this->site->name]);
        $this->actingAs($this->actor);
        $this->panel('attendance')->assertOk()->assertSee('Public site')->assertDontSee('Private site');
        $this->panel('activity')->assertOk()->assertSee('Public site')->assertDontSee('Private site')->assertDontSee('LEGACY UNSAFE');
    }

    public function test_nested_expense_save_close_submit_failure_and_safe_return_context(): void
    {
        $this->actingAs($this->actor);
        $category = ExpenseCategory::create(['code' => 'TEST', 'name' => 'Test', 'status' => 'active', 'invoice_photo_required' => false]);
        $supplier = Supplier::create(['code' => 'SUP', 'name' => 'Supplier', 'status' => 'active']);
        $data = ['expense_date' => today()->toDateString(), 'expense_category_id' => $category->id, 'supplier_id' => $supplier->id, 'payment_type' => 'Supplier Credit', 'taxable_amount' => 100, 'vat_applicable' => 0, 'description' => 'Workspace draft'];
        $url = route('admin.master.sites.expenses.store', $this->site);
        $origin = route('admin.master.sites.show', $this->site, false).'#expenses';
        $this->post($url, $data + ['_save_action' => 'close'])->assertSessionHasNoErrors()->assertRedirect($origin);
        $this->post($url, $data + ['_save_action' => 'stay'])->assertSessionHasNoErrors();
        $expense = SiteExpense::latest('id')->firstOrFail();
        $this->assertSame($this->site->id, $expense->site_id);
        $this->get(route('admin.site-expenses.edit', [$expense, 'return_to' => $origin]))->assertOk()->assertSee('name="_return_to"', false)->assertSee($origin, false);
        $this->post($url, $data + ['_intent' => 'submit'])->assertRedirect(route('admin.site-expenses.show', [SiteExpense::latest('id')->firstOrFail(), 'return_to' => $origin]));
        $this->assertSame('draft', SiteExpense::latest('id')->first()->status); // No workflow configured: draft retained, no approval invented.
        $this->get(route('admin.site-expenses.show', [$expense, 'return_to' => $origin]))->assertOk()->assertSee($origin, false);
        $reviewer = $this->actor(['Site Expenses']);
        $reviewer->roles()->first()->permissions()->attach(Permission::firstOrCreate(['module' => 'Site Expenses', 'action' => 'approve'])->id);
        $workflow = ApprovalWorkflow::create(['name' => 'Site reviewers', 'module' => 'Site Expenses', 'trigger_action' => 'Expense Submitted', 'scope' => 'All Projects', 'auto_posting' => 'Create Accounting Entry', 'status' => 'active']);
        $workflow->steps()->create(['step_no' => 1, 'approver_role_id' => $reviewer->roles()->first()->id, 'is_required' => true, 'can_reject' => false]);
        $this->post($url, $data + ['_intent' => 'submit'])->assertSessionHasNoErrors()->assertRedirect(route('admin.site-expenses.show', [SiteExpense::latest('id')->firstOrFail(), 'return_to' => $origin]));
        $submitted = SiteExpense::latest('id')->firstOrFail();
        $this->assertSame('pending', $submitted->status);
        $this->assertFalse($submitted->accounting_posted);
    }

    public function test_arabic_view_is_readonly_and_does_not_log_or_mutate_business_data(): void
    {
        $before = $this->site->fresh()->getAttributes();
        $logs = ActivityLog::count();
        $this->actor->update(['language' => 'Arabic']);
        $this->actingAs($this->actor)->get(route('admin.master.sites.show', $this->site))->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('workspace.overview', [], 'ar'));
        foreach (array_keys(Panels::panels()) as $panel) {
            $this->panel($panel)->assertOk();
        }
        $this->assertEquals($before, $this->site->fresh()->getAttributes());
        $this->assertSame($logs, ActivityLog::count());
        $this->assertSame(0, SiteExpense::count());
    }
}
