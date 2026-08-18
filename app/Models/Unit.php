<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['code', 'name', 'allows_decimal', 'status'];

    protected function casts(): array
    {
        return ['allows_decimal' => 'boolean'];
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
