<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\VatPeriod;
use App\Models\Warehouse;
use App\Services\Accounting\SupplierBillPostingService;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SupplierBillApprovalSubject;
use App\Support\Workspace\ProjectWorkspacePanels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierBillApprovalRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $first;

    private User $finance;

    private Project $project;

    private Site $site;

    private Supplier $supplier;

    private ApprovalWorkflow $workflow;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::create(['code' => 'BR-P', 'name' => 'Bill project', 'status' => 'active']);
        $this->site = Site::create(['code' => 'BR-S', 'name' => 'Bill site', 'project_id' => $this->project->id, 'status' => 'active']);
        $this->supplier = Supplier::create(['code' => 'BR-SUP', 'name' => 'Training supplier', 'status' => 'active', 'allowed_payment_types' => 'Both']);
        $this->owner = $this->actor('Creator', ['view', 'create', 'edit', 'delete']);
        $this->first = $this->actor('Reviewer', ['view', 'approve', 'reject']);
        $this->finance = $this->actor('Finance', ['view', 'approve', 'reject', 'post', 'retry', 'process']);
        foreach (['1110' => 'asset', '1120' => 'asset', '1300' => 'asset', '2100' => 'liability', '2150' => 'liability', '5200' => 'expense'] as $code => $type) {
            ChartOfAccount::firstOrCreate(['account_code' => $code], ['account_name' => 'Test '.$code, 'account_type' => $type, 'normal_balance' => $type === 'liability' ? 'credit' : 'debit', 'status' => 'active']);
        }
        AutomaticPostingRule::create(['source_module' => 'Supplier Bill', 'trigger_event' => 'Bill Approved', 'auto_post' => true, 'status' => 'active']);
        $this->workflow = ApprovalWorkflow::create(['name' => 'Bill reviewers', 'module' => 'Supplier Bill', 'trigger_action' => 'Bill Submitted', 'scope' => 'All Projects', 'auto_posting' => 'Create Accounting Entry', 'status' => 'active']);
        foreach ([$this->first, $this->finance] as $i => $actor) {
            $this->workflow->steps()->create(['step_no' => $i + 1, 'approver_role_id' => $actor->roles()->first()->id, 'is_required' => true, 'can_reject' => true]);
        }
    }

    private function actor(string $name, array $actions): User
    {
        $role = Role::create(['code' => 'BR-'.++$this->seq, 'name' => $name, 'level' => 3, 'access_scope' => 'Company Level', 'status' => 'active']);
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'project_id' => $this->project->id, 'site_id' => $this->site->id]);
        $user->roles()->attach($role, ['is_primary' => true]);
        $this->grant($user, 'Accounts Payable', $actions);

        return $user->fresh();
    }

    private function grant(User $user, string $module, array $actions): void
    {
        foreach ($actions as $action) {
            $user->roles()->first()->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => $module, 'action' => $action])->id]);
        }
        $user->unsetRelation('roles');
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['supplier_id' => $this->supplier->id, 'bill_number' => 'BR-'.++$this->seq, 'bill_date' => today()->toDateString(),
            'project_id' => $this->project->id, 'site_id' => $this->site->id, 'vat_rate' => 15,
            'lines' => [['description' => 'Training service', 'quantity' => 1, 'unit_price' => 100]]];
    }

    private function bill(array $overrides = []): SupplierBill
    {
        $this->actingAs($this->owner)->post(route('admin.accounting.accounts-payable.store'), $this->payload($overrides))->assertSessionHasNoErrors()->assertRedirect();

        return SupplierBill::latest('id')->firstOrFail();
    }

    private function submit(SupplierBill $bill, ?int $previous = null): ApprovalInstance
    {
        return app(ApprovalRuntimeService::class)->start(app(SupplierBillApprovalSubject::class), $bill->id, $this->workflow->id, $this->owner, $previous);
    }

    private function decision(SupplierBill $bill, ApprovalInstance $instance, User $actor, int $step = 0, string $decision = 'approve', ?string $comment = null): ApprovalInstance
    {
        return app(ApprovalRuntimeService::class)->decide(app(SupplierBillApprovalSubject::class), $bill->id, $instance->id, $instance->steps[$step]->id, $actor, $decision, $comment);
    }

    private function approveBill(SupplierBill $bill, ?int $previous = null): ApprovalInstance
    {
        $instance = $this->submit($bill, $previous);
        $this->decision($bill, $instance, $this->first);
        $this->decision($bill, $instance, $this->finance, 1);

        return $instance->fresh('steps');
    }

    private function paymentPayload(): array
    {
        return ['payment_date' => today()->toDateString(), 'payment_account_id' => ChartOfAccount::where('account_code', '1110')->value('id'), 'amount' => 115, 'idempotency_key' => 'BR-PAY'];
    }

    public function test_new_bill_has_runtime_mode_but_no_fabricated_history_and_forged_state_is_ignored(): void
    {
        $bill = $this->bill(['approval_mode' => 'legacy', 'approval_status' => 'approved', 'requested_by' => $this->finance->id]);
        $this->assertSame('runtime', $bill->approval_mode);
        $this->assertNull($bill->approval_status);
        $this->assertEquals($this->owner->id, $bill->requested_by);
        $this->assertDatabaseCount('approval_instances', 0);
        $this->actingAs($this->finance)->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasErrors('bill');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_explicit_submit_copies_required_steps_and_preserves_draft_financial_status(): void
    {
        $bill = $this->bill();
        $this->post(route('admin.accounting.accounts-payable.submit-approval', $bill), ['workflow_id' => $this->workflow->id])->assertSessionHasNoErrors();
        $instance = $bill->approvals()->firstOrFail();
        $this->assertSame('pending', $instance->status);
        $this->assertSame('pending', $bill->fresh()->approval_status);
        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertSame([1, 2], $instance->steps->pluck('step_no')->all());
        $this->submit($bill);
        $this->assertDatabaseCount('approval_instances', 1);
    }

    public function test_missing_workflow_blocks_without_legacy_fallback(): void
    {
        $bill = $this->bill();
        $this->workflow->update(['status' => 'inactive']);
        $this->post(route('admin.accounting.accounts-payable.submit-approval', $bill))->assertSessionHasErrors('approval');
        $this->assertDatabaseCount('approval_instances', 0);
        $this->assertSame('draft', $bill->fresh()->status);
    }

    public static function invalidWorkflows(): array
    {
        return [['amount'], ['posting'], ['self_only'], ['empty_required'], ['parallel']];
    }

    #[DataProvider('invalidWorkflows')]
    public function test_unsupported_workflow_fails_closed(string $case): void
    {
        $bill = $this->bill();
        match ($case) {
            'amount' => $this->workflow->steps()->first()->update(['amount_limit' => 1]),
            'posting' => $this->workflow->update(['auto_posting' => 'No Auto Posting']),
            'self_only' => $this->workflow->steps()->first()->update(['approver_role_id' => $this->owner->roles()->first()->id]),
            'empty_required' => $this->workflow->steps()->update(['is_required' => false]),
            'parallel' => $this->workflow->steps()->update(['step_no' => 1]),
        };
        $this->post(route('admin.accounting.accounts-payable.submit-approval', $bill))->assertSessionHasErrors('approval');
        $this->assertDatabaseCount('approval_instances', 0);
    }

    public function test_later_step_self_and_unconfigured_approver_are_blocked(): void
    {
        $bill = $this->bill();
        $instance = $this->submit($bill);
        $payload = ['instance_id' => $instance->id, 'step_id' => $instance->steps[1]->id];
        $this->actingAs($this->finance)->postJson(route('admin.accounting.accounts-payable.runtime.approve', $bill), $payload)->assertUnprocessable();
        $this->grant($this->owner, 'Accounts Payable', ['approve']);
        $this->actingAs($this->owner)->postJson(route('admin.accounting.accounts-payable.runtime.approve', $bill), $payload)->assertForbidden();
        $outsider = $this->actor('Other finance', ['view', 'approve']);
        $this->actingAs($outsider)->postJson(route('admin.accounting.accounts-payable.runtime.approve', $bill), $payload)->assertForbidden();
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_all_required_steps_then_post_once_and_exact_decision_retry_safe(): void
    {
        $bill = $this->bill();
        $instance = $this->submit($bill);
        $this->decision($bill, $instance, $this->first);
        $this->assertSame('pending', $instance->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->decision($bill, $instance, $this->finance, 1);
        $this->decision($bill, $instance, $this->finance, 1);
        $this->actingAs($this->finance)->post(route('admin.accounting.accounts-payable.retry', $bill))->assertSessionHasNoErrors();
        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('vat_transactions', 1);
        $lines = $bill->fresh()->journalEntry->lines;
        $this->assertEquals(115, $lines->sum('debit'));
        $this->assertEquals(115, $lines->sum('credit'));
        $this->postJson(route('admin.accounting.accounts-payable.runtime.reject', $bill), ['instance_id' => $instance->id, 'step_id' => $instance->steps[1]->id, 'comment' => 'Conflict'])->assertUnprocessable();
    }

    public function test_rejection_reason_correction_and_new_attempt_preserve_history(): void
    {
        $bill = $this->bill();
        $instance = $this->submit($bill);
        $this->actingAs($this->first)->postJson(route('admin.accounting.accounts-payable.runtime.reject', $bill), ['instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id])->assertUnprocessable();
        $this->decision($bill, $instance, $this->first, 0, 'reject', 'Correct reference');
        $this->actingAs($this->owner)->put(route('admin.accounting.accounts-payable.update', $bill), $this->payload(['bill_number' => $bill->bill_number, 'reference_number' => 'Corrected']))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $bill->fresh()->approval_status);
        $new = $this->submit($bill, $instance->id);
        $this->assertSame(2, $new->attempt);
        $this->assertSame('rejected', $instance->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame('Correct reference', $instance->fresh('steps')->steps[0]->comment);
    }

    public function test_pending_and_approved_fields_locked_and_history_not_deletable(): void
    {
        $bill = $this->bill();
        $instance = $this->submit($bill);
        $this->get(route('admin.accounting.accounts-payable.edit', $bill))->assertForbidden();
        $this->put(route('admin.accounting.accounts-payable.update', $bill), $this->payload())->assertSessionHasErrors('bill');
        $this->delete(route('admin.accounting.accounts-payable.destroy', $bill))->assertSessionHasErrors('bill');
        $this->decision($bill, $instance, $this->first, 0, 'reject', 'Review');
        $this->delete(route('admin.accounting.accounts-payable.destroy', $bill))->assertSessionHasErrors('bill');
        $this->assertDatabaseHas('supplier_bills', ['id' => $bill->id]);
    }

    public function test_posting_failure_retains_approval_retry_is_permissioned_and_idempotent(): void
    {
        $bill = $this->bill();
        $account = ChartOfAccount::where('account_code', '2100')->firstOrFail();
        $account->update(['status' => 'inactive']);
        $instance = $this->approveBill($bill);
        $this->assertSame('approved', $instance->status);
        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertNotNull($bill->fresh()->posting_error);
        $this->actingAs($this->finance)->get(route('admin.accounting.accounts-payable.show', $bill))->assertOk()->assertSee('Retry Posting');
        $this->actingAs($this->owner)->postJson(route('admin.accounting.accounts-payable.retry', $bill))->assertForbidden();
        $account->update(['status' => 'active']);
        $this->actingAs($this->finance)->post(route('admin.accounting.accounts-payable.retry', $bill))->assertSessionHasNoErrors();
        $this->post(route('admin.accounting.accounts-payable.retry', $bill))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('vat_transactions', 1);
        $this->assertDatabaseCount('approval_instances', 1);
    }

    public function test_payment_requires_posted_journal_not_approval_or_review_journal(): void
    {
        $bill = $this->bill();
        AutomaticPostingRule::where('source_module', 'Supplier Bill')->update(['auto_post' => false]);
        $this->approveBill($bill);
        $this->assertSame('draft', $bill->fresh()->journalEntry->status);
        $this->actingAs($this->finance)->get(route('admin.accounting.accounts-payable.payment', $bill))->assertForbidden();
        $this->post(route('admin.accounting.accounts-payable.payment.store', $bill), $this->paymentPayload())->assertSessionHasErrors('payment');
        $this->grant($this->finance, 'Journal Entries', ['view', 'post', 'edit', 'delete']);
        $entry = $bill->fresh()->journalEntry;
        $this->get(route('admin.accounting.journal-entries.edit', $entry))->assertForbidden();
        $this->actingAs($this->finance->fresh())->post(route('admin.accounting.journal-entries.post', $entry))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.accounting.accounts-payable.payment.store', $bill), $this->paymentPayload())->assertSessionHasNoErrors();
        $this->post(route('admin.accounting.accounts-payable.payment.store', $bill), $this->paymentPayload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('supplier_payments', 1);
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_reopen_retains_history_and_old_approval_cannot_repost_corrected_values(): void
    {
        $bill = $this->bill();
        $old = $this->approveBill($bill);
        $admin = $this->actor('Admin', ['view', 'approve']);
        $admin->roles()->first()->update(['code' => 'SUPER_ADMIN']);
        $this->actingAs($admin->fresh())->post(route('admin.accounting.accounts-payable.reopen', $bill), ['reason' => 'Correction'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $old->fresh()->status);
        $this->assertSame('correction', $bill->fresh()->approval_status);
        app(SupplierBillPostingService::class)->attempt($bill->id, $this->finance->id);
        $this->assertNull($bill->fresh()->journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 2); // Original and opposite entry.
        $this->actingAs($this->owner)->put(route('admin.accounting.accounts-payable.update', $bill), $this->payload(['bill_number' => $bill->bill_number]))->assertSessionHasNoErrors();
        $new = $this->approveBill($bill, $old->id);
        $this->assertSame(2, $new->attempt);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->assertSame('unpaid', $bill->fresh()->status);
    }

    public function test_legacy_bill_is_not_enrolled_or_mutated_until_explicit_submit(): void
    {
        $bill = $this->bill();
        $bill->update(['approval_mode' => 'legacy', 'requested_by' => null, 'last_edited_by' => null]);
        $before = $bill->getAttributes();
        $migration = require database_path('migrations/2026_10_03_000001_add_supplier_bill_approval_state.php');
        $migration->up();
        $this->assertSame($before, $bill->fresh()->getAttributes());
        $this->assertDatabaseCount('approval_instances', 0);
        $instance = $this->submit($bill);
        $this->assertEquals($this->owner->id, $instance->requested_by);
        $this->assertSame('runtime', $bill->fresh()->approval_mode);
    }

    public function test_legacy_posted_bill_keeps_existing_accounting_and_no_fake_history(): void
    {
        $bill = $this->bill();
        $bill->update(['approval_mode' => 'legacy']);
        $this->actingAs($this->finance)->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertDatabaseCount('approval_instances', 0);
    }

    public function test_last_editor_cannot_approve_even_when_someone_else_submits(): void
    {
        $bill = $this->bill();
        $bill->update(['last_edited_by' => $this->first->id]);
        $this->post(route('admin.accounting.accounts-payable.submit-approval', $bill))->assertSessionHasErrors('approval');
        $this->assertDatabaseCount('approval_instances', 0);
    }

    public function test_queue_and_workspace_show_actionable_bill_and_context(): void
    {
        $bill = $this->bill();
        $instance = $this->submit($bill);
        $this->actingAs($this->first)->get(route('admin.my-approvals.index', ['module' => 'Accounts Payable']))->assertOk()->assertSee($bill->bill_number)->assertSee($this->supplier->name);
        $this->actingAs($this->finance)->get(route('admin.my-approvals.index'))->assertOk()->assertDontSee($bill->bill_number);
        $this->decision($bill, $instance, $this->first);
        $this->actingAs($this->finance)->get(route('admin.my-approvals.index'))->assertSee($bill->bill_number);
        $this->get(route('admin.accounting.accounts-payable.show', $bill))->assertOk()->assertSee('Pending Approval')->assertSee('Supplier Bill Approval');
        $data = ProjectWorkspacePanels::data($this->project, 'finance', $this->finance);
        $this->assertSame('Pending Approval', $data['supplierBills']->first()->approvalLabel());
    }

    public function test_out_of_scope_and_foreign_instance_routes_fail_closed(): void
    {
        $bill = $this->bill();
        $instance = $this->submit($bill);
        $foreign = $this->bill();
        $this->actingAs($this->first)->postJson(route('admin.accounting.accounts-payable.runtime.approve', $foreign), ['instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id])->assertNotFound();
        $other = Project::create(['code' => 'OTHER', 'name' => 'Other']);
        $this->first->roles()->first()->update(['access_scope' => 'Project Level']);
        $this->first->update(['project_id' => $other->id]);
        $this->actingAs($this->first->fresh())->get(route('admin.accounting.accounts-payable.show', $bill))->assertNotFound();
        $this->postJson(route('admin.accounting.accounts-payable.runtime.approve', $bill), ['instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id])->assertNotFound();
        $this->postJson(route('admin.accounting.accounts-payable.retry', $bill))->assertNotFound();
    }

    private function receipt(): GoodsReceipt
    {
        $warehouse = Warehouse::create(['code' => 'BR-W', 'name' => 'Warehouse', 'project_id' => $this->project->id, 'site_id' => $this->site->id]);
        $item = Item::create(['item_code' => 'BR-I', 'name' => 'Material']);
        $receipt = GoodsReceipt::create(['grn_number' => 'BR-GRN', 'supplier_id' => $this->supplier->id, 'warehouse_id' => $warehouse->id, 'received_date' => today(), 'status' => 'posted']);
        $receipt->lines()->create(['item_id' => $item->id, 'received_quantity' => 10, 'accepted_quantity' => 10, 'unit_cost' => 100, 'taxable_amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total_amount' => 1150]);

        return $receipt->fresh('lines');
    }

    public function test_scope_protects_every_bill_action_and_current_permissions_are_rechecked(): void
    {
        $bill = $this->bill();
        $instance = $this->submit($bill);
        $other = Project::create(['code' => 'BR-OUT', 'name' => 'Outside project']);
        $outsider = $this->actor('Out of scope', ['view', 'create', 'edit', 'approve', 'reject', 'post', 'retry', 'process']);
        $outsider->roles()->first()->update(['access_scope' => 'Project Level']);
        $outsider->update(['project_id' => $other->id, 'site_id' => null]);
        $this->actingAs($outsider->fresh());
        foreach (['submit-approval', 'runtime.approve', 'runtime.reject', 'retry', 'reopen', 'payment.store'] as $action) {
            $this->postJson(route('admin.accounting.accounts-payable.'.$action, $bill), ['workflow_id' => $this->workflow->id,
                'instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id, 'comment' => 'Blocked', 'reason' => 'Blocked'] + $this->paymentPayload())->assertNotFound();
        }
        $this->get(route('admin.accounting.accounts-payable.payment', $bill))->assertNotFound();
        $this->first->roles()->first()->permissions()->detach(Permission::where('module', 'Accounts Payable')->where('action', 'approve')->value('id'));
        $this->actingAs($this->first->fresh())->postJson(route('admin.accounting.accounts-payable.runtime.approve', $bill),
            ['instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id])->assertForbidden();
        $this->assertSame('pending', $instance->fresh()->status);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_supplier_workspace_shows_bill_approval_without_triggering_actions(): void
    {
        $bill = $this->bill();
        $this->submit($bill);
        $this->grant($this->finance, 'Suppliers', ['view']);
        $this->actingAs($this->finance->fresh())->getJson(route('admin.master.suppliers.workspace.panel', [$this->supplier, 'bills']))
            ->assertOk()->assertJsonPath('html', fn ($html) => str_contains($html, 'Pending Approval') && str_contains($html, $bill->bill_number));
        $this->assertSame('pending', $bill->fresh()->approval_status);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_workflow_activation_refuses_unsupported_bill_thresholds_and_parallel_numbers(): void
    {
        $this->grant($this->owner, 'Roles', ['view', 'create']);
        $payload = ['name' => 'Invalid Bill workflow', 'module' => 'Supplier Bill', 'trigger_action' => 'Bill Submitted',
            'scope' => 'All Projects', 'auto_posting' => 'Create Accounting Entry', 'status' => 'active',
            'steps' => [['step_no' => 1, 'approver_role_id' => $this->first->roles()->first()->id, 'is_required' => true, 'amount_limit' => 100]]];
        $this->actingAs($this->owner->fresh())->postJson(route('admin.roles.approval-workflows.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('steps');
        unset($payload['steps'][0]['amount_limit']);
        $payload['steps'][] = $payload['steps'][0];
        $this->postJson(route('admin.roles.approval-workflows.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('steps');
        $this->assertDatabaseCount('approval_workflows', 1);
    }

    public function test_pending_and_rejected_grn_reservation_blocks_competitors_without_early_consumption(): void
    {
        $receipt = $this->receipt();
        $line = $receipt->lines[0];
        $payload = ['lines' => [['description' => 'Matched', 'goods_receipt_line_id' => $line->id, 'matched_quantity' => 10, 'quantity' => 10, 'unit_price' => 100]]];
        $a = $this->bill($payload);
        $b = $this->bill($payload); // Legacy optimistic drafts can coexist before submission.
        $instance = $this->submit($a);
        $this->assertEquals(0, $line->fresh()->invoiced_quantity);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->post(route('admin.accounting.accounts-payable.submit-approval', $b))->assertSessionHasErrors('matching');
        $this->decision($a, $instance, $this->first, 0, 'reject', 'Review receipt');
        $this->actingAs($this->owner)->post(route('admin.accounting.accounts-payable.submit-approval', $b))->assertSessionHasErrors('matching');
        $this->put(route('admin.accounting.accounts-payable.update', $a), $this->payload($payload + ['bill_number' => $a->bill_number]))->assertSessionHasNoErrors();
        $this->assertNotNull($a->grnMatches()->first()->reserved_at);
        $this->approveBill($a, $instance->id);
        $this->assertEquals(10, $line->fresh()->invoiced_quantity);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_mixed_matched_direct_price_variance_uses_existing_f04_posting(): void
    {
        $line = $this->receipt()->lines[0];
        $bill = $this->bill(['lines' => [
            ['description' => 'Matched', 'goods_receipt_line_id' => $line->id, 'matched_quantity' => 2, 'quantity' => 2, 'unit_price' => 110],
            ['description' => 'Direct', 'quantity' => 1, 'unit_price' => 50],
        ]]);
        $this->approveBill($bill);
        $lines = $bill->fresh()->journalEntry->lines()->with('account')->get();
        $this->assertEquals(200, $lines->where('account.account_code', '2150')->sum('debit'));
        $this->assertEquals(70, $lines->where('account.account_code', '5200')->sum('debit'));
        $this->assertEquals(40.5, $lines->where('account.account_code', '1300')->sum('debit'));
        $this->assertEquals(310.5, $lines->where('account.account_code', '2100')->sum('credit'));
        $this->assertEquals(2, $line->fresh()->invoiced_quantity);
    }

    public function test_sealed_vat_failure_preserves_runtime_and_uncommitted_reservation(): void
    {
        $line = $this->receipt()->lines[0];
        $bill = $this->bill(['lines' => [['description' => 'Matched', 'goods_receipt_line_id' => $line->id, 'matched_quantity' => 2, 'quantity' => 2, 'unit_price' => 100]]]);
        VatPeriod::create(['period_name' => 'Sealed test', 'start_date' => today()->startOfQuarter(), 'end_date' => today()->endOfQuarter(), 'status' => 'finalized']);
        $this->approveBill($bill);
        $this->assertSame('approved', $bill->fresh()->approval_status);
        $this->assertSame('draft', $bill->fresh()->status);
        $this->assertEquals(0, $line->fresh()->invoiced_quantity);
        $this->assertNotNull($bill->grnMatches()->first()->reserved_at);
        $this->assertNull($bill->grnMatches()->first()->committed_at);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
