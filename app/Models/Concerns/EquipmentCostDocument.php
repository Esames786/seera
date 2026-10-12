<?php

namespace App\Models\Concerns;

use App\Models\ChartOfAccount;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentDocument;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;

/**
 * Shared shape of the two equipment cost records (fuel logs, maintenance
 * records): an operational entry that becomes project cost only once Finance
 * posts it (Cash / Bank journal) or the linked supplier bill is posted.
 */
trait EquipmentCostDocument
{
    public const PAYMENT_TYPES = ['Cash', 'Bank', 'Supplier Credit'];

    public const ACC_NOT_POSTED = 'not_posted';

    public const ACC_BILL_PENDING = 'bill_pending';

    public const ACC_POSTED = 'posted';

    public const ACC_FAILED = 'failed';

    public function isPosted(): bool
    {
        return $this->accounting_status === self::ACC_POSTED;
    }

    public function isFinanciallyLinked(): bool
    {
        return $this->journal_entry_id !== null || $this->supplier_bill_id !== null;
    }

    public function accountingLabel(): string
    {
        return match ($this->accounting_status) {
            self::ACC_POSTED => 'Posted to accounting',
            self::ACC_BILL_PENDING => 'Supplier bill created — awaiting Finance',
            self::ACC_FAILED => 'Posting failed',
            default => 'Not posted',
        };
    }

    public function asset()
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }

    public function assignment()
    {
        return $this->belongsTo(EquipmentAssignment::class, 'equipment_assignment_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function supplierBill()
    {
        return $this->belongsTo(SupplierBill::class);
    }

    public function document()
    {
        return $this->belongsTo(EquipmentDocument::class, 'document_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
