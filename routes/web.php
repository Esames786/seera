<?php

use App\Http\Controllers\Admin\Accounting\AccountingDashboardController;
use App\Http\Controllers\Admin\Accounting\AccountsPayableController;
use App\Http\Controllers\Admin\Accounting\AccountsReceivableController;
use App\Http\Controllers\Admin\Accounting\AutoPostingRuleController;
use App\Http\Controllers\Admin\Accounting\ChartOfAccountController;
use App\Http\Controllers\Admin\Accounting\CostCenterController;
use App\Http\Controllers\Admin\Accounting\FinancialReportController;
use App\Http\Controllers\Admin\Accounting\GeneralLedgerController;
use App\Http\Controllers\Admin\Accounting\JournalEntryController;
use App\Http\Controllers\Admin\Accounting\VatController;
use App\Http\Controllers\Admin\Accounting\ZatcaInvoiceController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ApprovalWorkflowController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Hr\AttendanceController;
use App\Http\Controllers\Admin\Hr\EmployeeController;
use App\Http\Controllers\Admin\Hr\EmployeeDocumentController;
use App\Http\Controllers\Admin\Hr\EndOfServiceController;
use App\Http\Controllers\Admin\Hr\HrDashboardController;
use App\Http\Controllers\Admin\Hr\LeaveRequestController;
use App\Http\Controllers\Admin\Hr\OvertimeController;
use App\Http\Controllers\Admin\Hr\PayrollRunController;
use App\Http\Controllers\Admin\Hr\SalaryStructureController;
use App\Http\Controllers\Admin\Hr\ShiftController;
use App\Http\Controllers\Admin\Inventory\GoodsReceiptController;
use App\Http\Controllers\Admin\Inventory\InventoryDashboardController;
use App\Http\Controllers\Admin\Inventory\InventoryReportController;
use App\Http\Controllers\Admin\Inventory\ItemCategoryController;
use App\Http\Controllers\Admin\Inventory\ItemController;
use App\Http\Controllers\Admin\Inventory\PurchaseOrderController;
use App\Http\Controllers\Admin\Inventory\PurchaseRequestController;
use App\Http\Controllers\Admin\Inventory\StockAdjustmentController;
use App\Http\Controllers\Admin\Inventory\StockController;
use App\Http\Controllers\Admin\Inventory\StockIssueController;
use App\Http\Controllers\Admin\Inventory\StockLedgerController;
use App\Http\Controllers\Admin\Inventory\StockTransferController;
use App\Http\Controllers\Admin\Inventory\UnitController;
use App\Http\Controllers\Admin\Master\BranchController;
use App\Http\Controllers\Admin\Master\CompanyProfileController;
use App\Http\Controllers\Admin\Master\CustomerController;
use App\Http\Controllers\Admin\Master\DepartmentController;
use App\Http\Controllers\Admin\Master\DesignationController;
use App\Http\Controllers\Admin\Master\ExpenseCategoryController;
use App\Http\Controllers\Admin\Master\ProjectController;
use App\Http\Controllers\Admin\Master\SiteController;
use App\Http\Controllers\Admin\Master\SupplierController;
use App\Http\Controllers\Admin\Master\WarehouseController;
use App\Http\Controllers\Admin\PasswordChangeController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoleHierarchyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'admin.dashboard' : 'login'));

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Admin (Phase 1 + Phase 2)
|--------------------------------------------------------------------------
*/
/*
 * Setting your own password is not a permissioned action, so it sits outside the
 * permission and scope checks. Without this an account issued with the shared
 * default password would be redirected here and then refused entry.
 */
Route::middleware(['auth', 'active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('set-password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('set-password', [PasswordChangeController::class, 'update'])->name('password.change.update');
});

