<?php

namespace App\Services\Sync;

use App\Contracts\OfflineSyncHandler;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OfflineSyncReceipt;
use App\Models\Site;
use App\Models\User;
use App\Services\Hr\AttendanceGeofenceService;
use App\Services\Hr\GeofenceBlockedException;
use App\Services\Hr\MobileAttendanceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Offline GPS attendance, synced later. The device contributes the position
 * captured at tap time, the capture time and the site it displayed. The
 * server re-derives the employee from the login, re-checks eligibility and
 * assignment, recomputes the geofence from the captured coordinates against
 * the site policy in force at sync time, applies the time-trust policy and
 * keeps the one-record-per-day rule. The captured time is the attendance
 * time; the server time of arrival is kept separately as *_recorded_at.
 */
final class AttendanceSyncHandler implements OfflineSyncHandler
{
    public const CHECK_IN = 'check_in';

    public const CHECK_OUT = 'check_out';

    public function __construct(private readonly MobileAttendanceService $attendance, private readonly AttendanceGeofenceService $geofence) {}

    public function operation(): string
    {
        return OfflineSyncReceipt::OPERATION_ATTENDANCE;
    }

    public function actions(): array
    {
        return [self::CHECK_IN, self::CHECK_OUT];
    }

    public function payloadRules(string $action): array
    {
        return [
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'site_id' => ['required', 'integer'],
            'project_id' => ['required', 'integer'],
            // Displayed on the device while offline; never trusted, kept only so the audit can explain what the user saw.
            'client_geofence_status' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function identity(string $action, array $payload): array
    {
        return ['latitude' => $payload['latitude'], 'longitude' => $payload['longitude'], 'accuracy' => $payload['accuracy'] ?? null,
            'site_id' => $payload['site_id'], 'project_id' => $payload['project_id']];
    }

    /** Check-ins before check-outs, earlier captures first (stable). */
    public function order(array $items): array
    {
        $rank = fn (array $item) => [$item['captured_at']->getTimestamp(), $item['action'] === self::CHECK_OUT ? 1 : 0];
        usort($items, fn ($a, $b) => $rank($a) <=> $rank($b));

        return $items;
    }

    public function handle(Request $request, User $user, OfflineSyncReceipt $receipt, string $action, array $payload, Carbon $capturedAt, ?UploadedFile $file): SyncResult
    {
        $context = $this->attendance->context($user);
        if (! $context['eligible']) {
            return SyncResult::needsAttention(SyncError::PERMISSION_DENIED, 'not_eligible', $context['reason']);
        }
        /** @var Employee $employee */
        $employee = $context['employee'];
        $receipt->employee_id = $employee->id;

        if ($policy = $this->timePolicy($request, $capturedAt)) {
            return $policy;
        }
        try {
            $position = $this->geofence->validatedPosition($payload['latitude'], $payload['longitude'], $payload['accuracy'] ?? null);
        } catch (ValidationException $invalid) {
            return SyncResult::failed(SyncError::VALIDATION_ERROR, 'invalid_position', collect($invalid->errors())->flatten()->first());
        }

        try {
            return $action === self::CHECK_IN
                ? $this->checkIn($request, $employee, $context, $payload, $position, $capturedAt, $receipt)
                : $this->checkOut($request, $employee, $payload, $position, $capturedAt, $receipt);
        } catch (GeofenceBlockedException $blocked) {
            ActivityLog::record($request, 'Attendance', 'Blocked offline '.str_replace('_', '-', $action), $blocked->auditDescription.' (captured '.$capturedAt->format('Y-m-d H:i').')', 'failed');

            return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'geofence_blocked', __('offline_sync.geofence_blocked', ['reason' => $blocked->reason]));
        } catch (QueryException $exception) {
            if ($action === self::CHECK_IN && $this->attendance->isDuplicateKey($exception)) {
                // Lost the race against an online check-in for the same day: report the collision, never a second row.
                $existing = $this->attendance->recordOn($employee, $capturedAt->toDateString());

                return $this->alreadyRecorded($existing, $capturedAt);
            }
            throw $exception;
        }
    }

    /** Captured-time trust: not in the future, not older than the window, and the device clock close enough to the server's. */
    private function timePolicy(Request $request, Carbon $capturedAt): ?SyncResult
    {
        $now = Carbon::now();
        $future = (int) config('seera.offline_sync.future_tolerance_minutes', 10);
        $maxAge = (int) config('seera.offline_sync.attendance_max_age_hours', 72);
        if ($capturedAt->gt($now->copy()->addMinutes($future))) {
            return SyncResult::failed(SyncError::VALIDATION_ERROR, 'captured_in_future', __('offline_sync.captured_in_future', ['captured' => $capturedAt->format('Y-m-d H:i')]));
        }
        if ($capturedAt->lt($now->copy()->subHours($maxAge))) {
            return SyncResult::failed(SyncError::VALIDATION_ERROR, 'captured_too_old', __('offline_sync.captured_too_old', ['captured' => $capturedAt->format('Y-m-d H:i'), 'hours' => $maxAge]));
        }
        $skew = $request->attributes->get('sync.clock_skew_seconds');
        $limit = (int) config('seera.offline_sync.clock_skew_attention_minutes', 30);
        if ($skew !== null && abs((int) $skew) > $limit * 60) {
            return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'clock_skew', __('offline_sync.clock_skew', ['minutes' => (int) round(abs((int) $skew) / 60)]), ['skew_seconds' => (int) $skew]);
        }

        return null;
    }

