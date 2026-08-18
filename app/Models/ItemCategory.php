<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    protected $fillable = ['code', 'name', 'parent_id', 'inventory_account_id', 'expense_account_id', 'status'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function inventoryAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }
}
