<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date'];
    }
}
