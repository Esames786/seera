<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Permission;
use Illuminate\Support\Collection;

/**
 * Which permission modules matter to which department.
 *
 * The client asked for the permission matrix to show only the modules that
 * are relevant to the department a role belongs to (Accounts sees accounting,
 * HR sees HR and payroll, and so on) instead of the whole catalogue. These
 * groups only decide what is shown first; hiding a row never grants or
 * revokes anything, and every screen keeps a "show all modules" switch.
 */
class PermissionGroups
{
    /** Shown to every department so nobody loses the landing page. */
    public const COMMON = ['Dashboard'];

    /**
     * Department code => label and relevant modules. Codes match the seeded
     * departments; an unknown code falls back to the full catalogue.
     *
     * @var array<string, array{label: string, modules: array<int, string>}>
     */
    public const GROUPS = [
        'ADMIN' => [
            'label' => 'Administration',
            'modules' => [
                'Users', 'Roles', 'Settings', 'Activity Logs', 'Company Profile',
                'Branches', 'Departments', 'Designations', 'Warehouses',
                'Expense Categories', 'Suppliers', 'Customers', 'Projects', 'Sites', 'Reports',
            ],
        ],
        'FIN' => [
            'label' => 'Accounts',
            'modules' => [
                'Accounting', 'Accounting Dashboard', 'Chart of Accounts', 'Journal Entries',
                'General Ledger', 'Accounts Payable', 'Accounts Receivable', 'VAT Management',
                'ZATCA Invoicing', 'Financial Reports', 'Cost Centers', 'Auto Posting Rules',
                'Payroll', 'Site Expenses', 'Suppliers', 'Customers', 'Reports',
            ],
        ],
        'HR' => [
            'label' => 'Human Resource',
            'modules' => [
                'HR', 'Payroll', 'Attendance', 'Departments', 'Designations',
                'Financial Reports', 'Reports',
            ],
        ],
        'PRJ' => [
            'label' => 'Projects',
            'modules' => [
                'Projects', 'Sites', 'Site Expenses', 'Attendance', 'Customers',
                'Purchase Requests', 'Warehouse Stock', 'Inventory Reports',
                'Financial Reports', 'Cost Centers', 'Equipment', 'Vehicles', 'Reports',
            ],
        ],
        'SITE' => [
            'label' => 'Site Operations',
            'modules' => [
                'Attendance', 'Projects', 'Sites', 'Site Expenses', 'HR',
                'Purchase Requests', 'Warehouse Stock', 'Stock Issues',
                'Equipment', 'Vehicles',
            ],
        ],
        'PUR' => [
            'label' => 'Purchase & Stores',
            'modules' => [
                'Inventory', 'Inventory Dashboard', 'Items', 'Item Categories', 'Units',
                'Warehouses', 'Warehouse Stock', 'Purchase Requests', 'Purchase Orders',
                'Goods Receipts', 'Stock Issues', 'Stock Transfers', 'Stock Adjustments',
                'Stock Ledger', 'Inventory Reports', 'Suppliers', 'Accounts Payable',
            ],
        ],
        'MKT' => [
            'label' => 'Marketing',
            'modules' => [
                'Marketing', 'Customers', 'Projects', 'Accounts Receivable', 'ZATCA Invoicing',
                'Financial Reports', 'Reports',
            ],
        ],
    ];

    /**
     * Modules relevant to a department, in catalogue order. Null means the
     * department has no defined group and the full catalogue applies.
     *
     * @return array<int, string>|null
     */
    public static function modulesForCode(?string $code): ?array
    {
        if ($code === null || ! isset(self::GROUPS[$code])) {
            return null;
        }

        $wanted = array_merge(self::COMMON, self::GROUPS[$code]['modules']);

        return array_values(array_filter(
            Permission::MODULES,
            fn (string $module) => in_array($module, $wanted, true)
        ));
    }

    /**
     * @return array<int, string>|null
     */
    public static function modulesForDepartment(?Department $department): ?array
    {
        return self::modulesForCode($department?->code);
    }

    /**
     * Department id => relevant modules, for the role form's client-side filter.
     * Departments without a group are omitted so the form shows everything.
     *
     * @param  Collection<int, Department>  $departments
     * @return array<int, array<int, string>>
     */
    public static function byDepartmentId(Collection $departments): array
    {
        return $departments
            ->mapWithKeys(fn (Department $department) => [$department->id => self::modulesForCode($department->code)])
            ->filter()
            ->all();
    }

    /**
     * Group options for the standalone matrix filter: code => label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::GROUPS)->map(fn (array $group) => $group['label'])->all();
    }

    public static function label(?string $code): ?string
    {
        return $code !== null ? (self::GROUPS[$code]['label'] ?? null) : null;
    }
}
