<?php

namespace App\Support;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\JournalEntry;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the grouped admin sidebar.
 *
 * Each group is collapsible; a group containing the active route is forced
 * open on render so deep links always reveal where you are.
 */
class SidebarMenu
{
    /**
     * @return array<int, array{key: string, label: string, items: array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        $badges = static::badges();

        $groups = [
            [
                'key' => 'main',
                'label' => 'Main',
                'items' => [
                    static::link('admin.dashboard', 'admin.dashboard', '📊', 'Dashboard'),
                ],
            ],
            [
                'key' => 'administration',
                'label' => 'Administration',
                'items' => [
                    static::link('admin.users.index', 'admin.users.*', '👥', 'Users'),
                    static::link('admin.roles.index', ['admin.roles.index', 'admin.roles.create', 'admin.roles.show', 'admin.roles.edit'], '🛡️', 'Roles'),
                    static::link('admin.roles.permission-matrix', 'admin.roles.permission-matrix', '✅', 'Permission Matrix'),
                    static::link('admin.roles.hierarchy', 'admin.roles.hierarchy', '🌳', 'Role Hierarchy'),
                    static::link('admin.roles.assign-users', 'admin.roles.assign-users*', '🔗', 'Assign Users'),
                    static::link('admin.roles.approval-workflows.index', 'admin.roles.approval-workflows.*', '🔁', 'Approval Workflows'),
                    static::link('admin.activity-logs.index', 'admin.activity-logs.*', '🧾', 'Activity Logs'),
                ],
            ],
            [
                'key' => 'master-setup',
                'label' => 'Master Setup',
                'items' => [
                    static::link('admin.master.company-profile', 'admin.master.company-profile', '🏢', 'Company Profile'),
                    static::link('admin.master.branches.index', 'admin.master.branches.*', '🏬', 'Branches'),
                    static::link('admin.master.departments.index', 'admin.master.departments.*', '🗂️', 'Departments'),
                    static::link('admin.master.designations.index', 'admin.master.designations.*', '🪪', 'Designations'),
                    static::link('admin.master.projects.index', 'admin.master.projects.*', '🏗️', 'Projects'),
                    static::link('admin.master.sites.index', 'admin.master.sites.*', '📍', 'Sites / Geo-Fence'),
                    static::link('admin.master.warehouses.index', 'admin.master.warehouses.*', '🏭', 'Warehouses'),
                    static::link('admin.master.expense-categories.index', 'admin.master.expense-categories.*', '🏷️', 'Expense Categories'),
                    static::link('admin.master.suppliers.index', 'admin.master.suppliers.*', '🚚', 'Suppliers'),
                    static::link('admin.master.customers.index', 'admin.master.customers.*', '🤝', 'Customers'),
                ],
            ],
            [
                'key' => 'operations',
                'label' => 'Operations',
                'items' => [
                    static::link('admin.hr.dashboard', 'admin.hr.dashboard', '📋', 'HR Dashboard'),
                    static::link('admin.hr.employees.index', 'admin.hr.employees.*', '👷', 'Employees'),
                    static::link('admin.hr.documents.index', 'admin.hr.documents.*', '🪪', 'Documents / IQAMA'),
                    static::link('admin.hr.shifts.index', 'admin.hr.shifts.*', '🔄', 'Shifts'),
                    static::link('admin.hr.attendance.index', 'admin.hr.attendance.*', '🕒', 'Attendance'),
                    static::link('admin.hr.leaves.index', 'admin.hr.leaves.*', '🌴', 'Leaves', badge: $badges['leaves']),
                    static::link('admin.hr.overtime.index', 'admin.hr.overtime.*', '⏱️', 'Overtime'),
                    static::link('admin.hr.salary-structures.index', 'admin.hr.salary-structures.*', '🧮', 'Salary Structures'),
                    static::link('admin.hr.payroll.index', 'admin.hr.payroll.*', '💵', 'Payroll'),
                    static::link('admin.hr.eosb.index', 'admin.hr.eosb.*', '📄', 'End of Service'),
                    static::soon('project-dashboard', '📊', 'Projects & Site Expenses'),
                    static::soon('equipment', '🚜', 'Equipment & Vehicles'),
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'Finance',
                'items' => [
                    static::link('admin.accounting.dashboard', 'admin.accounting.dashboard', '📊', 'Accounting Dashboard'),
                    static::link('admin.accounting.chart-of-accounts.index', 'admin.accounting.chart-of-accounts.*', '📒', 'Chart of Accounts'),
                    static::link('admin.accounting.journal-entries.index', 'admin.accounting.journal-entries.*', '📓', 'Journal Entries', badge: $badges['journals']),
                    static::link('admin.accounting.general-ledger', 'admin.accounting.general-ledger', '📚', 'General Ledger'),
                    static::link('admin.accounting.accounts-payable.index', 'admin.accounting.accounts-payable.*', '📤', 'Accounts Payable'),
                    static::link('admin.accounting.accounts-receivable.index', 'admin.accounting.accounts-receivable.*', '📥', 'Accounts Receivable'),
                    static::link('admin.accounting.vat.index', 'admin.accounting.vat.*', '🧮', 'VAT Management'),
                    static::link('admin.accounting.zatca.index', 'admin.accounting.zatca.*', '🧾', 'ZATCA E-Invoicing'),
                    static::link('admin.accounting.cost-centers.index', 'admin.accounting.cost-centers.*', '🎯', 'Cost Centers'),
                    static::link('admin.accounting.posting-rules.index', 'admin.accounting.posting-rules.*', '⚙️', 'Automatic Posting Rules'),
                ],
            ],
            static::inventoryGroup($badges),
            [
                'key' => 'reports',
                'label' => 'Reports',
                'items' => array_values(array_filter([
                    static::link('admin.accounting.reports.index', 'admin.accounting.reports.*', '📈', 'Accounting Reports'),
                    static::routeExists('admin.inventory.reports.index')
                        ? static::link('admin.inventory.reports.index', 'admin.inventory.reports.*', '📦', 'Inventory Reports')
                        : static::soon('inventory-reports', '📦', 'Inventory Reports'),
                    static::soon('hr-reports', '🧾', 'HR Reports'),
                    static::soon('project-reports', '🏗️', 'Project Reports'),
                ])),
            ],
            [
                'key' => 'settings',
                'label' => 'Settings',
                'items' => [
                    static::soon('settings', '⚙️', 'System Settings'),
                ],
            ],
        ];

        foreach ($groups as &$group) {
            $group['items'] = array_values(array_filter($group['items']));
        }

        return array_values(array_filter($groups, fn ($group) => $group && $group['items'] !== []));
    }

    /**
     * Inventory becomes live links once the Phase 6 routes are registered.
     */
    private static function inventoryGroup(array $badges): array
    {
        if (! static::routeExists('admin.inventory.dashboard')) {
            return [
                'key' => 'inventory',
                'label' => 'Inventory',
                'items' => [
                    static::soon('materials', '📦', 'Materials'),
                    static::soon('stock-in', '📩', 'Stock In'),
                    static::soon('stock-out', '📨', 'Stock Out'),
                    static::soon('stock-transfers', '🔀', 'Stock Transfers'),
                    static::soon('low-stock-alerts', '🚨', 'Low Stock Alerts'),
                ],
            ];
        }

        return [
            'key' => 'inventory',
            'label' => 'Inventory',
            'items' => [
                static::link('admin.inventory.dashboard', 'admin.inventory.dashboard', '📊', 'Inventory Dashboard'),
                static::link('admin.inventory.items.index', 'admin.inventory.items.*', '📦', 'Materials / Items'),
                static::link('admin.inventory.categories.index', 'admin.inventory.categories.*', '🗂️', 'Item Categories'),
                static::link('admin.inventory.units.index', 'admin.inventory.units.*', '📏', 'Units'),
                static::link('admin.inventory.stock.index', 'admin.inventory.stock.*', '🏬', 'Stock On Hand', badge: $badges['lowStock']),
                static::link('admin.inventory.purchase-requests.index', 'admin.inventory.purchase-requests.*', '📝', 'Purchase Requests', badge: $badges['purchaseRequests']),
                static::link('admin.inventory.purchase-orders.index', 'admin.inventory.purchase-orders.*', '🧾', 'Purchase Orders'),
                static::link('admin.inventory.goods-receipts.index', 'admin.inventory.goods-receipts.*', '📩', 'Goods Receipt Notes'),
                static::link('admin.inventory.stock-issues.index', 'admin.inventory.stock-issues.*', '📨', 'Stock Issues'),
                static::link('admin.inventory.stock-transfers.index', 'admin.inventory.stock-transfers.*', '🔀', 'Stock Transfers'),
                static::link('admin.inventory.stock-adjustments.index', 'admin.inventory.stock-adjustments.*', '⚖️', 'Stock Adjustments'),
                static::link('admin.inventory.stock-ledger', 'admin.inventory.stock-ledger', '📚', 'Stock Ledger'),
                static::link('admin.inventory.reports.index', 'admin.inventory.reports.*', '📈', 'Inventory Reports'),
            ],
        ];
    }

