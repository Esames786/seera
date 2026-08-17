<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    public const TYPES = ['IQAMA', 'Passport', 'Contract', 'Medical Insurance', 'Driving License', 'Other'];

    protected $fillable = [
        'employee_id', 'document_type', 'document_number', 'issue_date',
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
