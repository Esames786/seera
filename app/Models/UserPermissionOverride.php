<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermissionOverride extends Model
{
    protected $guarded = ['id'];

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
