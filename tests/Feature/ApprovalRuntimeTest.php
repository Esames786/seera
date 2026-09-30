<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\PurchaseRequestApprovalSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApprovalRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $first;

    private User $second;

    private ApprovalWorkflow $workflow;

    private PurchaseRequest $pr;

    private Project $project;

    private ApprovalRuntimeService $runtime;

    private PurchaseRequestApprovalSubject $subject;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::create(['code' => 'APR-A', 'name' => 'Approval project', 'status' => 'active']);
        $this->requester = $this->actor('Requester', ['view', 'create', 'edit', 'delete']);
        $this->first = $this->actor('Supervisor', ['view', 'approve', 'reject']);
        $this->second = $this->actor('Finance', ['view', 'approve', 'reject']);
        $unit = Unit::create(['code' => 'EA', 'name' => 'Each', 'status' => 'active']);
        $item = Item::create(['item_code' => 'APR-I', 'name' => 'Synthetic item', 'unit_id' => $unit->id, 'status' => 'active']);
        $this->pr = PurchaseRequest::create(['pr_number' => 'APR-TEST', 'request_date' => today(), 'requested_by' => $this->requester->id, 'project_id' => $this->project->id, 'status' => 'draft', 'priority' => 'normal', 'estimated_total' => 20]);
        $this->pr->forceFill(['approval_mode' => 'runtime'])->save();
        $this->pr->lines()->create(['item_id' => $item->id, 'quantity' => 2, 'estimated_unit_cost' => 10, 'estimated_total' => 20]);
        $this->workflow = ApprovalWorkflow::create(['name' => 'Synthetic two required approvals', 'module' => 'Purchase Request', 'trigger_action' => 'Request Created', 'scope' => 'All Projects', 'auto_posting' => 'No Auto Posting', 'status' => 'active']);
        foreach ([$this->first, $this->second] as $i => $actor) {
            $this->workflow->steps()->create(['step_no' => $i + 1, 'approver_role_id' => $actor->roles()->first()->id, 'is_required' => true, 'can_reject' => true]);
        }
        $this->runtime = app(ApprovalRuntimeService::class);
        $this->subject = app(PurchaseRequestApprovalSubject::class);
    }

    private function actor(string $name, array $actions, string $scope = 'Company Level', ?Role $role = null): User
    {
        $n = ++$this->sequence;
        $role ??= Role::create(['name' => $name, 'code' => 'APR_ROLE_'.$n, 'level' => 4, 'access_scope' => $scope, 'status' => 'active']);
        foreach ($actions as $action) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Purchase Requests', 'action' => $action])->id]);
        }
        $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Approval History', 'action' => 'view'])->id]);
        $actor = User::factory()->create(['name' => $name, 'status' => 'active', 'project_id' => $this->project->id]);
        $actor->roles()->attach($role, ['is_primary' => true]);

        return $actor->fresh();
    }

    private function start(?int $previous = null): ApprovalInstance
    {
        return $this->runtime->start($this->subject, $this->pr->id, $this->workflow->id, $this->requester, $previous);
    }

    private function decide(ApprovalInstance $instance, User $actor, int $step = 0, string $action = 'approve', ?string $reason = null): ApprovalInstance
    {
        return $this->runtime->decide($this->subject, $this->pr->id, $instance->id, $instance->steps[$step]->id, $actor, $action, $reason);
    }

    private function postDecision(ApprovalInstance $instance, User $actor, int $step = 0, string $action = 'approve', array $extra = [])
    {
        return $this->actingAs($actor->fresh())->postJson(route('admin.inventory.purchase-requests.'.$action, $this->pr), [
            'instance_id' => $instance->id, 'step_id' => $instance->steps[$step]->id, ...$extra,
        ]);
    }

    public function test_submission_snapshots_config_document_required_steps_and_eligible_users(): void
    {
        $instance = $this->start();
        $this->assertSame('pending', $instance->status);
        $this->assertCount(2, $instance->steps);
        $this->assertSame([$this->first->id], $instance->steps[0]->eligible_user_ids);
        $this->assertSame([$this->second->id], $instance->steps[1]->eligible_user_ids);
        $this->assertSame('APR-TEST', $instance->snapshot['subject']['reference']);
        $this->assertCount(1, $instance->snapshot['subject']['lines']);
        $this->assertFalse($this->pr->fresh()->isEditable());
        $this->workflow->steps()->delete();
        $this->workflow->delete();
        $this->assertNull($instance->fresh()->approval_workflow_id);
        $this->assertCount(2, $instance->fresh()->steps);
        $this->assertSame($this->first->roles()->first()->name, $instance->fresh()->steps[0]->snapshot['role_name']);
        $this->decide($instance, $this->first);
        $this->assertSame('approved', $this->decide($instance, $this->second, 1)->status);
    }

    public function test_sequential_all_required_and_no_accounting_posting(): void
    {
        $instance = $this->start();
        $this->postDecision($instance, $this->second, 1)->assertUnprocessable();
        $this->postDecision($instance, $this->first)->assertRedirect();
        $this->assertSame('pending', $this->pr->fresh()->status);
        $this->assertNull($this->pr->fresh()->approved_by);
        $this->assertSame(2, $instance->fresh()->currentStep()->step_no);
        $this->postDecision($instance, $this->second, 1)->assertRedirect();
        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertNotNull($instance->fresh()->completed_at);
        $this->assertSame('approved', $this->pr->fresh()->status);
        $this->assertSame($this->second->id, $this->pr->fresh()->approved_by);
        foreach (['journal_entries', 'stock_ledger_entries', 'vat_transactions', 'supplier_bills'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(['Approval requested', 'Step approved', 'Step approved', 'Fully approved'], ActivityLog::where('module', 'Approvals')->orderBy('id')->pluck('action')->all());
    }

    public function test_requester_and_submitter_are_not_allowed_to_self_approve(): void
    {
        $role = $this->first->roles()->first();
        $this->requester->roles()->attach($role);
        $instance = $this->start();
        $this->assertNotContains($this->requester->id, $instance->steps[0]->eligible_user_ids);
        $this->postDecision($instance, $this->requester)->assertForbidden();
    }

    public function test_editor_submitting_someone_elses_request_cannot_approve_it(): void
    {
        $role = $this->first->roles()->first();
        $role->permissions()->attach(Permission::firstOrCreate(['module' => 'Purchase Requests', 'action' => 'edit'])->id);
        $alternate = $this->actor('Alternate supervisor', [], role: $role);
        $instance = $this->runtime->start($this->subject, $this->pr->id, $this->workflow->id, $this->first->fresh());
        $this->assertSame([$alternate->id], $instance->steps[0]->eligible_user_ids);
        $this->postDecision($instance, $this->first)->assertForbidden();
    }

    public function test_roles_config_permission_is_not_document_approval_authority(): void
    {
        $actor = $this->actor('Configurator', ['view']);
        $actor->roles()->first()->permissions()->attach(Permission::firstOrCreate(['module' => 'Roles', 'action' => 'edit'])->id);
        $this->postDecision($this->start(), $actor)->assertForbidden();
    }

    public function test_role_and_permission_alone_do_not_allow_wrong_project_approval(): void
    {
        $role = $this->first->roles()->first();
        $role->update(['access_scope' => 'Project Level']);
        $other = Project::create(['code' => 'APR-B', 'name' => 'Other', 'status' => 'active']);
        $stranger = $this->actor('Other project supervisor', [], role: $role);
        $stranger->update(['project_id' => $other->id]);
        $instance = $this->start();
        $this->assertNotContains($stranger->id, $instance->steps[0]->eligible_user_ids);
        $this->postDecision($instance, $stranger)->assertNotFound();
        $this->actingAs($stranger->fresh())->get(route('admin.my-approvals.index'))->assertOk()->assertDontSee('APR-TEST');
        $this->postDecision($instance, $this->first)->assertRedirect();
    }

    public function test_project_scope_is_rechecked_after_submission(): void
    {
        $this->first->roles()->first()->update(['access_scope' => 'Project Level']);
        $instance = $this->start();
        $this->first->update(['project_id' => null]);
        $this->postDecision($instance, $this->first)->assertNotFound();
        $this->assertSame('pending', $instance->fresh()->steps[0]->status);
    }

    #[DataProvider('scopedAssignments')]
    public function test_site_and_warehouse_scope_is_enforced(string $scope): void
    {
        $site = Site::create(['code' => 'APR-S', 'name' => 'Approval site', 'project_id' => $this->project->id, 'status' => 'active']);
        $warehouse = Warehouse::create(['code' => 'APR-W', 'name' => 'Approval warehouse', 'project_id' => $this->project->id, 'site_id' => $site->id, 'status' => 'active']);
        $this->pr->update(['site_id' => $site->id, 'warehouse_id' => $warehouse->id]);
        $this->first->roles()->first()->update(['access_scope' => $scope]);
        $this->first->update(['site_id' => $site->id, 'warehouse_id' => $warehouse->id]);
        $instance = $this->start();
        $this->first->update([$scope === 'Site Level' ? 'site_id' : 'warehouse_id' => null]);
        $this->postDecision($instance, $this->first)->assertNotFound();
        $this->first->update(['site_id' => $site->id, 'warehouse_id' => $warehouse->id]);
        $this->postDecision($instance, $this->first)->assertRedirect();
    }

    public static function scopedAssignments(): array
    {
        return [['Site Level'], ['Warehouse Level']];
    }

    public function test_additive_migration_preserves_legacy_rows_and_grants_history_view(): void
    {
        $migration = require database_path('migrations/2026_09_30_000001_create_approval_runtime_tables.php');
        $this->pr->update(['status' => 'approved', 'approved_by' => $this->first->id, 'approved_at' => now()]);
        $before = Arr::only($this->pr->fresh()->getAttributes(), ['pr_number', 'status', 'approved_by', 'approved_at', 'estimated_total']);
        $migration->down();
        DB::table('permissions')->where('module', 'Approval History')->delete();
        $migration->up();
        $this->assertSame($before, Arr::only($this->pr->fresh()->getAttributes(), ['pr_number', 'status', 'approved_by', 'approved_at', 'estimated_total']));
        $this->assertSame('legacy', $this->pr->fresh()->approval_mode);
        $this->assertDatabaseCount('approval_instances', 0);
        $this->assertTrue($this->requester->fresh()->hasPermission('Approval History', 'view'));
    }

    #[DataProvider('revocations')]
    public function test_current_authorization_is_rechecked(string $change): void
    {
        $instance = $this->start();
        $role = $this->first->roles()->first();
        match ($change) {
            'permission' => $role->permissions()->detach(Permission::where('module', 'Purchase Requests')->where('action', 'approve')->value('id')),
            'role' => $role->update(['status' => 'inactive']),
            'user' => $this->first->update(['status' => 'inactive']),
            'assignment' => $this->first->roles()->detach(),
            'expired' => $this->first->roles()->updateExistingPivot($role->id, ['is_temporary' => true, 'access_end_date' => today()->subDay()]),
        };
        try {
            $this->decide($instance, $this->first);
            $this->fail('Revoked actor was accepted');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('pending', $instance->fresh()->steps[0]->status);
    }

    public static function revocations(): array
    {
        return array_map(fn ($v) => [$v], ['permission', 'role', 'user', 'assignment', 'expired']);
    }

    public function test_explicit_user_restricts_the_configured_role_pool(): void
    {
        $alternative = $this->actor('Other same role', [], role: $this->first->roles()->first());
        $this->workflow->steps()->first()->update(['approver_user_id' => $this->first->id]);
        $instance = $this->start();
        $this->assertSame([$this->first->id], $instance->steps[0]->eligible_user_ids);
        $this->postDecision($instance, $alternative)->assertForbidden();
    }

    public function test_role_pool_requires_one_decision_per_configured_slot_not_every_role_member(): void
    {
        $alternative = $this->actor('Other same role', [], role: $this->first->roles()->first());
        $instance = $this->start();
        $this->assertCount(2, $instance->steps[0]->eligible_user_ids);
        $this->postDecision($instance, $alternative)->assertRedirect();
        $this->postDecision($instance, $this->first)->assertUnprocessable();
        $this->assertSame('pending', $instance->fresh()->status);
        $this->postDecision($instance, $this->second, 1)->assertRedirect();
    }

    public function test_new_role_member_is_not_silently_added_to_snapshotted_attempt(): void
    {
        $instance = $this->start();
        $new = $this->actor('New member', [], role: $this->first->roles()->first());
        $this->postDecision($instance, $new)->assertForbidden();
    }

    public function test_rejection_requires_reason_stops_steps_and_preserves_audit(): void
    {
        $instance = $this->start();
        $this->postDecision($instance, $this->first, action: 'reject')->assertUnprocessable();
        $this->postDecision($instance, $this->first, action: 'reject', extra: ['rejection_reason' => '   '])->assertUnprocessable();
        $this->postDecision($instance, $this->first, action: 'reject', extra: ['rejection_reason' => 'Correct quantities'])->assertRedirect();
        $this->postDecision($instance, $this->second, 1)->assertUnprocessable();
        $this->assertSame('rejected', $instance->fresh()->status);
        $this->assertNotNull($instance->fresh()->rejected_at);
        $this->assertSame('Correct quantities', $instance->fresh()->steps[0]->comment);
        $this->assertSame($this->first->id, $instance->fresh()->steps[0]->decided_by);
        $this->assertSame('rejected', $this->pr->fresh()->status);
        $this->assertNull($this->pr->fresh()->approved_at);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_correction_and_explicit_resubmission_keep_old_attempt(): void
    {
        $old = $this->start();
        $this->decide($old, $this->first, action: 'reject', reason: 'Correct quantities');
        $snapshot = $old->fresh()->toArray();
        $this->assertTrue($this->pr->fresh()->isEditable());
        $this->actingAs($this->requester)->put(route('admin.inventory.purchase-requests.update', $this->pr), $this->payload(['reason' => 'Corrected', 'lines' => [['item_id' => Item::first()->id, 'quantity' => 4, 'estimated_unit_cost' => 10]]]))->assertSessionHasNoErrors();
        $this->actingAs($this->requester)->post(route('admin.inventory.purchase-requests.submit-approval', $this->pr), ['approval_workflow_id' => $this->workflow->id, 'previous_instance_id' => $old->id])->assertSessionHasNoErrors();
        $new = ApprovalInstance::latest('id')->first();
        $this->assertSame(2, $new->attempt);
        $this->assertSame($snapshot, $old->fresh()->toArray());
        $this->assertSame('40.00', $new->snapshot['subject']['document']['estimated_total']);
        $this->assertSame('pending', $new->status);
        $this->assertSame($new->id, $this->start($old->id)->id);
        $this->assertDatabaseCount('approval_instances', 2);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Approvals', 'action' => 'Resubmitted']);
        $this->actingAs($this->requester)->deleteJson(route('admin.inventory.purchase-requests.destroy', $this->pr))->assertSessionHasErrors();
    }

    public function test_exact_retry_is_safe_but_conflicting_decision_or_comment_is_refused(): void
    {
        $instance = $this->start();
        $this->assertSame($instance->id, $this->start()->id);
        $this->postDecision($instance, $this->first, extra: ['comment' => 'Reviewed'])->assertRedirect();
        $count = ActivityLog::count();
        $this->postDecision($instance, $this->first, extra: ['comment' => 'Reviewed'])->assertRedirect();
        $this->assertSame($count, ActivityLog::count());
        $this->postDecision($instance, $this->first, action: 'reject', extra: ['rejection_reason' => 'Changed mind'])->assertUnprocessable();
        $this->postDecision($instance, $this->first, extra: ['comment' => 'Changed comment'])->assertUnprocessable();
        $this->assertSame('Reviewed', $instance->fresh()->steps[0]->comment);
        $this->decide($instance, $this->second, 1);
        $this->decide($instance, $this->second, 1);
        $this->assertSame(1, ActivityLog::where('action', 'Fully approved')->count());
    }

    public function test_reject_retry_is_safe_and_cannot_become_approval(): void
    {
        $instance = $this->start();
        $this->decide($instance, $this->first, action: 'reject', reason: 'No');
        $this->decide($instance, $this->first, action: 'reject', reason: 'No');
        $this->assertSame(1, ActivityLog::where('action', 'Rejected')->count());
        $this->postDecision($instance, $this->first)->assertUnprocessable();
    }

    public function test_zero_comment_is_preserved_and_exact_retry_is_idempotent(): void
    {
        $instance = $this->start();
        $this->decide($instance, $this->first, reason: '0');
        $this->decide($instance, $this->first, reason: '0');
        $this->assertSame('0', $instance->fresh()->steps[0]->comment);
        $this->assertSame(1, ActivityLog::where('action', 'Step approved')->count());
    }

    public function test_failure_during_completion_rolls_back_step_document_and_audit(): void
    {
        $instance = $this->start();
        $this->decide($instance, $this->first);
        $events = ActivityLog::count();
        $brokenAdapter = new class extends PurchaseRequestApprovalSubject
        {
            public function completed(Model $document, User $actor): void
            {
                parent::completed($document, $actor);
                throw new \RuntimeException('Synthetic downstream failure');
            }
        };
        try {
            $this->runtime->decide($brokenAdapter, $this->pr->id, $instance->id, $instance->steps[1]->id, $this->second, 'approve');
            $this->fail('Completion failure was hidden');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic downstream failure', $e->getMessage());
        }
        $this->assertSame('pending', $this->pr->fresh()->status);
        $this->assertSame('pending', $instance->fresh()->status);
        $this->assertSame('pending', $instance->fresh()->steps[1]->status);
        $this->assertNull($instance->fresh()->steps[1]->decided_at);
        $this->assertSame($events, ActivityLog::count());
    }

    public function test_foreign_document_instance_cannot_be_used_on_this_endpoint(): void
    {
        $instance = $this->start();
        $other = $this->pr->replicate();
        $other->pr_number = 'APR-OTHER';
        $other->save();
        $this->actingAs($this->first)->postJson(route('admin.inventory.purchase-requests.approve', $other), [
            'instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id,
        ])->assertNotFound();
        $this->assertSame('pending', $instance->fresh()->steps[0]->status);
    }

    public function test_stale_resubmit_and_active_instance_replacement_are_refused(): void
    {
        $instance = $this->start();
        $url = route('admin.inventory.purchase-requests.submit-approval', $this->pr);
        $this->actingAs($this->requester)->postJson($url, ['approval_workflow_id' => $this->workflow->id, 'previous_instance_id' => $instance->id])->assertForbidden();
        $this->decide($instance, $this->first, action: 'reject', reason: 'Correct');
        $this->actingAs($this->requester)->postJson($url, ['approval_workflow_id' => $this->workflow->id, 'previous_instance_id' => 99999])->assertUnprocessable();
        $this->assertDatabaseCount('approval_instances', 1);
    }

    public function test_can_reject_flag_and_reject_permission_are_both_required(): void
    {
        $this->workflow->steps()->first()->update(['can_reject' => false]);
        $instance = $this->start();
        $this->postDecision($instance, $this->first, action: 'reject', extra: ['rejection_reason' => 'No'])->assertForbidden();
        $this->postDecision($instance, $this->first)->assertRedirect();
        $this->second->roles()->first()->permissions()->detach(Permission::where('module', 'Purchase Requests')->where('action', 'reject')->value('id'));
        $this->postDecision($instance, $this->second, 1, 'reject', ['rejection_reason' => 'No'])->assertForbidden();
    }

    public function test_queue_shows_only_current_actionable_tasks_and_filters(): void
    {
        $instance = $this->start();
        $url = route('admin.my-approvals.index');
        $this->actingAs($this->first)->get($url)->assertOk()->assertSee('APR-TEST')->assertSee('Review &amp; approve', false);
        $this->actingAs($this->second)->get($url)->assertOk()->assertDontSee('APR-TEST');
        $this->actingAs($this->requester)->get($url)->assertOk()->assertDontSee('APR-TEST');
        $this->actingAs($this->first)->get($url.'?project=999999')->assertOk()->assertDontSee('APR-TEST');
        $this->get($url.'?from='.today()->addDay()->toDateString())->assertOk()->assertDontSee('APR-TEST');
        $this->get($url.'?to='.today()->subDay()->toDateString())->assertOk()->assertDontSee('APR-TEST');
        $this->decide($instance, $this->first);
        $this->actingAs($this->first)->get($url)->assertDontSee('APR-TEST');
        $this->actingAs($this->second)->get($url.'?module=Purchase%20Requests&status=pending&project='.$this->project->id)->assertOk()->assertSee('APR-TEST');
        $this->decide($instance, $this->second, 1);
        $this->get($url)->assertDontSee('APR-TEST');
    }

    public function test_document_history_and_actions_obey_separate_permissions(): void
    {
        $instance = $this->start();
        $this->decide($instance, $this->first, reason: 'Visible audit comment');
        $url = route('admin.inventory.purchase-requests.show', $this->pr);
        $this->actingAs($this->requester)->get($url)->assertOk()->assertSee('Approval history')->assertSee('Visible audit comment')->assertDontSee('Approve step 2');
        $this->actingAs($this->second)->get($url)->assertOk()->assertSee('Approve step 2');
        $this->requester->roles()->first()->permissions()->detach(Permission::where('module', 'Approval History')->where('action', 'view')->value('id'));
        $this->actingAs($this->requester->fresh())->get($url)->assertOk()->assertDontSee('Visible audit comment')->assertSee('Detailed history requires');
    }

    public function test_pending_instance_blocks_edit_delete_and_unscoped_forged_decisions(): void
    {
        $instance = $this->start();
        $this->actingAs($this->requester)->get(route('admin.inventory.purchase-requests.edit', $this->pr))->assertForbidden();
        $this->put(route('admin.inventory.purchase-requests.update', $this->pr), $this->payload())->assertSessionHasErrors();
        $this->delete(route('admin.inventory.purchase-requests.destroy', $this->pr))->assertSessionHasErrors();
        $this->postDecision($instance, $this->first, extra: ['instance_id' => 999999])->assertNotFound();
        $this->postDecision($instance, $this->first, extra: ['step_id' => 999999])->assertNotFound();
        $this->assertSame('pending', $this->pr->fresh()->status);
    }

    public function test_legacy_approved_documents_get_no_fake_history_and_pending_can_explicitly_enrol(): void
    {
        $this->pr->forceFill(['approval_mode' => 'legacy', 'status' => 'approved', 'approved_by' => $this->first->id, 'approved_at' => now()])->save();
        $this->actingAs($this->first)->get(route('admin.inventory.purchase-requests.show', $this->pr))->assertOk()->assertSee('Legacy document');
        $this->assertDatabaseCount('approval_instances', 0);
        $this->actingAs($this->requester)->postJson(route('admin.inventory.purchase-requests.submit-approval', $this->pr), ['approval_workflow_id' => $this->workflow->id])->assertForbidden();
        $this->pr->update(['status' => 'pending', 'approved_at' => null, 'approved_by' => null]);
        $this->assertSame('pending', $this->start()->status);
        $this->assertSame('runtime', $this->pr->fresh()->approval_mode);
    }

    public function test_legacy_pending_keeps_existing_authorized_direct_approval(): void
    {
        $this->pr->forceFill(['approval_mode' => 'legacy'])->save();
        $this->actingAs($this->first)->post(route('admin.inventory.purchase-requests.approve', $this->pr))->assertRedirect();
        $this->assertSame('approved', $this->pr->fresh()->status);
        $this->assertDatabaseCount('approval_instances', 0);
    }

    #[DataProvider('invalidConfigurations')]
    public function test_unsupported_or_unresolvable_configuration_fails_without_partial_enrolment(string $case): void
    {
        $step = $this->workflow->steps()->first();
        match ($case) {
            'threshold' => $step->update(['amount_limit' => 100]),
            'inactive' => $this->workflow->update(['status' => 'inactive']),
            'module' => $this->workflow->update(['module' => 'Site Expenses']),
            'trigger' => $this->workflow->update(['trigger_action' => 'Expense Submitted']),
            'scope' => $this->workflow->update(['scope' => 'Branch Level']),
            'posting' => $this->workflow->update(['auto_posting' => 'Create Accounting Entry']),
            'department' => $this->workflow->update(['department_id' => Department::create(['name' => 'Other dept', 'code' => 'OTH', 'status' => 'active'])->id]),
            'no-required' => $this->workflow->steps()->update(['is_required' => false]),
            'no-actor' => $this->first->roles()->detach(),
            'self-only' => $step->update(['approver_role_id' => $this->requester->roles()->first()->id]),
            'wrong-explicit-user' => $step->update(['approver_user_id' => $this->second->id]),
            'no-lines' => $this->pr->lines()->delete(),
            'inactive-role' => $this->first->roles()->first()->update(['status' => 'inactive']),
        };
        try {
            $this->start();
            $this->fail('Configuration was silently accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('approval', $e->errors());
        }
        $this->assertDatabaseCount('approval_instances', 0);
        $this->assertDatabaseCount('approval_instance_steps', 0);
        $this->assertSame('draft', $this->pr->fresh()->status);
    }

    public static function invalidConfigurations(): array
    {
        return array_map(fn ($v) => [$v], ['threshold', 'inactive', 'module', 'trigger', 'scope', 'posting', 'department', 'no-required', 'no-actor', 'self-only', 'wrong-explicit-user', 'no-lines', 'inactive-role']);
    }

    public function test_optional_steps_are_snapshotted_but_not_required_or_actionable(): void
    {
        $this->workflow->steps()->first()->update(['is_required' => false]);
        $instance = $this->start();
        $this->assertSame('skipped', $instance->steps[0]->status);
        $this->assertSame(2, $instance->currentStep()->step_no);
        $this->assertSame('approved', $this->decide($instance, $this->second, 1)->status);
    }

    public function test_other_creator_cannot_submit_someone_elses_request_without_edit_permission(): void
    {
        $other = $this->actor('Unrelated creator', ['view', 'create']);
        $this->actingAs($other)->postJson(route('admin.inventory.purchase-requests.submit-approval', $this->pr), ['approval_workflow_id' => $this->workflow->id])->assertForbidden();
        $this->assertDatabaseCount('approval_instances', 0);
    }

    public function test_guest_cannot_access_queue_or_direct_decisions(): void
    {
        $this->get(route('admin.my-approvals.index'))->assertRedirect(route('login'));
        $this->post(route('admin.inventory.purchase-requests.approve', $this->pr))->assertRedirect(route('login'));
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['request_date' => today()->toDateString(), 'project_id' => $this->project->id, 'priority' => 'normal', 'status' => 'draft', 'lines' => [['item_id' => Item::first()->id, 'quantity' => 2, 'estimated_unit_cost' => 10]]], $changes);
    }
}
