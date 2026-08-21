<?php

namespace App\Providers;

use App\Models\AttendanceRecord;
use App\Models\CustomerInvoice;
use App\Models\CustomerReceipt;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EndOfServiceRecord;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntryLine;
use App\Models\LeaveRequest;
use App\Models\OvertimeRecord;
use App\Models\PayrollRunItem;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\SalaryStructure;
use App\Models\Site;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockIssueLine;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\ZatcaInvoiceRecord;
use App\Services\UserAccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The ERP admin uses its own stylesheet, not Tailwind, so render
        // pagination with the ERP-styled view.
        Paginator::defaultView('pagination::erp');
        Paginator::defaultSimpleView('pagination::erp');
        Schema::defaultStringLength(191);

        $scopedModels = [
            Project::class, Site::class, Warehouse::class,
            Employee::class, AttendanceRecord::class,
            EmployeeDocument::class, LeaveRequest::class,
            OvertimeRecord::class, SalaryStructure::class,
            EndOfServiceRecord::class, PayrollRunItem::class,
            JournalEntryLine::class, SupplierBill::class,
            SupplierPayment::class, CustomerInvoice::class,
            CustomerReceipt::class, ZatcaInvoiceRecord::class,
            PurchaseRequest::class, PurchaseRequestLine::class,
            PurchaseOrder::class, PurchaseOrderLine::class,
            GoodsReceipt::class, GoodsReceiptLine::class,
            StockIssue::class, StockIssueLine::class,
            StockTransfer::class, StockTransferLine::class,
            StockAdjustment::class, StockLedgerEntry::class,
            WarehouseStock::class,
        ];

        foreach ($scopedModels as $modelClass) {
            $modelClass::addGlobalScope('user_access', function (Builder $builder) {
                if ($user = auth()->user()) {
                    app(UserAccessScopeService::class)->apply($builder, $builder->getModel(), $user);
                }
            });
        }
    }
}