    /**
     * @param  string|array<int, string>  $pattern
     * @return array<string, mixed>
     */
    private static function link(string $route, string|array $pattern, string $icon, string $label, ?int $badge = null): ?array
    {
        [$module] = EnsureUserHasPermission::permissionForRoute($route);
        if (auth()->check() && (! $module || ! auth()->user()->hasPermission($module, 'view'))) {
            return null;
        }

        $patterns = (array) $pattern;

        return [
            'url' => route($route),
            'active' => request()->routeIs(...$patterns),
            'icon' => $icon,
            'label' => $label,
            'soon' => false,
            'badge' => $badge > 0 ? $badge : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function soon(string $module, string $icon, string $label): ?array
    {
        [$permissionModule] = EnsureUserHasPermission::permissionForRoute('admin.coming-soon', $module);
        if (auth()->check() && ! auth()->user()->hasPermission($permissionModule, 'view')) {
            return null;
        }

        return [
            'url' => route('admin.coming-soon', $module),
            'active' => request()->routeIs('admin.coming-soon') && request()->route('module') === $module,
            'icon' => $icon,
            'label' => $label,
            'soon' => true,
            'badge' => null,
        ];
    }

    private static function routeExists(string $name): bool
    {
        return app('router')->has($name);
    }

    /**
     * Pending-work counts shown as sidebar badges. Guarded on table existence
     * so the panel still renders before a phase's migrations have run.
     *
     * @return array<string, int>
     */
    private static function badges(): array
    {
        return [
            'leaves' => Schema::hasTable('leave_requests')
                ? LeaveRequest::where('status', 'pending')->count()
                : 0,
            'journals' => Schema::hasTable('journal_entries')
                ? JournalEntry::whereIn('status', ['draft', 'approved'])->count()
                : 0,
            'purchaseRequests' => Schema::hasTable('purchase_requests')
                ? \App\Models\PurchaseRequest::where('status', 'pending')->count()
                : 0,
            'lowStock' => Schema::hasTable('warehouse_stocks')
                ? \App\Models\WarehouseStock::lowStockCount()
                : 0,
        ];
    }
}
