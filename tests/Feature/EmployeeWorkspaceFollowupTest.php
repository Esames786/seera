<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWorkspaceFollowupTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->employee = Employee::create([
            'employee_code' => 'SP-FOLLOWUP', 'first_name' => 'Followup', 'last_name' => 'Employee',
            'status' => 'active', 'basic_salary' => 3500, 'employee_classification' => 'Sponsorship',
        ]);
    }

    private function panel(string $panel, string $action = 'panel'): string
    {
        return route('admin.hr.employees.workspace.'.$action, [$this->employee, $panel]);
    }

    private function shiftData(array $overrides = []): array
    {
        return $overrides + ['name' => 'New Night Shift', 'code' => 'NIGHT-NEW', 'start_time' => '20:00', 'end_time' => '05:00', 'break_minutes' => 30, 'grace_minutes' => 5, 'overtime_after_minutes' => 480, 'status' => 'active'];
    }

    public function test_empty_shift_master_can_be_created_and_assigned_without_leaving_employee(): void
    {
        // Isolated SQLite fixture only. No production master seed or deletion.
        AttendanceRecord::query()->update(['shift_id' => null]);
        EmployeeShiftAssignment::query()->delete();
        Shift::query()->delete();
        $html = $this->getJson($this->panel('shifts'))->assertOk()->json('html');
        $this->assertStringContainsString('No options yet.', $html);
        $this->assertStringContainsString('data-quick-create="employee-master-shift"', $html);
        $this->assertStringNotContainsString('Shift Id', $html);
        $created = $this->postJson(route('admin.hr.shifts.store'), $this->shiftData())->assertCreated()->assertJsonPath('label', 'New Night Shift');
        $id = $created->json('id');
        $response = $this->postJson($this->panel('shifts', 'save'), ['shift_id' => $id, 'effective_from' => today()->toDateString(), 'status' => 'active', '_save_action' => 'stay'])->assertOk();
        $this->assertDatabaseHas('employee_shift_assignments', ['employee_id' => $this->employee->id, 'shift_id' => $id]);
        $saved = $this->getJson($response->json('panel_url'))->assertOk()->json('html');
        $this->assertStringContainsString('name="record_id"', $saved);
        $this->assertStringContainsString('New Night Shift', $saved);
        $this->postJson(route('admin.hr.shifts.store'), $this->shiftData())->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson(route('admin.hr.shifts.store'), $this->shiftData(['code' => 'INVALID', 'break_minutes' => -1]))->assertUnprocessable()->assertJsonValidationErrors('break_minutes');
    }

    public function test_leave_type_can_be_created_validated_and_used_without_seeding(): void
    {
        $data = ['name' => 'Study Leave', 'code' => 'study', 'max_days_per_year' => 7, 'is_paid' => 0];
        $response = $this->postJson(route('admin.hr.leave-types.store'), $data)->assertCreated();
        $id = $response->json('id');
        $this->assertDatabaseHas('leave_types', ['id' => $id, 'code' => 'STUDY', 'is_paid' => 0, 'status' => 'active']);
        $this->postJson(route('admin.hr.leave-types.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson(route('admin.hr.leave-types.store'), ['code' => 'BAD', 'max_days_per_year' => 366] + $data)->assertUnprocessable()->assertJsonValidationErrors('max_days_per_year');
        $this->getJson($this->panel('leaves'))->assertOk()->assertSee('Study Leave')->assertDontSee('Leave Type Id');
        $this->postJson($this->panel('leaves', 'save'), ['leave_type_id' => $id, 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), '_save_action' => 'next'])->assertOk();
        $this->assertDatabaseHas('leave_requests', ['employee_id' => $this->employee->id, 'leave_type_id' => $id, 'status' => 'pending']);
    }

    public function test_every_editable_panel_has_consistent_intents_and_static_master_dialogs(): void
    {
        foreach (['salary', 'attendance', 'leaves', 'overtime', 'shifts', 'eosb', 'account'] as $panel) {
            $html = $this->getJson($this->panel($panel))->assertOk()->json('html');
            foreach (['stay', 'next', 'close'] as $intent) {
                $this->assertStringContainsString('name="_save_action" value="'.$intent.'"', $html);
            }
            $this->assertStringNotContainsString('Save here', $html);
            $this->assertStringNotContainsString('Save changes here', $html);
        }
        $this->getJson($this->panel('eosb'))->assertOk()->assertSee('Final Wage (SAR)')->assertDontSee('Last Basic Salary');
        $html = $this->get(route('admin.hr.employees.edit', $this->employee))->assertOk()->getContent();
        foreach (['employee-master-shift', 'employee-master-leave-type', 'employee-master-role', 'ews-project', 'ews-site'] as $id) {
            $this->assertSame(1, substr_count($html, 'id="'.$id.'"'));
        }
        $this->assertStringContainsString('data-close-url="'.route('admin.hr.employees.index').'"', $html);
        $this->assertStringContainsString('class="quick-create-form" method="POST"', $html);
        $this->assertStringContainsString('data-quick-target="project_id"', $html);
    }

    public function test_save_intents_only_return_canonical_record_urls_and_validation_still_blocks(): void
    {
        foreach (['stay', 'next', 'close'] as $intent) {
            $saved = $this->postJson($this->panel('overtime', 'save'), ['overtime_date' => today()->toDateString(), 'hours' => 1, 'rate' => 25, '_save_action' => $intent])->assertOk();
            $this->assertStringStartsWith($this->panel('overtime').'?record=', $saved->json('panel_url'));
        }
        $this->postJson($this->panel('overtime', 'save'), ['_save_action' => 'https://outside.example'])->assertUnprocessable()->assertJsonValidationErrors('_save_action');
        $this->postJson($this->panel('overtime', 'save'), ['_save_action' => 'close'])->assertUnprocessable()->assertJsonValidationErrors('hours');
        $this->assertSame(3, $this->employee->overtimeRecords()->count());
    }

    public function test_profile_save_next_from_access_continues_into_related_sections(): void
    {
        $this->put(route('admin.hr.employees.update', $this->employee), [
            'employee_code' => $this->employee->employee_code, 'first_name' => 'Followup', 'contract_type' => 'Full Time',
            'employee_classification' => 'Sponsorship', 'basic_salary' => 3500, 'payment_method' => 'Bank Transfer',
            'status' => 'active', '_save_action' => 'next', '_workspace_section' => 'access',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.hr.employees.edit', $this->employee).'#salary');
    }

    public function test_project_and_site_inline_creation_keep_parent_and_role_creation_has_no_implicit_permissions(): void
    {
        $project = $this->postJson(route('admin.master.projects.store'), ['name' => 'Inline Project', 'code' => 'INLINE-24', 'status' => 'planning'])->assertCreated();
        $site = $this->postJson(route('admin.master.sites.store'), ['name' => 'Inline Site', 'code' => 'INLINE-SITE-24', 'project_id' => $project->json('id'), 'status' => 'draft'])->assertCreated();
        $site->assertJsonPath('parent', $project->json('id'));
        $this->assertDatabaseHas('sites', ['id' => $site->json('id'), 'project_id' => $project->json('id'), 'status' => 'draft']);
        $userCount = User::count();
        $role = $this->postJson(route('admin.roles.store'), ['name' => 'Inline Restricted', 'level' => 3, 'access_scope' => 'Site Level', 'status' => 'active'])->assertCreated();
        $this->assertSame(0, Role::findOrFail($role->json('id'))->permissions()->count());
        $this->assertSame($userCount, User::count());
    }

    public function test_master_creation_requires_its_own_create_permission(): void
    {
        $role = Role::create(['name' => 'Workspace edit only', 'code' => 'WORKSPACE_EDIT_24', 'level' => 2, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', ['HR', 'Payroll', 'Attendance', 'Users'])->whereIn('action', ['view', 'edit'])->pluck('id'));
        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($actor);
        $this->getJson($this->panel('shifts'))->assertOk()->assertDontSee('data-quick-create');
        foreach (['admin.hr.leave-types.store', 'admin.hr.shifts.store', 'admin.master.projects.store', 'admin.master.sites.store', 'admin.roles.store'] as $route) {
            $this->postJson(route($route), [])->assertForbidden();
        }
        $this->assertDatabaseMissing('leave_types', ['code' => 'STUDY']);
    }

    public function test_site_scoped_operator_cannot_create_out_of_scope_inline_masters(): void
    {
        $site = Site::firstOrFail();
        $actor = User::factory()->create(['status' => 'active', 'site_id' => $site->id, 'project_id' => $site->project_id]);
        $role = Role::create(['name' => 'Scoped master creator', 'code' => 'SCOPED_MASTER_24', 'level' => 3, 'access_scope' => 'Site Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', ['HR', 'Sites', 'Projects'])->pluck('id'));
        $actor->roles()->attach($role, ['is_primary' => true]);
        $this->actingAs($actor)->postJson(route('admin.master.projects.store'), ['name' => 'Forbidden project', 'code' => 'FORBIDDEN-P', 'status' => 'planning'])->assertForbidden();
        $this->postJson(route('admin.master.sites.store'), ['name' => 'Forbidden site', 'code' => 'FORBIDDEN-S', 'project_id' => $site->project_id, 'status' => 'draft'])->assertForbidden();
        $this->assertDatabaseMissing('sites', ['code' => 'FORBIDDEN-S']);
    }
}
