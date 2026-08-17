<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructureItem extends Model
{
    protected $fillable = ['salary_structure_id', 'item_type', 'name', 'amount', 'is_taxable'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    public function salaryStructure()
    {
        return $this->belongsTo(SalaryStructure::class);
    }
}
