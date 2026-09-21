<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Employee extends Model
{
    /** Company-sponsored staff versus outside freelancers (client requirement). */
    public const CLASSIFICATIONS = ['Sponsorship', 'Freelancer'];

    protected $fillable = [
        'employee_code', 'first_name', 'last_name', 'email', 'phone',
        'emergency_contact', 'nationality', 'department_id', 'designation_id',
        'branch_id', 'project_id', 'site_id', 'manager_id', 'user_id',
        'joining_date', 'contract_type', 'contract_start_date', 'contract_end_date',
        'annual_leave_entitlement', 'employee_classification',
        'iqama_number', 'iqama_expiry_date', 'passport_number', 'passport_expiry_date',
        'insurance_number', 'insurance_expiry_date',
        'driving_license_number', 'driving_license_expiry_date',
        'basic_salary', 'housing_allowance', 'transport_allowance', 'food_allowance',
        'fuel_allowance', 'other_allowance',
        'payment_method', 'bank_name', 'iban',
        'mobile_access', 'status',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'iqama_expiry_date' => 'date',
            'passport_expiry_date' => 'date',
            'insurance_expiry_date' => 'date',
            'driving_license_expiry_date' => 'date',
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'food_allowance' => 'decimal:2',
            'fuel_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
            'mobile_access' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }

    /**
     * Document validity wording shared by the employee listing and document screens.
     */
    public static function expiryStatus(?string $date): string
    {
        if (! $date) {
            return 'unknown';
        }

        $expiry = \Illuminate\Support\Carbon::parse($date)->startOfDay();

        return match (true) {
            $expiry->isPast() => 'expired',
            $expiry->lte(now()->startOfDay()->addDays(60)) => 'expiring soon',
            default => 'valid',
        };
    }

    public function iqamaStatus(): string
    {
        return static::expiryStatus($this->iqama_expiry_date?->toDateString());
    }

    /**
     * Document type => the employee columns that summarise it. The attachment
     * rows are the single source of truth (client change request NR-12); these
     * columns are refreshed from the newest document of each type so the HR
     * dashboard, the employee list and the register never disagree.
     */
    public const DOCUMENT_SUMMARY = [
        'IQAMA' => ['iqama_number', 'iqama_expiry_date'],
        'Passport' => ['passport_number', 'passport_expiry_date'],
        'Medical Insurance' => ['insurance_number', 'insurance_expiry_date'],
        'Driving License' => ['driving_license_number', 'driving_license_expiry_date'],
    ];

    public function syncDocumentSummary(): void
    {
        $documents = $this->documents()->where('status', 'active')->get();
        $changes = [];

        foreach (self::DOCUMENT_SUMMARY as $type => [$numberColumn, $expiryColumn]) {
            $latest = $documents->where('document_type', $type)
                ->sortByDesc(fn (EmployeeDocument $document) => $document->expiry_date?->timestamp ?? 0)
                ->first();

            if (! $latest) {
                continue;
            }

            $changes[$numberColumn] = $latest->document_number ?: $this->{$numberColumn};
            $changes[$expiryColumn] = $latest->expiry_date?->toDateString() ?? $this->{$expiryColumn}?->toDateString();
        }

        if ($changes !== []) {
            $this->forceFill($changes)->saveQuietly();
        }
    }

    /**
     * Annual leave position for a year (client change request NR-18):
     * entitlement, approved days used, days awaiting approval and the balance.
     *
     * @return array{year: int, entitlement: int, used: float, pending: float, remaining: float}
     */
    public function leaveBalance(?int $year = null): array
    {
        $year ??= (int) now()->year;
        $annualTypeIds = LeaveType::where('code', 'ANNUAL')->pluck('id');

        $base = $this->leaveRequests()
            ->when($annualTypeIds->isNotEmpty(), fn ($q) => $q->whereIn('leave_type_id', $annualTypeIds))
            ->whereYear('start_date', $year);

        $used = (float) (clone $base)->where('status', 'approved')->sum('total_days');
        $pending = (float) (clone $base)->where('status', 'pending')->sum('total_days');

        return [
            'year' => $year,
            'entitlement' => (int) $this->annual_leave_entitlement,
            'used' => $used,
            'pending' => $pending,
            'remaining' => round((int) $this->annual_leave_entitlement - $used, 1),
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function shiftAssignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function overtimeRecords()
    {
        return $this->hasMany(OvertimeRecord::class);
    }

    public function salaryStructures()
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function activeSalaryStructure()
    {
        return $this->hasOne(SalaryStructure::class)->where('status', 'active')->latestOfMany('effective_from');
    }

    /**
     * The pay entered on the employee's own Payroll Information section.
     * This is the single place the client types salary; the salary structure,
     * the payroll run and this profile must all agree on it (FR-04).
     *
     * @return array<string, float>
     */
    public function payrollDefaults(): array
    {
        return [
            'basic_salary' => (float) $this->basic_salary,
            'housing_allowance' => (float) $this->housing_allowance,
            'transport_allowance' => (float) $this->transport_allowance,
            'food_allowance' => (float) $this->food_allowance,
            'fuel_allowance' => (float) $this->fuel_allowance,
            'other_allowance' => (float) $this->other_allowance,
        ];
    }

    /** Allowances entered on the profile, used when no salary structure exists yet. */
    public function defaultAllowanceTotal(): float
    {
        return round(array_sum(array_diff_key($this->payrollDefaults(), ['basic_salary' => null])), 2);
    }

    /**
     * The date the first structure takes effect: the contract start if there is
     * one, otherwise the joining date, otherwise today. Chosen so the structure
     * is already valid for any payroll period since the person started.
     */
    public function salaryEffectiveFrom(): string
    {
        return ($this->contract_start_date ?? $this->joining_date ?? now())->toDateString();
    }

    /**
     * Create the employee's first salary structure from the pay just entered on
     * the profile (FR-04). Does nothing when a structure already exists: payroll
     * history must never be rewritten behind the user's back, so a later salary
     * change needs a new structure raised deliberately.
     */
    public function ensureSalaryStructure(): ?SalaryStructure
    {
        if ((float) $this->basic_salary <= 0 || $this->salaryStructures()->exists()) {
            return null;
        }

        return $this->salaryStructures()->create($this->payrollDefaults() + [
            'fixed_deduction' => 0,
            'effective_from' => $this->salaryEffectiveFrom(),
            'status' => 'active',
        ]);
    }

    /**
     * True when the active structure no longer matches the profile, for example
     * after a raise. The employee page shows this as a notice with a prefilled
     * "new structure" action rather than changing the existing structure.
     */
    public function salaryStructureOutOfDate(): bool
    {
        $structure = $this->activeSalaryStructure;

        if (! $structure) {
            return false;
        }

        foreach ($this->payrollDefaults() as $field => $value) {
            if (abs((float) $structure->{$field} - $value) >= 0.01) {
                return true;
            }
        }

        return false;
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function endOfServiceRecords()
    {
        return $this->hasMany(EndOfServiceRecord::class);
    }
}
