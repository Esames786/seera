<?php

namespace App\Services\Hr;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Location-validated attendance runtime, Phase 1 (online mobile web).
 *
 * Identity and context are derived on the server: the signed-in user's linked
 * employee (employees.user_id), that employee's current project and site, and
 * the site's own geofence policy. The browser contributes only a position; the
 * server computes distance and status, uses its own clock for the attendance
 * time, and serialises check-in / check-out with row locks so a retry or a
 * concurrent request can never create a second record or a second check-out.
 * Lateness uses the assigned shift's start time and grace minutes (the only
 * shift rule the system defines); overtime stays a separate approved claim.
 */
final class MobileAttendanceService
{
    public function __construct(private readonly AttendanceGeofenceService $geofence) {}

    /**
     * Who the signed-in user is as an employee and whether they may use mobile
     * attendance. Each refusal names its reason for the page; nothing is written.
     *
     * @return array{eligible: bool, reason: string|null, employee: Employee|null, site: Site|null, shift: Shift|null, today: AttendanceRecord|null, state: string}
     */
    public function context(User $user): array
    {
        $employee = $this->employeeFor($user);
        $deny = fn (string $reason) => ['eligible' => false, 'reason' => $reason, 'employee' => $employee, 'site' => null, 'shift' => null, 'today' => null, 'state' => 'ineligible'];

        if (! $employee) {
            return $deny(__('mobile_attendance.no_linked_employee'));
        }
        if ($employee->status !== 'active') {
            return $deny(__('mobile_attendance.employee_inactive'));
        }
        if (! $user->mobile_access || ! $user->hasPermission('Attendance', 'mobile')) {
            return $deny(__('mobile_attendance.mobile_access_required'));
        }
        if (! $employee->project_id || ! $employee->site_id) {
            return $deny(__('mobile_attendance.no_site_assignment'));
        }
        $site = Site::withoutGlobalScopes()->whereKey($employee->site_id)->first();
        if (! $site || $site->status !== 'active' || (int) $site->project_id !== (int) $employee->project_id) {
            return $deny(__('mobile_attendance.site_unavailable'));
        }
        $site->setRelation('project', $employee->project);

        $today = $this->todayRecord($employee);
        $state = match (true) {
            $today === null => 'can_check_in',
            $today->isOpenForCheckOut() => 'can_check_out',
            $today->isGpsRecord() => 'completed',
            default => 'recorded_manually',
        };

        return ['eligible' => true, 'reason' => null, 'employee' => $employee, 'site' => $site, 'shift' => $this->shiftFor($employee), 'today' => $today, 'state' => $state];
    }

    /**
     * Evaluate a position for the page without writing anything.
     *
     * @return array<string, mixed>
     */
    public function locate(User $user, array $position): array
    {
        $context = $this->requireEligible($user);
        $pos = $this->geofence->validatedPosition($position['latitude'] ?? null, $position['longitude'] ?? null, $position['accuracy'] ?? null);

        return $this->geofence->evaluate($context['site'], $pos['latitude'], $pos['longitude']) + ['position' => $pos, 'state' => $context['state']];
    }

