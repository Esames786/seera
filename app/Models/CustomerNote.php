<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared note on a customer (NR-09): anyone who may view the customer can read
 * it, so field staff and Accounts leave information for each other.
 */
class CustomerNote extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'note'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
