<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates the portal login accounts for the company organization chart.
 *
 * Safe to run on production: it only touches departments, roles, designations
 * and the twelve named users. It creates no demo transactions, and re-running
 * it will not reset a password that the holder has already changed.
 */
class OrganizationHierarchySeeder extends Seeder
{
    /** Shared first-login password. Every account is forced to change it. */
    public const DEFAULT_PASSWORD = '123456';

    /**
     * The organization chart.
     * [name, username, department, designation, role, mobile access]
     */
    public const ROSTER = [
        ['Omar Mukhtar', 'omar', 'ADMIN', 'General Manager', 'SUPER_ADMIN', true],
        ['Nabeel Mukhtar', 'nabeel', 'PRJ', 'Project Manager', 'PROJECT_MANAGER', true],
        ['Zubair Ahmed', 'zubair', 'FIN', 'Accounts Manager', 'FINANCE_MANAGER', false],
        ['Zulfiqar', 'zulfiqar', 'PUR', 'Purchase Manager', 'PURCHASE_MANAGER', false],
        ['Waleed', 'waleed', 'HR', 'HR Manager', 'HR_MANAGER', false],
        ['Abdullah Mukhtar', 'abdullah', 'MKT', 'Marketing Manager', 'MARKETING_MANAGER', false],
        ['Zafar Ali', 'zafar', 'SITE', 'Site In-Charge', 'SITE_SUPERVISOR', true],
        ['Abdullah Shahmeer', 'shahmeer', 'FIN', 'Account Assistant', 'ACCOUNT_ASSISTANT', false],
        ['Ayaz', 'ayaz', 'PUR', 'Purchase Assistant', 'PURCHASE_ASSISTANT', false],
        ['Kamran', 'kamran', 'SITE', 'Mechanic', 'MECHANIC', true],
        ['Shaban', 'shaban', 'SITE', 'Operator', 'OPERATOR', true],
        ['Rizwan', 'rizwan', 'SITE', 'Operator', 'OPERATOR', true],
    ];

    public function run(): void
    {
        $domain = trim((string) config('seera.organization.email_domain', 'seera.local'));

        DB::transaction(function () use ($domain) {
            $this->seedStructure();
            $this->grantRolePermissions();

            $departments = Department::pluck('id', 'code');
            $designations = Designation::pluck('id', 'name');
            $roles = Role::pluck('id', 'code');

            foreach (self::ROSTER as $index => [$name, $username, $deptCode, $designation, $roleCode, $mobile]) {
                if (! isset($departments[$deptCode], $roles[$roleCode])) {
                    $this->command?->warn("Skipped {$name}: department {$deptCode} or role {$roleCode} is missing.");

                    continue;
                }

                $email = $username.'@'.$domain;
                $user = User::where('email', $email)->first();

                // A different account already owning this username would make the
                // new one unsaveable, so leave both alone and say so.
                if (! $user && User::where('username', $username)->exists()) {
                    $this->command?->warn("Skipped {$name}: username '{$username}' already belongs to another account.");

                    continue;
                }

                $profile = [
                    'name' => $name,
                    'department_id' => $departments[$deptCode],
                    'designation_id' => $designations[$designation] ?? null,
                    'mobile_access' => $mobile,
                    'status' => 'active',
                ];

                if ($user) {
                    // Refresh the org details but never touch an existing password.
                    $user->update($profile);
                } else {
                    // employee_id is unique across users, so only claim it if free.
                    $employeeId = sprintf('EMP-%03d', $index + 1);
                    if (User::where('employee_id', $employeeId)->exists()) {
                        $employeeId = null;
                    }

                    $user = User::create($profile + [
                        'email' => $email,
                        'username' => $username,
                        'employee_id' => $employeeId,
                        'password' => self::DEFAULT_PASSWORD,
                        'must_change_password' => true,
                    ]);
                }

                $user->roles()->syncWithoutDetaching([$roles[$roleCode] => ['is_primary' => true]]);
            }

        });
    }

