<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person to meet at the customer's office or at one of its sites (NR-08),
 * kept apart from the customer's main contact so visiting staff know whom to ask for.
 */
class CustomerContact extends Model
{
    public const LOCATION_TYPES = ['office', 'site'];

    protected $fillable = ['customer_id', 'location_type', 'site_id', 'name', 'title', 'phone', 'email', 'address'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
