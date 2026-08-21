<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    public const MODULES = [
        'Dashboard', 'Users', 'Roles', 'Accounting', 'HR', 'Payroll',
        'Attendance', 'Projects', 'Sites', 'Inventory', 'Site Expenses',
        'Equipment', 'Vehicles', 'ZATCA Invoicing', 'Reports', 'Settings',
        'Activity Logs', 'Company Profile', 'Branches', 'Departments',
        'Designations', 'Warehouses', 'Expense Categories', 'Suppliers', 'Customers',
        'Accounting Dashboard', 'Chart of Accounts', 'Journal Entries',
        'General Ledger', 'Accounts Payable', 'Accounts Receivable',
        'VAT Management', 'Financial Reports', 'Cost Centers', 'Auto Posting Rules',
        'Inventory Dashboard', 'Items', 'Item Categories', 'Units',
        'Warehouse Stock', 'Purchase Requests', 'Purchase Orders',
        'Goods Receipts', 'Stock Issues', 'Stock Transfers',
        'Stock Adjustments', 'Stock Ledger', 'Inventory Reports',
    ];

    /**
     * Every action the permission matrix can grant. Keep this in sync with the
     * seeder — the matrix only submits checkboxes for the actions listed here,
     * so an omitted action would be silently revoked on save.
     */
    public const ACTIONS = [
        'view', 'create', 'edit', 'delete', 'approve', 'reject',
        'export', 'mobile', 'post', 'process', 'retry',
        'receive', 'issue', 'transfer', 'adjust',
    ];

    protected $fillable = ['module', 'module_group', 'action'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
