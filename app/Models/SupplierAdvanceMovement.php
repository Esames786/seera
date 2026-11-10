<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierAdvanceMovement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['movement_date' => 'date', 'amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function advance()
    {
        return $this->belongsTo(SupplierAdvance::class, 'supplier_advance_id');
    }

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
