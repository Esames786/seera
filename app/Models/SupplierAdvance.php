<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierAdvance extends Model
{
    public const PURPOSES = ['advance', 'salary_on_behalf', 'repair_on_behalf'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements()
    {
        return $this->hasMany(SupplierAdvanceMovement::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function available(): float
    {
        return $this->reversed_at ? 0 : round((float) $this->amount - (float) $this->movements()->withoutGlobalScopes()->whereNull('reversed_at')->sum('amount'), 2);
    }
}
