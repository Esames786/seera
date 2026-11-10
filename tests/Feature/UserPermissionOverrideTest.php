<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UserPermissionOverrideController;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\SalesVatPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserPermissionOverrideTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($this->admin);
    }

    private function person(array $grants = [], ?Role $role = null): User
    {
        $n = ++$this->sequence;
        $role ??= Role::create(['name' => 'Personal test '.$n, 'code' => 'PERSONAL_'.$n, 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
        foreach ($grants as [$module, $action]) {
            $role->permissions()->syncWithoutDetaching([Permission::where('module', $module)->where('action', $action)->firstOrFail()->id]);
        }
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }

    private function id(string $module, string $action): int
    {
        return Permission::where('module', $module)->where('action', $action)->firstOrFail()->id;
    }

    private function save(User $target, array $settings, array $extra = [])
    {
        return $this->actingAs($this->admin->fresh())->put(route('admin.users.permission-overrides.update', $target), $extra + [
            'settings' => $settings, 'revision' => UserPermissionOverrideController::revision($target), 'reason' => 'Approved training/probation restriction',
        ]);
    }

    public function test_deny_wins_over_multiple_roles_without_changing_other_people_or_role_defaults(): void
    {
        $person = $this->person([['Customers', 'view']]);
        $shared = $person->roles()->first();
        $other = $this->person([], $shared);
        $secondRole = Role::create(['name' => 'Second grant', 'code' => 'SECOND_GRANT', 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
        $secondRole->permissions()->attach($this->id('Customers', 'view'));
        $person->roles()->attach($secondRole);
        $this->save($person, [$this->id('Customers', 'view') => 'deny'])->assertSessionHasNoErrors();
        $this->assertFalse($person->fresh()->hasPermission('Customers', 'view'));
        $this->assertTrue($other->fresh()->hasPermission('Customers', 'view'));
        $this->assertTrue($shared->permissions()->where('module', 'Customers')->where('action', 'view')->exists());
        $this->actingAs($person->fresh())->get(route('admin.master.customers.index'))->assertForbidden();
        $this->actingAs($other->fresh())->get(route('admin.master.customers.index'))->assertOk();
    }

    public function test_allow_adds_permission_but_never_widens_project_scope(): void
    {
        $mine = Project::create(['code' => 'OVERRIDE-A', 'name' => 'Mine', 'status' => 'active']);
        $theirs = Project::create(['code' => 'OVERRIDE-B', 'name' => 'Theirs', 'status' => 'active']);
        $person = $this->person();
        $person->roles()->first()->update(['access_scope' => 'Project Level']);
        $person->update(['project_id' => $mine->id]);
        $this->assertFalse($person->fresh()->hasPermission('Projects', 'view'));
        $this->save($person, [$this->id('Projects', 'view') => 'allow'])->assertSessionHasNoErrors();
        $this->assertTrue($person->fresh()->hasPermission('Projects', 'view'));
        $this->assertSame('project', $person->fresh()->effectiveAccessScope());
        $this->actingAs($person->fresh())->get(route('admin.master.projects.show', $mine))->assertOk();
        $this->get(route('admin.master.projects.show', $theirs))->assertNotFound();
    }

    public function test_inherit_clears_only_submitted_exception_and_records_reason_before_and_after(): void
    {
        $person = $this->person([['Customers', 'view']]);
        $view = $this->id('Customers', 'view');
        $create = $this->id('Customers', 'create');
        $this->save($person, [$view => 'deny', $create => 'allow'])->assertSessionHasNoErrors();
        $this->save($person, [$view => 'inherit'])->assertSessionHasNoErrors();
        $this->assertTrue($person->fresh()->hasPermission('Customers', 'view'));
        $this->assertTrue($person->fresh()->hasPermission('Customers', 'create'));
        $this->assertSame(1, $person->permissionOverrides()->count());
        $audit = ActivityLog::where('action', 'Updated individual permissions')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Approved training/probation restriction', $audit->description);
        $this->assertCount(2, json_decode($audit->old_value, true));
        $this->assertCount(1, json_decode($audit->new_value, true));
    }

    public function test_only_super_admin_can_manage_exceptions_even_if_an_ordinary_user_has_users_edit(): void
    {
        $actor = $this->person([['Users', 'view'], ['Users', 'edit']]);
        $target = $this->person();
        $this->actingAs($actor)->put(route('admin.users.permission-overrides.update', $target), ['settings' => [$this->id('Customers', 'view') => 'allow'], 'reason' => 'forged', 'revision' => UserPermissionOverrideController::revision($target)])->assertForbidden();
        $this->assertDatabaseCount('user_permission_overrides', 0);
        $this->get(route('admin.users.edit', $target))->assertOk()->assertDontSee('name="settings[', false);
    }

    public function test_missing_reason_invalid_permission_and_invalid_effect_do_not_write(): void
    {
        $person = $this->person();
        $id = $this->id('Customers', 'view');
        $this->save($person, [$id => 'allow'], ['reason' => ''])->assertSessionHasErrors('reason');
        $this->save($person, [99999999 => 'allow'])->assertSessionHasErrors('settings');
        $this->save($person, ['1 OR 1' => 'allow'])->assertSessionHasErrors('settings');
        $this->save($person, [$id => 'super_admin'])->assertSessionHasErrors('settings.'.$id);
        $this->assertDatabaseCount('user_permission_overrides', 0);
    }

    public function test_stale_workspace_cannot_overwrite_newer_exceptions(): void
    {
        $person = $this->person();
        $id = $this->id('Customers', 'view');
        $revision = UserPermissionOverrideController::revision($person);
        $this->save($person, [$id => 'allow'])->assertSessionHasNoErrors();
        $this->save($person, [$id => 'deny'], ['revision' => $revision])->assertSessionHasErrors('settings');
        $this->assertTrue($person->fresh()->hasPermission('Customers', 'view'));
        $this->assertSame(1, ActivityLog::where('action', 'Updated individual permissions')->count());
    }

    public function test_expired_or_inactive_role_cannot_be_revived_by_allow(): void
    {
        $person = $this->person();
        $role = $person->roles()->first();
        $this->save($person, [$this->id('Customers', 'view') => 'allow'])->assertSessionHasNoErrors();
        $this->assertTrue($person->fresh()->hasPermission('Customers', 'view'));
        $person->roles()->updateExistingPivot($role->id, ['is_temporary' => true, 'access_start_date' => today()->subDays(5), 'access_end_date' => today()->subDay()]);
        $this->assertFalse($person->fresh()->hasPermission('Customers', 'view'));
        $person->roles()->updateExistingPivot($role->id, ['is_temporary' => false]);
        $role->update(['status' => 'inactive']);
        $this->assertFalse($person->fresh()->hasPermission('Customers', 'view'));
    }

    public function test_permission_allow_does_not_make_super_admin_or_bypass_sales_vat_policy(): void
    {
        $person = $this->person();
        $this->save($person, [$this->id('Accounts Receivable', 'create') => 'allow', $this->id('Users', 'edit') => 'allow'])->assertSessionHasNoErrors();
        $person = $person->fresh();
        $this->assertFalse($person->isSuperAdmin());
        $request = Request::create('/', 'POST', ['vat_override_reason' => 'forged']);
        $request->setUserResolver(fn () => $person);
        $this->expectException(ValidationException::class);
        app(SalesVatPolicy::class)->authorize($request, 5);
    }

    public function test_connected_manage_shows_inherited_effective_controls_in_both_languages_and_view_is_readonly(): void
    {
        $target = $this->person([['Customers', 'view']]);
        foreach (['English', 'Arabic'] as $language) {
            $this->admin->update(['language' => $language]);
            $this->actingAs($this->admin->fresh())->get(route('admin.users.edit', $target))->assertOk()->assertSee('id="individual-permissions"', false)->assertSee('name="revision"', false)->assertSee('value="deny"', false);
            $this->get(route('admin.users.show', $target))->assertOk()->assertDontSee('name="settings[', false);
        }
    }

    public function test_permission_grants_cannot_escalate_through_existing_super_admin_assignment_paths(): void
    {
        $actor = $this->person([['Users', 'view'], ['Users', 'create'], ['Users', 'edit'], ['Roles', 'view'], ['Roles', 'process'], ['Roles', 'edit']]);
        $adminRole = $this->admin->primaryRole();
        $this->actingAs($actor)->post(route('admin.users.store'), ['name' => 'Forged administrator', 'email' => 'forged-admin@example.test', 'status' => 'active', 'role_id' => $adminRole->id])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'forged-admin@example.test']);
        $this->put(route('admin.users.update', $actor), ['name' => $actor->name, 'email' => $actor->email, 'status' => 'active', 'role_id' => $adminRole->id])->assertForbidden();
        $this->post(route('admin.users.workspace.save', [$actor, 'roles']), ['operation' => 'permanent', 'role_id' => $adminRole->id])->assertForbidden();
        $this->post(route('admin.roles.assign-users.save'), ['role_id' => $adminRole->id, 'user_ids' => [$actor->id]])->assertForbidden();
        $this->assertFalse($actor->fresh()->isSuperAdmin());
    }

    public function test_non_super_admin_cannot_take_over_super_admin_profile_or_rewrite_its_role(): void
    {
        $actor = $this->person([['Users', 'view'], ['Users', 'edit'], ['Roles', 'view'], ['Roles', 'edit']]);
        $this->actingAs($actor)->put(route('admin.users.update', $this->admin), ['_workspace_profile' => 1, 'name' => 'Takeover', 'email' => 'takeover@example.test', 'language' => 'English'])->assertForbidden();
        $this->put(route('admin.roles.update', $this->admin->primaryRole()), [])->assertForbidden();
        $this->put(route('admin.roles.permission-matrix.update'), ['role_id' => $this->admin->primaryRole()->id, 'permissions' => [], 'visible_permission_ids' => [$this->id('Users', 'edit')]])->assertForbidden();
        $this->assertNotSame('takeover@example.test', $this->admin->fresh()->email);
        $this->assertTrue($this->admin->fresh()->hasPermission('Users', 'edit'));
    }
}
