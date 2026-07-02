<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ApprovalWorkflowController;
use App\Http\Controllers\Admin\DashboardController;
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
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
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
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
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

    // Future modules (Phase 3+) render a Coming Soon page.
    Route::get('coming-soon/{module}', function (string $module) {
        return view('admin.coming-soon', [
            'module' => (string) Str::of($module)->replace('-', ' ')->title()->replace('Zatca', 'ZATCA'),
        ]);
    })->where('module', '[a-z0-9\-]+')->name('coming-soon');
});
