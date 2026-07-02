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
    }

    private function seedDepartments(): void
    {
        $departments = [
            ['name' => 'Administration', 'code' => 'ADMIN', 'description' => 'System administration and access control'],
            ['name' => 'Finance', 'code' => 'FIN', 'description' => 'Finance Manager, Accountant, Payables'],
            ['name' => 'Human Resource', 'code' => 'HR', 'description' => 'HR Manager, Payroll, Documents'],
            ['name' => 'Projects', 'code' => 'PRJ', 'description' => 'Project Manager, Engineers'],
            ['name' => 'Site Operations', 'code' => 'SITE', 'description' => 'Supervisors, Mechanics, Workers'],
            ['name' => 'Equipment', 'code' => 'EQP', 'description' => 'Equipment, Vehicles, Maintenance'],
        ];

        foreach ($departments as $department) {
            Department::create($department + ['status' => 'active']);
        }
    }

    private function seedRolesAndPermissions(): void
    {
        $departments = Department::pluck('id', 'code');

        $superAdmin = Role::create([
            'name' => 'Super Admin', 'code' => 'SUPER_ADMIN',
            'department_id' => $departments['ADMIN'], 'level' => 1,
            'access_scope' => 'All Company', 'default_dashboard' => 'Admin Dashboard',
            'mobile_app_access' => true, 'is_system' => true,
            'description' => 'Full access to every module and setting.',
        ]);

        $financeManager = Role::create([
            'name' => 'Finance Manager', 'code' => 'FINANCE_MANAGER',
            'department_id' => $departments['FIN'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Finance Dashboard',
            'description' => 'Manages accounting, payables, receivables and VAT.',
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

        $inventoryManager = Role::create([
            'name' => 'Inventory Manager', 'code' => 'INVENTORY_MANAGER',
            'department_id' => $departments['EQP'], 'parent_id' => $superAdmin->id,
            'level' => 2, 'access_scope' => 'Company Level',
            'default_dashboard' => 'Inventory Dashboard',
            'description' => 'Manages warehouses, materials and stock movements.',
        ]);

        $siteSupervisor = Role::create([
            'name' => 'Site Supervisor', 'code' => 'SITE_SUPERVISOR',
            'department_id' => $departments['SITE'], 'parent_id' => $projectManager->id,
            'level' => 3, 'access_scope' => 'Site Level',
            'default_dashboard' => 'Site Dashboard', 'mobile_app_access' => true,
            'description' => 'Can manage site attendance, site expenses, and approvals for assigned project/site.',
        ]);

        Role::create([
            'name' => 'Mechanic', 'code' => 'MECHANIC',
            'department_id' => $departments['EQP'], 'parent_id' => $siteSupervisor->id,
            'level' => 4, 'access_scope' => 'Site Level',
            'default_dashboard' => 'Site Dashboard', 'mobile_app_access' => true,
            'description' => 'Submits equipment issues, fuel and maintenance requests.',
        ]);

        Role::create([
            'name' => 'Site Worker', 'code' => 'SITE_WORKER',
            'department_id' => $departments['SITE'], 'parent_id' => $siteSupervisor->id,
            'level' => 4, 'access_scope' => 'Site Level',
            'default_dashboard' => 'Site Dashboard', 'mobile_app_access' => true,
            'description' => 'Mobile attendance and basic site requests only.',
        ]);

        $modules = [
            'Dashboard', 'Users', 'Roles', 'Accounting', 'HR', 'Payroll',
            'Attendance', 'Projects', 'Sites', 'Inventory', 'Site Expenses',
            'Equipment', 'Vehicles', 'ZATCA Invoicing', 'Reports', 'Settings',
        ];
        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'export', 'mobile'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::create(['module' => $module, 'action' => $action]);
            }
        }

        $superAdmin->permissions()->attach(Permission::pluck('id'));

        $grants = [
            $financeManager->id => [
                'Dashboard' => ['view', 'export'],
                'Accounting' => ['view', 'create', 'edit', 'approve', 'export'],
                'Payroll' => ['view', 'approve', 'export'],
                'Site Expenses' => ['view', 'approve', 'export'],
                'ZATCA Invoicing' => ['view', 'create', 'edit', 'export'],
                'Reports' => ['view', 'export'],
            ],
            $hrManager->id => [
                'Dashboard' => ['view'],
                'HR' => ['view', 'create', 'edit', 'approve', 'export'],
                'Payroll' => ['view', 'create', 'edit', 'export'],
                'Attendance' => ['view', 'edit', 'approve', 'export'],
                'Reports' => ['view', 'export'],
            ],
            $projectManager->id => [
                'Dashboard' => ['view'],
                'Projects' => ['view', 'create', 'edit', 'approve', 'export'],
                'Sites' => ['view', 'create', 'edit'],
                'Site Expenses' => ['view', 'approve', 'export'],
                'Attendance' => ['view', 'approve'],
                'Inventory' => ['view'],
                'Reports' => ['view'],
            ],
            $inventoryManager->id => [
                'Dashboard' => ['view'],
                'Inventory' => ['view', 'create', 'edit', 'approve', 'export'],
                'Equipment' => ['view', 'create', 'edit'],
                'Vehicles' => ['view', 'create', 'edit'],
                'Reports' => ['view'],
            ],
            $siteSupervisor->id => [
                'Dashboard' => ['view'],
                'Attendance' => ['view', 'create', 'edit', 'approve', 'mobile'],
                'Site Expenses' => ['view', 'create', 'approve', 'mobile'],
                'Projects' => ['view'],
                'Sites' => ['view'],
                'Inventory' => ['view', 'create'],
                'Equipment' => ['view', 'mobile'],
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
    }

    private function seedDesignations(): void
    {
        $departments = Department::pluck('id', 'code');
        $roles = Role::pluck('id', 'code');

        $designations = [
            ['name' => 'Finance Manager', 'department_id' => $departments['FIN'], 'grade' => 'L3', 'default_role_id' => $roles['FINANCE_MANAGER'], 'mobile_access_default' => false],
            ['name' => 'HR Manager', 'department_id' => $departments['HR'], 'grade' => 'L3', 'default_role_id' => $roles['HR_MANAGER'], 'mobile_access_default' => false],
            ['name' => 'Project Manager', 'department_id' => $departments['PRJ'], 'grade' => 'L3', 'default_role_id' => $roles['PROJECT_MANAGER'], 'mobile_access_default' => true],
            ['name' => 'Site Supervisor', 'department_id' => $departments['SITE'], 'grade' => 'L2', 'default_role_id' => $roles['SITE_SUPERVISOR'], 'mobile_access_default' => true],
            ['name' => 'Mechanic', 'department_id' => $departments['EQP'], 'grade' => 'L1', 'default_role_id' => $roles['MECHANIC'], 'mobile_access_default' => true],
            ['name' => 'Store Keeper', 'department_id' => $departments['EQP'], 'grade' => 'L1', 'default_role_id' => $roles['INVENTORY_MANAGER'], 'mobile_access_default' => true],
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

        $users = [
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'employee_id' => 'EMP-001', 'username' => 'admin', 'phone' => '+966 50 000 1111', 'department_id' => $departments['ADMIN'], 'branch_id' => $branches['BR-RYD'], 'role' => 'SUPER_ADMIN', 'mobile_access' => true, 'two_factor_enabled' => true, 'joining_date' => '2026-01-01', 'last_login_at' => now()],
            ['name' => 'M. Bin Ashfaq', 'email' => 'ashfaq@example.com', 'employee_id' => 'EMP-002', 'username' => 'ashfaq', 'phone' => '+966 50 222 1111', 'department_id' => $departments['PRJ'], 'designation_id' => $designations['Project Manager'], 'branch_id' => $branches['BR-RYD'], 'role' => 'PROJECT_MANAGER', 'mobile_access' => true, 'joining_date' => '2026-01-05', 'last_login_at' => now()->subHours(3)],
            ['name' => 'Fatima Al Harbi', 'email' => 'fatima@example.com', 'employee_id' => 'EMP-004', 'username' => 'fatima', 'phone' => '+966 55 444 2222', 'department_id' => $departments['FIN'], 'designation_id' => $designations['Finance Manager'], 'branch_id' => $branches['BR-RYD'], 'role' => 'FINANCE_MANAGER', 'two_factor_enabled' => true, 'joining_date' => '2026-01-10', 'last_login_at' => now()->subDay()],
            ['name' => 'Zubair Khan', 'email' => 'zubair@example.com', 'employee_id' => 'EMP-005', 'username' => 'zubair', 'phone' => '+966 55 555 3333', 'department_id' => $departments['HR'], 'designation_id' => $designations['HR Manager'], 'branch_id' => $branches['BR-JED'], 'role' => 'HR_MANAGER', 'joining_date' => '2026-01-12', 'last_login_at' => now()->subHours(6)],
            ['name' => 'Nabeel Ahmed', 'email' => 'nabeel@example.com', 'employee_id' => 'EMP-007', 'username' => 'nabeel', 'phone' => '+966 55 888 2222', 'department_id' => $departments['SITE'], 'designation_id' => $designations['Site Supervisor'], 'branch_id' => $branches['BR-RYD'], 'role' => 'SITE_SUPERVISOR', 'mobile_access' => true, 'joining_date' => '2026-01-01', 'last_login_at' => now()->subHour()],
            ['name' => 'Waiz Rahman', 'email' => 'waiz@example.com', 'employee_id' => 'EMP-008', 'username' => 'waiz', 'phone' => '+966 55 888 4444', 'department_id' => $departments['SITE'], 'designation_id' => $designations['Site Supervisor'], 'branch_id' => $branches['BR-JED'], 'role' => 'SITE_SUPERVISOR', 'mobile_access' => true, 'joining_date' => '2026-02-01', 'last_login_at' => now()->subDays(2)],
            ['name' => 'Uzaid Malik', 'email' => 'uzaid@example.com', 'employee_id' => 'EMP-009', 'username' => 'uzaid', 'phone' => '+966 55 888 5555', 'department_id' => $departments['SITE'], 'designation_id' => $designations['Site Supervisor'], 'branch_id' => $branches['BR-DMM'], 'role' => 'SITE_SUPERVISOR', 'mobile_access' => true, 'joining_date' => '2026-02-15', 'last_login_at' => now()->subDays(3)],
            ['name' => 'Kamran Iqbal', 'email' => 'kamran@example.com', 'employee_id' => 'EMP-011', 'username' => 'kamran', 'phone' => '+966 55 999 3333', 'department_id' => $departments['EQP'], 'designation_id' => $designations['Mechanic'], 'branch_id' => $branches['BR-JED'], 'role' => 'MECHANIC', 'mobile_access' => true, 'status' => 'pending', 'joining_date' => '2026-03-01', 'last_login_at' => now()->subDay()],
            ['name' => 'Imran Shah', 'email' => 'imran@example.com', 'employee_id' => 'EMP-012', 'username' => 'imran', 'phone' => '+966 55 999 4444', 'department_id' => $departments['EQP'], 'designation_id' => $designations['Mechanic'], 'branch_id' => $branches['BR-DMM'], 'role' => 'MECHANIC', 'mobile_access' => true, 'status' => 'inactive', 'joining_date' => '2026-03-10'],
        ];

        foreach ($users as $data) {
            $roleCode = $data['role'];
            unset($data['role']);

            $user = User::create($data + ['password' => 'password']);
            $user->roles()->attach($roles[$roleCode], ['is_primary' => true]);
        }

        // Wire up managers/heads now that users exist.
        Department::where('code', 'FIN')->update(['head_user_id' => User::where('email', 'fatima@example.com')->value('id')]);
        Department::where('code', 'HR')->update(['head_user_id' => User::where('email', 'zubair@example.com')->value('id')]);
        Department::where('code', 'SITE')->update(['head_user_id' => User::where('email', 'nabeel@example.com')->value('id')]);
        Department::where('code', 'PRJ')->update(['head_user_id' => User::where('email', 'ashfaq@example.com')->value('id')]);
        Branch::where('code', 'BR-RYD')->update(['manager_id' => User::where('email', 'admin@example.com')->value('id')]);
        Branch::where('code', 'BR-JED')->update(['manager_id' => User::where('email', 'zubair@example.com')->value('id')]);
        Branch::where('code', 'BR-DMM')->update(['manager_id' => User::where('email', 'uzaid@example.com')->value('id')]);
    }

    private function seedProjectsSitesWarehouses(): void
    {
        $branches = Branch::pluck('id', 'code');
        $customers = Customer::pluck('id', 'code');
        $users = User::pluck('id', 'email');

        $riyadhTower = Project::create([
            'name' => 'Riyadh Tower', 'code' => 'PRJ-001',
            'customer_id' => $customers['CUS-001'], 'branch_id' => $branches['BR-RYD'],
            'manager_id' => $users['ashfaq@example.com'],
            'start_date' => '2026-01-01', 'end_date' => '2026-12-30',
            'budget' => 2500000, 'location' => 'Al Olaya District, Riyadh',
            'description' => '22-floor commercial tower with basement parking.',
            'status' => 'active',
        ]);

        $jeddahWarehouse = Project::create([
            'name' => 'Jeddah Warehouse', 'code' => 'PRJ-002',
            'customer_id' => $customers['CUS-002'], 'branch_id' => $branches['BR-JED'],
            'manager_id' => $users['ashfaq@example.com'],
            'start_date' => '2026-02-15', 'end_date' => '2026-10-15',
            'budget' => 1400000, 'location' => 'Industrial Area, Jeddah',
            'description' => 'Logistics warehouse with loading docks and office block.',
            'status' => 'planning',
        ]);

        $dammamRoad = Project::create([
            'name' => 'Dammam Road Extension', 'code' => 'PRJ-003',
            'customer_id' => $customers['CUS-003'], 'branch_id' => $branches['BR-DMM'],
            'manager_id' => $users['ashfaq@example.com'],
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

        Site::create([
            'name' => 'Block B', 'code' => 'SITE-B', 'project_id' => $riyadhTower->id,
            'supervisor_id' => $users['waiz@example.com'],
            'latitude' => 24.7150, 'longitude' => 46.6790, 'geofence_radius' => 250,
            'address' => 'Al Olaya District, Riyadh', 'status' => 'active',
        ]);

        Site::create([
            'name' => 'Equipment Yard', 'code' => 'SITE-YARD', 'project_id' => $jeddahWarehouse->id,
            'supervisor_id' => $users['uzaid@example.com'],
            'latitude' => 21.4858, 'longitude' => 39.1925, 'geofence_radius' => 500,
            'geofence_enabled' => false, 'address' => 'Industrial Area, Jeddah', 'status' => 'draft',
        ]);

        Site::create([
            'name' => 'Section 1 - Drainage', 'code' => 'SITE-D1', 'project_id' => $dammamRoad->id,
            'supervisor_id' => $users['uzaid@example.com'],
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
            ['user_id' => $users['admin@example.com'], 'user_name' => 'Admin User', 'module' => 'Roles', 'action' => 'Updated role permission', 'old_value' => 'Approve: No', 'new_value' => 'Approve: Yes', 'ip_address' => '192.168.1.15', 'status' => 'success', 'created_at' => now()->subHours(2)],
            ['user_id' => $users['fatima@example.com'], 'user_name' => 'Fatima Al Harbi', 'module' => 'Users', 'action' => 'Assigned role', 'old_value' => 'None', 'new_value' => 'Finance Manager', 'ip_address' => '192.168.1.20', 'status' => 'success', 'created_at' => now()->subHours(4)],
            ['user_id' => $users['ashfaq@example.com'], 'user_name' => 'M. Bin Ashfaq', 'module' => 'Projects', 'action' => 'Created project', 'new_value' => 'Riyadh Tower', 'ip_address' => '192.168.1.22', 'status' => 'success', 'created_at' => now()->subDay()],
            ['user_id' => $users['zubair@example.com'], 'user_name' => 'Zubair Khan', 'module' => 'Suppliers', 'action' => 'Added supplier', 'new_value' => 'Saudi Cement Supplier', 'ip_address' => '192.168.1.30', 'status' => 'success', 'created_at' => now()->subDay()],
            ['user_id' => $users['nabeel@example.com'], 'user_name' => 'Nabeel Ahmed', 'module' => 'Sites', 'action' => 'Updated geo-fence radius', 'old_value' => '200 m', 'new_value' => '300 m', 'ip_address' => '10.0.0.14', 'status' => 'reviewed', 'created_at' => now()->subDays(2)],
            ['user_id' => null, 'user_name' => 'System', 'module' => 'Security', 'action' => 'Failed login', 'new_value' => 'Invalid password', 'ip_address' => '185.10.1.2', 'status' => 'failed', 'created_at' => now()->subDays(2)],
            ['user_id' => $users['admin@example.com'], 'user_name' => 'Admin User', 'module' => 'Company', 'action' => 'Updated VAT details', 'old_value' => 'VAT 5%', 'new_value' => 'VAT 15%', 'ip_address' => '192.168.1.15', 'status' => 'success', 'created_at' => now()->subDays(3)],
            ['user_id' => $users['admin@example.com'], 'user_name' => 'Admin User', 'module' => 'Workflows', 'action' => 'Created approval workflow', 'new_value' => 'Site Expense Approval', 'ip_address' => '192.168.1.15', 'status' => 'success', 'created_at' => now()->subDays(4)],
            ['user_id' => $users['kamran@example.com'], 'user_name' => 'Kamran Iqbal', 'module' => 'Equipment', 'action' => 'Updated equipment issue', 'ip_address' => '10.0.0.31', 'status' => 'success', 'created_at' => now()->subDays(4)],
            ['user_id' => $users['nabeel@example.com'], 'user_name' => 'Nabeel Ahmed', 'module' => 'Attendance', 'action' => 'Approved site attendance', 'ip_address' => '10.0.0.14', 'status' => 'success', 'created_at' => now()->subDays(5)],
        ];

        foreach ($logs as $log) {
            ActivityLog::create($log);
        }
    }
}
