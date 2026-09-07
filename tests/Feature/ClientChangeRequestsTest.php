<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Client change register of 7 September 2026 (CR-01 .. CR-13).
 */
class ClientChangeRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function operator(): User
    {
        return User::where('email', 'shaban@example.com')->firstOrFail();
    }

    // CR-02 / CR-03 ---------------------------------------------------------

    public function test_listing_pages_show_the_add_button_once(): void
    {
        $users = $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk()->getContent();
        $roles = $this->actingAs($this->admin())->get(route('admin.roles.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($users, '+ Add New User'));
        $this->assertSame(1, substr_count($roles, '+ Add New Role'));
    }

    // CR-11 -----------------------------------------------------------------

    public function test_employee_form_accepts_more_than_four_documents(): void
    {
        $employee = Employee::firstOrFail();
        $before = $employee->documents()->count();

        $this->actingAs($this->admin())->get(route('admin.hr.employees.edit', $employee))
            ->assertOk()
            ->assertSee('+ Add Document')
            ->assertSee('document-row-template');

        $rows = collect(['IQAMA', 'Passport', 'Contract', 'Medical Insurance', 'Driving License'])
            ->map(fn ($type, $i) => [
                'document_type' => $type,
                'document_number' => 'DOC-'.$i,
                'issue_date' => now()->subYear()->toDateString(),
                'expiry_date' => now()->addYear()->toDateString(),
            ])->all();

        $this->actingAs($this->admin())
            ->put(route('admin.hr.employees.update', $employee), [
                'employee_code' => $employee->employee_code,
                'first_name' => $employee->first_name,
                'contract_type' => $employee->contract_type,
                'employee_classification' => 'Sponsorship',
                'basic_salary' => $employee->basic_salary,
                'payment_method' => 'Bank Transfer',
                'status' => 'active',
                'documents' => $rows,
            ])
            ->assertRedirect(route('admin.hr.employees.index'));

        $this->assertSame($before + 5, $employee->documents()->count());
        $this->assertDatabaseHas('employee_documents', ['employee_id' => $employee->id, 'document_type' => 'Driving License']);
    }

    // CR-05 / CR-06 / CR-07 ---------------------------------------------------

    public function test_role_code_is_derived_from_the_name_and_permissions_can_be_copied(): void
    {
        $purchase = Role::where('code', 'PURCHASE_MANAGER')->firstOrFail();
        $department = Department::where('code', 'PUR')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.roles.store'), [
                'name' => 'Purchase Coordinator',
                'department_id' => $department->id,
                'level' => 3,
                'access_scope' => 'Company Level',
                'status' => 'active',
                'copy_permissions_from' => $purchase->id,
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Purchase Coordinator')->firstOrFail();
        $this->assertSame('PURCHASE_COORDINATOR', $role->code);
        $this->assertSame($purchase->permissions()->count(), $role->permissions()->count());

        // Same name again gets a unique code instead of a validation dead end.
        $this->actingAs($this->admin())->post(route('admin.roles.store'), [
            'name' => 'Purchase Coordinator',
            'level' => 3,
            'access_scope' => 'Company Level',
            'status' => 'active',
        ])->assertRedirect();
        $this->assertTrue(Role::where('code', 'PURCHASE_COORDINATOR_2')->exists());
    }

    public function test_role_form_save_keeps_permissions_it_did_not_show_and_never_rewrites_the_code(): void
    {
        $role = Role::where('code', 'FINANCE_MANAGER')->firstOrFail();
        $post = Permission::where('module', 'Journal Entries')->where('action', 'post')->firstOrFail();
        $dashboardView = Permission::where('module', 'Dashboard')->where('action', 'view')->firstOrFail();
        $dashboardExport = Permission::where('module', 'Dashboard')->where('action', 'export')->firstOrFail();
        $dashboardCreate = Permission::where('module', 'Dashboard')->where('action', 'create')->firstOrFail();

        $this->assertTrue($role->permissions->contains($post));
        $this->assertTrue($role->permissions->contains($dashboardExport));

        $this->actingAs($this->admin())
            ->put(route('admin.roles.update', $role), [
                'name' => $role->name,
                'code' => 'HACKED_CODE',
                'department_id' => $role->department_id,
                'level' => $role->level,
                'access_scope' => $role->access_scope,
                'status' => 'active',
                // Only these two cells were rendered; view is unticked, create ticked.
                'visible_permission_ids' => [$dashboardView->id, $dashboardCreate->id],
                'permissions' => [$dashboardCreate->id],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role->refresh()->load('permissions');
        $this->assertSame('FINANCE_MANAGER', $role->code);
        $this->assertTrue($role->permissions->contains($post), 'hidden Post permission must survive the save');
        $this->assertTrue($role->permissions->contains($dashboardExport), 'hidden Export permission must survive the save');
        $this->assertTrue($role->permissions->contains($dashboardCreate));
        $this->assertFalse($role->permissions->contains($dashboardView), 'a visible unticked box is revoked');
    }

    public function test_permission_matrix_filters_by_department_group_and_preserves_hidden_grants(): void
    {
        $hr = Role::where('code', 'HR_MANAGER')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.roles.permission-matrix', ['role' => $hr->id]))
            ->assertOk()
            ->assertSee('<th class="all-col">All</th>', false)
            ->assertSee('data-module="Payroll"', false)
            ->assertDontSee('data-module="Stock Adjustments"', false);

        $this->actingAs($this->admin())
            ->get(route('admin.roles.permission-matrix', ['role' => $hr->id, 'group' => 'all']))
            ->assertOk()
            ->assertSee('data-module="Stock Adjustments"', false);

        $payrollView = Permission::where('module', 'Payroll')->where('action', 'view')->firstOrFail();
        $hrView = Permission::where('module', 'HR')->where('action', 'view')->firstOrFail();
        $attendanceView = Permission::where('module', 'Attendance')->where('action', 'view')->firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.roles.permission-matrix.update'), [
                'role_id' => $hr->id,
                'group' => 'HR',
                'visible_permission_ids' => [$payrollView->id, $hrView->id],
                'permissions' => [$hrView->id],
            ])
            ->assertRedirect(route('admin.roles.permission-matrix', ['role' => $hr->id, 'group' => 'HR']));

        $hr->refresh()->load('permissions');
        $this->assertFalse($hr->permissions->contains($payrollView));
        $this->assertTrue($hr->permissions->contains($hrView));
        $this->assertTrue($hr->permissions->contains($attendanceView), 'a module outside the visible set keeps its grant');
    }

    // CR-01 / CR-09 / CR-10 ---------------------------------------------------

    public function test_master_records_can_be_created_inline_as_json(): void
    {
        $admin = $this->admin();

        $customer = $this->actingAs($admin)
            ->postJson(route('admin.master.customers.store'), ['name' => 'Wafiq Contracting', 'type' => 'Company', 'status' => 'active'])
            ->assertCreated()
            ->assertJsonStructure(['id', 'label', 'code'])
            ->assertJsonPath('label', 'Wafiq Contracting')
            ->json();
        $this->assertStringStartsWith('CUS-', Customer::findOrFail($customer['id'])->code);

        $branch = $this->actingAs($admin)
            ->postJson(route('admin.master.branches.store'), ['name' => 'Dammam Branch', 'city' => 'Dammam', 'status' => 'active'])
            ->assertCreated()->json();
        $this->assertStringStartsWith('BR-', Branch::findOrFail($branch['id'])->code);

        $department = $this->actingAs($admin)
            ->postJson(route('admin.master.departments.store'), ['name' => 'Maintenance', 'status' => 'active'])
            ->assertCreated()->json();
        $this->assertSame('MAIN', Department::findOrFail($department['id'])->code);

        $this->actingAs($admin)
            ->postJson(route('admin.master.designations.store'), ['name' => 'Maintenance Lead', 'department_id' => $department['id'], 'status' => 'active'])
            ->assertCreated()
            ->assertJsonPath('parent', $department['id']);

        $this->actingAs($admin)
            ->postJson(route('admin.master.suppliers.store'), ['name' => 'Al Noor Trading', 'status' => 'active'])
            ->assertCreated();
        $this->assertStringStartsWith('SUP-', Supplier::where('name', 'Al Noor Trading')->firstOrFail()->code);

        // Validation errors come back as JSON so the dialog can show them inline.
        $this->actingAs($admin)
            ->postJson(route('admin.master.customers.store'), ['type' => 'Company', 'status' => 'active'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['name']]);

        // Full forms still work exactly as before.
        $this->actingAs($admin)
            ->post(route('admin.master.customers.store'), ['name' => 'Form Client', 'code' => 'CUS-FORM', 'type' => 'Company', 'status' => 'active'])
            ->assertRedirect(route('admin.master.customers.index'));
    }

    public function test_inline_creation_respects_permissions(): void
    {
        $this->actingAs($this->operator())
            ->postJson(route('admin.master.customers.store'), ['name' => 'Nope', 'type' => 'Company', 'status' => 'active'])
            ->assertForbidden();

        // The "+ New" button is only rendered for users who may create the record.
        $this->actingAs($this->admin())->get(route('admin.master.projects.create'))
            ->assertOk()
            ->assertSee('data-quick-create="qc-customer"', false)
            ->assertSee('data-quick-create="qc-manager"', false);
    }

    public function test_a_role_and_a_manager_account_can_be_created_from_a_dialog(): void
    {
        $department = Department::where('code', 'PRJ')->firstOrFail();
        $projectManager = Role::where('code', 'PROJECT_MANAGER')->firstOrFail();

        $role = $this->actingAs($this->admin())
            ->postJson(route('admin.roles.store'), [
                'name' => 'Projects Coordinator',
                'department_id' => $department->id,
                'level' => 3,
                'access_scope' => 'Project Level',
                'status' => 'active',
                'copy_permissions_from' => $projectManager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('code', 'PROJECTS_COORDINATOR')
            ->json();

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), [
                'name' => 'Faisal Manager',
                'email' => 'faisal@example.com',
                'department_id' => $department->id,
                'role_id' => $role['id'],
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('must_change_password', true);

        $user = User::findOrFail($response->json('id'));
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('123456', $user->password));
        $this->assertSame('Projects Coordinator', $user->primaryRole()?->name);

        // The new account is forced onto the password screen.
        $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertRedirect(route('admin.password.change'));
    }

    public function test_organization_structure_hub_replaces_three_menu_entries(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Organization Structure')
            ->assertDontSee('<span>Branches</span>', false)
            ->assertDontSee('<span>Designations</span>', false);

        $this->actingAs($this->admin())->get(route('admin.master.organization'))
            ->assertOk()
            ->assertSee('Branches')
            ->assertSee('Departments')
            ->assertSee('Designations')
            ->assertSee(route('admin.master.designations.create'));

        // The full lists are still reachable.
        $this->actingAs($this->admin())->get(route('admin.master.branches.index'))->assertOk();

        $this->actingAs($this->operator())->get(route('admin.master.organization'))->assertForbidden();
    }

    // CR-04 -----------------------------------------------------------------

    public function test_user_classification_is_saved_and_kept_in_step_with_the_linked_employee(): void
    {
        $role = Role::where('code', 'OPERATOR')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.users.store'), [
                'name' => 'Free Lancer',
                'email' => 'freelancer@example.com',
                'employee_classification' => 'Freelancer',
                'role_id' => $role->id,
                'status' => 'active',
                'password' => 'Secret123!',
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'freelancer@example.com')->firstOrFail();
        $this->assertSame('Freelancer', $user->employee_classification);
        $this->assertFalse($user->must_change_password);

        $employee = Employee::firstOrFail();
        $employee->update(['user_id' => $user->id, 'employee_classification' => 'Sponsorship']);

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'employee_classification' => 'Freelancer',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('Freelancer', $employee->refresh()->employee_classification);

        // And the other way round: saving the employee updates the login.
        $this->actingAs($this->admin())
            ->put(route('admin.hr.employees.update', $employee), [
                'employee_code' => $employee->employee_code,
                'first_name' => $employee->first_name,
                'contract_type' => $employee->contract_type,
                'employee_classification' => 'Sponsorship',
                'basic_salary' => $employee->basic_salary,
                'payment_method' => 'Bank Transfer',
                'status' => 'active',
                'user_id' => $user->id,
            ])
            ->assertRedirect();

        $this->assertSame('Sponsorship', $user->refresh()->employee_classification);
        $this->actingAs($this->admin())->get(route('admin.users.show', $user))->assertOk()->assertSee('Sponsorship');
    }

    // CR-12 / CR-13 -----------------------------------------------------------

    public function test_purchase_order_lines_carry_description_discount_and_line_vat(): void
    {
        $supplier = Supplier::firstOrFail();
        $warehouse = Warehouse::firstOrFail();
        [$machine, $cable] = Item::take(2)->get()->all();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'po_date' => now()->toDateString(),
                'warehouse_id' => $warehouse->id,
                'vat_rate' => 15,
                'lines' => [
                    // The client's reference line: 1 x 28,500 at 15% = 32,775.00
                    ['item_id' => $machine->id, 'description' => "Cable Pulling Machine with TUV Certified Operator\nRope Length 1000m", 'quantity' => 1, 'unit_price' => 28500],
                    // 10% discount and a zero-rated line VAT that overrides the order default.
                    ['item_id' => $cable->id, 'quantity' => 2, 'unit_price' => 100, 'discount_percent' => 10, 'vat_rate' => 0],
                ],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::latest('id')->with('lines')->firstOrFail();
        $first = $order->lines->firstWhere('item_id', $machine->id);
        $second = $order->lines->firstWhere('item_id', $cable->id);

        $this->assertStringContainsString('TUV Certified Operator', $first->description);
        $this->assertSame('28500.00', (string) $first->taxable_amount);
        $this->assertSame('4275.00', (string) $first->vat_amount);
        $this->assertSame('32775.00', (string) $first->total_amount);

        $this->assertSame('10.00', (string) $second->discount_percent);
        $this->assertSame('20.00', (string) $second->discount_amount);
        $this->assertSame('180.00', (string) $second->taxable_amount);
        $this->assertSame('0.00', (string) $second->vat_rate);
        $this->assertSame('0.00', (string) $second->vat_amount);
        $this->assertSame('180.00', (string) $second->total_amount);
        $this->assertSame(90.0, $second->netUnitPrice());

        $this->assertSame('28680.00', (string) $order->taxable_amount);
        $this->assertSame('20.00', (string) $order->discount_amount);
        $this->assertSame('4275.00', (string) $order->vat_amount);
        $this->assertSame('32955.00', (string) $order->total_amount);

        $this->actingAs($this->admin())->get(route('admin.inventory.purchase-orders.show', $order))
            ->assertOk()
            ->assertSee('TUV Certified Operator')
            ->assertSee('10.00%');

        // A goods receipt raised from the order values stock at the discounted price.
        $this->actingAs($this->admin())->post(route('admin.inventory.purchase-orders.approve', $order))->assertRedirect();
        $this->actingAs($this->admin())
            ->get(route('admin.inventory.goods-receipts.create', ['purchase_order' => $order->id]))
            ->assertOk()
            ->assertSee('value="90"', false);

        $this->assertSame(['gross' => 200.0, 'discount_amount' => 20.0, 'taxable_amount' => 180.0, 'vat_amount' => 27.0, 'total_amount' => 207.0], PurchaseOrderLine::calculate(2, 100, 10, 15));
    }

    public function test_supplier_quotations_can_be_attached_downloaded_and_removed(): void
    {
        Storage::fake(PurchaseOrderAttachment::DISK);

        $supplier = Supplier::firstOrFail();
        $warehouse = Warehouse::firstOrFail();
        $item = Item::firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'po_date' => now()->toDateString(),
                'warehouse_id' => $warehouse->id,
                'vat_rate' => 15,
                'lines' => [['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 10]],
                'quotations' => [
                    UploadedFile::fake()->create('quotation.pdf', 120, 'application/pdf'),
                    UploadedFile::fake()->image('scan.jpg'),
                ],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::latest('id')->firstOrFail();
        $this->assertSame(2, $order->attachments()->count());
        $attachment = $order->attachments()->where('file_name', 'quotation.pdf')->firstOrFail();
        Storage::disk(PurchaseOrderAttachment::DISK)->assertExists($attachment->file_path);

        $this->actingAs($this->admin())
            ->get(route('admin.inventory.purchase-orders.attachments.download', [$order, $attachment]))
            ->assertOk()
            ->assertDownload('quotation.pdf');

        // Files are private: a company-level role without Purchase Orders access is refused
        // (site-scoped roles such as Operator never even resolve the order and get 404).
        $this->actingAs(User::where('email', 'waleed@example.com')->firstOrFail())
            ->get(route('admin.inventory.purchase-orders.attachments.download', [$order, $attachment]))
            ->assertForbidden();
        $this->actingAs($this->operator())
            ->get(route('admin.inventory.purchase-orders.attachments.download', [$order, $attachment]))
            ->assertNotFound();

        // Unsupported files are rejected without leaving a half-saved order.
        $this->actingAs($this->admin())
            ->from(route('admin.inventory.purchase-orders.create'))
            ->post(route('admin.inventory.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'po_date' => now()->toDateString(),
                'warehouse_id' => $warehouse->id,
                'vat_rate' => 15,
                'lines' => [['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 10]],
                'quotations' => [UploadedFile::fake()->create('macro.exe', 10, 'application/octet-stream')],
            ])
            ->assertSessionHasErrors('quotations.0');
        $this->assertSame($order->id, PurchaseOrder::latest('id')->firstOrFail()->id);

        // Draft orders may drop a quotation; approved ones keep them but still accept new ones.
        $this->actingAs($this->admin())
            ->delete(route('admin.inventory.purchase-orders.attachments.destroy', [$order, $attachment]))
            ->assertRedirect();
        $this->assertSame(1, $order->attachments()->count());
        Storage::disk(PurchaseOrderAttachment::DISK)->assertMissing($attachment->file_path);

        $this->actingAs($this->admin())->post(route('admin.inventory.purchase-orders.approve', $order))->assertRedirect();

        $this->actingAs($this->admin())
            ->post(route('admin.inventory.purchase-orders.attachments.store', $order), [
                'quotations' => [UploadedFile::fake()->create('revised-quotation.pdf', 50, 'application/pdf')],
            ])
            ->assertRedirect();
        $this->assertSame(2, $order->attachments()->count());

        $remaining = $order->attachments()->firstOrFail();
        $this->actingAs($this->admin())
            ->delete(route('admin.inventory.purchase-orders.attachments.destroy', [$order, $remaining]))
            ->assertSessionHasErrors('quotations');
        $this->assertSame(2, $order->attachments()->count());
    }

    public function test_purchase_order_form_renders_the_rich_line_editor(): void
    {
        $this->actingAs($this->admin())->get(route('admin.inventory.purchase-orders.create'))
            ->assertOk()
            ->assertSee('po-line-template')
            ->assertSee('+ Add Line')
            ->assertSee('Disc %')
            ->assertSee('Supplier Quotation')
            ->assertSee('data-quick-create="qc-supplier"', false);
    }
}
