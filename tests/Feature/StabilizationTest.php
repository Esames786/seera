<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class StabilizationTest extends TestCase
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

    public function test_assign_users_to_role_persists(): void
    {
        $role = Role::where('code', 'MECHANIC')->firstOrFail();
        $newUser = User::where('email', 'waiz@example.com')->firstOrFail();
        $userIds = $role->users()->pluck('users.id')->push($newUser->id)->all();

        $this->actingAs($this->admin())
            ->post(route('admin.roles.assign-users.save'), [
                'role_id' => $role->id,
                'user_ids' => $userIds,
            ])
            ->assertRedirect(route('admin.roles.assign-users', ['role' => $role->id]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_roles', ['role_id' => $role->id, 'user_id' => $newUser->id]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Roles', 'action' => 'Updated role assignments']);
    }

    public function test_assign_users_removal_persists(): void
    {
        $role = Role::where('code', 'SITE_SUPERVISOR')->firstOrFail();
        $removed = User::where('email', 'uzaid@example.com')->firstOrFail();
        $keptIds = $role->users()->pluck('users.id')->reject(fn ($id) => $id === $removed->id)->values()->all();

        $this->actingAs($this->admin())
            ->post(route('admin.roles.assign-users.save'), [
                'role_id' => $role->id,
                'user_ids' => $keptIds,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_roles', ['role_id' => $role->id, 'user_id' => $removed->id]);
        $this->assertSame(count($keptIds), $role->users()->count());
    }

    public function test_temporary_role_access_persists(): void
    {
        $role = Role::where('code', 'MECHANIC')->firstOrFail();
        $user = User::where('email', 'kamran@example.com')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.roles.assign-users.save'), [
                'role_id' => $role->id,
                'user_ids' => [$user->id],
                'temporary_access' => 1,
                'access_start_date' => '2026-07-10',
                'access_end_date' => '2026-08-10',
                'reason' => 'Temporary project coverage for Riyadh Tower',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_roles', [
            'role_id' => $role->id,
            'user_id' => $user->id,
            'is_temporary' => 1,
            'access_start_date' => '2026-07-10',
            'access_end_date' => '2026-08-10',
            'reason' => 'Temporary project coverage for Riyadh Tower',
        ]);
    }

    public function test_temporary_access_end_date_must_follow_start_date(): void
    {
        $role = Role::where('code', 'MECHANIC')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.roles.assign-users.save'), [
                'role_id' => $role->id,
                'user_ids' => [],
                'temporary_access' => 1,
                'access_start_date' => '2026-08-10',
                'access_end_date' => '2026-07-10',
            ])
            ->assertSessionHasErrors('access_end_date');
    }

    public function test_approval_workflow_can_be_created(): void
    {
        $roles = Role::pluck('id', 'code');

        $this->actingAs($this->admin())
            ->post(route('admin.roles.approval-workflows.store'), [
                'name' => 'Inventory Transfer Approval',
                'module' => 'Inventory Transfer',
                'trigger_action' => 'Transfer Requested',
                'department_id' => Department::where('code', 'EQP')->value('id'),
                'scope' => 'All Company',
                'auto_posting' => 'No Auto Posting',
                'notify_requester' => 1,
                'lock_after_approval' => 1,
                'status' => 'active',
                'steps' => [
                    ['step_no' => 2, 'approver_role_id' => $roles['PROJECT_MANAGER'], 'is_required' => 1, 'sla_hours' => 48, 'can_reject' => 1, 'can_send_back' => 0],
                    ['step_no' => 1, 'approver_role_id' => $roles['INVENTORY_MANAGER'], 'is_required' => 1, 'amount_limit' => 10000, 'sla_hours' => 24, 'escalation_role_id' => $roles['SUPER_ADMIN'], 'can_reject' => 1, 'can_send_back' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $workflow = ApprovalWorkflow::where('name', 'Inventory Transfer Approval')->firstOrFail();
        $this->assertCount(2, $workflow->steps);
        // Steps are normalized in step_no order regardless of submission order.
        $this->assertSame($roles['INVENTORY_MANAGER'], $workflow->steps[0]->approver_role_id);
        $this->assertSame(1, $workflow->steps[0]->step_no);
        $this->assertSame($roles['PROJECT_MANAGER'], $workflow->steps[1]->approver_role_id);
        $this->assertFalse($workflow->steps[1]->can_send_back);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Workflows', 'action' => 'Created approval workflow']);
    }

    public function test_approval_workflow_can_be_updated(): void
    {
        $workflow = ApprovalWorkflow::where('name', 'Site Expense Approval')->firstOrFail();
        $roles = Role::pluck('id', 'code');

        $this->actingAs($this->admin())
            ->put(route('admin.roles.approval-workflows.update', $workflow), [
                'name' => 'Site Expense Approval v2',
                'module' => 'Site Expenses',
                'trigger_action' => 'Expense Submitted',
                'department_id' => $workflow->department_id,
                'scope' => 'Assigned Project/Site',
                'auto_posting' => 'Create Accounting Entry',
                'notify_requester' => 1,
                'lock_after_approval' => 1,
                'status' => 'active',
                'steps' => [
                    ['step_no' => 1, 'approver_role_id' => $roles['SITE_SUPERVISOR'], 'is_required' => 1, 'amount_limit' => 7500, 'sla_hours' => 24, 'can_reject' => 1, 'can_send_back' => 1],
                    ['step_no' => 2, 'approver_role_id' => $roles['FINANCE_MANAGER'], 'is_required' => 1, 'sla_hours' => 48, 'can_reject' => 1, 'can_send_back' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $workflow->refresh()->load('steps');
        $this->assertSame('Site Expense Approval v2', $workflow->name);
        $this->assertCount(2, $workflow->steps);
        $this->assertSame('7500.00', (string) $workflow->steps[0]->amount_limit);
    }

    public function test_approval_workflow_requires_at_least_one_step(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.roles.approval-workflows.store'), [
                'name' => 'Broken Workflow',
                'module' => 'Payroll',
                'scope' => 'All Company',
                'auto_posting' => 'No Auto Posting',
                'status' => 'active',
                'steps' => [],
            ])
            ->assertSessionHasErrors('steps');
    }

    public function test_approval_workflow_can_be_deleted(): void
    {
        $workflow = ApprovalWorkflow::where('name', 'Leave Request Approval')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.roles.approval-workflows.destroy', $workflow))
            ->assertRedirect(route('admin.roles.approval-workflows.index'));

        $this->assertDatabaseMissing('approval_workflows', ['id' => $workflow->id]);
        $this->assertDatabaseMissing('approval_workflow_steps', ['approval_workflow_id' => $workflow->id]);
    }

    public function test_seeded_site_expense_workflow_exists_with_three_steps(): void
    {
        $workflow = ApprovalWorkflow::where('name', 'Site Expense Approval')->with('steps.approverRole')->firstOrFail();

        $this->assertSame(
            ['Site Supervisor', 'Project Manager', 'Finance Manager'],
            $workflow->steps->pluck('approverRole.name')->all()
        );
    }

    public function test_password_reset_request_creates_token_and_sends_notification(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'admin@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'admin@example.com']);
        Notification::assertSentTo($this->admin(), ResetPassword::class);
    }

    public function test_password_reset_request_for_unknown_email_does_not_reveal_user(): void
    {
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->admin();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'new-secret-123',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_workflow_builder_screens_load(): void
    {
        $workflow = ApprovalWorkflow::firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.roles.approval-workflows.create'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.roles.approval-workflows.edit', $workflow))->assertOk();
    }
}
