<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 24 September client feedback, R24-01 and R24-02: separate View / Edit /
 * Deactivate actions on the employee list, and linked Employee <-> User
 * navigation that follows the real `employees.user_id` relationship only.
 */
class EmployeeViewEditDeactivateTest extends TestCase
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

    /** A fresh actor whose only grants are the given module => actions. */
    private function actorWith(array $grants, string $scope = 'Company Level', array $attributes = []): User
    {
        static $n = 0;
        $n++;
        $role = Role::create(['name' => "Test role $n", 'code' => "TEST_ROLE_$n", 'level' => 2, 'access_scope' => $scope, 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());

        $user = User::create($attributes + [
            'name' => "Actor $n", 'email' => "actor$n@example.test", 'username' => "actor$n",
            'password' => 'a-strong-password-123', 'status' => 'active',
        ]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    private function employee(array $extra = []): Employee
    {
        static $n = 0;
        $n++;

        return Employee::create($extra + [
            'employee_code' => "SP-VED-$n", 'first_name' => 'Viewed', 'last_name' => "Person $n",
            'email' => "viewed$n@example.test", 'status' => 'active', 'basic_salary' => 4000,
            'employee_classification' => 'Sponsorship', 'joining_date' => '2025-03-01',
        ]);
    }

    private function linkedPair(array $employeeExtra = [], array $userExtra = []): array
    {
        $user = User::create($userExtra + [
            'name' => 'Linked Account Holder', 'email' => 'linked-holder@example.test', 'username' => 'linked.holder',
            'password' => 'a-strong-password-123', 'status' => 'active',
        ]);
        $employee = $this->employee($employeeExtra + ['user_id' => $user->id]);

        return [$employee, $user];
    }

    // R24-01 ----------------------------------------------------------------

    public function test_list_offers_view_edit_and_deactivate_as_separate_actions_by_permission(): void
    {
        $employee = $this->employee();
        // The list is paginated, so pin it to this employee.
        $list = route('admin.hr.employees.index', ['search' => $employee->employee_code]);

        // Super Admin: all three, and the name goes to the read-only page, never straight into Edit.
        $page = $this->actingAs($this->admin())->get($list);
        $page->assertOk()
            ->assertSee('>View</a>', false)->assertSee('>Edit</a>', false)->assertSee('>Deactivate</button>', false)
            ->assertDontSee('>Open</a>', false)
            ->assertSee('href="'.route('admin.hr.employees.show', $employee).'" style', false)
            ->assertSee('data-deactivate="1"', false)
            ->assertSee('data-deactivate-title="Confirm Deactivation"', false);

        // View-only HR user: View only.
        $viewer = $this->actorWith(['HR' => ['view'], 'Dashboard' => ['view']]);
        $this->actingAs($viewer)->get($list)
            ->assertOk()->assertSee('>View</a>', false)
            ->assertDontSee('>Edit</a>', false)->assertDontSee('>Deactivate</button>', false)
            ->assertDontSee(route('admin.hr.employees.edit', $employee), false);

        // Editor without delete: View and Edit, no Deactivate.
        $editor = $this->actorWith(['HR' => ['view', 'edit', 'create'], 'Dashboard' => ['view']]);
        $this->actingAs($editor)->get($list)
            ->assertOk()->assertSee('>View</a>', false)->assertSee('>Edit</a>', false)
            ->assertDontSee('>Deactivate</button>', false);
    }

    public function test_view_is_read_only_and_writes_nothing_even_for_an_editor(): void
    {
        $employee = $this->employee();
        $viewer = $this->actorWith(['HR' => ['view'], 'Dashboard' => ['view']]);

        $before = [
            'employee' => Employee::withoutGlobalScopes()->find($employee->id)->toArray(),
            'logs' => ActivityLog::count(),
            'structures' => $employee->salaryStructures()->count(),
            'documents' => $employee->documents()->count(),
        ];

        // Repeated visits by a viewer and by an editor change nothing.
        foreach ([$viewer, $this->admin()] as $actor) {
            for ($i = 0; $i < 2; $i++) {
                $this->actingAs($actor)->get(route('admin.hr.employees.show', $employee))->assertOk();
            }
        }

        $this->assertSame($before['employee'], Employee::withoutGlobalScopes()->find($employee->id)->toArray(), 'viewing must not alter the record');
        $this->assertSame($before['logs'], ActivityLog::count(), 'viewing must not write activity');
        $this->assertSame($before['structures'], $employee->salaryStructures()->count());
        $this->assertSame($before['documents'], $employee->documents()->count());

        // The read-only page carries no edit controls for a viewer, and no form that could save.
        $show = $this->actingAs($viewer)->get(route('admin.hr.employees.show', $employee));
        $show->assertOk()
            ->assertDontSee(route('admin.hr.employees.edit', $employee), false)
            ->assertDontSee('Edit Employee')
            ->assertDontSee('+ Attach Document')
            ->assertDontSee(route('admin.hr.salary-structures.create', ['employee' => $employee->id]), false);

        // No form on the page posts to any HR endpoint (only the layout's logout/delete-modal shells exist).
        preg_match_all('/<form[^>]+action="([^"]*)"/', $show->getContent(), $forms);
        foreach ($forms[1] as $action) {
            $this->assertStringNotContainsString('/admin/hr/', $action, 'the view page must carry no HR data form');
        }
    }

    public function test_edit_and_deactivate_require_their_own_permissions_at_the_endpoint(): void
    {
        $employee = $this->employee();
        $viewer = $this->actorWith(['HR' => ['view'], 'Dashboard' => ['view']]);
        $editor = $this->actorWith(['HR' => ['view', 'edit', 'create'], 'Dashboard' => ['view']]);

        $payload = [
            'employee_code' => $employee->employee_code, 'first_name' => 'Renamed', 'contract_type' => 'Full Time',
            'employee_classification' => 'Sponsorship', 'basic_salary' => 4000, 'payment_method' => 'Cash', 'status' => 'active',
        ];

        // A viewer can neither open the editor nor write, and cannot deactivate.
        $this->actingAs($viewer)->get(route('admin.hr.employees.edit', $employee))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.hr.employees.update', $employee), $payload)->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.hr.employees.destroy', $employee))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.hr.employees.workspace.save', [$employee, 'attendance']), [])->assertForbidden();
        $this->assertSame('Viewed', $employee->fresh()->first_name);
        $this->assertSame('active', $employee->fresh()->status);

        // An editor may edit but not deactivate.
        $this->actingAs($editor)->get(route('admin.hr.employees.edit', $employee))->assertOk();
        $this->actingAs($editor)->delete(route('admin.hr.employees.destroy', $employee))->assertForbidden();
        $this->assertSame('active', $employee->fresh()->status);
    }

    public function test_deactivation_keeps_history_and_does_not_touch_the_linked_account(): void
    {
        [$employee, $user] = $this->linkedPair();
        $employee->ensureSalaryStructure();
        $structures = $employee->salaryStructures()->count();
        $this->assertGreaterThan(0, $structures);

        $this->actingAs($this->admin())->delete(route('admin.hr.employees.destroy', $employee))
            ->assertRedirect(route('admin.hr.employees.index'))
            ->assertSessionHas('status');

        $employee = Employee::withoutGlobalScopes()->findOrFail($employee->id);
        $this->assertSame('inactive', $employee->status, 'deactivated, not deleted');
        $this->assertSame($structures, $employee->salaryStructures()->count(), 'salary history retained');
        $this->assertSame($user->id, $employee->user_id, 'the link is kept');
        $this->assertSame('active', $user->fresh()->status, 'the linked login is not deactivated');
        $this->assertDatabaseHas('activity_logs', ['action' => 'Deactivated employee']);
    }

    public function test_existing_workspace_saves_still_function_after_the_action_change(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->admin())->postJson(route('admin.hr.employees.workspace.save', [$employee, 'attendance']), [
            'attendance_date' => today()->toDateString(), 'check_in' => '08:00', 'check_out' => '17:00',
            'late_minutes' => 0, 'overtime_minutes' => 0, 'status' => 'present', 'geofence_status' => 'unknown',
            '_save_action' => 'stay',
        ])->assertOk();

        $this->assertSame(1, $employee->attendanceRecords()->count());

        // Edit still renders the connected workspace and the shared unsaved-change dialog.
        $this->actingAs($this->admin())->get(route('admin.hr.employees.edit', $employee))
            ->assertOk()
            ->assertSee('data-employee-related="salary"', false)
            ->assertSee('id="unsaved-changes"', false);
    }

    public function test_arabic_labels_exist_for_the_three_actions(): void
    {
        $this->actingAs($this->admin())->withSession(['locale' => 'ar'])
            ->get(route('admin.hr.employees.index', ['lang' => 'ar']));

        $strings = json_decode(file_get_contents(lang_path('ar.json')), true);
        foreach (['View', 'Edit', 'Deactivate'] as $label) {
            $this->assertArrayHasKey($label, $strings, "Arabic translation for $label");
        }
        $ui = require lang_path('ar/ui.php');
        foreach (['linked_user', 'linked_employee', 'view_linked_record', 'edit_linked_record', 'link_unlinked', 'link_unavailable', 'link_inconsistent', 'confirm_deactivate', 'deactivate_help'] as $key) {
            $this->assertArrayHasKey($key, $ui, "Arabic ui.$key");
        }
    }

    // R24-02 ----------------------------------------------------------------

    public function test_linked_user_is_named_on_the_employee_pages_with_authorized_links_only(): void
    {
        [$employee, $user] = $this->linkedPair();

        // Full access: name, status and both destinations.
        foreach (['show', 'edit'] as $page) {
            $this->actingAs($this->admin())->get(route('admin.hr.employees.'.$page, $employee))
                ->assertOk()
                ->assertSee('Linked system user')
                ->assertSee('Linked Account Holder')
                ->assertSee('data-linked-view href="'.route('admin.users.show', $user).'"', false)
                ->assertSee('data-linked-edit href="'.route('admin.users.edit', $user).'"', false);
        }

        // HR viewer with Users view only: name and View, but no Edit destination.
        $viewer = $this->actorWith(['HR' => ['view'], 'Users' => ['view'], 'Dashboard' => ['view']]);
        $this->actingAs($viewer)->get(route('admin.hr.employees.show', $employee))
            ->assertOk()->assertSee('Linked Account Holder')
            ->assertSee('data-linked-view', false)->assertDontSee('data-linked-edit', false);

        // HR viewer without any Users permission: the account is not disclosed at all.
        $noUsers = $this->actorWith(['HR' => ['view'], 'Dashboard' => ['view']]);
        $this->actingAs($noUsers)->get(route('admin.hr.employees.show', $employee))
            ->assertOk()->assertDontSee('Linked Account Holder')->assertDontSee(route('admin.users.show', $user), false)
            ->assertSee('outside your permissions');

        // Unlinked employee: says so, and no name is guessed from a matching code or name.
        $lookalike = $this->employee(['first_name' => 'Linked Account', 'last_name' => 'Holder', 'employee_code' => 'LOOKALIKE-1']);
        User::create(['name' => 'Lookalike Person', 'email' => 'lookalike@example.test', 'username' => 'lookalike', 'password' => 'a-strong-password-123', 'status' => 'active', 'employee_id' => 'LOOKALIKE-1']);
        $this->actingAs($this->admin())->get(route('admin.hr.employees.show', $lookalike))
            ->assertOk()->assertSee('No linked record')->assertDontSee('Lookalike Person');
    }

    public function test_linked_employee_is_named_on_the_user_pages_and_scope_is_respected(): void
    {
        [$employee, $user] = $this->linkedPair();

        foreach (['show', 'edit'] as $page) {
            $this->actingAs($this->admin())->get(route('admin.users.'.$page, $user))
                ->assertOk()
                ->assertSee('Linked employee')
                ->assertSee($employee->name)
                ->assertSee('data-linked-view href="'.route('admin.hr.employees.show', $employee).'"', false)
                ->assertSee('data-linked-edit href="'.route('admin.hr.employees.edit', $employee).'"', false);
        }

        // Users admin with no HR permission: no employee identity or URL.
        $usersOnly = $this->actorWith(['Users' => ['view', 'edit'], 'Dashboard' => ['view']]);
        $this->actingAs($usersOnly)->get(route('admin.users.show', $user))
            ->assertOk()->assertDontSee($employee->name)->assertDontSee(route('admin.hr.employees.show', $employee), false)
            ->assertSee('outside your permissions');

        // Site-level actor: the user is in their site but the employee is not, so nothing about the employee leaks.
        [$siteA, $siteB] = Site::query()->orderBy('id')->limit(2)->get()->all();
        $this->assertNotNull($siteB, 'the demo data needs two sites');
        $user->update(['site_id' => $siteA->id]);
        $employee->update(['site_id' => $siteB->id]);
        $siteActor = $this->actorWith(['Users' => ['view', 'edit'], 'HR' => ['view', 'edit'], 'Dashboard' => ['view']], 'Site Level', ['site_id' => $siteA->id]);
        $this->actingAs($siteActor)->get(route('admin.users.show', $user))
            ->assertOk()->assertDontSee($employee->name)->assertDontSee(route('admin.hr.employees.show', $employee), false);

        // A user outside the actor's site is not reachable by URL at all.
        $user->update(['site_id' => $siteB->id]);
        $this->actingAs($siteActor)->get(route('admin.users.show', $user))->assertForbidden();
        $this->actingAs($siteActor)->get(route('admin.users.edit', $user))->assertForbidden();
    }

    public function test_inconsistent_links_are_reported_and_never_repaired_silently(): void
    {
        [$employee, $user] = $this->linkedPair();
        $second = $this->employee(['user_id' => $user->id]);

        $this->actingAs($this->admin())->get(route('admin.users.show', $user))
            ->assertOk()->assertSee('Multiple employee links require administrator review');
        $this->actingAs($this->admin())->get(route('admin.hr.employees.show', $employee))
            ->assertOk()->assertSee('Multiple employee links require administrator review');

        $this->assertSame($user->id, $employee->fresh()->user_id);
        $this->assertSame($user->id, $second->fresh()->user_id);
    }

    public function test_account_search_names_the_existing_account_for_a_linked_employee(): void
    {
        [$employee, $user] = $this->linkedPair(['first_name' => 'Searchable', 'last_name' => 'Linked']);

        $response = $this->actingAs($this->admin())->getJson(route('admin.users.employee-search', ['q' => 'Searchable Linked']));
        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('unavailable.0.employee_code', $employee->employee_code)
            ->assertJsonPath('unavailable.0.linked_user.name', 'Linked Account Holder')
            ->assertJsonPath('unavailable.0.linked_user.view_url', route('admin.users.show', $user))
            ->assertJsonPath('unavailable.0.linked_user.edit_url', route('admin.users.edit', $user));

        // Only identity and destinations travel in the payload.
        $this->assertSame(['state', 'name', 'status', 'view_url', 'edit_url'], array_keys($response->json('unavailable.0.linked_user')));
        $this->assertStringNotContainsString('basic_salary', $response->getContent());
        $this->assertStringNotContainsString('iban', $response->getContent());

        // Without Users view the reason is still explained, but no account is named or linked.
        $hrOnly = $this->actorWith(['Users' => ['create'], 'HR' => ['view', 'edit'], 'Dashboard' => ['view']]);
        $this->actingAs($hrOnly)->getJson(route('admin.users.employee-search', ['q' => 'Searchable Linked']))
            ->assertOk()
            ->assertJsonPath('unavailable.0.linked_user', null)
            ->assertDontSee('Linked Account Holder');

        // A code conflict without a real link stays a diagnostic, not an identity.
        $conflict = $this->employee(['first_name' => 'Codeonly', 'last_name' => 'Match', 'employee_code' => 'CODE-USED-1']);
        User::create(['name' => 'Code Holder', 'email' => 'code-holder@example.test', 'username' => 'code.holder', 'password' => 'a-strong-password-123', 'status' => 'active', 'employee_id' => 'CODE-USED-1']);
        $this->actingAs($this->admin())->getJson(route('admin.users.employee-search', ['q' => 'Codeonly Match']))
            ->assertOk()->assertJsonPath('unavailable.0.linked_user', null)->assertDontSee('Code Holder');
    }
}
