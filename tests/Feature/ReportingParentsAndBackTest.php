<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingParentsAndBackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
    }

    private function role(string $name): Role
    {
        return Role::create(['name' => $name, 'code' => strtoupper($name), 'level' => 4, 'access_scope' => 'Site Level', 'status' => 'active']);
    }

    private function data(Role $role, array $extra = []): array
    {
        return $extra + $role->only(['name', 'level', 'access_scope', 'status', 'parent_id']);
    }

    public function test_multiple_parents_survive_create_edit_and_are_shown_on_all_role_screens(): void
    {
        $primary = $this->role('Primary-reporting');
        $second = $this->role('Purchasing-reporting');
        $third = $this->role('Site-reporting');
        $this->postJson(route('admin.roles.store'), [
            'name' => 'Multiple-parent child', 'level' => 5, 'status' => 'active', 'access_scope' => 'Site Level',
            'parent_id' => $primary->id, 'additional_parent_ids' => [$second->id, $third->id],
        ])->assertCreated();
        $child = Role::where('name', 'Multiple-parent child')->firstOrFail();
        $this->assertEqualsCanonicalizing([$second->id, $third->id], $child->additionalParents->pluck('id')->all());
        $this->assertSame($primary->id, $child->parent_id);
        foreach ([route('admin.roles.edit', $child), route('admin.roles.show', $child), route('admin.roles.index', ['search' => $child->name]), route('admin.roles.hierarchy', ['role' => $child->id])] as $url) {
            $this->get($url)->assertOk()->assertSee($primary->name)->assertSee($second->name)->assertSee($third->name);
        }
        $this->get(route('admin.roles.edit', $child))->assertSee('name="additional_parent_ids[]"', false);
        $this->put(route('admin.roles.update', $child), $this->data($child, ['additional_parent_ids' => [$third->id]]))->assertSessionHasNoErrors();
        $this->assertSame([$third->id], $child->additionalParents()->pluck('roles.id')->all());
    }

    public function test_legacy_update_preserves_extra_links_and_explicit_empty_selection_clears_them(): void
    {
        $child = $this->role('Child');
        $parent = $this->role('Parent');
        $child->additionalParents()->attach($parent);
        $this->put(route('admin.roles.update', $child), $this->data($child))->assertSessionHasNoErrors();
        $this->assertSame(1, $child->additionalParents()->count());
        $this->put(route('admin.roles.update', $child), $this->data($child, ['additional_parent_ids' => '']))->assertSessionHasNoErrors();
        $this->assertSame(0, $child->additionalParents()->count());
    }

    public function test_duplicate_self_and_unknown_parents_are_rejected_without_saving(): void
    {
        $child = $this->role('Child');
        $parent = $this->role('Parent');
        foreach ([[$child->id], [$parent->id, $parent->id], [999999]] as $ids) {
            $this->putJson(route('admin.roles.update', $child), $this->data($child, ['additional_parent_ids' => $ids]))->assertUnprocessable();
        }
        $this->putJson(route('admin.roles.update', $child), $this->data($child, ['parent_id' => $parent->id, 'additional_parent_ids' => [$parent->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('additional_parent_ids');
        $this->assertNull($child->fresh()->parent_id);
        $this->assertSame(0, $child->additionalParents()->count());
    }

    public function test_mixed_primary_and_additional_cycles_rollback_role_and_permissions(): void
    {
        $a = $this->role('GraphA');
        $b = $this->role('GraphB');
        $c = $this->role('GraphC');
        $b->update(['parent_id' => $a->id]);
        $c->additionalParents()->attach($b);
        $permission = Permission::firstOrFail();
        $a->permissions()->attach($permission);
        foreach ([['parent_id' => $c->id], ['additional_parent_ids' => [$c->id]]] as $bad) {
            $this->putJson(route('admin.roles.update', $a), $this->data($a, $bad + ['name' => 'Should rollback', 'visible_permission_ids' => [$permission->id], 'permissions' => []]))
                ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
            $this->assertSame('GraphA', $a->fresh()->name);
            $this->assertNull($a->fresh()->parent_id);
            $this->assertSame(1, $a->permissions()->count());
        }
    }

    public function test_primary_only_cycles_and_old_cyclic_data_terminate_safely(): void
    {
        $a = $this->role('GraphA');
        $b = $this->role('GraphB');
        $b->update(['parent_id' => $a->id]);
        $this->putJson(route('admin.roles.update', $a), $this->data($a, ['parent_id' => $b->id]))->assertUnprocessable();
        // Simulate legacy corrupt data, not production mutation.
        $a->update(['parent_id' => $b->id]);
        $this->assertSame([$b->id], $a->descendantIds());
    }

    public function test_reporting_parent_cannot_be_deleted_while_used(): void
    {
        $child = $this->role('Child');
        $parent = $this->role('Parent');
        $child->additionalParents()->attach($parent);
        $this->delete(route('admin.roles.destroy', $parent))->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $parent->id]);
    }

    public function test_additional_links_do_not_expand_permissions_activity_scope_or_primary_tree(): void
    {
        $child = $this->role('Child');
        $parent = $this->role('Parent');
        $childUser = User::factory()->create();
        $parentUser = User::factory()->create();
        $childUser->roles()->attach($child, ['is_primary' => true]);
        $parentUser->roles()->attach($parent, ['is_primary' => true]);
        $parent->permissions()->attach(Permission::where('module', 'Roles')->where('action', 'edit')->firstOrFail());
        $before = $parentUser->fresh()->visibleUserIds();
        $this->put(route('admin.roles.update', $child), $this->data($child, ['additional_parent_ids' => [$parent->id]]))->assertSessionHasNoErrors();
        $this->assertSame($before, $parentUser->fresh()->visibleUserIds());
        $this->assertSame([], $parent->descendantIds());
        $this->assertFalse($childUser->fresh()->hasPermission('Roles', 'edit'));
        $this->assertSame('site', $childUser->fresh()->effectiveAccessScope());
    }

    public function test_unprivileged_role_updates_are_forbidden(): void
    {
        $child = $this->role('Child');
        $parent = $this->role('Parent');
        $this->actingAs(User::where('email', 'shaban@example.com')->firstOrFail());
        $this->putJson(route('admin.roles.update', $child), $this->data($child, ['additional_parent_ids' => [$parent->id]]))->assertForbidden();
        $this->assertSame(0, $child->additionalParents()->count());
    }

    public function test_back_controls_exist_on_employee_and_customer_supplier_related_sections(): void
    {
        $employee = Employee::firstOrFail();
        $this->get(route('admin.hr.employees.edit', $employee))->assertOk()->assertSee('data-workspace-previous', false);
        foreach (['leaves', 'shifts', 'payroll-history'] as $panel) {
            $html = $this->getJson(route('admin.hr.employees.workspace.panel', [$employee, $panel]))->assertOk()->json('html');
            $this->assertStringContainsString('data-workspace-previous', $html);
            $this->assertStringContainsString('Back / previous section', $html);
        }
        foreach (['customers' => Customer::firstOrFail(), 'suppliers' => Supplier::firstOrFail()] as $module => $entity) {
            $panel = $module === 'customers' ? 'contacts' : 'projects';
            $html = $this->getJson(route('admin.master.'.$module.'.workspace.panel', [$entity, $panel]))->assertOk()->json('html');
            $this->assertStringContainsString('data-workspace-previous', $html);
            $this->get(route('admin.master.'.$module.'.show', $entity))->assertOk()->assertDontSee('data-workspace-previous', false);
        }
    }
}
