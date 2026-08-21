<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /** @var array<string, string> */
    private const MODULES = [
        'dashboard' => 'Dashboard',
        'users' => 'Users',
        'roles' => 'Roles',
        'activity-logs' => 'Activity Logs',
        'master.company-profile' => 'Company Profile',
        'master.branches' => 'Branches',
        'master.departments' => 'Departments',
        'master.designations' => 'Designations',
        'master.projects' => 'Projects',
        'master.sites' => 'Sites',
        'master.warehouses' => 'Warehouses',
        'master.expense-categories' => 'Expense Categories',
        'master.suppliers' => 'Suppliers',
        'master.customers' => 'Customers',
        'hr.dashboard' => 'HR',
        'hr.employees' => 'HR',
        'hr.documents' => 'HR',
        'hr.shifts' => 'HR',
        'hr.attendance' => 'Attendance',
        'hr.leaves' => 'HR',
        'hr.overtime' => 'Payroll',
        'hr.salary-structures' => 'Payroll',
        'hr.payroll' => 'Payroll',
        'hr.eosb' => 'Payroll',
        'accounting.dashboard' => 'Accounting Dashboard',
        'accounting.chart-of-accounts' => 'Chart of Accounts',
        'accounting.journal-entries' => 'Journal Entries',
        'accounting.general-ledger' => 'General Ledger',
        'accounting.accounts-payable' => 'Accounts Payable',
        'accounting.accounts-receivable' => 'Accounts Receivable',
        'accounting.vat' => 'VAT Management',
        'accounting.zatca' => 'ZATCA Invoicing',
        'accounting.reports' => 'Financial Reports',
        'accounting.cost-centers' => 'Cost Centers',
        'accounting.posting-rules' => 'Auto Posting Rules',
        'inventory.dashboard' => 'Inventory Dashboard',
        'inventory.items' => 'Items',
        'inventory.categories' => 'Item Categories',
        'inventory.units' => 'Units',
        'inventory.stock' => 'Warehouse Stock',
        'inventory.stock-ledger' => 'Stock Ledger',
        'inventory.reports' => 'Inventory Reports',
        'inventory.purchase-requests' => 'Purchase Requests',
        'inventory.purchase-orders' => 'Purchase Orders',
        'inventory.goods-receipts' => 'Goods Receipts',
        'inventory.stock-issues' => 'Stock Issues',
        'inventory.stock-transfers' => 'Stock Transfers',
        'inventory.stock-adjustments' => 'Stock Adjustments',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();
        [$module, $action] = self::permissionForRoute($routeName, $request->route('module'));

        abort_unless($module && $request->user()?->hasPermission($module, $action), 403, 'You do not have permission to perform this action.');

        return $next($request);
    }

    /** @return array{0: ?string, 1: string} */
    public static function permissionForRoute(string $routeName, ?string $comingSoonModule = null): array
    {
        $name = str($routeName)->after('admin.')->toString();

        if ($name === 'coming-soon') {
            return [match ($comingSoonModule) {
                'project-dashboard', 'project-reports' => 'Projects',
                'equipment' => 'Equipment',
                'inventory-reports' => 'Inventory Reports',
                'hr-reports' => 'HR',
                'settings' => 'Settings',
                default => 'Dashboard',
            }, 'view'];
        }

        $module = collect(self::MODULES)
            ->sortKeysDesc()
            ->first(fn (string $value, string $prefix) => $name === $prefix || str_starts_with($name, $prefix.'.'));

        $suffix = str($name)->afterLast('.')->toString();
        $action = match ($suffix) {
            'create', 'store' => 'create',
            'edit', 'update', 'cancel' => 'edit',
            'destroy' => 'delete',
            'approve', 'finalize' => 'approve',
            'reject' => 'reject',
            'post', 'post-stock' => 'post',
            'process', 'payment', 'receipt', 'save', 'recalculate' => 'process',
            'receive' => 'receive',
            'dispatch' => 'transfer',
            'retry' => 'retry',
            default => 'view',
        };

        if (str_ends_with($name, '.payment.store') || str_ends_with($name, '.receipt.store')) {
            $action = 'process';
        }

        return [$module, $action];
    }
}
