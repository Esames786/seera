<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\ApprovalWorkflow;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Designation;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedRolesAndPermissions();
        $this->seedDesignations();
        $this->seedCompanyProfile();
        $this->seedBranches();
        $this->seedPartners();
        $this->seedUsers();
        $this->seedProjectsSitesWarehouses();
        $this->seedWorkflows();
        $this->seedActivityLogs();
        $this->call(Phase3HrSeeder::class);
        $this->call(Phase4AccountingSeeder::class);
        $this->call(Phase6InventorySeeder::class);
    }

    private function seedDepartments(): void
    {
        $departments = [
            ['name' => 'Administration', 'code' => 'ADMIN', 'description' => 'General management and access control'],
            ['name' => 'Accounts', 'code' => 'FIN', 'description' => 'Accounts Manager, Account Assistant, payables and receivables'],
            ['name' => 'Human Resource', 'code' => 'HR', 'description' => 'HR Manager, payroll, documents'],
            ['name' => 'Projects', 'code' => 'PRJ', 'description' => 'Project Manager and engineers'],
            ['name' => 'Site Operations', 'code' => 'SITE', 'description' => 'Site In-Charge, mechanics, operators'],
            ['name' => 'Purchase', 'code' => 'PUR', 'description' => 'Purchase Manager, Purchase Assistant, stores'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Marketing and business development'],
        ];

        foreach ($departments as $department) {
            Department::create($department + ['status' => 'active']);
        }
    }

    private function seedRolesAndPermissions(): void
    {
        $departments = Department::pluck('id', 'code');

        // Roles follow the company organization chart. The role is the access
        // level; the job title itself lives on the designation.
        $superAdmin = Role::create([
            'name' => 'Super Admin', 'code' => 'SUPER_ADMIN',
            'department_id' => $departments['ADMIN'], 'level' => 1,
            'access_scope' => 'All Company', 'default_dashboard' => 'Admin Dashboard',
            'mobile_app_access' => true, 'is_system' => true,
            'description' => 'General Manager level. Full access to every module and setting.',
        ]);

        $accountsManager = Role::create([
            'name' => 'Accounts Manager', 'code' => 'FINANCE_MANAGER',
            'department_id' => $departments['FIN'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Finance Dashboard',
            'description' => 'Manages accounting, payables, receivables, VAT and payroll approval.',
        ]);

        $hrManager = Role::create([
            'name' => 'HR Manager', 'code' => 'HR_MANAGER',
            'department_id' => $departments['HR'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Company Level',
            'default_dashboard' => 'HR Dashboard',
            'description' => 'Manages employees, attendance, leaves and payroll.',
        ]);

        $projectManager = Role::create([
            'name' => 'Project Manager', 'code' => 'PROJECT_MANAGER',
            'department_id' => $departments['PRJ'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Project Level',
            'default_dashboard' => 'Project Dashboard', 'mobile_app_access' => true,
            'description' => 'Manages assigned projects, budgets and site approvals.',
        ]);

        $purchaseManager = Role::create([
            'name' => 'Purchase Manager', 'code' => 'PURCHASE_MANAGER',
            'department_id' => $departments['PUR'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Inventory Dashboard',
            'description' => 'Owns purchase requests, purchase orders and supplier relationships.',
        ]);

        $marketingManager = Role::create([
            'name' => 'Marketing Manager', 'code' => 'MARKETING_MANAGER',
            'department_id' => $departments['MKT'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Admin Dashboard',
            'description' => 'Manages customers, tenders and business development.',
        ]);

        $inventoryManager = Role::create([
            'name' => 'Inventory Manager', 'code' => 'INVENTORY_MANAGER',
            'department_id' => $departments['PUR'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Inventory Dashboard',
            'description' => 'Manages warehouses, materials and stock movements.',
        ]);

        $siteInCharge = Role::create([
            'name' => 'Site In-Charge', 'code' => 'SITE_SUPERVISOR',
            'department_id' => $departments['SITE'], 'parent_id' => $projectManager->id,
            'level' => 3, 'access_scope' => 'Site Level',
            'default_dashboard' => 'Site Dashboard', 'mobile_app_access' => true,
            'description' => 'Runs the site: attendance, site expenses and approvals for the assigned project and site.',
        ]);

        $accountAssistant = Role::create([
            'name' => 'Account Assistant', 'code' => 'ACCOUNT_ASSISTANT',
            'department_id' => $departments['FIN'], 'parent_id' => $accountsManager->id,
            'level' => 3, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Finance Dashboard',
            'description' => 'Enters bills, invoices and journals for the Accounts Manager to approve.',
        ]);

        $purchaseAssistant = Role::create([
            'name' => 'Purchase Assistant', 'code' => 'PURCHASE_ASSISTANT',
            'department_id' => $departments['PUR'], 'parent_id' => $purchaseManager->id,
            'level' => 3, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Inventory Dashboard',
            'description' => 'Prepares purchase orders and follows up deliveries.',
        ]);

        $warehouseIncharge = Role::create([
            'name' => 'Warehouse Incharge', 'code' => 'WAREHOUSE_INCHARGE',
            'department_id' => $departments['PUR'], 'parent_id' => $inventoryManager->id,
            'level' => 3, 'access_scope' => 'Warehouse Level',
            'default_dashboard' => 'Inventory Dashboard', 'mobile_app_access' => true,
            'description' => 'Receives, issues, transfers and adjusts stock for assigned warehouses. No master data or accounting access.',
        ]);

        Role::create([
            'name' => 'Mechanic', 'code' => 'MECHANIC',
            'department_id' => $departments['SITE'], 'parent_id' => $siteInCharge->id,
            'level' => 4, 'access_scope' => 'Site Level',
            'default_dashboard' => 'Site Dashboard', 'mobile_app_access' => true,
            'description' => 'Submits equipment issues, fuel and maintenance requests.',
        ]);

        Role::create([
            'name' => 'Operator', 'code' => 'OPERATOR',
            'department_id' => $departments['SITE'], 'parent_id' => $siteInCharge->id,
            'level' => 4, 'access_scope' => 'Site Level',
            'default_dashboard' => 'Site Dashboard', 'mobile_app_access' => true,
            'description' => 'Operates machinery on site. Mobile attendance and basic site requests.',
        ]);

        Role::create([
            'name' => 'Site Worker', 'code' => 'SITE_WORKER',
            'department_id' => $departments['SITE'], 'parent_id' => $siteInCharge->id,
            'level' => 4, 'access_scope' => 'Site Level',
            'default_dashboard' => 'Site Dashboard', 'mobile_app_access' => true,
            'description' => 'Mobile attendance and basic site requests only.',
        ]);

        foreach (Permission::MODULES as $module) {
            foreach (Permission::ACTIONS as $action) {
                Permission::create(['module' => $module, 'action' => $action]);
            }
        }

        $superAdmin->permissions()->attach(Permission::pluck('id'));

        $grants = [
            $accountsManager->id => [
                'Dashboard' => ['view', 'export'],
                'Accounting' => ['view', 'create', 'edit', 'approve', 'export'],
                'Payroll' => ['view', 'approve', 'export'],
                'Site Expenses' => ['view', 'approve', 'export'],
                'ZATCA Invoicing' => ['view', 'create', 'edit', 'export', 'retry'],
                'Reports' => ['view', 'export'],
                // Full accounting control for the finance backbone.
                'Accounting Dashboard' => ['view', 'export'],
                'Chart of Accounts' => ['view', 'create', 'edit', 'delete', 'export'],
                'Journal Entries' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'post'],
                'General Ledger' => ['view', 'export'],
                'Accounts Payable' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'process'],
                'Accounts Receivable' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'process'],
                'VAT Management' => ['view', 'edit', 'approve', 'export', 'process'],
                'Financial Reports' => ['view', 'export'],
                'Cost Centers' => ['view', 'create', 'edit', 'delete'],
                'Auto Posting Rules' => ['view', 'create', 'edit', 'delete'],
            ],
            $hrManager->id => [
                'Dashboard' => ['view'],
                'HR' => ['view', 'create', 'edit', 'approve', 'export'],
                'Payroll' => ['view', 'create', 'edit', 'export'],
                'Attendance' => ['view', 'edit', 'approve', 'export'],
                'Reports' => ['view', 'export'],
                // Payroll-related finance summary only.
                'Financial Reports' => ['view'],
            ],
            $projectManager->id => [
                'Dashboard' => ['view'],
                'Projects' => ['view', 'create', 'edit', 'approve', 'export'],
                'Sites' => ['view', 'create', 'edit'],
                'Site Expenses' => ['view', 'approve', 'export'],
                'Attendance' => ['view', 'approve'],
                'Inventory' => ['view'],
                'Reports' => ['view'],
                // Project cost visibility only.
                'Financial Reports' => ['view', 'export'],
                'Cost Centers' => ['view'],
                // Approves site material requests and watches project consumption.
                'Purchase Requests' => ['view', 'approve', 'reject', 'export'],
                'Inventory Reports' => ['view', 'export'],
                'Warehouse Stock' => ['view'],
            ],
            $inventoryManager->id => [
                'Dashboard' => ['view'],
                'Inventory' => ['view', 'create', 'edit', 'approve', 'export'],
                'Equipment' => ['view', 'create', 'edit'],
                'Vehicles' => ['view', 'create', 'edit'],
                'Reports' => ['view'],
                // Full inventory control across every warehouse.
                'Inventory Dashboard' => ['view', 'export'],
                'Items' => ['view', 'create', 'edit', 'delete', 'export'],
                'Item Categories' => ['view', 'create', 'edit', 'delete'],
                'Units' => ['view', 'create', 'edit', 'delete'],
                'Warehouse Stock' => ['view', 'export'],
                'Purchase Requests' => ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'export'],
                'Purchase Orders' => ['view', 'create', 'edit', 'delete', 'approve', 'export'],
                'Goods Receipts' => ['view', 'create', 'edit', 'delete', 'receive', 'post', 'export'],
                'Stock Issues' => ['view', 'create', 'edit', 'delete', 'issue', 'post', 'export'],
                'Stock Transfers' => ['view', 'create', 'edit', 'delete', 'transfer', 'export'],
                'Stock Adjustments' => ['view', 'create', 'edit', 'delete', 'approve', 'adjust', 'post', 'export'],
                'Stock Ledger' => ['view', 'export'],
                'Inventory Reports' => ['view', 'export'],
            ],
            $purchaseManager->id => [
                'Dashboard' => ['view'],
                'Inventory Dashboard' => ['view'],
                'Suppliers' => ['view', 'create', 'edit'],
                'Expense Categories' => ['view'],
                'Warehouses' => ['view'],
                'Purchase Requests' => ['view', 'create', 'edit', 'approve', 'reject', 'export'],
                'Purchase Orders' => ['view', 'create', 'edit', 'approve', 'export'],
                'Goods Receipts' => ['view', 'export'],
                'Inventory Reports' => ['view', 'export'],
            ],
            $accountAssistant->id => [
                'Dashboard' => ['view'],
                'Accounting Dashboard' => ['view'],
                'Chart of Accounts' => ['view'],
                'Journal Entries' => ['view', 'create', 'edit'],
                'General Ledger' => ['view'],
                'Accounts Payable' => ['view', 'create', 'edit'],
                'Accounts Receivable' => ['view', 'create', 'edit'],
                'Financial Reports' => ['view'],
                'Cost Centers' => ['view'],
            ],
            $purchaseAssistant->id => [
                'Dashboard' => ['view'],
                'Inventory Dashboard' => ['view'],
                'Suppliers' => ['view'],
                'Warehouses' => ['view'],
                'Purchase Requests' => ['view', 'create', 'edit'],
                'Purchase Orders' => ['view', 'create', 'edit'],
                'Goods Receipts' => ['view'],
                'Inventory Reports' => ['view'],
            ],
            $marketingManager->id => [
                'Dashboard' => ['view'],
                'Customers' => ['view', 'create', 'edit'],
                'Projects' => ['view'],
                'Reports' => ['view', 'export'],
            ],
            // Warehouse-floor role: moves stock, but owns no master data,
            // no purchasing approval and no accounting.
            $warehouseIncharge->id => [
                'Dashboard' => ['view'],
                'Warehouse Stock' => ['view'],
                'Stock Ledger' => ['view'],
                'Goods Receipts' => ['view', 'create', 'receive', 'post'],
                'Stock Issues' => ['view', 'create', 'issue', 'post'],
                'Stock Transfers' => ['view', 'create', 'transfer'],
                'Stock Adjustments' => ['view', 'create', 'adjust'],
                'Inventory Reports' => ['view', 'export'],
            ],
            $siteInCharge->id => [
                'Dashboard' => ['view'],
                'Attendance' => ['view', 'create', 'edit', 'approve', 'mobile'],
                'Site Expenses' => ['view', 'create', 'approve', 'mobile'],
                'Projects' => ['view'],
                'Sites' => ['view'],
                'Inventory' => ['view', 'create'],
                'Equipment' => ['view', 'mobile'],
                // Raises material requests for the assigned site.
                'Purchase Requests' => ['view', 'create'],
                'Warehouse Stock' => ['view'],
            ],
        ];

        foreach ($grants as $roleId => $moduleActions) {
            $permissionIds = collect($moduleActions)
                ->flatMap(fn ($granted, $module) => Permission::where('module', $module)
                    ->whereIn('action', $granted)
                    ->pluck('id'))
                ->all();
            Role::find($roleId)->permissions()->attach($permissionIds);
        }

        foreach (['MECHANIC', 'OPERATOR', 'SITE_WORKER'] as $roleCode) {
            $role = Role::where('code', $roleCode)->firstOrFail();
            $permissionIds = Permission::where(function ($query) {
                $query->where(fn ($module) => $module->where('module', 'Dashboard')->where('action', 'view'))
                    ->orWhere(fn ($module) => $module->where('module', 'Attendance')->whereIn('action', ['view', 'create', 'mobile']))
                    ->orWhere(fn ($module) => $module->where('module', 'Equipment')->whereIn('action', ['view', 'mobile']));
            })->pluck('id');
            $role->permissions()->attach($permissionIds);
        }
    }

    private function seedDesignations(): void
    {
        $departments = Department::pluck('id', 'code');
        $roles = Role::pluck('id', 'code');

        // Job titles from the organization chart. Each one belongs to a single
        // department, which is what drives the dependent designation dropdown.
        $designations = [
            ['name' => 'General Manager', 'department_id' => $departments['ADMIN'], 'grade' => 'L5', 'default_role_id' => $roles['SUPER_ADMIN'], 'mobile_access_default' => true],
            ['name' => 'Accounts Manager', 'department_id' => $departments['FIN'], 'grade' => 'L3', 'default_role_id' => $roles['FINANCE_MANAGER'], 'mobile_access_default' => false],
            ['name' => 'Account Assistant', 'department_id' => $departments['FIN'], 'grade' => 'L2', 'default_role_id' => $roles['ACCOUNT_ASSISTANT'], 'mobile_access_default' => false],
            ['name' => 'HR Manager', 'department_id' => $departments['HR'], 'grade' => 'L3', 'default_role_id' => $roles['HR_MANAGER'], 'mobile_access_default' => false],
            ['name' => 'Project Manager', 'department_id' => $departments['PRJ'], 'grade' => 'L3', 'default_role_id' => $roles['PROJECT_MANAGER'], 'mobile_access_default' => true],
            ['name' => 'Site In-Charge', 'department_id' => $departments['SITE'], 'grade' => 'L2', 'default_role_id' => $roles['SITE_SUPERVISOR'], 'mobile_access_default' => true],
            ['name' => 'Mechanic', 'department_id' => $departments['SITE'], 'grade' => 'L1', 'default_role_id' => $roles['MECHANIC'], 'mobile_access_default' => true],
            ['name' => 'Operator', 'department_id' => $departments['SITE'], 'grade' => 'L1', 'default_role_id' => $roles['OPERATOR'], 'mobile_access_default' => true],
            ['name' => 'Purchase Manager', 'department_id' => $departments['PUR'], 'grade' => 'L3', 'default_role_id' => $roles['PURCHASE_MANAGER'], 'mobile_access_default' => false],
            ['name' => 'Purchase Assistant', 'department_id' => $departments['PUR'], 'grade' => 'L2', 'default_role_id' => $roles['PURCHASE_ASSISTANT'], 'mobile_access_default' => false],
            ['name' => 'Store Keeper', 'department_id' => $departments['PUR'], 'grade' => 'L1', 'default_role_id' => $roles['WAREHOUSE_INCHARGE'], 'mobile_access_default' => true],
            ['name' => 'Marketing Manager', 'department_id' => $departments['MKT'], 'grade' => 'L3', 'default_role_id' => $roles['MARKETING_MANAGER'], 'mobile_access_default' => false],
        ];

        foreach ($designations as $designation) {
            Designation::create($designation + ['status' => 'active']);
        }
    }

    private function seedCompanyProfile(): void
    {
        CompanyProfile::create([
            'name' => 'Al Omar Construction Company',
            'name_ar' => 'شركة العمر للمقاولات',
            'email' => 'info@company.sa',
            'phone' => '+966 50 000 0000',
            'website' => 'www.company.sa',
            'cr_number' => '1010123456',
            'vat_number' => '300123456700003',
            'zatca_registration_number' => 'ZATCA-REG-001',
            'default_vat_rate' => 15,
            'invoice_mode' => 'ZATCA Phase 2 - Clearance',
            'certificate_status' => 'Active',
            'country' => 'Saudi Arabia',
            'city' => 'Riyadh',
            'currency' => 'SAR',
            'fiscal_year_start' => '01 January',
            'fiscal_year_end' => '31 December',
            'address' => 'Building 12, King Fahd Road, Al Olaya District, Riyadh, Saudi Arabia',
            'status' => 'active',
        ]);
    }

    private function seedBranches(): void
    {
        $branches = [
            ['name' => 'Riyadh Main Branch', 'code' => 'BR-RYD', 'city' => 'Riyadh', 'phone' => '+966 11 200 1000', 'email' => 'riyadh@company.sa', 'address' => 'King Fahd Road, Al Olaya, Riyadh'],
            ['name' => 'Jeddah Branch', 'code' => 'BR-JED', 'city' => 'Jeddah', 'phone' => '+966 12 600 2000', 'email' => 'jeddah@company.sa', 'address' => 'Madinah Road, Al Salamah, Jeddah'],
            ['name' => 'Dammam Branch', 'code' => 'BR-DMM', 'city' => 'Dammam', 'phone' => '+966 13 800 3000', 'email' => 'dammam@company.sa', 'address' => 'King Saud Street, Dammam'],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch + ['status' => 'active']);
        }
    }

    private function seedPartners(): void
    {
        $customers = [
            ['name' => 'ABC Holdings', 'code' => 'CUS-001', 'type' => 'Company', 'vat_number' => '300111222300003', 'cr_number' => '1010111222', 'contact_person' => 'Mr. Khalid', 'phone' => '+966 50 111 2222', 'email' => 'khalid@abcholdings.sa', 'credit_limit' => 500000, 'opening_receivable' => 120000, 'linked_account' => 'Accounts Receivable - Customers', 'billing_address' => 'Olaya Towers, Riyadh'],
            ['name' => 'XYZ Trading', 'code' => 'CUS-002', 'type' => 'Company', 'vat_number' => '300444555600003', 'cr_number' => '1010444555', 'contact_person' => 'Mr. Saleh', 'phone' => '+966 55 333 4444', 'email' => 'saleh@xyztrading.sa', 'credit_limit' => 300000, 'opening_receivable' => 65000, 'linked_account' => 'Accounts Receivable - Customers', 'billing_address' => 'Corniche Road, Jeddah'],
            ['name' => 'Najd Development', 'code' => 'CUS-003', 'type' => 'Company', 'vat_number' => '300777888900003', 'cr_number' => '1010777888', 'contact_person' => 'Mr. Fahad', 'phone' => '+966 56 555 6666', 'email' => 'fahad@najddev.sa', 'credit_limit' => 750000, 'opening_receivable' => 0, 'linked_account' => 'Accounts Receivable - Customers', 'billing_address' => 'King Abdulaziz Road, Dammam'],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer + ['status' => 'active']);
        }

        $suppliers = [
            ['name' => 'Saudi Cement Supplier', 'code' => 'SUP-001', 'category' => 'Materials', 'vat_number' => '300123123100003', 'cr_number' => '1010123123', 'contact_person' => 'Ahmed', 'phone' => '+966 50 700 1111', 'email' => 'sales@saudicement.sa', 'payment_terms' => '30 Days', 'opening_balance' => 42000, 'linked_account' => 'Accounts Payable - Suppliers', 'address' => 'Industrial City 2, Riyadh'],
            ['name' => 'Fuel Station Vendor', 'code' => 'SUP-002', 'category' => 'Fuel', 'vat_number' => '300456456100003', 'cr_number' => '1010456456', 'contact_person' => 'Yousef', 'phone' => '+966 55 700 2222', 'email' => 'accounts@fuelvendor.sa', 'payment_terms' => '15 Days', 'opening_balance' => 8000, 'linked_account' => 'Accounts Payable - Suppliers', 'address' => 'Exit 10, Riyadh'],
            ['name' => 'Gulf Steel Trading', 'code' => 'SUP-003', 'category' => 'Materials', 'vat_number' => '300789789100003', 'cr_number' => '1010789789', 'contact_person' => 'Ibrahim', 'phone' => '+966 56 700 3333', 'email' => 'orders@gulfsteel.sa', 'payment_terms' => 'Cash', 'opening_balance' => 0, 'linked_account' => 'Accounts Payable - Suppliers', 'address' => 'Industrial Area, Dammam'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier + ['status' => 'active']);
        }

        $expenseCategories = [
            ['name' => 'Material Purchase', 'code' => 'EXP-MAT', 'linked_account' => 'Project Material Expense', 'payment_type' => 'Both', 'vat_treatment' => 'VAT 15%', 'description' => 'Site material purchases entered from web or mobile.'],
            ['name' => 'Fuel', 'code' => 'EXP-FUEL', 'linked_account' => 'Fuel Expense', 'payment_type' => 'Cash', 'vat_treatment' => 'VAT 15%', 'description' => 'Used for daily fuel expense entries from mobile app.'],
            ['name' => 'Food', 'code' => 'EXP-FOOD', 'linked_account' => 'Staff Welfare Expense', 'payment_type' => 'Cash', 'vat_treatment' => 'Non-VAT', 'invoice_photo_required' => false, 'description' => 'Staff food and welfare expenses.'],
            ['name' => 'Transportation', 'code' => 'EXP-TRANS', 'linked_account' => 'Transportation Expense', 'payment_type' => 'Both', 'vat_treatment' => 'VAT 15%', 'description' => 'Material and staff transportation costs.'],
            ['name' => 'Equipment Maintenance', 'code' => 'EXP-MAINT', 'linked_account' => 'Maintenance Expense', 'payment_type' => 'Bank', 'vat_treatment' => 'VAT 15%', 'description' => 'Spare parts and workshop maintenance.'],
        ];

        foreach ($expenseCategories as $category) {
            ExpenseCategory::create($category + ['status' => 'active']);
        }
    }

    private function seedUsers(): void
    {
        $departments = Department::pluck('id', 'code');
        $designations = Designation::pluck('id', 'name');
        $branches = Branch::pluck('id', 'code');
        $roles = Role::pluck('id', 'code');

        // The real company organization chart.
        // [name, email, username, department, designation, branch, role, mobile, status]
        $roster = [
            ['Omar Mukhtar', 'admin', 'ADMIN', 'General Manager', 'BR-RYD', 'SUPER_ADMIN', true, 'active', '2020-01-01'],
            ['Nabeel Mukhtar', 'nabeel', 'PRJ', 'Project Manager', 'BR-RYD', 'PROJECT_MANAGER', true, 'active', '2020-03-01'],
            ['Zubair Ahmed', 'zubair', 'FIN', 'Accounts Manager', 'BR-RYD', 'FINANCE_MANAGER', false, 'active', '2020-04-01'],
            ['Zulfiqar', 'zulfiqar', 'PUR', 'Purchase Manager', 'BR-RYD', 'PURCHASE_MANAGER', false, 'active', '2020-06-01'],
            ['Waleed', 'waleed', 'HR', 'HR Manager', 'BR-RYD', 'HR_MANAGER', false, 'active', '2020-07-01'],
            ['Abdullah Mukhtar', 'abdullah', 'MKT', 'Marketing Manager', 'BR-RYD', 'MARKETING_MANAGER', false, 'active', '2021-01-15'],
            ['Zafar Ali', 'zafar', 'SITE', 'Site In-Charge', 'BR-RYD', 'SITE_SUPERVISOR', true, 'active', '2021-02-01'],
            ['Abdullah Shahmeer', 'shahmeer', 'FIN', 'Account Assistant', 'BR-RYD', 'ACCOUNT_ASSISTANT', false, 'active', '2021-05-01'],
            ['Ayaz', 'ayaz', 'PUR', 'Purchase Assistant', 'BR-RYD', 'PURCHASE_ASSISTANT', false, 'active', '2021-06-15'],
            ['Kamran', 'kamran', 'SITE', 'Mechanic', 'BR-JED', 'MECHANIC', true, 'pending', '2022-03-01'],
            ['Shaban', 'shaban', 'SITE', 'Operator', 'BR-RYD', 'OPERATOR', true, 'active', '2022-05-01'],
            ['Rizwan', 'rizwan', 'SITE', 'Operator', 'BR-DMM', 'OPERATOR', true, 'active', '2022-09-01'],
        ];

        foreach ($roster as $index => [$name, $username, $deptCode, $designation, $branchCode, $roleCode, $mobile, $status, $joining]) {
            $user = User::create([
                'name' => $name,
                'email' => $username.'@example.com',
                'employee_id' => sprintf('EMP-%03d', $index + 1),
                'username' => $username,
                'phone' => '+966 5'.(($index % 6) + 1).' '.str_pad((string) (100 + $index), 3, '0', STR_PAD_LEFT).' '.str_pad((string) (2000 + $index), 4, '0', STR_PAD_LEFT),
                'department_id' => $departments[$deptCode],
                'designation_id' => $designations[$designation] ?? null,
                'branch_id' => $branches[$branchCode],
                'mobile_access' => $mobile,
                'two_factor_enabled' => $index < 3,
                'status' => $status,
                'joining_date' => $joining,
                'last_login_at' => now()->subHours($index + 1),
                'password' => 'password',
            ]);

            $user->roles()->attach($roles[$roleCode], ['is_primary' => true]);
        }

        // Department heads and branch managers, per the organization chart.
        $heads = [
            'ADMIN' => 'admin@example.com',
            'FIN' => 'zubair@example.com',
            'HR' => 'waleed@example.com',
            'PRJ' => 'nabeel@example.com',
            'SITE' => 'zafar@example.com',
            'PUR' => 'zulfiqar@example.com',
            'MKT' => 'abdullah@example.com',
        ];

        foreach ($heads as $code => $email) {
            Department::where('code', $code)->update(['head_user_id' => User::where('email', $email)->value('id')]);
        }

        Branch::where('code', 'BR-RYD')->update(['manager_id' => User::where('email', 'admin@example.com')->value('id')]);
        Branch::where('code', 'BR-JED')->update(['manager_id' => User::where('email', 'zulfiqar@example.com')->value('id')]);
        Branch::where('code', 'BR-DMM')->update(['manager_id' => User::where('email', 'zafar@example.com')->value('id')]);
    }

    private function seedProjectsSitesWarehouses(): void
    {
        $branches = Branch::pluck('id', 'code');
        $customers = Customer::pluck('id', 'code');
        $users = User::pluck('id', 'email');

        $riyadhTower = Project::create([
            'name' => 'Riyadh Tower', 'code' => 'PRJ-001',
            'customer_id' => $customers['CUS-001'], 'branch_id' => $branches['BR-RYD'],
            'manager_id' => $users['nabeel@example.com'],
            'start_date' => '2026-01-01', 'end_date' => '2026-12-30',
            'budget' => 2500000, 'location' => 'Al Olaya District, Riyadh',
            'description' => '22-floor commercial tower with basement parking.',
            'status' => 'active',
        ]);

        $jeddahWarehouse = Project::create([
            'name' => 'Jeddah Warehouse', 'code' => 'PRJ-002',
            'customer_id' => $customers['CUS-002'], 'branch_id' => $branches['BR-JED'],
            'manager_id' => $users['nabeel@example.com'],
            'start_date' => '2026-02-15', 'end_date' => '2026-10-15',
            'budget' => 1400000, 'location' => 'Industrial Area, Jeddah',
            'description' => 'Logistics warehouse with loading docks and office block.',
            'status' => 'planning',
        ]);

        $dammamRoad = Project::create([
            'name' => 'Dammam Road Extension', 'code' => 'PRJ-003',
            'customer_id' => $customers['CUS-003'], 'branch_id' => $branches['BR-DMM'],
            'manager_id' => $users['nabeel@example.com'],
            'start_date' => '2026-03-01', 'end_date' => '2027-03-01',
            'budget' => 3800000, 'location' => 'Eastern Ring Road, Dammam',
            'description' => 'Road extension and drainage infrastructure works.',
            'status' => 'active',
        ]);

        $blockA = Site::create([
            'name' => 'Block A', 'code' => 'SITE-A', 'project_id' => $riyadhTower->id,
            'supervisor_id' => $users['nabeel@example.com'],
            'latitude' => 24.7136, 'longitude' => 46.6753, 'geofence_radius' => 300,
            'address' => 'Al Olaya District, Riyadh', 'status' => 'active',
        ]);

        $blockB = Site::create([
            'name' => 'Block B', 'code' => 'SITE-B', 'project_id' => $riyadhTower->id,
            'supervisor_id' => $users['shaban@example.com'],
            'latitude' => 24.7150, 'longitude' => 46.6790, 'geofence_radius' => 250,
            'address' => 'Al Olaya District, Riyadh', 'status' => 'active',
        ]);

        $equipmentYard = Site::create([
            'name' => 'Equipment Yard', 'code' => 'SITE-YARD', 'project_id' => $jeddahWarehouse->id,
            'supervisor_id' => $users['zafar@example.com'],
            'latitude' => 21.4858, 'longitude' => 39.1925, 'geofence_radius' => 500,
            'geofence_enabled' => false, 'address' => 'Industrial Area, Jeddah', 'status' => 'draft',
        ]);

        $dammamSite = Site::create([
            'name' => 'Section 1 - Drainage', 'code' => 'SITE-D1', 'project_id' => $dammamRoad->id,
            'supervisor_id' => $users['zafar@example.com'],
            'latitude' => 26.4207, 'longitude' => 50.0888, 'geofence_radius' => 800,
            'address' => 'Eastern Ring Road, Dammam', 'status' => 'active',
        ]);

        Warehouse::create([
            'name' => 'Riyadh Main Store', 'code' => 'WH-RYD',
            'branch_id' => $branches['BR-RYD'], 'incharge_id' => $users['admin@example.com'],
            'valuation_method' => 'FIFO', 'address' => 'King Fahd Road, Riyadh', 'status' => 'active',
        ]);

        Warehouse::create([
            'name' => 'Riyadh Tower Site Store', 'code' => 'WH-SITE-A',
            'branch_id' => $branches['BR-RYD'], 'project_id' => $riyadhTower->id,
            'site_id' => $blockA->id, 'incharge_id' => $users['nabeel@example.com'],
            'valuation_method' => 'Average', 'address' => 'Block A, Al Olaya, Riyadh', 'status' => 'active',
        ]);

        Warehouse::create([
            'name' => 'Jeddah Branch Store', 'code' => 'WH-JED',
            'branch_id' => $branches['BR-JED'], 'incharge_id' => $users['zubair@example.com'],
            'valuation_method' => 'FIFO', 'address' => 'Madinah Road, Jeddah', 'status' => 'active',
        ]);

        // Assign scope to the site supervisor used in demos.
        User::where('email', 'nabeel@example.com')->update([
            'project_id' => $riyadhTower->id, 'site_id' => $blockA->id,
        ]);
        User::where('email', 'zafar@example.com')->update([
            'project_id' => $dammamRoad->id, 'site_id' => $dammamSite->id,
        ]);
        User::where('email', 'shaban@example.com')->update([
            'project_id' => $riyadhTower->id, 'site_id' => $blockB->id,
        ]);
        User::where('email', 'rizwan@example.com')->update([
            'project_id' => $dammamRoad->id, 'site_id' => $dammamSite->id,
        ]);
        User::where('email', 'kamran@example.com')->update([
            'project_id' => $jeddahWarehouse->id, 'site_id' => $equipmentYard->id,
        ]);
    }

    private function seedWorkflows(): void
    {
        $departments = Department::pluck('id', 'code');
        $roles = Role::pluck('id', 'code');

        $siteExpense = ApprovalWorkflow::create([
            'name' => 'Site Expense Approval', 'module' => 'Site Expenses',
            'trigger_action' => 'Expense Submitted', 'department_id' => $departments['SITE'],
            'scope' => 'Assigned Project/Site', 'auto_posting' => 'Create Accounting Entry',
        ]);

        $siteExpense->steps()->createMany([
            ['step_no' => 1, 'approver_role_id' => $roles['SITE_SUPERVISOR'], 'approver_note' => 'Any assigned supervisor', 'amount_limit' => 5000, 'sla_hours' => 24, 'escalation_role_id' => $roles['PROJECT_MANAGER']],
            ['step_no' => 2, 'approver_role_id' => $roles['PROJECT_MANAGER'], 'approver_note' => 'Assigned PM', 'amount_limit' => 20000, 'sla_hours' => 48, 'escalation_role_id' => $roles['FINANCE_MANAGER']],
            ['step_no' => 3, 'approver_role_id' => $roles['FINANCE_MANAGER'], 'approver_note' => 'Any finance manager', 'amount_limit' => null, 'sla_hours' => 48, 'escalation_role_id' => $roles['SUPER_ADMIN']],
        ]);

        $purchase = ApprovalWorkflow::create([
            'name' => 'Purchase Request Approval', 'module' => 'Purchase Request',
            'trigger_action' => 'Request Created', 'department_id' => $departments['PRJ'],
            'scope' => 'All Projects', 'auto_posting' => 'No Auto Posting',
        ]);

        $purchase->steps()->createMany([
            ['step_no' => 1, 'approver_role_id' => $roles['PROJECT_MANAGER'], 'approver_note' => 'Assigned PM', 'amount_limit' => 50000, 'sla_hours' => 48, 'escalation_role_id' => $roles['FINANCE_MANAGER']],
            ['step_no' => 2, 'approver_role_id' => $roles['FINANCE_MANAGER'], 'approver_note' => 'Any finance manager', 'amount_limit' => null, 'sla_hours' => 72, 'escalation_role_id' => $roles['SUPER_ADMIN']],
        ]);

        $leave = ApprovalWorkflow::create([
            'name' => 'Leave Request Approval', 'module' => 'Leave Request',
            'trigger_action' => 'Request Created', 'department_id' => $departments['HR'],
            'scope' => 'All Company', 'auto_posting' => 'No Auto Posting',
        ]);

        $leave->steps()->createMany([
            ['step_no' => 1, 'approver_role_id' => $roles['SITE_SUPERVISOR'], 'approver_note' => 'Direct supervisor', 'sla_hours' => 24, 'escalation_role_id' => $roles['HR_MANAGER']],
            ['step_no' => 2, 'approver_role_id' => $roles['HR_MANAGER'], 'approver_note' => 'HR final approval', 'sla_hours' => 48, 'escalation_role_id' => $roles['SUPER_ADMIN']],
        ]);
    }

    private function seedActivityLogs(): void
    {
        $users = User::pluck('id', 'email');

        $logs = [
            ['user_id' => $users['admin@example.com'], 'user_name' => 'Omar Mukhtar', 'module' => 'Roles', 'action' => 'Updated role permission', 'old_value' => 'Approve: No', 'new_value' => 'Approve: Yes', 'ip_address' => '192.168.1.15', 'status' => 'success', 'created_at' => now()->subHours(2)],
            ['user_id' => $users['zubair@example.com'], 'user_name' => 'Zubair Ahmed', 'module' => 'Users', 'action' => 'Assigned role', 'old_value' => 'None', 'new_value' => 'Accounts Manager', 'ip_address' => '192.168.1.20', 'status' => 'success', 'created_at' => now()->subHours(4)],
            ['user_id' => $users['nabeel@example.com'], 'user_name' => 'Nabeel Mukhtar', 'module' => 'Projects', 'action' => 'Created project', 'new_value' => 'Riyadh Tower', 'ip_address' => '192.168.1.22', 'status' => 'success', 'created_at' => now()->subDay()],
            ['user_id' => $users['zubair@example.com'], 'user_name' => 'Waleed', 'module' => 'Suppliers', 'action' => 'Added supplier', 'new_value' => 'Saudi Cement Supplier', 'ip_address' => '192.168.1.30', 'status' => 'success', 'created_at' => now()->subDay()],
            ['user_id' => $users['nabeel@example.com'], 'user_name' => 'Zafar Ali', 'module' => 'Sites', 'action' => 'Updated geo-fence radius', 'old_value' => '200 m', 'new_value' => '300 m', 'ip_address' => '10.0.0.14', 'status' => 'reviewed', 'created_at' => now()->subDays(2)],
            ['user_id' => null, 'user_name' => 'System', 'module' => 'Security', 'action' => 'Failed login', 'new_value' => 'Invalid password', 'ip_address' => '185.10.1.2', 'status' => 'failed', 'created_at' => now()->subDays(2)],
            ['user_id' => $users['admin@example.com'], 'user_name' => 'Omar Mukhtar', 'module' => 'Company', 'action' => 'Updated VAT details', 'old_value' => 'VAT 5%', 'new_value' => 'VAT 15%', 'ip_address' => '192.168.1.15', 'status' => 'success', 'created_at' => now()->subDays(3)],
            ['user_id' => $users['admin@example.com'], 'user_name' => 'Omar Mukhtar', 'module' => 'Workflows', 'action' => 'Created approval workflow', 'new_value' => 'Site Expense Approval', 'ip_address' => '192.168.1.15', 'status' => 'success', 'created_at' => now()->subDays(4)],
            ['user_id' => $users['kamran@example.com'], 'user_name' => 'Kamran', 'module' => 'Equipment', 'action' => 'Updated equipment issue', 'ip_address' => '10.0.0.31', 'status' => 'success', 'created_at' => now()->subDays(4)],
            ['user_id' => $users['nabeel@example.com'], 'user_name' => 'Zafar Ali', 'module' => 'Attendance', 'action' => 'Approved site attendance', 'ip_address' => '10.0.0.14', 'status' => 'success', 'created_at' => now()->subDays(5)],
        ];

        foreach ($logs as $log) {
            ActivityLog::create($log);
        }
    }
}