Route::middleware(['auth', 'active', 'password.changed', 'permission', 'scope'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::resource('users', UserController::class);

    // Roles - static routes must be registered before roles/{role}.
    Route::get('roles/permission-matrix', [PermissionMatrixController::class, 'index'])->name('roles.permission-matrix');
    Route::put('roles/permission-matrix', [PermissionMatrixController::class, 'update'])->name('roles.permission-matrix.update');
    Route::get('roles/hierarchy', [RoleHierarchyController::class, 'index'])->name('roles.hierarchy');
    Route::get('roles/assign-users', [RoleController::class, 'assignUsers'])->name('roles.assign-users');
    Route::post('roles/assign-users', [RoleController::class, 'saveAssignedUsers'])->name('roles.assign-users.save');

    Route::get('roles/approval-workflows', [ApprovalWorkflowController::class, 'index'])->name('roles.approval-workflows.index');
    Route::get('roles/approval-workflows/create', [ApprovalWorkflowController::class, 'create'])->name('roles.approval-workflows.create');
    Route::post('roles/approval-workflows', [ApprovalWorkflowController::class, 'store'])->name('roles.approval-workflows.store');
    Route::get('roles/approval-workflows/{approval_workflow}/edit', [ApprovalWorkflowController::class, 'edit'])->name('roles.approval-workflows.edit');
    Route::put('roles/approval-workflows/{approval_workflow}', [ApprovalWorkflowController::class, 'update'])->name('roles.approval-workflows.update');
    Route::delete('roles/approval-workflows/{approval_workflow}', [ApprovalWorkflowController::class, 'destroy'])->name('roles.approval-workflows.destroy');

    Route::resource('roles', RoleController::class);

    // Activity Logs
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    // Master Setup
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('company-profile', [CompanyProfileController::class, 'edit'])->name('company-profile');
        Route::put('company-profile', [CompanyProfileController::class, 'update'])->name('company-profile.update');

        Route::resource('branches', BranchController::class);
        Route::resource('departments', DepartmentController::class);
        Route::resource('designations', DesignationController::class);
        Route::resource('projects', ProjectController::class);
        Route::resource('sites', SiteController::class);
        Route::resource('warehouses', WarehouseController::class);
        Route::resource('expense-categories', ExpenseCategoryController::class);
        Route::resource('suppliers', SupplierController::class);
        Route::resource('customers', CustomerController::class);
    });

    // HR & Payroll (Phase 3)
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::get('dashboard', [HrDashboardController::class, 'index'])->name('dashboard');

        Route::resource('employees', EmployeeController::class);
        Route::get('documents', [EmployeeDocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('documents.download');
        Route::resource('shifts', ShiftController::class)->except(['show']);

        Route::resource('attendance', AttendanceController::class)
            ->except(['show'])
            ->parameters(['attendance' => 'attendance_record']);

        Route::post('leaves/{leave_request}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
        Route::post('leaves/{leave_request}/reject', [LeaveRequestController::class, 'reject'])->name('leaves.reject');
        Route::resource('leaves', LeaveRequestController::class)->parameters(['leaves' => 'leave_request']);

        Route::post('overtime/{overtime_record}/approve', [OvertimeController::class, 'approve'])->name('overtime.approve');
        Route::resource('overtime', OvertimeController::class)
            ->except(['show'])
            ->parameters(['overtime' => 'overtime_record']);

        Route::resource('salary-structures', SalaryStructureController::class);

        Route::post('payroll/{payroll_run}/process', [PayrollRunController::class, 'process'])->name('payroll.process');
        Route::post('payroll/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->name('payroll.approve');
        Route::resource('payroll', PayrollRunController::class)->parameters(['payroll' => 'payroll_run']);

        Route::post('eosb/{end_of_service_record}/approve', [EndOfServiceController::class, 'approve'])->name('eosb.approve');
        Route::resource('eosb', EndOfServiceController::class)->parameters(['eosb' => 'end_of_service_record']);
    });

    // Accounting + VAT + ZATCA (Phase 4)
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('dashboard', [AccountingDashboardController::class, 'index'])->name('dashboard');

        Route::resource('chart-of-accounts', ChartOfAccountController::class);

        Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
        Route::post('journal-entries/{journal_entry}/cancel', [JournalEntryController::class, 'cancel'])->name('journal-entries.cancel');
        Route::resource('journal-entries', JournalEntryController::class);

        Route::get('general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger');

        Route::post('accounts-payable/{accounts_payable}/approve', [AccountsPayableController::class, 'approve'])->name('accounts-payable.approve');
        Route::get('accounts-payable/{accounts_payable}/payment', [AccountsPayableController::class, 'paymentForm'])->name('accounts-payable.payment');
        Route::post('accounts-payable/{accounts_payable}/payment', [AccountsPayableController::class, 'storePayment'])->name('accounts-payable.payment.store');
        Route::resource('accounts-payable', AccountsPayableController::class);

        Route::post('accounts-receivable/{accounts_receivable}/approve', [AccountsReceivableController::class, 'approve'])->name('accounts-receivable.approve');
        Route::get('accounts-receivable/{accounts_receivable}/receipt', [AccountsReceivableController::class, 'receiptForm'])->name('accounts-receivable.receipt');
        Route::post('accounts-receivable/{accounts_receivable}/receipt', [AccountsReceivableController::class, 'storeReceipt'])->name('accounts-receivable.receipt.store');
        Route::resource('accounts-receivable', AccountsReceivableController::class);

        Route::get('vat', [VatController::class, 'index'])->name('vat.index');
        Route::get('vat/{vat}', [VatController::class, 'show'])->name('vat.show');
        Route::post('vat/{vat}/recalculate', [VatController::class, 'recalculate'])->name('vat.recalculate');
        Route::post('vat/{vat}/finalize', [VatController::class, 'finalize'])->name('vat.finalize');

        Route::get('zatca', [ZatcaInvoiceController::class, 'index'])->name('zatca.index');
        Route::get('zatca/{zatca}', [ZatcaInvoiceController::class, 'show'])->name('zatca.show');
        Route::post('zatca/{zatca}/retry', [ZatcaInvoiceController::class, 'retry'])->name('zatca.retry');

        // Report routes are static, so they must be registered before any wildcard.
        Route::get('reports', [FinancialReportController::class, 'index'])->name('reports.index');
        Route::get('reports/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('reports/profit-loss', [FinancialReportController::class, 'profitLoss'])->name('reports.profit-loss');
        Route::get('reports/trial-balance', [FinancialReportController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('reports/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('reports.cash-flow');
        Route::get('reports/vat-report', [FinancialReportController::class, 'vatReport'])->name('reports.vat-report');
        Route::get('reports/project-cost-report', [FinancialReportController::class, 'projectCostReport'])->name('reports.project-cost-report');

        Route::resource('cost-centers', CostCenterController::class);
        Route::resource('posting-rules', AutoPostingRuleController::class)->parameters(['posting-rules' => 'posting_rule']);
    });

    // Inventory & Warehouse (Phase 6)
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('dashboard', [InventoryDashboardController::class, 'index'])->name('dashboard');

        Route::resource('items', ItemController::class);
        Route::resource('categories', ItemCategoryController::class)->except(['show'])->parameters(['categories' => 'category']);
        Route::resource('units', UnitController::class)->except(['show']);

        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock-ledger', [StockLedgerController::class, 'index'])->name('stock-ledger');

        // Report routes are static, so they are registered before any wildcard.
        Route::get('reports', [InventoryReportController::class, 'index'])->name('reports.index');
        Route::get('reports/stock-valuation', [InventoryReportController::class, 'stockValuation'])->name('reports.stock-valuation');
        Route::get('reports/low-stock', [InventoryReportController::class, 'lowStock'])->name('reports.low-stock');
        Route::get('reports/project-consumption', [InventoryReportController::class, 'projectConsumption'])->name('reports.project-consumption');
        Route::get('reports/movement', [InventoryReportController::class, 'movement'])->name('reports.movement');

        Route::post('purchase-requests/{purchase_request}/approve', [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');
        Route::post('purchase-requests/{purchase_request}/reject', [PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
        Route::resource('purchase-requests', PurchaseRequestController::class);

        Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
        Route::resource('purchase-orders', PurchaseOrderController::class);

        Route::post('goods-receipts/{goods_receipt}/post-stock', [GoodsReceiptController::class, 'postStock'])->name('goods-receipts.post-stock');
        Route::resource('goods-receipts', GoodsReceiptController::class);

        Route::post('stock-issues/{stock_issue}/post', [StockIssueController::class, 'post'])->name('stock-issues.post');
        Route::resource('stock-issues', StockIssueController::class);

        Route::post('stock-transfers/{stock_transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('stock-transfers.dispatch');
        Route::post('stock-transfers/{stock_transfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
        Route::resource('stock-transfers', StockTransferController::class);

        Route::post('stock-adjustments/{stock_adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
        Route::post('stock-adjustments/{stock_adjustment}/post', [StockAdjustmentController::class, 'post'])->name('stock-adjustments.post');
        Route::resource('stock-adjustments', StockAdjustmentController::class);
    });

    // Future modules (Phase 5, 7+) render a Coming Soon page.
    Route::get('coming-soon/{module}', function (string $module) {
        return view('admin.coming-soon', [
            'module' => (string) Str::of($module)->replace('-', ' ')->title()->replace('Zatca', 'ZATCA'),
        ]);
    })->where('module', '[a-z0-9\-]+')->name('coming-soon');
});
