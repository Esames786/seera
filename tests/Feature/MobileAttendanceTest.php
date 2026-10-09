<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\Hr\AttendanceGeofenceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * GPS attendance + geofence runtime, Phase 1 (online mobile web). The signed-in
 * user's linked employee, its project and site and the site's own policy are
 * resolved on the server; the browser contributes a position only. Distance
 * and status are computed server-side, the server clock is the attendance
 * time, check-in / check-out are idempotent and manual attendance is untouched.
 */
class MobileAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** Riyadh test site: 24.7136, 46.6753; a point ~100 m east is 46.67629, ~1 km east is 46.68518. */
    private const SITE_LAT = 24.7136;

    private const SITE_LNG = 46.6753;

    protected Project $project;

    protected Project $otherProject;

    protected Site $site;

    protected Site $otherSite;

    protected Employee $employee;

    protected User $worker;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->project = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-GPS-A', 'status' => 'active']);
        $this->otherProject = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-GPS-B', 'status' => 'active']);
        $this->site = Site::create(['name' => 'Riyadh Tower - Main Site', 'code' => 'SITE-GPS-A', 'project_id' => $this->project->id, 'status' => 'active',
            'latitude' => self::SITE_LAT, 'longitude' => self::SITE_LNG, 'geofence_radius' => 300, 'geofence_enabled' => true, 'attendance_inside_only' => true]);
        $this->otherSite = Site::create(['name' => 'Jeddah Site', 'code' => 'SITE-GPS-B', 'project_id' => $this->otherProject->id, 'status' => 'active',
            'latitude' => 21.4858, 'longitude' => 39.1925, 'geofence_radius' => 300, 'geofence_enabled' => true, 'attendance_inside_only' => true]);

        $this->worker = $this->mobileUser('worker', $this->project);
        $this->employee = Employee::create(['employee_code' => 'EMP-GPS-A', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'email' => 'ahmed.gps@example.test', 'status' => 'active',
            'employee_classification' => 'Sponsorship', 'joining_date' => '2024-03-01', 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'user_id' => $this->worker->id]);
    }

    // ---------------------------------------------------------------- helpers

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** A project-level user with Attendance view + mobile and Mobile Access on. */
    protected function mobileUser(string $suffix, ?Project $project, array $extraGrants = [], bool $mobileAccess = true): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'GPS role '.$suffix, 'code' => 'GPS_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 5, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach (['Attendance' => ['view', 'mobile']] + $extraGrants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'GPS '.$suffix, 'email' => 'gps-'.$suffix.'@example.test', 'username' => 'gps.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id, 'mobile_access' => $mobileAccess]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function position(float $lat = self::SITE_LAT, float $lng = self::SITE_LNG, ?float $accuracy = 12.5, array $extra = []): array
    {
        return $extra + ['latitude' => $lat, 'longitude' => $lng, 'accuracy' => $accuracy];
    }

    protected function checkIn(array $position, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->worker)->postJson(route('admin.hr.attendance.mobile.check-in'), $position);
    }

    protected function checkOut(array $position, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->worker)->postJson(route('admin.hr.attendance.mobile.check-out'), $position);
    }

    protected function todayRecord(): ?AttendanceRecord
    {
        return AttendanceRecord::withoutGlobalScopes()->where('employee_id', $this->employee->id)->whereDate('attendance_date', today())->first();
    }

    // ------------------------------------------------------------------ eligibility

    public function test_linked_active_employee_with_mobile_access_opens_the_mobile_page(): void
    {
        $this->actingAs($this->worker)->get(route('admin.hr.attendance.mobile'))->assertOk()
            ->assertSee('EMP-GPS-A')->assertSee('Riyadh Tower - Main Site')->assertSee('Not checked in yet')->assertSee('data-action="locate"', false)
            ->assertSee('captured only at the moment you check in or check out');
    }

    public function test_unlinked_inactive_and_non_mobile_users_are_refused(): void
    {
        $unlinked = $this->mobileUser('unlinked', $this->project);
        $this->actingAs($unlinked)->get(route('admin.hr.attendance.mobile'))->assertOk()->assertSee('not linked to an employee record')->assertDontSee('data-action="locate"', false);
        $this->checkIn($this->position(), $unlinked)->assertStatus(422);

        $this->employee->update(['status' => 'inactive']);
        $this->actingAs($this->worker)->get(route('admin.hr.attendance.mobile'))->assertOk()->assertSee('employee record is not active');
        $this->checkIn($this->position())->assertStatus(422);
        $this->employee->update(['status' => 'active']);

        $this->worker->update(['mobile_access' => false]);
        $this->actingAs($this->worker->fresh())->get(route('admin.hr.attendance.mobile'))->assertOk()->assertSee('Mobile attendance is not enabled');
        $this->checkIn($this->position(), $this->worker->fresh())->assertStatus(422);
        $this->worker->update(['mobile_access' => true]);

        // Without the Attendance → mobile permission the endpoints are refused before the controller.
        $noPermission = $this->mobileUser('noperm', $this->project, [], true);
        $noPermission->roles()->first()->permissions()->sync(Permission::where('module', 'Attendance')->where('action', 'view')->pluck('id')->all());
        Employee::create(['employee_code' => 'EMP-GPS-NP', 'first_name' => 'No', 'last_name' => 'Perm', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'user_id' => $noPermission->id]);
        $this->actingAs($noPermission->fresh())->get(route('admin.hr.attendance.mobile'))->assertForbidden();
        $this->checkIn($this->position(), $noPermission->fresh())->assertForbidden();
        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->whereIn('employee_id', Employee::whereIn('employee_code', ['EMP-GPS-A', 'EMP-GPS-NP'])->pluck('id'))->count());
    }

    public function test_site_is_derived_from_the_employee_and_forged_ids_are_ignored(): void
    {
        // Out-of-scope project / site ids in the body are refused by the request-scope guard before anything runs.
        $this->checkIn($this->position(self::SITE_LAT, self::SITE_LNG, 10, ['site_id' => $this->otherSite->id, 'project_id' => $this->otherProject->id]))->assertForbidden();
        $this->assertNull($this->todayRecord());

        // In-scope but foreign ids, typed times, dates and statuses are simply ignored: everything derives from the linked employee.
        $other = Employee::create(['employee_code' => 'EMP-GPS-X', 'first_name' => 'Other', 'last_name' => 'Worker', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $this->project->id, 'site_id' => $this->site->id]);
        $this->checkIn($this->position(self::SITE_LAT, self::SITE_LNG, 10, [
            'employee_id' => $other->id, 'site_id' => $this->site->id, 'project_id' => $this->project->id,
            'geofence_status' => 'outside', 'source' => 'manual', 'check_in' => '04:00', 'attendance_date' => '2020-01-01', 'late_minutes' => 99,
        ]))->assertOk();

        $record = $this->todayRecord();
        $this->assertSame($this->employee->id, $record->employee_id);
        $this->assertSame($this->site->id, $record->site_id);
        $this->assertSame($this->project->id, $record->project_id);
        $this->assertSame(today()->toDateString(), $record->attendance_date->toDateString());
        $this->assertSame(AttendanceRecord::SOURCE_GPS, $record->source);
        $this->assertSame('inside', $record->geofence_status);
        $this->assertNotSame('04:00', $record->check_in);
        $this->assertSame(0, $record->late_minutes);
        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $other->id)->count());
    }

    public function test_assignment_problems_block_check_in(): void
    {
        $this->employee->update(['site_id' => $this->otherSite->id]);   // site of another project
        $this->checkIn($this->position(21.4858, 39.1925))->assertStatus(422)->assertJsonFragment(['attendance' => [__('mobile_attendance.site_unavailable')]]);

        $this->employee->update(['site_id' => $this->site->id]);
        $this->site->update(['status' => 'inactive']);
        $this->checkIn($this->position())->assertStatus(422);
        $this->site->update(['status' => 'active']);

        $this->employee->update(['site_id' => null]);
        $this->checkIn($this->position())->assertStatus(422)->assertJsonFragment(['attendance' => [__('mobile_attendance.no_site_assignment')]]);
        $this->assertNull($this->todayRecord());
    }

    // ------------------------------------------------------------------ positions and geofence

    public function test_impossible_coordinates_and_unusable_accuracy_are_rejected(): void
    {
        $this->checkIn($this->position(95, self::SITE_LNG))->assertStatus(422)->assertJsonValidationErrors(['latitude']);
        $this->checkIn($this->position(self::SITE_LAT, 181))->assertStatus(422)->assertJsonValidationErrors(['longitude']);
        $this->checkIn(['latitude' => 'abc', 'longitude' => self::SITE_LNG])->assertStatus(422)->assertJsonValidationErrors(['latitude']);
        $this->checkIn(['longitude' => self::SITE_LNG])->assertStatus(422)->assertJsonValidationErrors(['latitude']);
        $this->checkIn($this->position(self::SITE_LAT, self::SITE_LNG, -1))->assertStatus(422)->assertJsonValidationErrors(['accuracy']);
        $this->checkIn($this->position(self::SITE_LAT, self::SITE_LNG, 5000))->assertStatus(422)->assertJsonValidationErrors(['accuracy']);
        $this->assertNull($this->todayRecord());
    }

    public function test_distance_is_computed_server_side_with_haversine(): void
    {
        $service = app(AttendanceGeofenceService::class);
        $this->assertSame(0.0, $service->distanceMeters(self::SITE_LAT, self::SITE_LNG, self::SITE_LAT, self::SITE_LNG));
        $hundred = $service->distanceMeters(self::SITE_LAT, self::SITE_LNG, self::SITE_LAT, 46.67629);
        $this->assertGreaterThan(95, $hundred);
        $this->assertLessThan(105, $hundred);
        $kilometre = $service->distanceMeters(self::SITE_LAT, self::SITE_LNG, self::SITE_LAT, 46.68518);
        $this->assertGreaterThan(990, $kilometre);
        $this->assertLessThan(1010, $kilometre);

        // A client-sent distance or status is ignored; the server's figure is stored.
        $response = $this->actingAs($this->worker)->postJson(route('admin.hr.attendance.mobile.locate'), $this->position(self::SITE_LAT, 46.67629, 8, ['distance_meters' => 1, 'status' => 'inside']))->assertOk();
        $this->assertEqualsWithDelta(100, $response->json('distance_meters'), 5);
        $this->assertSame('inside', $response->json('status'));
        $this->assertFalse($response->json('blocked'));
        $this->assertNull($this->todayRecord(), 'locate never writes');
    }

    public function test_inside_radius_check_in_stores_evidence_and_server_time(): void
    {
        Carbon::setTestNow('2026-10-09 07:42:10');
        try {
            $response = $this->checkIn($this->position(self::SITE_LAT, 46.67629, 9.4, ['captured_at' => '2020-01-01T00:00:00Z']))->assertOk();
            $response->assertJsonFragment(['ok' => true, 'step' => 'check_in', 'state' => 'can_check_out', 'status' => 'inside', 'time' => '07:42', 'attendance_status' => 'present']);

            $record = $this->todayRecord();
            $this->assertSame('07:42', $record->check_in);
            $this->assertSame('2026-10-09 07:42:10', $record->check_in_recorded_at->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-09', $record->attendance_date->toDateString());
            $this->assertSame(AttendanceRecord::SOURCE_GPS, $record->source);
            $this->assertSame('inside', $record->geofence_status);
            $this->assertSame('inside', $record->check_in_geofence_status);
            $this->assertEqualsWithDelta(24.7136, (float) $record->check_in_latitude, 0.00001);
            $this->assertEqualsWithDelta(46.67629, (float) $record->check_in_longitude, 0.00001);
            $this->assertSame(9.4, (float) $record->check_in_accuracy_meters);
            $this->assertEqualsWithDelta(100, (float) $record->check_in_distance_meters, 5);
            $this->assertNull($record->check_out);
            $this->assertNull($record->check_out_latitude);
            $this->assertSame(0, $record->late_minutes);
            $this->assertSame(0, $record->overtime_minutes);
            $this->assertDatabaseHas('activity_logs', ['module' => 'Attendance', 'action' => 'Mobile check-in']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_outside_radius_is_blocked_on_an_inside_only_site_and_logged(): void
    {
        $response = $this->checkIn($this->position(self::SITE_LAT, 46.68518))->assertStatus(422);
        $this->assertStringContainsString('allowed radius is 300 m', $response->json('errors.attendance.0'));
        $this->assertNull($this->todayRecord());
        $this->assertDatabaseHas('activity_logs', ['module' => 'Attendance', 'action' => 'Blocked mobile check-in', 'status' => 'failed']);
        $this->assertStringNotContainsString('46.68', ActivityLog::where('action', 'Blocked mobile check-in')->value('description'), 'no raw coordinates in the generic log');
    }

    public function test_outside_radius_is_recorded_as_outside_when_the_site_is_not_inside_only(): void
    {
        $this->site->update(['attendance_inside_only' => false]);
        $this->checkIn($this->position(self::SITE_LAT, 46.68518))->assertOk()->assertJsonFragment(['status' => 'outside']);
        $record = $this->todayRecord();
        $this->assertSame('outside', $record->geofence_status);
        $this->assertEqualsWithDelta(1000, (float) $record->check_in_distance_meters, 10);
    }

    public function test_disabled_geofence_records_the_position_as_not_enforced(): void
    {
        $this->site->update(['geofence_enabled' => false]);
        $this->checkIn($this->position(self::SITE_LAT, 46.68518))->assertOk()->assertJsonFragment(['status' => AttendanceRecord::GEOFENCE_NOT_ENFORCED]);
        $record = $this->todayRecord();
        $this->assertSame('not_enforced', $record->geofence_status);
        $this->assertEqualsWithDelta(1000, (float) $record->check_in_distance_meters, 10, 'distance is still recorded as evidence');
    }

    public function test_missing_site_coordinates_are_handled_safely(): void
    {
        $this->site->update(['latitude' => null, 'longitude' => null]);
        $this->checkIn($this->position())->assertStatus(422)->assertJsonFragment(['attendance' => [__('mobile_attendance.site_not_located')]]);
        $this->assertNull($this->todayRecord());

        $this->site->update(['attendance_inside_only' => false]);
        $this->checkIn($this->position())->assertOk()->assertJsonFragment(['status' => AttendanceRecord::GEOFENCE_LOCATION_UNAVAILABLE]);
        $this->assertNull($this->todayRecord()->check_in_distance_meters);
    }

    // ------------------------------------------------------------------ duplicates, check-out

    public function test_duplicate_check_in_is_refused_and_a_retry_adds_nothing(): void
    {
        $this->checkIn($this->position())->assertOk();
        $this->checkIn($this->position())->assertStatus(422)->assertJsonFragment(['attendance' => [__('mobile_attendance.already_checked_in')]]);
        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()->where('employee_id', $this->employee->id)->count());
        $this->actingAs($this->worker)->get(route('admin.hr.attendance.mobile'))->assertOk()->assertSee('Checked in, not checked out')->assertSee('Check out');
    }

    public function test_check_out_uses_the_open_record_fresh_coordinates_and_policy(): void
    {
        $this->checkOut($this->position())->assertStatus(422)->assertJsonFragment(['attendance' => [__('mobile_attendance.nothing_to_check_out')]]);

        Carbon::setTestNow('2026-10-09 07:00:00');
        $this->checkIn($this->position(self::SITE_LAT, 46.67629, 9))->assertOk();
        Carbon::setTestNow('2026-10-09 16:05:30');
        try {
            // Outside at check-out is blocked on an inside-only site; the check-in stays intact.
            $this->checkOut($this->position(self::SITE_LAT, 46.68518, 15))->assertStatus(422);
            $this->assertDatabaseHas('activity_logs', ['action' => 'Blocked mobile check-out', 'status' => 'failed']);
            $this->assertNull($this->todayRecord()->check_out);

            $this->checkOut($this->position(self::SITE_LAT, self::SITE_LNG, 7.5))->assertOk()->assertJsonFragment(['step' => 'check_out', 'state' => 'completed', 'time' => '16:05']);
            $record = $this->todayRecord();
            $this->assertSame('16:05', $record->check_out);
            $this->assertSame('2026-10-09 16:05:30', $record->check_out_recorded_at->format('Y-m-d H:i:s'));
            $this->assertEqualsWithDelta(46.6753, (float) $record->check_out_longitude, 0.00001);
            $this->assertEqualsWithDelta(46.67629, (float) $record->check_in_longitude, 0.00001, 'check-in evidence is preserved separately');
            $this->assertSame(7.5, (float) $record->check_out_accuracy_meters);
            $this->assertEqualsWithDelta(0, (float) $record->check_out_distance_meters, 1);
            $this->assertSame('inside', $record->check_out_geofence_status);
            $this->assertSame(0, $record->overtime_minutes, 'overtime stays a separate approved claim');

            $this->checkOut($this->position())->assertStatus(422)->assertJsonFragment(['attendance' => [__('mobile_attendance.already_checked_out')]]);
            $this->assertSame('16:05', $this->todayRecord()->check_out);
            $this->actingAs($this->worker)->get(route('admin.hr.attendance.mobile'))->assertOk()->assertSee('Checked in and out')->assertDontSee('data-action="locate"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manual_record_for_today_leaves_no_mobile_action_and_stays_manual(): void
    {
        AttendanceRecord::create(['employee_id' => $this->employee->id, 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'attendance_date' => today(), 'check_in' => '08:00', 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'unknown']);
        $this->actingAs($this->worker)->get(route('admin.hr.attendance.mobile'))->assertOk()->assertSee('Recorded by HR for today')->assertDontSee('data-action="locate"', false);
        $this->checkIn($this->position())->assertStatus(422);
        $this->checkOut($this->position())->assertStatus(422)->assertJsonFragment(['attendance' => [__('mobile_attendance.nothing_to_check_out')]]);
        $this->assertSame('manual', $this->todayRecord()->source);
    }

    public function test_shift_start_and_grace_decide_lateness_only(): void
    {
        $shift = Shift::create(['name' => 'Tower Day', 'code' => 'GPS-DAY', 'start_time' => '07:00', 'end_time' => '16:00', 'break_minutes' => 60, 'grace_minutes' => 10, 'overtime_after_minutes' => 30, 'status' => 'active']);
        EmployeeShiftAssignment::create(['employee_id' => $this->employee->id, 'shift_id' => $shift->id, 'effective_from' => '2026-01-01', 'status' => 'active']);

        Carbon::setTestNow('2026-10-09 07:25:00');
        try {
            $this->checkIn($this->position())->assertOk()->assertJsonFragment(['late_minutes' => 15, 'attendance_status' => 'late']);
            $record = $this->todayRecord();
            $this->assertSame($shift->id, $record->shift_id);
            $this->assertSame('late', $record->status);
            $this->assertSame(15, $record->late_minutes);
        } finally {
            Carbon::setTestNow();
        }
    }

    // ------------------------------------------------------------------ manual coexistence and HR correction

    public function test_hr_cannot_type_the_gps_source_and_corrections_keep_the_evidence(): void
    {
        $this->actingAs($this->admin())->post(route('admin.hr.attendance.store'), [
            'employee_id' => $this->employee->id, 'attendance_date' => now()->subDay()->toDateString(), 'check_in' => '08:00', 'late_minutes' => 0, 'overtime_minutes' => 0,
            'status' => 'present', 'source' => 'gps', 'geofence_status' => 'inside',
        ])->assertSessionHasErrors('source');

        $this->checkIn($this->position(self::SITE_LAT, 46.67629, 9))->assertOk();
        $record = $this->todayRecord();

        $this->actingAs($this->admin())->get(route('admin.hr.attendance.edit', $record))->assertOk()->assertSee('Location evidence')->assertSee('created by the mobile runtime');
        $this->actingAs($this->admin())->put(route('admin.hr.attendance.update', $record), [
            'employee_id' => Employee::where('employee_code', '!=', 'EMP-GPS-A')->value('id'), 'site_id' => $this->otherSite->id, 'project_id' => $this->otherProject->id,
            'attendance_date' => $record->attendance_date->toDateString(), 'check_in' => $record->check_in, 'check_out' => '17:30', 'late_minutes' => 0, 'overtime_minutes' => 45,
            'status' => 'present', 'remarks' => 'Left late, confirmed by supervisor',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.hr.attendance.index'));

        $record->refresh();
        $this->assertSame('17:30', $record->check_out);
        $this->assertSame(45, $record->overtime_minutes);
        $this->assertSame(AttendanceRecord::SOURCE_GPS, $record->source);
        $this->assertSame('inside', $record->geofence_status);
        $this->assertSame($this->employee->id, $record->employee_id);
        $this->assertSame($this->site->id, $record->site_id);
        $this->assertEqualsWithDelta(46.67629, (float) $record->check_in_longitude, 0.00001);
        $this->assertNull($record->check_out_latitude, 'a typed check-out never fabricates location evidence');
        $this->assertDatabaseHas('activity_logs', ['action' => 'Corrected GPS attendance record']);
    }

    public function test_cross_project_and_cross_employee_requests_cannot_record_attendance(): void
    {
        // A user of another project with their own linked employee can only record for themselves, at their own site.
        $other = $this->mobileUser('other', $this->otherProject);
        $otherEmployee = Employee::create(['employee_code' => 'EMP-GPS-B', 'first_name' => 'Khalid', 'last_name' => 'Otaibi', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $this->otherProject->id, 'site_id' => $this->otherSite->id, 'user_id' => $other->id]);

        // Riyadh ids in the body are outside their scope: refused outright. Without them, a Riyadh position is judged
        // against Jeddah (their own site) and blocked as outside.
        $this->checkIn($this->position(self::SITE_LAT, self::SITE_LNG, 10, ['employee_id' => $this->employee->id, 'site_id' => $this->site->id, 'project_id' => $this->project->id]), $other)->assertForbidden();
        // A foreign employee id alone is also refused by the scope guard; a clean request is judged against their own site.
        $this->checkIn($this->position(self::SITE_LAT, self::SITE_LNG, 10, ['employee_id' => $this->employee->id]), $other)->assertForbidden();
        $this->checkIn($this->position(self::SITE_LAT, self::SITE_LNG, 10), $other)->assertStatus(422);
        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->whereIn('employee_id', [$this->employee->id, $otherEmployee->id])->count());

        $this->checkIn($this->position(21.4858, 39.1925, 10), $other)->assertOk();
        $record = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $otherEmployee->id)->firstOrFail();
        $this->assertSame($otherEmployee->id, $record->employee_id);
        $this->assertSame($this->otherSite->id, $record->site_id);
    }

    // ------------------------------------------------------------------ workspaces

    public function test_employee_and_site_workspaces_show_the_runtime_source_and_geofence_state(): void
    {
        $this->checkIn($this->position(self::SITE_LAT, 46.67629, 9))->assertOk();
        AttendanceRecord::create(['employee_id' => $this->employee->id, 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'attendance_date' => today()->subDay(), 'check_in' => '08:00', 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'unknown']);

        $hr = $this->mobileUser('hr', null, ['HR' => ['view'], 'Attendance' => ['view', 'mobile'], 'Sites' => ['view']]);
        $this->actingAs($hr)->get(route('admin.hr.employees.show', $this->employee))->assertOk()
            ->assertSee('GPS / Mobile')->assertSee('Inside')->assertSee('100 m')->assertSee('Manual')->assertSee('Unknown');

        $html = $this->actingAs($hr)->getJson(route('admin.master.sites.workspace.panel', [$this->site, 'attendance']))->assertOk()->json('html');
        $this->assertStringContainsString('GPS / Mobile', $html);
        $this->assertStringContainsString('Inside · 100 m', $html);
        $this->assertStringContainsString('Manual', $html);
        $this->assertStringContainsString('location-validated', $html);

        $this->actingAs($this->admin())->get(route('admin.hr.attendance.index', ['employee' => $this->employee->id]))->assertOk()->assertSee('GPS / Mobile')->assertSee('Mobile Attendance');
    }
}
