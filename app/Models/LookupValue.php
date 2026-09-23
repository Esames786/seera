<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Small extensible dropdown lists (client change request NR-02): supplier
 * category, nationality, and any other list that used to be hard-coded in a
 * form. Values are added inline with "+ New" and reused everywhere.
 */
class LookupValue extends Model
{
    public const TYPES = [
        'supplier_category' => 'Supplier Category',
        'nationality' => 'Nationality',
        'document_type' => 'Document Type',
        'customer_type' => 'Customer Type',
    ];

    protected $fillable = ['type', 'value', 'sort_order', 'status'];

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type)->where('status', 'active')->orderBy('sort_order')->orderBy('value');
    }

    /**
     * Active values of a type, always including one already stored on the
     * record being edited so an older value is never silently dropped.
     *
     * @return Collection<int, string>
     */
    public static function options(string $type, ?string $current = null): Collection
    {
        $values = static::ofType($type)->pluck('value');

        if ($current !== null && $current !== '' && ! $values->contains($current)) {
            $values->push($current);
        }

        return $values->values();
    }
}