    /** Check in: create today's record for the linked employee under the employee lock. */
    public function checkIn(Request $request, array $position): AttendanceRecord
    {
        $user = $request->user();
        $context = $this->requireEligible($user);
        $pos = $this->geofence->validatedPosition($position['latitude'] ?? null, $position['longitude'] ?? null, $position['accuracy'] ?? null);

        try {
            return DB::transaction(function () use ($request, $user, $context, $pos) {
                // The employee row is the mutex for the day: two check-ins queue here and the second finds the record.
                $employee = Employee::withoutGlobalScopes()->whereKey($context['employee']->id)->lockForUpdate()->firstOrFail();
                if ($this->todayRecord($employee)) {
                    throw ValidationException::withMessages(['attendance' => __('mobile_attendance.already_checked_in')]);
                }

                $site = Site::withoutGlobalScopes()->whereKey($employee->site_id)->lockForUpdate()->firstOrFail();
                $result = $this->geofence->evaluate($site, $pos['latitude'], $pos['longitude']);
                $now = Carbon::now();
                if ($result['blocked']) {
                    throw new GeofenceBlockedException($result['reason'], $this->blockedDescription($employee, $site, $result));
                }

                $shift = $this->shiftFor($employee);
                $late = $this->lateMinutes($shift, $now);

                $record = AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'project_id' => $employee->project_id,
                    'site_id' => $site->id,
                    'shift_id' => $shift?->id,
                    'attendance_date' => $now->toDateString(),
                    'check_in' => $now->format('H:i'),
                    'late_minutes' => $late,
                    'overtime_minutes' => 0,
                    'status' => $late > 0 ? 'late' : 'present',
                    'source' => AttendanceRecord::SOURCE_GPS,
                    'geofence_status' => $result['status'],
                    'check_in_latitude' => $pos['latitude'],
                    'check_in_longitude' => $pos['longitude'],
                    'check_in_accuracy_meters' => $pos['accuracy'],
                    'check_in_distance_meters' => $result['distance'],
                    'check_in_geofence_status' => $result['status'],
                    'check_in_recorded_at' => $now,
                ]);

                ActivityLog::record($request, 'Attendance', 'Mobile check-in', $employee->name.' - '.$now->toDateString().' '.$now->format('H:i').' at '.$site->name.' ('.AttendanceRecord::geofenceLabel($result['status']).($result['distance'] !== null ? ', '.number_format($result['distance']).' m' : '').')');

                return $record;
            });
        } catch (GeofenceBlockedException $blocked) {
            // Logged after the rollback so the refused attempt is kept on record.
            ActivityLog::record($request, 'Attendance', 'Blocked mobile check-in', $blocked->auditDescription, 'failed');
            throw ValidationException::withMessages(['attendance' => $blocked->reason]);
        } catch (QueryException $exception) {
            // The unique (employee, date) index is the last line of defence against a race the lock did not serialise.
            if ($this->isDuplicateKey($exception)) {
                throw ValidationException::withMessages(['attendance' => __('mobile_attendance.already_checked_in')]);
            }
            throw $exception;
        }
    }

    /** Check out: close today's open GPS record under its own row lock with a fresh position. */
    public function checkOut(Request $request, array $position): AttendanceRecord
    {
        $user = $request->user();
        $context = $this->requireEligible($user);
        $pos = $this->geofence->validatedPosition($position['latitude'] ?? null, $position['longitude'] ?? null, $position['accuracy'] ?? null);

        try {
            return DB::transaction(function () use ($request, $context, $pos) {
            $employee = Employee::withoutGlobalScopes()->whereKey($context['employee']->id)->lockForUpdate()->firstOrFail();
            $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)
                ->whereDate('attendance_date', Carbon::today()->toDateString())->lockForUpdate()->first();

            if (! $record || ! $record->isGpsRecord()) {
                throw ValidationException::withMessages(['attendance' => __('mobile_attendance.nothing_to_check_out')]);
            }
            if ($record->check_out !== null) {
                throw ValidationException::withMessages(['attendance' => __('mobile_attendance.already_checked_out')]);
            }

            // Policy is the record's own site (the site checked in at), even if the assignment changed during the day.
            $site = Site::withoutGlobalScopes()->whereKey($record->site_id)->first() ?? Site::withoutGlobalScopes()->whereKey($employee->site_id)->firstOrFail();
            $result = $this->geofence->evaluate($site, $pos['latitude'], $pos['longitude']);
            $now = Carbon::now();
            if ($result['blocked']) {
                throw new GeofenceBlockedException($result['reason'], $this->blockedDescription($employee, $site, $result));
            }

            $record->update([
                'check_out' => $now->format('H:i'),
                'check_out_latitude' => $pos['latitude'],
                'check_out_longitude' => $pos['longitude'],
                'check_out_accuracy_meters' => $pos['accuracy'],
                'check_out_distance_meters' => $result['distance'],
                'check_out_geofence_status' => $result['status'],
                'check_out_recorded_at' => $now,
            ]);

            ActivityLog::record($request, 'Attendance', 'Mobile check-out', $employee->name.' - '.$now->toDateString().' '.$now->format('H:i').' at '.$site->name.' ('.AttendanceRecord::geofenceLabel($result['status']).($result['distance'] !== null ? ', '.number_format($result['distance']).' m' : '').')');

            return $record->fresh();
            });
        } catch (GeofenceBlockedException $blocked) {
            ActivityLog::record($request, 'Attendance', 'Blocked mobile check-out', $blocked->auditDescription, 'failed');
            throw ValidationException::withMessages(['attendance' => $blocked->reason]);
        }
    }

    /** Lateness from the assigned shift: minutes past start time plus grace; zero without a shift. */
    public function lateMinutes(?Shift $shift, Carbon $at): int
    {
        if (! $shift || ! $shift->start_time) {
            return 0;
        }
        [$hours, $minutes] = array_map('intval', explode(':', substr((string) $shift->start_time, 0, 5)));
        $startMinutes = $hours * 60 + $minutes + (int) $shift->grace_minutes;
        $nowMinutes = (int) $at->format('H') * 60 + (int) $at->format('i');

        return max(0, $nowMinutes - $startMinutes);
    }

    /** The shift assignment in force today, when there is one. */
    public function shiftFor(Employee $employee): ?Shift
    {
        $today = Carbon::today()->toDateString();
        $assignment = EmployeeShiftAssignment::withoutGlobalScopes()->where('employee_id', $employee->id)->where('status', 'active')
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->latest('effective_from')->first();

        return $assignment ? Shift::find($assignment->shift_id) : null;
    }

    /** The user's own employee record. The employees.user_id link is the authority, independent of browse scope. */
    public function employeeFor(User $user): ?Employee
    {
        return Employee::withoutGlobalScopes()->where('user_id', $user->id)->with(['project', 'site'])->first();
    }

    public function todayRecord(Employee $employee): ?AttendanceRecord
    {
        return AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)
            ->whereDate('attendance_date', Carbon::today()->toDateString())->first();
    }

    private function requireEligible(User $user): array
    {
        $context = $this->context($user);
        if (! $context['eligible']) {
            throw ValidationException::withMessages(['attendance' => $context['reason']]);
        }

        return $context;
    }

    private function blockedDescription(Employee $employee, Site $site, array $result): string
    {
        return $employee->name.' - '.$site->name.': '.AttendanceRecord::geofenceLabel($result['status'])
            .($result['distance'] !== null ? ', '.number_format($result['distance']).' m from site (radius '.$result['radius'].' m)' : '');
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique') || str_contains($message, 'duplicate');
    }
}
