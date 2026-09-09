<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Supplier payment terms with the agreed number of days (client change
 * request CR-17). Cash / 15 / 30 / 60 days are created by the migration; the
 * client adds any other agreement from the supplier form.
 */
class PaymentTerm extends Model
{
    protected $fillable = ['name', 'days', 'description', 'status'];

    protected function casts(): array
    {
        return ['days' => 'integer'];
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function label(): string
    {
        return $this->days > 0 ? $this->name.' ('.$this->days.' days)' : $this->name;
    }
}
