<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'code', 'type', 'vat_number', 'cr_number', 'opening_receivable',
        'credit_limit', 'contact_person', 'phone', 'email', 'linked_account',
        'billing_address', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_receivable' => 'decimal:2',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
