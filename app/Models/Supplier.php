<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'code', 'category', 'vat_number', 'cr_number', 'opening_balance',
        'contact_person', 'phone', 'email', 'payment_terms', 'linked_account',
        'address', 'status',
    ];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2'];
    }
}
