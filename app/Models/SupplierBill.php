<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierBill extends Model
{
    public const STATUSES = ['draft', 'approved', 'unpaid', 'partially_paid', 'paid', 'cancelled'];

    protected $fillable = [
        'supplier_id', 'bill_number', 'bill_date', 'due_date', 'reference_number',
        'project_id', 'site_id', 'cost_center_id', 'taxable_amount', 'vat_rate',
        'vat_amount', 'total_amount', 'paid_amount', 'balance_amount', 'status',
        'journal_entry_id', 'notes',
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

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'cancelled'], true);
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
