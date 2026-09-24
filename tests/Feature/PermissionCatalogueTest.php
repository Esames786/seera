<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\RoleController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 24 September feedback R24-06 / R24-07: the role form and the Permission
 * Matrix share one action catalogue, a save can only revoke what it displayed,
 * and the wide matrix keeps its module column and header in view.
 */
class PermissionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function permission(string $module, string $action): Permission
    {
        return Permission::where('module', $module)->where('action', $action)->firstOrFail();
    }

    /** The role's own attributes, as the edit form would resubmit them. */
    private function rolePayload(Role $role, array $extra = []): array
    {
        return $extra + [
            'name' => $role->name, 'department_id' => $role->department_id, 'parent_id' => $role->parent_id,
            'level' => $role->level, 'access_scope' => $role->access_scope, 'default_dashboard' => $role->default_dashboard,
            'mobile_app_access' => (int) $role->mobile_app_access, 'can_approve_child_requests' => (int) $role->can_approve_child_requests,
            'description' => $role->description, 'status' => $role->status,
        ];
    }

    private function viewOnlyRolesActor(): User
    {
        $role = Role::create(['name' => 'Roles viewer', 'code' => 'ROLES_VIEWER', 'level' => 2, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where('module', 'Roles')->where('action', 'view')->orWhere(fn ($q) => $q->where('module', 'Dashboard')->where('action', 'view'))->pluck('id'));
        $user = User::create(['name' => 'Roles Viewer', 'email' => 'roles-viewer@example.test', 'username' => 'roles.viewer', 'password' => 'a-strong-password-123', 'status' => 'active']);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    // R24-06 ----------------------------------------------------------------

    public function test_both_role_screens_offer_the_same_fifteen_actions(): void
    {
        $this->assertSame(Permission::ACTIONS, RoleController::FORM_ACTIONS);
        $this->assertCount(15, Permission::ACTIONS);
        $this->assertSame(array_keys(Permission::ACTION_LABELS), Permission::ACTIONS, 'every action has a label');

        $role = Role::where('code', 'OPERATOR')->firstOrFail();

        foreach ([
            route('admin.roles.create'),
            route('admin.roles.edit', $role),
            route('admin.roles.permission-matrix', ['role' => $role->id, 'group' => 'all']),
        ] as $url) {
            $page = $this->actingAs($this->admin())->get($url)->assertOk();
            foreach (Permission::ACTIONS as $action) {
                $page->assertSee('<th>'.Permission::label($action).'</th>', false);
            }
            $page->assertSee('class="table-wrap matrix-wrap"', false);
        }
    }

    public function test_role_form_lists_every_permission_with_its_visible_marker(): void
    {
        $role = Role::where('code', 'OPERATOR')->firstOrFail();
        $html = $this->actingAs($this->admin())->get(route('admin.roles.edit', $role))->assertOk()->getContent();

        $this->assertSame(
            Permission::count(),
            substr_count($html, 'name="visible_permission_ids[]"'),
            'the form declares exactly the permissions it displays, one marker per checkbox'
        );
        // The matrix script also mentions the field name in a selector, so count the actual checkboxes.
        $this->assertSame(Permission::count(), substr_count($html, 'type="checkbox" name="permissions[]"'));
    }

    public function test_extended_actions_round_trip_through_the_role_form(): void
    {
        $receive = $this->permission('Goods Receipts', 'receive');
        $post = $this->permission('Journal Entries', 'post');
        $view = $this->permission('Dashboard', 'view');

        $this->actingAs($this->admin())->post(route('admin.roles.store'), [
            'name' => 'Stores Poster', 'department_id' => null, 'level' => 3, 'access_scope' => 'Company Level', 'status' => 'active',
            'permissions' => [$receive->id, $post->id, $view->id],
        ])->assertSessionHasNoErrors();

        $role = Role::where('name', 'Stores Poster')->firstOrFail();
        $this->assertEqualsCanonicalizing([$receive->id, $post->id, $view->id], $role->permissions()->pluck('permissions.id')->all());

        // The edit form shows them ticked, so they can be reviewed and changed here too.
        $this->actingAs($this->admin())->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('value="'.$receive->id.'" checked', false)
            ->assertSee('value="'.$post->id.'" checked', false);
    }

    public function test_a_save_can_only_revoke_what_it_displayed(): void
    {
        $role = Role::where('code', 'OPERATOR')->firstOrFail();
        $hidden = $this->permission('Users', 'view');
        $shown = $this->permission('Dashboard', 'view');
        $role->permissions()->syncWithoutDetaching([$hidden->id, $shown->id]);

        // Displayed and unticked: revoked. Not displayed: preserved.
        $this->actingAs($this->admin())->put(route('admin.roles.update', $role), $this->rolePayload($role, [
            'visible_permission_ids' => [$shown->id],
            'permissions' => [],
        ]))->assertSessionHasNoErrors();

        $this->assertFalse($role->permissions()->whereKey($shown->id)->exists(), 'a displayed, unticked permission is revoked');
        $this->assertTrue($role->permissions()->whereKey($hidden->id)->exists(), 'an undisplayed permission survives');

        // A request that declares nothing as displayed can add but never remove.
        $extra = $this->permission('Attendance', 'view');
        $this->actingAs($this->admin())->put(route('admin.roles.update', $role), $this->rolePayload($role, [
            'permissions' => [$extra->id],
        ]))->assertSessionHasNoErrors();

        $this->assertTrue($role->permissions()->whereKey($hidden->id)->exists());
        $this->assertTrue($role->permissions()->whereKey($extra->id)->exists());

        // The matrix path behaves the same when a group filter hides modules.
        $this->actingAs($this->admin())->put(route('admin.roles.permission-matrix.update'), [
            'role_id' => $role->id, 'group' => 'HR',
            'visible_permission_ids' => [$extra->id],
            'permissions' => [],
        ])->assertRedirect();

        $this->assertFalse($role->permissions()->whereKey($extra->id)->exists());
        $this->assertTrue($role->permissions()->whereKey($hidden->id)->exists());
    }

    public function test_only_role_editors_can_change_permissions(): void
    {
        $role = Role::where('code', 'OPERATOR')->firstOrFail();
        $before = $role->permissions()->pluck('permissions.id')->sort()->values()->all();
        $viewer = $this->viewOnlyRolesActor();

        $this->actingAs($viewer)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.roles.edit', $role))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.roles.update', $role), $this->rolePayload($role, [
            'visible_permission_ids' => $before, 'permissions' => [],
        ]))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.roles.permission-matrix.update'), [
            'role_id' => $role->id, 'visible_permission_ids' => $before, 'permissions' => [],
        ])->assertForbidden();

        $this->assertSame($before, $role->permissions()->pluck('permissions.id')->sort()->values()->all(), 'nothing changed');
    }
}
