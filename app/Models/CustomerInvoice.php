<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInvoice extends Model
{
    public const PAYMENT_STATUSES = ['draft', 'unpaid', 'partially_paid', 'paid', 'cancelled'];

    public const ZATCA_STATUSES = ['draft', 'generated', 'pending_clearance', 'cleared', 'failed'];

    protected $fillable = [
        'customer_id', 'invoice_number', 'invoice_date', 'due_date', 'project_id',
        'cost_center_id', 'taxable_amount', 'vat_rate', 'vat_amount', 'total_amount',
        'received_amount', 'balance_amount', 'payment_status', 'zatca_status',
        'journal_entry_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->payment_status, ['draft', 'cancelled'], true);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
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
        return $this->hasMany(CustomerInvoiceLine::class);
    }

    public function receipts()
    {
        return $this->hasMany(CustomerReceipt::class);
    }

    public function zatcaRecord()
    {
        return $this->hasOne(ZatcaInvoiceRecord::class);
    }

    public function refreshPaymentStatus(): void
    {
        if (in_array($this->payment_status, ['draft', 'cancelled'], true)) {
            return;
        }

        $received = (float) $this->receipts()->sum('amount');
        $total = (float) $this->total_amount;

        $this->update([
            'received_amount' => round($received, 2),
            'balance_amount' => round($total - $received, 2),
            'payment_status' => match (true) {
                $received <= 0 => 'unpaid',
                $received + 0.01 >= $total => 'paid',
                default => 'partially_paid',
            },
        ]);
    }

    public static function nextNumber(int $year): string
    {
        $prefix = 'INV-'.$year.'-';
        return app(\App\Services\DocumentNumberService::class)
            ->next('invoice-'.$year, $prefix, 'customer_invoices', 'invoice_number');
    }
}
