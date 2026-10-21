<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OfflineSyncReceipt;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Offline Mobile Sync foundation + offline GPS attendance. The transport is
 * idempotent per client UUID (replay, conflict, foreign receipt, batch); the
 * attendance handler re-runs every server rule on the captured position and
 * time, keeps captured and received time apart and never loosens the
 * one-record-per-day rule.
 */
class OfflineSyncAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_LAT = 24.7136;

    private const SITE_LNG = 46.6753;

    private const EAST_100M = 46.67629;

    private const EAST_1KM = 46.68518;

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
        $this->project = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-OFF-A', 'status' => 'active']);
        $this->otherProject = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-OFF-B', 'status' => 'active']);
        $this->site = Site::create(['name' => 'Riyadh Tower - Main Site', 'code' => 'SITE-OFF-A', 'project_id' => $this->project->id, 'status' => 'active',
            'latitude' => self::SITE_LAT, 'longitude' => self::SITE_LNG, 'geofence_radius' => 300, 'geofence_enabled' => true, 'attendance_inside_only' => true]);
        $this->otherSite = Site::create(['name' => 'Jeddah Site', 'code' => 'SITE-OFF-B', 'project_id' => $this->otherProject->id, 'status' => 'active',
            'latitude' => 21.4858, 'longitude' => 39.1925, 'geofence_radius' => 300, 'geofence_enabled' => true, 'attendance_inside_only' => true]);
        $this->worker = $this->mobileUser('worker', $this->project);
        $this->employee = $this->linkedEmployee('EMP-OFF-A', $this->worker, $this->site);
    }

    // ---------------------------------------------------------------- helpers

    protected function mobileUser(string $suffix, ?Project $project, array $grants = ['Attendance' => ['view', 'mobile']], bool $mobileAccess = true): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'Offline role '.$suffix, 'code' => 'OFF_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 5, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'Offline '.$suffix, 'email' => 'off-'.$suffix.'@example.test', 'username' => 'off.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id, 'mobile_access' => $mobileAccess]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function linkedEmployee(string $code, User $user, Site $site): Employee
    {
        return Employee::create(['employee_code' => $code, 'first_name' => 'Ahmed', 'last_name' => $code, 'email' => strtolower($code).'@example.test', 'status' => 'active',
            'employee_classification' => 'Sponsorship', 'joining_date' => '2024-03-01', 'project_id' => $site->project_id, 'site_id' => $site->id, 'user_id' => $user->id]);
    }

    protected function uuid(): string
    {
        return (string) Str::uuid();
    }

    /** One queued item captured 20 minutes ago at the given position, with the displayed site context. */
    protected function item(string $action, array $payload = [], array $extra = []): array
    {
        return $extra + [
            'client_uuid' => $this->uuid(), 'action' => $action,
            'captured_at' => Carbon::now()->subMinutes(20)->toIso8601String(),
            'payload' => $payload + ['latitude' => self::SITE_LAT, 'longitude' => self::EAST_100M, 'accuracy' => 9.4, 'site_id' => $this->site->id, 'project_id' => $this->project->id],
        ];
    }

    protected function sync(array $items, ?User $as = null, array $envelope = []): TestResponse
    {
        return $this->actingAs($as ?? $this->worker)->postJson(route('admin.mobile-sync.attendance'), ['items' => $items] + $envelope);
    }

    protected function records(): \Illuminate\Database\Eloquent\Collection
    {
        return AttendanceRecord::withoutGlobalScopes()->where('employee_id', $this->employee->id)->get();
    }

    // ------------------------------------------------------------- foundation

    public function test_unique_client_uuid_is_accepted_and_stores_captured_and_received_time_apart(): void
    {
        Carbon::setTestNow('2026-10-15 08:00:00');
        try {
            $item = $this->item('check_in', [], ['captured_at' => '2026-10-15T07:31:00+00:00']);
            $response = $this->sync([$item])->assertOk();
            $response->assertJsonPath('results.0.status', 'synced')->assertJsonPath('results.0.client_uuid', $item['client_uuid'])
                ->assertJsonPath('results.0.record.type', 'attendance_record')->assertJsonPath('results.0.record.time', '07:31')->assertJsonPath('results.0.record.status', 'inside');

            $record = $this->records()->sole();
            $this->assertSame(AttendanceRecord::SOURCE_GPS_OFFLINE, $record->source);
            $this->assertTrue($record->isGpsRecord());
            $this->assertSame('2026-10-15', $record->attendance_date->toDateString());
            $this->assertSame('07:31', $record->check_in);
            $this->assertSame('2026-10-15 07:31:00', $record->check_in_captured_at->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-15 08:00:00', $record->check_in_recorded_at->format('Y-m-d H:i:s'));
            $this->assertSame($item['client_uuid'], $record->check_in_client_uuid);
            $this->assertEqualsWithDelta(self::EAST_100M, (float) $record->check_in_longitude, 0.00001);
            $this->assertEqualsWithDelta(100, (float) $record->check_in_distance_meters, 5);
            $this->assertSame('inside', $record->check_in_geofence_status);

            $receipt = OfflineSyncReceipt::where('client_uuid', $item['client_uuid'])->sole();
            $this->assertSame('synced', $receipt->status);
            $this->assertSame($this->worker->id, $receipt->user_id);
            $this->assertSame($this->employee->id, $receipt->employee_id);
            $this->assertSame($record->id, $receipt->business_record_id);
            $this->assertSame(1, $receipt->attempt_count);
            $this->assertDatabaseHas('activity_logs', ['module' => 'Attendance', 'action' => 'Offline check-in synchronized']);
            $this->assertStringContainsString('delay 29 min', \App\Models\ActivityLog::latest('id')->value('description'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_exact_replay_returns_the_stored_outcome_and_writes_nothing(): void
    {
        $item = $this->item('check_in');
        $first = $this->sync([$item])->assertOk()->json('results.0');
        $second = $this->sync([$item])->assertOk()->json('results.0');
        $this->assertSame('synced', $second['status']);
        $this->assertSame($first['record']['id'], $second['record']['id']);
        $this->assertTrue($second['data']['replay']);
        $this->assertCount(1, $this->records());
        $this->assertSame(1, OfflineSyncReceipt::where('client_uuid', $item['client_uuid'])->value('attempt_count'));
        $this->assertSame(1, \App\Models\ActivityLog::where('action', 'Offline check-in synchronized')->count());
    }

    public function test_same_uuid_with_a_different_payload_is_refused_deterministically(): void
    {
        $item = $this->item('check_in');
        $this->sync([$item])->assertOk()->assertJsonPath('results.0.status', 'synced');
        $changed = $item;
        $changed['payload']['longitude'] = self::SITE_LNG;
        for ($i = 0; $i < 2; $i++) {
            $this->sync([$changed])->assertOk()->assertJsonPath('results.0.status', 'needs_attention')
                ->assertJsonPath('results.0.code', 'BUSINESS_CONFLICT')->assertJsonPath('results.0.reason', 'payload_mismatch');
        }
        $this->assertCount(1, $this->records());
        $this->assertSame('synced', OfflineSyncReceipt::where('client_uuid', $item['client_uuid'])->value('status'));
    }

    public function test_uuid_first_received_from_another_user_is_refused(): void
    {
        $other = $this->mobileUser('other', $this->project);
        $this->linkedEmployee('EMP-OFF-B', $other, $this->site);
        $item = $this->item('check_in');
        $this->sync([$item], $other)->assertOk()->assertJsonPath('results.0.status', 'synced');
        $this->sync([$item])->assertOk()->assertJsonPath('results.0.status', 'needs_attention')->assertJsonPath('results.0.code', 'PERMISSION_DENIED')->assertJsonPath('results.0.reason', 'foreign_receipt');
        $this->assertCount(0, $this->records());
        $this->assertSame(1, OfflineSyncReceipt::count());
    }

    public function test_batch_processes_independent_items_and_one_bad_item_does_not_fail_the_batch(): void
    {
        $bad = $this->item('check_in', ['latitude' => 'north']);
        $good = $this->item('check_in');
        $response = $this->sync([$bad, $good])->assertOk();
        $results = collect($response->json('results'))->keyBy('client_uuid');
        $this->assertSame('failed', $results[$bad['client_uuid']]['status']);
        $this->assertSame('VALIDATION_ERROR', $results[$bad['client_uuid']]['code']);
        $this->assertSame('synced', $results[$good['client_uuid']]['status']);
        $this->assertCount(1, $this->records());
        $this->assertDatabaseMissing('offline_sync_receipts', ['client_uuid' => $bad['client_uuid']]);

        // Envelope limits: bounded batch, strict UUIDs, known actions.
        $this->sync(array_map(fn () => $this->item('check_in'), range(1, 21)))->assertUnprocessable();
        $this->sync([$this->item('check_in', [], ['client_uuid' => 'not-a-uuid'])])->assertUnprocessable();
        $this->sync([$this->item('delete')])->assertUnprocessable();
        $this->sync([])->assertUnprocessable();
    }

    public function test_sync_requires_authentication_and_the_mobile_permission(): void
    {
        $this->postJson(route('admin.mobile-sync.attendance'), ['items' => [$this->item('check_in')]])->assertUnauthorized();
        $this->getJson(route('admin.mobile-sync.session'))->assertUnauthorized();

        $viewer = $this->mobileUser('viewer', $this->project, ['Attendance' => ['view']]);
        $this->linkedEmployee('EMP-OFF-V', $viewer, $this->site);
        $this->sync([$this->item('check_in')], $viewer)->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.mobile-sync.index'))->assertForbidden();
        $this->assertSame(0, AttendanceRecord::withoutGlobalScopes()->count() - $this->seededCount());

        // Eligibility is revalidated per item: Mobile Access removed after capture.
        $this->worker->update(['mobile_access' => false]);
        $this->sync([$this->item('check_in')], $this->worker->fresh())->assertOk()->assertJsonPath('results.0.status', 'needs_attention')->assertJsonPath('results.0.code', 'PERMISSION_DENIED')->assertJsonPath('results.0.reason', 'not_eligible');
        $this->assertCount(0, $this->records());
    }

    private function seededCount(): int
    {
        return AttendanceRecord::withoutGlobalScopes()->whereNotIn('employee_id', Employee::withoutGlobalScopes()->where('employee_code', 'like', 'EMP-OFF-%')->pluck('id'))->count();
    }

    public function test_foreign_project_or_site_in_the_captured_context_is_detected_and_nothing_is_recorded(): void
    {
        $foreignSite = $this->sync([$this->item('check_in', ['site_id' => $this->otherSite->id, 'project_id' => $this->otherProject->id])])->assertOk()->json('results.0');
        $this->assertSame('needs_attention', $foreignSite['status']);
        $this->assertSame('STALE_REFERENCE', $foreignSite['code']);
        $this->assertSame('assignment_changed', $foreignSite['reason']);
        $this->assertSame($this->otherSite->id, $foreignSite['data']['captured_site_id']);

        $foreignProject = $this->sync([$this->item('check_in', ['project_id' => $this->otherProject->id])])->assertOk()->json('results.0');
        $this->assertSame('assignment_changed', $foreignProject['reason']);
        $this->assertCount(0, $this->records());
    }

    public function test_site_assignment_changed_before_sync_and_inactive_site_need_attention(): void
    {
        // Captured at site A; HR moved the employee to a new site before the sync arrived.
        $newSite = Site::create(['name' => 'Riyadh Tower - Annex', 'code' => 'SITE-OFF-C', 'project_id' => $this->project->id, 'status' => 'active',
            'latitude' => self::SITE_LAT, 'longitude' => self::SITE_LNG, 'geofence_radius' => 300, 'geofence_enabled' => true, 'attendance_inside_only' => true]);
        $this->employee->update(['site_id' => $newSite->id]);
        $result = $this->sync([$this->item('check_in')])->assertOk()->json('results.0');
        $this->assertSame('assignment_changed', $result['reason']);
        $this->assertStringContainsString('Riyadh Tower - Main Site', $result['message']);
        $this->assertStringContainsString('Riyadh Tower - Annex', $result['message']);
        $this->assertCount(0, $this->records());

        // Stale reference: the site itself is no longer usable.
        $this->employee->update(['site_id' => $this->site->id]);
        $this->site->update(['status' => 'inactive']);
        $this->sync([$this->item('check_in')])->assertOk()->assertJsonPath('results.0.status', 'needs_attention')->assertJsonPath('results.0.reason', 'not_eligible');
        $this->assertCount(0, $this->records());
    }

    // ------------------------------------------------------------- attendance

    public function test_server_recomputes_the_geofence_and_ignores_the_client_verdict(): void
    {
        // 1 km east on an inside-only site: blocked at sync even though the device claimed "inside".
        $result = $this->sync([$this->item('check_in', ['longitude' => self::EAST_1KM, 'client_geofence_status' => 'inside'])])->assertOk()->json('results.0');
        $this->assertSame('needs_attention', $result['status']);
        $this->assertSame('BUSINESS_CONFLICT', $result['code']);
        $this->assertSame('geofence_blocked', $result['reason']);
        $this->assertStringContainsString('allowed radius is 300 m', $result['message']);
        $this->assertCount(0, $this->records());
        $this->assertDatabaseHas('activity_logs', ['module' => 'Attendance', 'action' => 'Blocked offline check-in', 'status' => 'failed']);

        // The same position on a site that is not inside-only is recorded as Outside.
        $this->site->update(['attendance_inside_only' => false]);
        $this->sync([$this->item('check_in', ['longitude' => self::EAST_1KM, 'client_geofence_status' => 'inside'])])->assertOk()
            ->assertJsonPath('results.0.status', 'synced')->assertJsonPath('results.0.record.status', 'outside');
        $record = $this->records()->sole();
        $this->assertSame('outside', $record->geofence_status);
        $this->assertEqualsWithDelta(1000, (float) $record->check_in_distance_meters, 20);
    }

    public function test_geofence_disabled_site_records_the_position_as_not_enforced(): void
    {
        $this->site->update(['geofence_enabled' => false]);
        $this->sync([$this->item('check_in', ['longitude' => self::EAST_1KM])])->assertOk()->assertJsonPath('results.0.status', 'synced')->assertJsonPath('results.0.record.status', 'not_enforced');
        $this->assertSame('not_enforced', $this->records()->sole()->check_in_geofence_status);
    }

    public function test_captured_time_policy_refuses_future_and_stale_events_and_flags_clock_skew(): void
    {
        $future = $this->sync([$this->item('check_in', [], ['captured_at' => Carbon::now()->addHours(2)->toIso8601String()])])->json('results.0');
        $this->assertSame(['failed', 'VALIDATION_ERROR', 'captured_in_future'], [$future['status'], $future['code'], $future['reason']]);

        $old = $this->sync([$this->item('check_in', [], ['captured_at' => '2020-01-01T08:00:00+00:00'])])->json('results.0');
        $this->assertSame(['failed', 'VALIDATION_ERROR', 'captured_too_old'], [$old['status'], $old['code'], $old['reason']]);
        $this->assertStringContainsString('72-hour', $old['message']);

        $skew = $this->sync([$this->item('check_in')], null, ['device_time' => Carbon::now()->subHours(3)->toIso8601String()])->json('results.0');
        $this->assertSame(['needs_attention', 'BUSINESS_CONFLICT', 'clock_skew'], [$skew['status'], $skew['code'], $skew['reason']]);
        $this->assertSame(0, $this->records()->count());

        // A small, plausible drift is accepted.
        $this->sync([$this->item('check_in')], null, ['device_time' => Carbon::now()->addMinutes(2)->toIso8601String()])->assertJsonPath('results.0.status', 'synced');
        $this->assertSame(1, $this->records()->count());
    }

    public function test_offline_check_out_syncs_and_the_batch_runs_check_in_before_check_out(): void
    {
        // Frozen clock: the attendance day is the captured UTC date, so "now minus nine hours" must stay on the same
        // day whatever the wall-clock hour the suite runs at (it failed before 09:00 UTC).
        Carbon::setTestNow('2026-10-15 17:00:00');
        try {
            $this->runCheckOutBatch();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function runCheckOutBatch(): void
    {
        $checkIn = $this->item('check_in', [], ['captured_at' => Carbon::now()->subHours(9)->toIso8601String()]);
        $checkOut = $this->item('check_out', ['longitude' => self::SITE_LNG], ['captured_at' => Carbon::now()->subMinutes(5)->toIso8601String()]);
        // Deliberately out of order: the server orders by captured time and action.
        $results = collect($this->sync([$checkOut, $checkIn])->assertOk()->json('results'))->keyBy('client_uuid');
        $this->assertSame('synced', $results[$checkIn['client_uuid']]['status']);
        $this->assertSame('synced', $results[$checkOut['client_uuid']]['status']);
        $this->assertSame('completed', $results[$checkOut['client_uuid']]['record']['state']);

        $record = $this->records()->sole();
        $this->assertNotNull($record->check_out);
        $this->assertSame($checkOut['client_uuid'], $record->check_out_client_uuid);
        $this->assertNotNull($record->check_out_captured_at);
        $this->assertNotNull($record->check_out_recorded_at);
        $this->assertEqualsWithDelta(0, (float) $record->check_out_distance_meters, 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Offline check-out synchronized']);

        // Exact replay of the check-out changes nothing.
        $this->sync([$checkOut])->assertJsonPath('results.0.status', 'synced')->assertJsonPath('results.0.data.replay', true);
        $this->assertCount(1, $this->records());
    }

    public function test_check_out_without_its_check_in_stays_pending_and_other_check_out_conflicts_are_explained(): void
    {
        $noCheckIn = $this->sync([$this->item('check_out')])->json('results.0');
        $this->assertSame(['needs_attention', 'BUSINESS_CONFLICT', 'no_check_in'], [$noCheckIn['status'], $noCheckIn['code'], $noCheckIn['reason']]);

        $checkIn = $this->item('check_in', [], ['captured_at' => Carbon::now()->subHours(2)->toIso8601String()]);
        $this->sync([$checkIn])->assertJsonPath('results.0.status', 'synced');
        // Captured before the check-in: permanent.
        $early = $this->sync([$this->item('check_out', [], ['captured_at' => Carbon::now()->subHours(3)->toIso8601String()])])->json('results.0');
        $this->assertSame(['failed', 'VALIDATION_ERROR', 'check_out_before_check_in'], [$early['status'], $early['code'], $early['reason']]);
        // Captured site differs from the day's record.
        $otherSite = $this->sync([$this->item('check_out', ['site_id' => $this->otherSite->id])])->json('results.0');
        $this->assertSame('site_changed_on_record', $otherSite['reason']);
        // Valid check-out, then a second one.
        $this->sync([$this->item('check_out')])->assertJsonPath('results.0.status', 'synced');
        $again = $this->sync([$this->item('check_out')])->json('results.0');
        $this->assertSame('already_checked_out', $again['reason']);
        $this->assertSame($this->records()->sole()->id, $again['data']['existing']['id']);
    }

    public function test_online_check_in_before_the_offline_one_syncs_is_a_conflict_not_a_duplicate(): void
    {
        $this->actingAs($this->worker)->postJson(route('admin.hr.attendance.mobile.check-in'), ['latitude' => self::SITE_LAT, 'longitude' => self::SITE_LNG, 'accuracy' => 5])->assertOk();
        $result = $this->sync([$this->item('check_in')])->assertOk()->json('results.0');
        $this->assertSame(['needs_attention', 'BUSINESS_CONFLICT', 'already_checked_in'], [$result['status'], $result['code'], $result['reason']]);
        $this->assertSame('gps', $result['data']['existing']['source']);
        $this->assertSame('GPS / Mobile', $result['data']['existing']['source_label']);
        $this->assertArrayNotHasKey('latitude', $result['data']['existing']);
        $this->assertCount(1, $this->records());
        $this->assertSame('gps', $this->records()->sole()->source);
    }

    public function test_one_record_per_day_is_preserved_across_offline_check_ins(): void
    {
        $this->sync([$this->item('check_in')])->assertJsonPath('results.0.status', 'synced');
        $second = $this->sync([$this->item('check_in', ['longitude' => self::SITE_LNG])])->json('results.0');
        $this->assertSame('already_checked_in', $second['reason']);
        $this->assertSame('Offline GPS / Synchronized', $second['data']['existing']['source_label']);
        $this->assertCount(1, $this->records());
    }

    public function test_manual_record_for_the_day_is_not_overwritten_and_hr_corrections_keep_the_offline_source(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        AttendanceRecord::create(['employee_id' => $this->employee->id, 'project_id' => $this->project->id, 'site_id' => $this->site->id,
            'attendance_date' => Carbon::now()->subMinutes(20)->toDateString(), 'check_in' => '07:00', 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'inside']);
        $result = $this->sync([$this->item('check_in')])->json('results.0');
        $this->assertSame('recorded_manually', $result['reason']);
        $this->assertSame('manual', $this->records()->sole()->source);

        // An offline record, corrected by HR, keeps its source and evidence.
        $this->records()->sole()->delete();
        $this->sync([$this->item('check_in')])->assertJsonPath('results.0.status', 'synced');
        $record = $this->records()->sole();
        $this->actingAs($admin)->put(route('admin.hr.attendance.update', $record), [
            'employee_id' => 999, 'project_id' => 999, 'site_id' => 999, 'attendance_date' => $record->attendance_date->toDateString(), 'check_in' => $record->check_in, 'check_out' => '17:00',
            'late_minutes' => 0, 'overtime_minutes' => 0, 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'outside', 'remarks' => 'Left early', '_save_action' => 'close',
        ])->assertRedirect();
        $record->refresh();
        $this->assertSame(AttendanceRecord::SOURCE_GPS_OFFLINE, $record->source);
        $this->assertSame('inside', $record->geofence_status);
        $this->assertSame($this->employee->id, $record->employee_id);
        $this->assertSame('17:00', $record->check_out);
        $this->assertNotNull($record->check_in_captured_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Corrected GPS attendance record']);
    }

    public function test_online_runtime_still_works_after_an_offline_check_in_and_shows_the_offline_source(): void
    {
        $this->sync([$this->item('check_in', [], ['captured_at' => Carbon::now()->subMinutes(30)->toIso8601String()])])->assertJsonPath('results.0.status', 'synced');
        $this->actingAs($this->worker)->get(route('admin.hr.attendance.mobile'))->assertOk()->assertSee('Checked in, not checked out');
        $this->actingAs($this->worker)->postJson(route('admin.hr.attendance.mobile.check-out'), ['latitude' => self::SITE_LAT, 'longitude' => self::SITE_LNG, 'accuracy' => 5])->assertOk()->assertJsonPath('state', 'completed');
        $record = $this->records()->sole();
        $this->assertSame(AttendanceRecord::SOURCE_GPS_OFFLINE, $record->source);
        $this->assertNotNull($record->check_out);
        $this->assertNull($record->check_out_client_uuid);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.hr.attendance.index', ['employee' => $this->employee->id]))->assertOk()->assertSee('Offline GPS / Synchronized');
        $this->actingAs($admin)->get(route('admin.hr.employees.show', $this->employee))->assertOk()->assertSee('Offline GPS / Synchronized');
    }

    public function test_sync_page_session_and_reference_endpoints_serve_the_capable_user(): void
    {
        $this->actingAs($this->worker)->get(route('admin.mobile-sync.index'))->assertOk()->assertSee('Mobile Sync')->assertSee('data-offline-sync-page', false)->assertSee('Clear offline data');
        $this->actingAs($this->worker)->getJson(route('admin.mobile-sync.session'))->assertOk()->assertJsonPath('user_id', $this->worker->id)->assertJsonStructure(['csrf_token', 'server_time']);
        $reference = $this->actingAs($this->worker)->getJson(route('admin.mobile-sync.reference'))->assertOk()->json();
        $this->assertTrue($reference['attendance']['eligible']);
        $this->assertSame($this->site->id, $reference['attendance']['site']['id']);
        $this->assertSame(300, $reference['attendance']['site']['geofence_radius']);
        $this->assertNull($reference['site_expense']);
        $this->assertSame(72, $reference['policy']['attendance_max_age_hours']);

        $this->sync([$this->item('check_in')])->assertJsonPath('results.0.status', 'synced');
        $this->actingAs($this->worker)->get(route('admin.mobile-sync.index'))->assertOk()->assertSee('Offline check-in')->assertSee('Synced');
    }
}