    /**
     * Departments, roles and designations the roster depends on. A production
     * database bootstrapped by ProductionSeeder only has ADMIN/SUPER_ADMIN, so
     * everything else is created here before the users are made.
     */
    private function seedStructure(): void
    {
        // The permission catalogue must exist before any role can be granted.
        foreach (Permission::MODULES as $module) {
            foreach (Permission::ACTIONS as $action) {
                Permission::firstOrCreate(['module' => $module, 'action' => $action]);
            }
        }

        $departments = [
            ['ADMIN', 'Administration', 'General management and access control'],
            ['FIN', 'Accounts', 'Accounts Manager, Account Assistant, payables and receivables'],
            ['HR', 'Human Resource', 'HR Manager, payroll, documents'],
            ['PRJ', 'Projects', 'Project Manager and engineers'],
            ['SITE', 'Site Operations', 'Site In-Charge, mechanics, operators'],
            ['PUR', 'Purchase', 'Purchase Manager, Purchase Assistant, stores'],
            ['MKT', 'Marketing', 'Marketing and business development'],
        ];

        foreach ($departments as [$code, $name, $description]) {
            Department::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $description, 'status' => 'active']
            );
        }

        $departmentIds = Department::pluck('id', 'code');

        // [code, name, department, parent code, level, scope, mobile]
        $roles = [
            ['SUPER_ADMIN', 'Super Admin', 'ADMIN', null, 1, 'All Company', true],
            ['FINANCE_MANAGER', 'Accounts Manager', 'FIN', 'SUPER_ADMIN', 2, 'Company Level', false],
            ['HR_MANAGER', 'HR Manager', 'HR', 'SUPER_ADMIN', 2, 'Company Level', false],
            ['PROJECT_MANAGER', 'Project Manager', 'PRJ', 'SUPER_ADMIN', 2, 'Project Level', true],
            ['PURCHASE_MANAGER', 'Purchase Manager', 'PUR', 'SUPER_ADMIN', 2, 'Company Level', false],
            ['MARKETING_MANAGER', 'Marketing Manager', 'MKT', 'SUPER_ADMIN', 2, 'Company Level', false],
            ['INVENTORY_MANAGER', 'Inventory Manager', 'PUR', 'SUPER_ADMIN', 2, 'Company Level', false],
            ['SITE_SUPERVISOR', 'Site In-Charge', 'SITE', 'PROJECT_MANAGER', 3, 'Site Level', true],
            ['ACCOUNT_ASSISTANT', 'Account Assistant', 'FIN', 'FINANCE_MANAGER', 3, 'Company Level', false],
            ['PURCHASE_ASSISTANT', 'Purchase Assistant', 'PUR', 'PURCHASE_MANAGER', 3, 'Company Level', false],
            ['WAREHOUSE_INCHARGE', 'Warehouse Incharge', 'PUR', 'INVENTORY_MANAGER', 3, 'Warehouse Level', true],
            ['MECHANIC', 'Mechanic', 'SITE', 'SITE_SUPERVISOR', 4, 'Site Level', true],
            ['OPERATOR', 'Operator', 'SITE', 'SITE_SUPERVISOR', 4, 'Site Level', true],
            ['SITE_WORKER', 'Site Worker', 'SITE', 'SITE_SUPERVISOR', 4, 'Site Level', true],
        ];

        foreach ($roles as [$code, $name, $deptCode, $parentCode, $level, $scope, $mobile]) {
            Role::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'department_id' => $departmentIds[$deptCode] ?? null,
                    'level' => $level,
                    'access_scope' => $scope,
                    'default_dashboard' => 'Admin Dashboard',
                    'mobile_app_access' => $mobile,
                    'is_system' => $code === 'SUPER_ADMIN',
                    'status' => 'active',
                ]
            );
        }

        // Parents are wired in a second pass so order does not matter.
        $roleIds = Role::pluck('id', 'code');
        foreach ($roles as [$code, , , $parentCode]) {
            if ($parentCode && isset($roleIds[$parentCode])) {
                Role::where('code', $code)->whereNull('parent_id')->update(['parent_id' => $roleIds[$parentCode]]);
            }
        }

        // [designation, department, role, mobile]
        $designations = [
            ['General Manager', 'ADMIN', 'SUPER_ADMIN', true],
            ['Accounts Manager', 'FIN', 'FINANCE_MANAGER', false],
            ['Account Assistant', 'FIN', 'ACCOUNT_ASSISTANT', false],
            ['HR Manager', 'HR', 'HR_MANAGER', false],
            ['Project Manager', 'PRJ', 'PROJECT_MANAGER', true],
            ['Site In-Charge', 'SITE', 'SITE_SUPERVISOR', true],
            ['Mechanic', 'SITE', 'MECHANIC', true],
            ['Operator', 'SITE', 'OPERATOR', true],
            ['Purchase Manager', 'PUR', 'PURCHASE_MANAGER', false],
            ['Purchase Assistant', 'PUR', 'PURCHASE_ASSISTANT', false],
            ['Store Keeper', 'PUR', 'WAREHOUSE_INCHARGE', true],
            ['Marketing Manager', 'MKT', 'MARKETING_MANAGER', false],
        ];

        foreach ($designations as [$name, $deptCode, $roleCode, $mobile]) {
            Designation::firstOrCreate(
                ['name' => $name],
                [
                    'department_id' => $departmentIds[$deptCode] ?? null,
                    'default_role_id' => $roleIds[$roleCode] ?? null,
                    'mobile_access_default' => $mobile,
                    'status' => 'active',
                ]
            );
        }
    }

    /**
     * Baseline access per role, so a seeded account can actually work on day
     * one. Super Admin gets everything; the rest get their own area.
     */
    private function grantRolePermissions(): void
    {
        $grants = [
            'FINANCE_MANAGER' => [
                'Dashboard' => ['view', 'export'],
                'Accounting Dashboard' => ['view', 'export'],
                'Chart of Accounts' => ['view', 'create', 'edit', 'delete', 'export'],
                'Journal Entries' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'post'],
                'General Ledger' => ['view', 'export'],
                'Accounts Payable' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'process'],
                'Accounts Receivable' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'process'],
                'VAT Management' => ['view', 'edit', 'approve', 'export', 'process'],
                'ZATCA Invoicing' => ['view', 'create', 'edit', 'export', 'retry'],
                'Financial Reports' => ['view', 'export'],
                'Cost Centers' => ['view', 'create', 'edit', 'delete'],
                'Auto Posting Rules' => ['view', 'create', 'edit', 'delete'],
                'Payroll' => ['view', 'approve', 'export'],
            ],
            'ACCOUNT_ASSISTANT' => [
                'Dashboard' => ['view'],
                'Accounting Dashboard' => ['view'],
                'Chart of Accounts' => ['view'],
                'Journal Entries' => ['view', 'create', 'edit'],
                'General Ledger' => ['view'],
                'Accounts Payable' => ['view', 'create', 'edit'],
                'Accounts Receivable' => ['view', 'create', 'edit'],
                'Financial Reports' => ['view'],
            ],
            'HR_MANAGER' => [
                'Dashboard' => ['view'],
                'HR' => ['view', 'create', 'edit', 'approve', 'export'],
                'Payroll' => ['view', 'create', 'edit', 'export', 'process'],
                'Attendance' => ['view', 'create', 'edit', 'approve', 'export'],
                'Financial Reports' => ['view'],
            ],
            'PROJECT_MANAGER' => [
                'Dashboard' => ['view'],
                'Projects' => ['view', 'create', 'edit', 'approve', 'export'],
                'Sites' => ['view', 'create', 'edit'],
                'Attendance' => ['view', 'approve'],
                'Purchase Requests' => ['view', 'approve', 'reject', 'export'],
                'Warehouse Stock' => ['view'],
                'Inventory Reports' => ['view', 'export'],
                'Financial Reports' => ['view', 'export'],
                'Cost Centers' => ['view'],
            ],
            'MARKETING_MANAGER' => [
                'Dashboard' => ['view'],
                'Customers' => ['view', 'create', 'edit', 'export'],
                'Projects' => ['view'],
                'Accounts Receivable' => ['view', 'create', 'edit', 'export'],
                'Financial Reports' => ['view', 'export'],
            ],
            'PURCHASE_MANAGER' => [
                'Dashboard' => ['view'],
                'Inventory Dashboard' => ['view', 'export'],
                'Items' => ['view', 'create', 'edit', 'export'],
                'Item Categories' => ['view', 'create', 'edit'],
                'Units' => ['view', 'create', 'edit'],
                'Warehouse Stock' => ['view', 'export'],
                'Purchase Requests' => ['view', 'create', 'edit', 'approve', 'reject', 'export'],
                'Purchase Orders' => ['view', 'create', 'edit', 'delete', 'approve', 'export'],
                'Goods Receipts' => ['view', 'create', 'receive', 'post', 'export'],
                'Stock Ledger' => ['view', 'export'],
                'Inventory Reports' => ['view', 'export'],
                'Suppliers' => ['view', 'create', 'edit'],
            ],
            'PURCHASE_ASSISTANT' => [
                'Dashboard' => ['view'],
                'Inventory Dashboard' => ['view'],
                'Items' => ['view'],
                'Warehouse Stock' => ['view'],
                'Purchase Requests' => ['view', 'create', 'edit'],
                'Purchase Orders' => ['view', 'create', 'edit'],
                'Goods Receipts' => ['view', 'create'],
                'Stock Ledger' => ['view'],
                'Inventory Reports' => ['view'],
            ],
            'INVENTORY_MANAGER' => [
                'Dashboard' => ['view'],
                'Inventory Dashboard' => ['view', 'export'],
                'Items' => ['view', 'create', 'edit', 'delete', 'export'],
                'Item Categories' => ['view', 'create', 'edit', 'delete'],
                'Units' => ['view', 'create', 'edit', 'delete'],
                'Warehouse Stock' => ['view', 'export'],
                'Purchase Requests' => ['view', 'create', 'edit', 'approve', 'reject', 'export'],
                'Purchase Orders' => ['view', 'create', 'edit', 'approve', 'export'],
                'Goods Receipts' => ['view', 'create', 'edit', 'receive', 'post', 'export'],
                'Stock Issues' => ['view', 'create', 'edit', 'issue', 'post', 'export'],
                'Stock Transfers' => ['view', 'create', 'edit', 'transfer', 'export'],
                'Stock Adjustments' => ['view', 'create', 'edit', 'approve', 'adjust', 'post', 'export'],
                'Stock Ledger' => ['view', 'export'],
                'Inventory Reports' => ['view', 'export'],
            ],
            'WAREHOUSE_INCHARGE' => [
                'Dashboard' => ['view'],
                'Warehouse Stock' => ['view'],
                'Stock Ledger' => ['view'],
                'Goods Receipts' => ['view', 'create', 'receive', 'post'],
                'Stock Issues' => ['view', 'create', 'issue', 'post'],
                'Stock Transfers' => ['view', 'create', 'transfer'],
                'Stock Adjustments' => ['view', 'create', 'adjust'],
                'Inventory Reports' => ['view', 'export'],
            ],
            'SITE_SUPERVISOR' => [
                'Dashboard' => ['view'],
                'Attendance' => ['view', 'create', 'edit', 'approve', 'mobile'],
                'Projects' => ['view'],
                'Sites' => ['view'],
                'Purchase Requests' => ['view', 'create'],
                'Warehouse Stock' => ['view'],
                'HR' => ['view'],
            ],
            'MECHANIC' => [
                'Dashboard' => ['view'],
                'Attendance' => ['view', 'mobile'],
                'Warehouse Stock' => ['view'],
            ],
            'OPERATOR' => [
                'Dashboard' => ['view'],
                'Attendance' => ['view', 'mobile'],
            ],
            'SITE_WORKER' => [
                'Dashboard' => ['view'],
                'Attendance' => ['view', 'mobile'],
            ],
        ];

        Role::where('code', 'SUPER_ADMIN')->first()?->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());

        foreach ($grants as $roleCode => $moduleActions) {
            $role = Role::where('code', $roleCode)->first();

            if (! $role) {
                continue;
            }

            $ids = collect($moduleActions)
                ->flatMap(fn (array $actions, string $module) => Permission::where('module', $module)
                    ->whereIn('action', $actions)
                    ->pluck('id'))
                ->all();

            $role->permissions()->syncWithoutDetaching($ids);
        }
    }
}
