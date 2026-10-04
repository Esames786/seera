<?php

namespace App\Models;

use App\Services\SiteExpenses\SiteExpenseAccountingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SupplierBill extends Model
{
    public const STATUSES = ['draft', 'approved', 'unpaid', 'partially_paid', 'paid', 'cancelled'];

    protected $fillable = [
        'supplier_id', 'bill_number', 'bill_date', 'due_date', 'reference_number',
        'project_id', 'site_id', 'cost_center_id', 'taxable_amount', 'vat_rate',
        'vat_amount', 'total_amount', 'paid_amount', 'balance_amount', 'status',
        'journal_entry_id', 'notes', 'site_expense_id',
        'approval_mode', 'approval_status', 'requested_by', 'last_edited_by', 'rejection_reason', 'posting_error',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function siteExpense()
    {
        return $this->belongsTo(SiteExpense::class);
    }

    protected static function booted(): void
    {
        static::updated(function (SupplierBill $bill) {
            if ($bill->site_expense_id && $bill->wasChanged(['status', 'journal_entry_id'])) {
                DB::afterCommit(fn () => app(SiteExpenseAccountingService::class)->sync($bill->site_expense_id));
            }
        });
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'cancelled'], true)
            && ! in_array($this->approval_status, ['pending', 'approved'], true);
    }

    public function approvals()
    {
        return $this->hasMany(ApprovalInstance::class, 'source_id')->where('source_type', 'supplier_bill');
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['unpaid', 'partially_paid'], true) && $this->journalEntry?->status === 'posted';
    }

    public function approvalLabel(): string
    {
        return match ($this->approval_status) {
            'pending' => 'Pending Approval',
            'rejected' => 'Rejected',
            'correction' => 'Correction — new approval required',
            'approved' => $this->journalEntry?->status === 'posted' ? 'Approved' : 'Approved — Posting Pending',
            default => $this->approval_mode === 'runtime' ? 'Draft — not submitted' : 'Legacy — no runtime history',
        };
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function lines()
    {
        return $this->hasMany(SupplierBillLine::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** Goods receipt lines this bill invoices (F04). */
    public function grnMatches()
    {
        return $this->hasMany(SupplierBillGrnMatch::class);
    }

    /**
     * Recalculate paid/balance and move the status along the unpaid → paid track.
     */
    public function refreshPaymentStatus(): void
    {
        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            return;
        }

        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->total_amount;

        $this->update([
            'paid_amount' => round($paid, 2),
            'balance_amount' => round($total - $paid, 2),
            'status' => match (true) {
                $paid <= 0 => 'unpaid',
                $paid + 0.01 >= $total => 'paid',
                default => 'partially_paid',
            },
        ]);
    }
}
