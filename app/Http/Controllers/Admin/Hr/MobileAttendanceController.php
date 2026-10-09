<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\Hr\MobileAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Mobile-web attendance (location-validated, online only). The page and the
 * endpoints act for the signed-in user's own linked employee; no employee,
 * project or site id is accepted from the request.
 */
class MobileAttendanceController extends Controller
{
    public function __construct(private readonly MobileAttendanceService $attendance) {}

    public function show(Request $request): View
    {
        $context = $this->attendance->context($request->user());

        return view('admin.hr.attendance.mobile', $context + [
            'serverTime' => Carbon::now(),
            'statusLabel' => $context['today'] ? AttendanceRecord::geofenceLabel($context['today']->geofence_status) : null,
        ]);
    }

    /** Evaluate the reported position against the employee's site policy; nothing is written. */
    public function locate(Request $request): JsonResponse
    {
        $result = $this->attendance->locate($request->user(), $this->position($request));

        return response()->json([
            'state' => $result['state'],
            'status' => $result['status'],
            'status_label' => AttendanceRecord::geofenceLabel($result['status']),
            'distance_meters' => $result['distance'],
            'radius_meters' => $result['radius'],
            'enforced' => $result['enforced'],
            'blocked' => $result['blocked'],
            'reason' => $result['reason'],
            'accuracy_meters' => $result['position']['accuracy'],
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $record = $this->attendance->checkIn($request, $this->position($request));

        return $this->recorded($record, 'check_in');
    }

    public function checkOut(Request $request): JsonResponse
    {
        $record = $this->attendance->checkOut($request, $this->position($request));

        return $this->recorded($record, 'check_out');
    }

    /** @return array{latitude: mixed, longitude: mixed, accuracy: mixed} */
    private function position(Request $request): array
    {
        // Only the position is read. employee_id / project_id / site_id in the body are ignored by design.
        return [
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy' => $request->input('accuracy'),
        ];
    }

    private function recorded(AttendanceRecord $record, string $step): JsonResponse
    {
        $status = $record->{$step.'_geofence_status'};

        return response()->json([
            'ok' => true,
            'step' => $step,
            'state' => $record->check_out ? 'completed' : 'can_check_out',
            'time' => $record->{$step},
            'date' => $record->attendance_date->toDateString(),
            'status' => $status,
            'status_label' => AttendanceRecord::geofenceLabel($status),
            'distance_meters' => $record->{$step.'_distance_meters'},
            'late_minutes' => $record->late_minutes,
            'attendance_status' => $record->status,
            'message' => __($step === 'check_in' ? 'attendance.checked_in' : 'attendance.checked_out', ['time' => $record->{$step}]),
        ]);
    }
}
