<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteExpense extends Model
{
    public const PAYMENT_TYPES = ['Cash', 'Bank', 'Employee Reimbursement', 'Supplier Credit'];

    public const STATUSES = ['draft', 'pending', 'rejected', 'approved_pending_posting', 'posted', 'cancelled', 'reversed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'taxable_amount' => 'decimal:2', 'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'accounting_posted' => 'boolean',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    // The runtime's canonical requester is always the original site submitter.
    public function getRequestedByAttribute(): int
    {
        return (int) $this->submitted_by_user_id;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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

    public function settlementJournal()
    {
        return $this->belongsTo(JournalEntry::class, 'settlement_journal_id');
    }

    public function reversalJournal()
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_id');
    }

    public function receipts()
    {
        return $this->hasMany(SiteExpenseReceipt::class);
    }

    public function approvals()
    {
        return $this->hasMany(ApprovalInstance::class, 'source_id')->where('source_type', 'site_expense');
    }
}