    private function checkIn(Request $request, Employee $employee, array $context, array $payload, array $position, Carbon $capturedAt, OfflineSyncReceipt $receipt): SyncResult
    {
        if ((int) $payload['site_id'] !== (int) $employee->site_id || (int) $payload['project_id'] !== (int) $employee->project_id) {
            $capturedSite = Site::withoutGlobalScopes()->find((int) $payload['site_id']);

            return SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'assignment_changed',
                __('offline_sync.assignment_changed', ['captured_site' => $capturedSite?->name ?? '#'.$payload['site_id'], 'current_site' => $context['site']->name]),
                ['captured_site_id' => (int) $payload['site_id'], 'current_site_id' => (int) $employee->site_id]);
        }
        $date = $capturedAt->toDateString();

        return DB::transaction(function () use ($request, $employee, $position, $capturedAt, $date, $receipt) {
            $locked = Employee::withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if ($existing = $this->attendance->recordOn($locked, $date)) {
                return $this->alreadyRecorded($existing, $capturedAt);
            }
            $site = Site::withoutGlobalScopes()->whereKey($locked->site_id)->lockForUpdate()->firstOrFail();
            $result = $this->geofence->evaluate($site, $position['latitude'], $position['longitude']);
            if ($result['blocked']) {
                throw new GeofenceBlockedException($result['reason'], $this->attendance->blockedDescription($locked, $site, $result));
            }
            $now = Carbon::now();
            $shift = $this->attendance->shiftFor($locked, $capturedAt);
            $late = $this->attendance->lateMinutes($shift, $capturedAt);
            $record = AttendanceRecord::create([
                'employee_id' => $locked->id, 'project_id' => $locked->project_id, 'site_id' => $site->id, 'shift_id' => $shift?->id,
                'attendance_date' => $date, 'check_in' => $capturedAt->format('H:i'), 'late_minutes' => $late, 'overtime_minutes' => 0,
                'status' => $late > 0 ? 'late' : 'present', 'source' => AttendanceRecord::SOURCE_GPS_OFFLINE, 'geofence_status' => $result['status'],
                'check_in_latitude' => $position['latitude'], 'check_in_longitude' => $position['longitude'], 'check_in_accuracy_meters' => $position['accuracy'],
                'check_in_distance_meters' => $result['distance'], 'check_in_geofence_status' => $result['status'],
                'check_in_recorded_at' => $now, 'check_in_captured_at' => $capturedAt, 'check_in_client_uuid' => $receipt->client_uuid,
            ]);
            $this->audit($request, 'Offline check-in synchronized', $locked, $site, $date, $capturedAt, $now, $result);

            return SyncResult::synced($this->record($record, 'check_in'), __('offline_sync.offline_checked_in', [
                'date' => $date, 'time' => $record->check_in, 'site' => $site->name, 'status' => AttendanceRecord::geofenceLabel($result['status'])]));
        }, 1);
    }

    private function checkOut(Request $request, Employee $employee, array $payload, array $position, Carbon $capturedAt, OfflineSyncReceipt $receipt): SyncResult
    {
        $date = $capturedAt->toDateString();

        return DB::transaction(function () use ($request, $employee, $payload, $position, $capturedAt, $date, $receipt) {
            $locked = Employee::withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $record = $this->attendance->recordOn($locked, $date, lock: true);
            if (! $record) {
                return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'no_check_in', __('offline_sync.no_check_in', ['date' => $date]));
            }
            if (! $record->isGpsRecord()) {
                return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'recorded_manually', __('offline_sync.recorded_manually', ['date' => $date]), $this->existing($record));
            }
            if ($record->check_out !== null) {
                return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'already_checked_out', __('offline_sync.already_checked_out', ['date' => $date, 'time' => $record->check_out]), $this->existing($record));
            }
            if ((int) $payload['site_id'] !== (int) $record->site_id) {
                $capturedSite = Site::withoutGlobalScopes()->find((int) $payload['site_id']);
                $recordSite = Site::withoutGlobalScopes()->find((int) $record->site_id);

                return SyncResult::needsAttention(SyncError::STALE_REFERENCE, 'site_changed_on_record',
                    __('offline_sync.site_changed_on_record', ['captured_site' => $capturedSite?->name ?? '#'.$payload['site_id'], 'record_site' => $recordSite?->name ?? '#'.$record->site_id]));
            }
            $checkInAt = $record->check_in_captured_at ?? Carbon::parse($record->attendance_date->toDateString().' '.$record->check_in);
            if ($capturedAt->lt($checkInAt)) {
                return SyncResult::failed(SyncError::VALIDATION_ERROR, 'check_out_before_check_in',
                    __('offline_sync.check_out_before_check_in', ['captured' => $capturedAt->format('Y-m-d H:i'), 'check_in' => $checkInAt->format('Y-m-d H:i')]));
            }
            // Policy is the record's own site (the site checked in at), as online.
            $site = Site::withoutGlobalScopes()->whereKey($record->site_id)->firstOrFail();
            $result = $this->geofence->evaluate($site, $position['latitude'], $position['longitude']);
            if ($result['blocked']) {
                throw new GeofenceBlockedException($result['reason'], $this->attendance->blockedDescription($locked, $site, $result));
            }
            $now = Carbon::now();
            $record->update([
                'check_out' => $capturedAt->format('H:i'),
                'check_out_latitude' => $position['latitude'], 'check_out_longitude' => $position['longitude'], 'check_out_accuracy_meters' => $position['accuracy'],
                'check_out_distance_meters' => $result['distance'], 'check_out_geofence_status' => $result['status'],
                'check_out_recorded_at' => $now, 'check_out_captured_at' => $capturedAt, 'check_out_client_uuid' => $receipt->client_uuid,
            ]);
            $this->audit($request, 'Offline check-out synchronized', $locked, $site, $date, $capturedAt, $now, $result);

            return SyncResult::synced($this->record($record->fresh(), 'check_out'), __('offline_sync.offline_checked_out', [
                'date' => $date, 'time' => $record->check_out, 'site' => $site->name, 'status' => AttendanceRecord::geofenceLabel($result['status'])]));
        }, 1);
    }

    private function alreadyRecorded(?AttendanceRecord $existing, Carbon $capturedAt): SyncResult
    {
        $date = $capturedAt->toDateString();
        if ($existing && ! $existing->isGpsRecord()) {
            return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'recorded_manually', __('offline_sync.recorded_manually', ['date' => $date]), $this->existing($existing));
        }

        return SyncResult::needsAttention(SyncError::BUSINESS_CONFLICT, 'already_checked_in',
            __('offline_sync.already_checked_in', ['date' => $date, 'time' => $existing?->check_in ?? '-', 'source' => $existing?->sourceLabel() ?? '-']),
            $existing ? $this->existing($existing) : []);
    }

    /** Enough of the existing record to resolve the conflict, without raw coordinates. */
    private function existing(AttendanceRecord $record): array
    {
        return ['existing' => ['id' => $record->id, 'date' => $record->attendance_date->toDateString(), 'check_in' => $record->check_in, 'check_out' => $record->check_out,
            'source' => $record->source, 'source_label' => $record->sourceLabel(), 'site_id' => $record->site_id, 'status' => $record->status]];
    }

    private function record(AttendanceRecord $record, string $step): array
    {
        $status = $record->{$step.'_geofence_status'};

        return ['type' => 'attendance_record', 'id' => $record->id, 'reference' => $record->attendance_date->toDateString(), 'step' => $step,
            'date' => $record->attendance_date->toDateString(), 'time' => $record->{$step}, 'status' => $status, 'status_label' => AttendanceRecord::geofenceLabel($status),
            'distance_meters' => $record->{$step.'_distance_meters'}, 'late_minutes' => $record->late_minutes, 'attendance_status' => $record->status,
            'captured_at' => $record->{$step.'_captured_at'}?->toIso8601String(), 'received_at' => $record->{$step.'_recorded_at'}?->toIso8601String(),
            'state' => $record->check_out ? 'completed' : 'can_check_out'];
    }

    private function audit(Request $request, string $action, Employee $employee, Site $site, string $date, Carbon $capturedAt, Carbon $receivedAt, array $result): void
    {
        $delay = $capturedAt->diffInMinutes($receivedAt);
        ActivityLog::record($request, 'Attendance', $action, $employee->name.' - '.$date.' '.$capturedAt->format('H:i').' at '.$site->name
            .' ('.AttendanceRecord::geofenceLabel($result['status']).($result['distance'] !== null ? ', '.number_format($result['distance']).' m' : '')
            .'; captured '.$capturedAt->format('Y-m-d H:i').', received '.$receivedAt->format('Y-m-d H:i').', delay '.(int) $delay.' min)');
    }
}
