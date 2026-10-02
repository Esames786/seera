<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteExpenseReceipt extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path'];

    public function expense()
    {
        return $this->belongsTo(SiteExpense::class, 'site_expense_id');
    }
}
