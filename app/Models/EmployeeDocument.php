<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    /** The standard types. The expiry summary on the employee record maps to these names. */
    public const TYPES = ['IQAMA', 'Passport', 'Contract', 'Medical Insurance', 'Driving License', 'Other'];

    /**
     * Every type that can be chosen: the standard ones plus whatever the company
     * has added with "+ New" (client feedback FR-02). A type already stored on a
     * document is always included so an older value is never dropped from a form.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function types(?string $current = null): \Illuminate\Support\Collection
    {
        $all = LookupValue::options('document_type', $current)->merge(self::TYPES)->unique();

        // Standard names keep their familiar order; company additions follow alphabetically.
        $standard = collect(self::TYPES)->filter(fn (string $value) => $all->contains($value));
        $custom = $all->reject(fn (string $value) => in_array($value, self::TYPES, true))->sort();

        return $standard->merge($custom)->values();
    }

    protected $fillable = [
        'employee_id', 'document_type', 'document_subtype', 'document_number', 'issue_date',
        'expiry_date', 'file_path', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function validityStatus(): string
    {
        return Employee::expiryStatus($this->expiry_date?->toDateString());
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
