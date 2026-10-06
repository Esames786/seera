<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Workspace\UserWorkspacePanels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $rights = ['Users' => ['view', 'create', 'edit', 'delete'], 'Roles' => ['view', 'process'], 'HR' => ['view', 'edit'], 'Activity Logs' => ['view']], string $scope = 'Company Level'): User
    {
        $role = Role::create(['name' => 'Admin', 'code' => 'R'.uniqid(), 'status' => 'active', 'access_scope' => $scope]);
        foreach ($rights as $module => $actions) {
            foreach ($actions as $action) {
                $role->permissions()->attach(Permission::firstOrCreate(compact('module', 'action'))->id);
            }
        }
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }

    private function role(string $name): Role
    {
        return Role::create(['name' => $name, 'code' => $name, 'status' => 'active', 'access_scope' => 'Site Level']);
    }

    public function test_profile_update_preserves_additional_and_temporary_assignments(): void
    {
        $admin = $this->actor();
        $target = User::factory()->create(['status' => 'active']);
        $primary = $this->role('PRIMARY');
        $extra = $this->role('EXTRA');
        $temp = $this->role('TEMP');
        $target->roles()->attach($primary, ['is_primary' => true]);
        $target->roles()->attach($extra);
        $target->roles()->attach($temp, ['is_temporary' => true, 'access_start_date' => '2020-01-01', 'access_end_date' => '2020-01-02']);
        $before = $target->roles()->whereKey($temp->id)->first()->pivot->getAttributes();
        $this->actingAs($admin)->put(route('admin.users.update', $target), ['name' => 'Changed', 'email' => $target->email, 'status' => 'active', 'role_id' => $primary->id])->assertSessionHasNoErrors();
        $this->assertEquals(3, $target->roles()->count());
        $this->assertEquals($before, $target->roles()->whereKey($temp->id)->first()->pivot->getAttributes());
        $this->assertFalse($target->fresh()->hasEffectiveRole($temp->id));
    }

    private function target(): User
    {
        $target = User::factory()->create(['status' => 'active', 'language' => 'English']);
        $target->roles()->attach($this->role('BASE'), ['is_primary' => true]);

        return $target;
    }

    public function test_view_readonly_manage_and_lazy_panels_with_identity(): void
    {
        $actor = $this->actor();
        $target = $this->target();
        $this->actingAs($actor)->get(route('admin.users.show', $target))->assertOk()->assertSee($target->name)->assertSee($target->email)->assertDontSee('_workspace_profile')->assertDontSee('name="password"', false);
        $this->get(route('admin.users.edit', $target))->assertOk()->assertSee('data-workspace-guard-tabs', false)->assertSee('_workspace_profile');
        foreach (array_keys(UserWorkspacePanels::panels()) as $panel) {
            $response = $this->getJson(route('admin.users.workspace.panel', [$target, $panel]))->assertOk();
            $this->assertStringNotContainsString('data-related-save', $response->json('html'));
            $this->getJson(route('admin.users.workspace.panel', [$target, $panel, 'manage' => 1]))->assertOk();
        }
    }

    public function test_profile_save_actions_and_forged_role_scope_fields_are_ignored(): void
    {
        $target = $this->target();
        $this->actingAs($this->actor());
        $url = route('admin.users.update', $target);
        $data = ['_workspace_profile' => 1, 'name' => 'Updated profile', 'email' => $target->email, 'language' => 'English', 'mobile_access' => 1, 'role_id' => 999, 'project_id' => null];
        foreach (['stay' => route('admin.users.edit', $target), 'close' => route('admin.users.index'), 'new' => route('admin.users.create')] as $action => $destination) {
            $this->put($url, $data + ['_save_action' => $action])->assertSessionHasNoErrors()->assertRedirect($destination);
        }
        $this->put($url, $data + ['_return_to' => 'https://evil.example'])->assertRedirect(route('admin.users.index'));
        $this->assertFalse($target->fresh()->mobile_access);
        $this->assertSame(1, $target->roles()->count());
    }

    public function test_temporary_lifecycle_and_primary_preserve_other_assignments(): void
    {
        $target = $this->target();
        $role = $this->role('TEMP');
        $this->actingAs($this->actor());
        $url = route('admin.users.workspace.save', [$target, 'temporary']);
        $this->postJson($url, ['role_id' => $role->id, 'operation' => 'temporary', 'access_start_date' => today()->toDateString(), 'access_end_date' => today()->subDay()->toDateString()])->assertUnprocessable();
        $this->postJson($url, ['role_id' => $role->id, 'operation' => 'temporary', 'access_start_date' => today()->toDateString(), 'access_end_date' => today()->addDay()->toDateString()])->assertOk();
        $this->assertTrue($target->fresh()->hasEffectiveRole($role->id));
        $permanent = $this->role('NEW_PRIMARY');
        $this->postJson(route('admin.users.workspace.save', [$target, 'roles']), ['role_id' => $permanent->id, 'operation' => 'primary'])->assertOk();
        $this->assertSame(3, $target->roles()->count());
        $this->postJson(route('admin.users.workspace.save', [$target, 'roles']), ['role_id' => $role->id, 'operation' => 'primary'])->assertUnprocessable();
        $this->postJson($url, ['role_id' => $role->id, 'operation' => 'end'])->assertOk();
        $this->assertFalse($target->fresh()->hasEffectiveRole($role->id));
        $this->assertSame(3, $target->roles()->count());
        $this->assertDatabaseHas('activity_logs', ['description' => '[User #'.$target->id.'] role #'.$role->id.' end']);
    }

    public function test_link_uses_real_relationship_no_implicit_access_and_conflicts_fail(): void
    {
        $target = $this->target();
        $other = User::factory()->create();
        $this->actingAs($this->actor());
        $employee = Employee::create(['employee_code' => 'LINK', 'first_name' => 'Linked worker', 'status' => 'active', 'mobile_access' => true]);
        $url = route('admin.users.workspace.save', [$target, 'employee']);
        $this->postJson($url, ['operation' => 'link', 'source_employee_id' => $employee->id, 'mobile_access' => 1])->assertOk();
        $this->assertSame($target->id, $employee->fresh()->user_id);
        $this->assertSame('LINK', $target->fresh()->employee_id);
        $this->assertFalse($target->fresh()->mobile_access);
        $this->assertNull($target->fresh()->project_id);
        $this->getJson(route('admin.users.workspace.panel', [$target, 'employee']))->assertOk()->assertSee('Linked worker');
        $second = Employee::create(['employee_code' => 'SECOND', 'first_name' => 'Other worker']);
        $this->postJson($url, ['operation' => 'link', 'source_employee_id' => $second->id])->assertUnprocessable();
        $this->postJson(route('admin.users.workspace.save', [$other, 'employee']), ['operation' => 'link', 'source_employee_id' => $employee->id])->assertUnprocessable();
        $this->postJson($url, ['operation' => 'unlink', 'source_employee_id' => $employee->id])->assertOk();
        $this->assertNull($employee->fresh()->user_id);
        $this->getJson(route('admin.users.employee-search', ['q' => 'LINK', 'target_user' => $target->id]))->assertOk()->assertJsonPath('data.0.id', $employee->id)->assertJsonMissingPath('data.0.fields.project_id')->assertJsonMissingPath('data.0.fields.site_id');
        $this->postJson($url, ['operation' => 'link', 'source_employee_id' => $employee->id])->assertOk();
    }

    public function test_child_panels_and_writes_are_permission_gated(): void
    {
        $target = $this->target();
        $actor = $this->actor(['Users' => ['view', 'edit']]);
        $this->actingAs($actor);
        $page = $this->get(route('admin.users.edit', $target))->assertOk();
        foreach (['employee', 'roles', 'temporary', 'activity'] as $panel) {
            $page->assertDontSee('data-workspace-related="'.$panel.'"', false);
            $this->getJson(route('admin.users.workspace.panel', [$target, $panel]))->assertForbidden();
            $this->postJson(route('admin.users.workspace.save', [$target, $panel]), [])->assertForbidden();
        }
        $viewer = $this->actor(['Users' => ['view'], 'Roles' => ['view']]);
        $this->actingAs($viewer)->postJson(route('admin.users.workspace.save', [$target, 'roles']), ['operation' => 'primary', 'role_id' => $target->roles()->first()->id])->assertForbidden();
    }

    public function test_scope_assignment_rejects_cross_project_and_foreign_employee(): void
    {
        $a = Project::create(['code' => 'A', 'name' => 'A']);
        $b = Project::create(['code' => 'B', 'name' => 'B']);
        $site = Site::create(['code' => 'B', 'name' => 'B', 'project_id' => $b->id]);
        $warehouse = Warehouse::create(['code' => 'B', 'name' => 'B', 'project_id' => $b->id, 'site_id' => $site->id]);
        $target = $this->target();
        $target->update(['project_id' => $a->id]);
        $actor = $this->actor();
        $this->actingAs($actor);
        $url = route('admin.users.workspace.save', [$target, 'scope']);
        $this->postJson($url, ['project_id' => $a->id, 'warehouse_id' => $warehouse->id])->assertNotFound();
        $this->postJson($url, ['project_id' => $a->id, 'site_id' => $site->id])->assertNotFound();
        $restricted = $this->actor(scope: 'Project Level');
        $restricted->update(['project_id' => $a->id]);
        $this->actingAs($restricted);
        $employee = Employee::create(['employee_code' => 'SECRET', 'first_name' => 'Secret', 'project_id' => $b->id]);
        $this->postJson(route('admin.users.workspace.save', [$target, 'employee']), ['operation' => 'link', 'source_employee_id' => $employee->id])->assertForbidden();
        $this->postJson($url, ['project_id' => $b->id])->assertForbidden();
        $target->update(['project_id' => $b->id]);
        $this->getJson(route('admin.users.workspace.panel', [$target, 'scope']))->assertForbidden();
    }

    public function test_activity_visibility_mobile_and_account_status(): void
    {
        $target = $this->target();
        $target->update(['name' => "Deon D'Amore MD"]);
        $actor = $this->actor();
        $this->actingAs($actor);
        ActivityLog::create(['user_id' => $target->id, 'user_name' => 'Hidden', 'module' => 'Users', 'action' => 'PRIVATE ACTIVITY']);
        $this->getJson(route('admin.users.workspace.panel', [$target, 'activity']))->assertOk()->assertDontSee('PRIVATE ACTIVITY');
        $saved = $this->postJson(route('admin.users.workspace.save', [$target, 'mobile']), ['mobile_access' => 1])->assertOk();
        $this->assertStringContainsString(e($target->name), $saved->json('identity_html'));
        $this->assertStringContainsString('Mobile Access: Yes', strip_tags($saved->json('identity_html')));
        $this->assertTrue($target->fresh()->mobile_access);
        foreach (['inactive', 'active'] as $status) {
            $this->postJson(route('admin.users.workspace.save', [$target, 'security']), compact('status'))->assertOk();
            $this->assertSame($status, $target->fresh()->status);
        }
        $this->delete(route('admin.users.destroy', $target))->assertRedirect();
        $this->assertSame('inactive', $target->fresh()->status);
        $this->getJson(route('admin.users.workspace.panel', [$target, 'security']))->assertOk()->assertDontSee($target->password);
    }

    public function test_arabic_workspace_keys_read_only_gets_and_non_ajax_save(): void
    {
        $actor = $this->actor();
        $actor->update(['language' => 'Arabic']);
        $target = $this->target();
        $this->actingAs($actor);
        $before = $target->fresh()->getAttributes();
        $logs = ActivityLog::count();
        $this->get(route('admin.users.show', $target))->assertOk()->assertSee('dir="rtl"', false)->assertSee(__('workspace.profile', [], 'ar'));
        $this->assertEquals($before, $target->fresh()->getAttributes());
        $this->assertSame($logs, ActivityLog::count());
        $english = require base_path('lang/en/workspace.php');
        $arabic = require base_path('lang/ar/workspace.php');
        $this->assertSame(array_keys($english), array_keys($arabic));
        foreach ($arabic as $value) {
            $this->assertMatchesRegularExpression('/[\x{0600}-\x{06ff}]/u', $value);
        }
        $this->post(route('admin.users.workspace.save', [$target, 'mobile']), ['mobile_access' => 1, '_save_action' => 'stay'])->assertRedirect(route('admin.users.edit', $target).'#mobile');
        $this->post(route('admin.users.workspace.save', [$target, 'mobile']), ['mobile_access' => 0, '_save_action' => 'close'])->assertRedirect(route('admin.users.index'));
    }
}
