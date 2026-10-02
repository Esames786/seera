<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VatPeriod;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SiteExpenseApprovalSubject;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use App\Support\Workspace\ProjectWorkspacePanels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $first;

    private User $finance;

    private Project $project;

    private Site $site;

    private ExpenseCategory $category;

    private ChartOfAccount $cash;

    private ChartOfAccount $bank;

    private ChartOfAccount $cost;

    private ApprovalWorkflow $workflow;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->project = Project::create(['code' => 'SE-A', 'name' => 'Expense Project A', 'status' => 'active']);
        $this->site = Site::create(['code' => 'SE-S-A', 'name' => 'Expense Site A', 'project_id' => $this->project->id, 'status' => 'active']);
        $this->owner = $this->actor('Owner', ['view', 'create', 'edit', 'delete']);
        $this->first = $this->actor('Supervisor', ['view', 'approve', 'reject']);
        $this->finance = $this->actor('Finance', ['view', 'create', 'edit', 'approve', 'reject', 'post', 'retry']);
        $this->cash = $this->account('1110', 'Cash', 'asset');
        $this->bank = $this->account('1120', 'Bank', 'asset');
        $this->cost = $this->account('5200', 'Site consumables', 'expense');
        $this->account('1300', 'Input VAT', 'asset');
        $this->account('2100', 'Accounts Payable', 'liability');
        $this->category = ExpenseCategory::create(['name' => 'Fuel', 'code' => 'SE-FUEL', 'chart_of_account_id' => $this->cost->id,
            'payment_type' => 'Both', 'vat_treatment' => 'VAT 15%', 'invoice_photo_required' => false, 'mobile_visible' => true, 'status' => 'active']);
        $this->workflow = ApprovalWorkflow::create(['name' => 'Site expense reviewers', 'module' => 'Site Expenses', 'trigger_action' => 'Expense Submitted',
            'scope' => 'All Projects', 'auto_posting' => 'Create Accounting Entry', 'status' => 'active']);
        foreach ([$this->first, $this->finance] as $i => $actor) {
            $this->workflow->steps()->create(['step_no' => $i + 1, 'approver_role_id' => $actor->roles()->first()->id, 'is_required' => true, 'can_reject' => true]);
        }
        foreach (['Site Expense' => 'Site Expense Approved', 'Supplier Bill' => 'Bill Approved'] as $module => $event) {
            AutomaticPostingRule::create(['source_module' => $module, 'trigger_event' => $event, 'auto_post' => true, 'status' => 'active']);
        }
    }

    private function actor(string $name, array $actions, string $scope = 'Company Level'): User
    {
        $role = Role::create(['name' => $name, 'code' => 'SE_ROLE_'.++$this->sequence, 'level' => 4, 'access_scope' => $scope, 'status' => 'active']);
        foreach ($actions as $action) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Site Expenses', 'action' => $action])->id]);
        }
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'project_id' => $this->project->id, 'site_id' => $this->site->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    private function grant(User $user, string $module, array $actions): void
    {
        foreach ($actions as $action) {
            $user->roles()->first()->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => $module, 'action' => $action])->id]);
        }
        $user->unsetRelation('roles');
    }

    private function account(string $code, string $name, string $type): ChartOfAccount
    {
        return ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $type === 'liability' ? 'credit' : 'debit', 'status' => 'active']);
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['expense_date' => today()->toDateString(), 'project_id' => $this->project->id, 'site_id' => $this->site->id,
            'expense_category_id' => $this->category->id, 'payment_type' => 'Cash', 'payment_account_id' => $this->cash->id,
            'taxable_amount' => '1000.00', 'vat_applicable' => 1, 'description' => 'Synthetic fuel receipt', '_save_action' => 'stay'];
    }

    private function draft(array $extra = []): SiteExpense
    {
        $this->actingAs($this->owner)->post(route('admin.site-expenses.store'), $this->payload($extra))->assertSessionHasNoErrors()->assertRedirect();

        return SiteExpense::latest('id')->firstOrFail();
    }

    private function start(SiteExpense $expense, ?int $previous = null): ApprovalInstance
    {
        return app(ApprovalRuntimeService::class)->start(app(SiteExpenseApprovalSubject::class), $expense->id, $this->workflow->id, $this->owner, $previous);
    }

    private function decide(SiteExpense $expense, ApprovalInstance $instance, User $actor, int $index = 0, string $action = 'approve', ?string $comment = null): ApprovalInstance
    {
        return app(ApprovalRuntimeService::class)->decide(app(SiteExpenseApprovalSubject::class), $expense->id, $instance->id, $instance->steps[$index]->id, $actor, $action, $comment);
    }

    private function approved(SiteExpense $expense): ApprovalInstance
    {
        $instance = $this->start($expense);
        $this->decide($expense, $instance, $this->first);

        return $this->decide($expense, $instance, $this->finance, 1);
    }

    public function test_draft_number_amounts_and_mass_assignment_protection(): void
    {
        $a = $this->draft(['status' => 'posted', 'accounting_posted' => 1, 'vat_amount' => 999, 'total_amount' => 1, 'submitted_by_user_id' => $this->finance->id]);
        $b = $this->draft();
        $this->assertNotSame($a->expense_number, $b->expense_number);
        $this->assertMatchesRegularExpression('/^SE-\d{4}-\d{6}$/', $a->expense_number);
        $this->assertSame('draft', $a->status);
        $this->assertSame($this->owner->id, $a->submitted_by_user_id);
        $this->assertSame('150.00', $a->vat_amount);
        $this->assertSame('1150.00', $a->total_amount);
        $this->assertFalse($a->accounting_posted);
    }

    public function test_mobile_entry_requires_flag_and_create_permission(): void
    {
        $this->actingAs($this->owner)->get(route('admin.site-expenses.mobile'))->assertForbidden();
        $this->owner->update(['mobile_access' => true]);
        $this->actingAs($this->owner->fresh())->get(route('admin.site-expenses.mobile'))->assertOk()->assertSee('Receipt / invoice photo');
        $this->category->update(['mobile_visible' => false]);
        foreach (['admin.site-expenses.mobile', 'admin.site-expenses.create'] as $route) {
            $this->get(route($route))->assertOk()->assertViewHas('categories', fn ($categories) => ! $categories->contains('id', $this->category->id));
        }
        $this->postJson(route('admin.site-expenses.store'), $this->payload())->assertForbidden();
        $this->first->update(['mobile_access' => true]);
        $this->actingAs($this->first->fresh())->get(route('admin.site-expenses.mobile'))->assertForbidden();
    }

    public function test_private_receipt_metadata_stream_and_forged_id(): void
    {
        $expense = $this->draft(['receipt' => UploadedFile::fake()->image('receipt.jpg')]);
        $file = $expense->receipts()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame($this->owner->id, $file->uploaded_by);
        $this->assertArrayNotHasKey('path', $file->toArray());
        $this->get(route('admin.site-expenses.receipt-file', [$expense, $file]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = $this->draft();
        $this->get(route('admin.site-expenses.receipt-file', [$other, $file]))->assertNotFound();
    }

    public function test_upload_rejects_dangerous_extension_and_oversize(): void
    {
        $this->actingAs($this->owner)->postJson(route('admin.site-expenses.store'), $this->payload(['receipt' => UploadedFile::fake()->create('attack.html', 20, 'text/html')]))->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->postJson(route('admin.site-expenses.store'), $this->payload(['receipt' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')]))->assertUnprocessable();
        $this->assertDatabaseCount('site_expenses', 0);
    }

    public function test_scoped_user_cannot_create_or_view_other_project_or_receipt(): void
    {
        $other = Project::create(['code' => 'SE-B', 'name' => 'Expense Project B']);
        $otherSite = Site::create(['code' => 'SE-S-B', 'name' => 'Expense Site B', 'project_id' => $other->id]);
        $expense = $this->draft(['project_id' => $other->id, 'site_id' => $otherSite->id, 'receipt' => UploadedFile::fake()->image('b.png')]);
        $scoped = $this->actor('Site staff', ['view', 'create', 'edit'], 'Site Level');
        $this->actingAs($scoped)->get(route('admin.site-expenses.show', $expense))->assertNotFound();
        $this->get(route('admin.site-expenses.receipt-file', [$expense, $expense->receipts()->first()->id]))->assertNotFound();
        $this->postJson(route('admin.site-expenses.store'), $this->payload(['project_id' => $other->id, 'site_id' => $otherSite->id]))->assertForbidden();
        $this->get(route('admin.site-expenses.index'))->assertOk()->assertDontSee($expense->expense_number);
    }

    public function test_site_must_belong_to_project_even_for_company_user(): void
    {
        $other = Project::create(['code' => 'B', 'name' => 'B']);
        $this->actingAs($this->owner)->postJson(route('admin.site-expenses.store'), $this->payload(['project_id' => $other->id]))->assertNotFound();
    }

    public function test_draft_edit_then_pending_freezes_and_cancel_retains_record(): void
    {
        $expense = $this->draft();
        $this->put(route('admin.site-expenses.update', $expense), $this->payload(['description' => 'Corrected']))->assertSessionHasNoErrors();
        $this->assertSame('Corrected', $expense->fresh()->description);
        $this->start($expense);
        $this->put(route('admin.site-expenses.update', $expense), $this->payload())->assertForbidden();
        $this->delete(route('admin.site-expenses.destroy', $expense))->assertForbidden();
        $draft = $this->draft();
        $this->delete(route('admin.site-expenses.destroy', $draft))->assertRedirect();
        $this->assertSame('cancelled', $draft->fresh()->status);
    }

    public function test_no_workflow_preserves_saved_draft(): void
    {
        $this->workflow->update(['status' => 'inactive']);
        $this->actingAs($this->owner)->post(route('admin.site-expenses.store'), $this->payload(['_intent' => 'submit']))->assertRedirect()->assertSessionHasErrors('approval');
        $this->assertDatabaseHas('site_expenses', ['status' => 'draft']);
        $this->assertDatabaseCount('approval_instances', 0);
    }

    public function test_receipt_required_blocks_submission_not_draft(): void
    {
        $this->category->update(['invoice_photo_required' => true]);
        $expense = $this->draft();
        $this->post(route('admin.site-expenses.submit', $expense), ['workflow_id' => $this->workflow->id])->assertSessionHasErrors('receipt');
        $this->assertSame('draft', $expense->fresh()->status);
    }

    public function test_self_approval_and_out_of_order_decision_blocked_all_steps_required(): void
    {
        $expense = $this->draft();
        $instance = $this->start($expense);
        $payload = ['instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id];
        $this->postJson(route('admin.site-expenses.approve', $expense), $payload)->assertForbidden();
        $this->actingAs($this->finance)->postJson(route('admin.site-expenses.approve', $expense), ['instance_id' => $instance->id, 'step_id' => $instance->steps[1]->id])->assertUnprocessable();
        $this->decide($expense, $instance, $this->first);
        $this->assertSame('pending', $expense->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->decide($expense, $instance, $this->finance, 1);
        $this->assertSame('posted', $expense->fresh()->status);
    }

    public function test_rejection_reason_resubmission_and_original_history(): void
    {
        $expense = $this->draft();
        $instance = $this->start($expense);
        $payload = ['instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id];
        $this->actingAs($this->first)->postJson(route('admin.site-expenses.reject', $expense), $payload)->assertUnprocessable();
        $this->decide($expense, $instance, $this->first, 0, 'reject', 'Receipt needs explanation');
        $this->assertSame('rejected', $expense->fresh()->status);
        $this->actingAs($this->owner)->put(route('admin.site-expenses.update', $expense), $this->payload(['description' => 'Explanation']))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $expense->fresh()->status);
        $new = $this->start($expense, $instance->id);
        $this->assertSame(2, $new->attempt);
        $this->assertSame('rejected', $instance->fresh()->status);
        $this->assertDatabaseCount('approval_instances', 2);
    }

    #[DataProvider('cashBank')]
    public function test_cash_bank_vat_postings_and_idempotency(string $type, bool $vat): void
    {
        $expense = $this->draft(['payment_type' => $type, 'payment_account_id' => $type === 'Cash' ? $this->cash->id : $this->bank->id, 'vat_applicable' => $vat ? 1 : 0]);
        $instance = $this->approved($expense);
        $this->decide($expense, $instance, $this->finance, 1);
        app(SiteExpenseAccountingService::class)->attempt($expense->id, $this->finance->id);
        $expense->refresh();
        $this->assertTrue($expense->accounting_posted);
        $entry = $expense->journalEntry;
        $this->assertSame($vat ? '1150.00' : '1000.00', $entry->total_debit);
        $this->assertSame($entry->total_debit, $entry->total_credit);
        $this->assertEquals(1000, $entry->lines()->where('chart_of_account_id', $this->cost->id)->sum('debit'));
        $this->assertEquals($vat ? 1150 : 1000, $entry->lines()->where('chart_of_account_id', $type === 'Cash' ? $this->cash->id : $this->bank->id)->sum('credit'));
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('vat_transactions', $vat ? 1 : 0);
        $this->assertEquals(1000, ProjectWorkspacePanels::finance($this->project)['cost']);
    }

    public static function cashBank(): array
    {
        return [['Cash', true], ['Cash', false], ['Bank', true], ['Bank', false]];
    }

    public function test_staff_cannot_override_vat_and_non_vat_category_is_not_taxed(): void
    {
        $expense = $this->draft(['vat_rate' => 99, 'vat_amount' => 1]);
        $this->assertSame('15.00', $expense->vat_rate);
        $this->category->update(['vat_treatment' => 'Non-VAT']);
        $expense = $this->draft(['vat_applicable' => 1]);
        $this->assertSame('0.00', $expense->vat_amount);
    }

    public function test_reimbursement_credits_payable_not_cash_or_bank(): void
    {
        $employee = Employee::create(['employee_code' => 'SE-EMP', 'first_name' => 'Linked', 'last_name' => 'Employee', 'user_id' => $this->owner->id, 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'status' => 'active']);
        $expense = $this->draft(['payment_type' => 'Employee Reimbursement']);
        $this->approved($expense);
        $expense->refresh();
        $this->assertSame($employee->id, $expense->employee_id);
        $this->assertTrue($expense->accounting_posted);
        $this->assertEquals(1150, $expense->journalEntry->lines()->where('chart_of_account_id', ChartOfAccount::where('account_code', '2310')->value('id'))->sum('credit'));
        $this->assertSame(0, $expense->journalEntry->lines()->whereIn('chart_of_account_id', [$this->cash->id, $this->bank->id])->count());
    }

    public function test_reimbursement_requires_linked_employee(): void
    {
        $this->actingAs($this->owner)->postJson(route('admin.site-expenses.store'), $this->payload(['payment_type' => 'Employee Reimbursement']))->assertUnprocessable()->assertJsonValidationErrors('payment_type');
    }

    public function test_credit_creates_one_draft_bill_no_journal_and_finance_posts_once(): void
    {
        $supplier = Supplier::create(['code' => 'SE-SUP', 'name' => 'Synthetic supplier', 'status' => 'active']);
        $expense = $this->draft(['payment_type' => 'Supplier Credit', 'supplier_id' => $supplier->id]);
        $instance = $this->approved($expense);
        app(SiteExpenseAccountingService::class)->attempt($expense->id, $this->finance->id);
        $this->decide($expense, $instance, $this->finance, 1);
        $expense->refresh();
        $bill = $expense->supplierBill;
        $this->assertNotNull($bill);
        $this->assertSame('draft', $bill->status);
        $this->assertSame($expense->id, $bill->site_expense_id);
        $this->assertSame('approved_pending_posting', $expense->status);
        $this->assertDatabaseCount('supplier_bills', 1);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertEquals(0, ProjectWorkspacePanels::finance($this->project)['cost']);
        $this->grant($this->finance, 'Accounts Payable', ['view', 'approve', 'edit', 'delete']);
        $this->actingAs($this->finance->fresh())->post(route('admin.accounting.accounts-payable.approve', $bill))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($expense->fresh()->accounting_posted);
        $this->assertNull($expense->fresh()->journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(1000, ProjectWorkspacePanels::finance($this->project)['cost']);
        $this->delete(route('admin.accounting.accounts-payable.destroy', $bill))->assertForbidden();
        $this->put(route('admin.accounting.accounts-payable.update', $bill), [])->assertForbidden();
    }

    public function test_mapping_failure_preserves_approval_then_retry_posts(): void
    {
        $this->category->update(['chart_of_account_id' => null]);
        $expense = $this->draft();
        $instance = $this->approved($expense);
        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame('approved_pending_posting', $expense->fresh()->status);
        $this->assertStringContainsString('no active expense accounting account', $expense->fresh()->posting_error);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->category->update(['chart_of_account_id' => $this->cost->id]);
        app(SiteExpenseAccountingService::class)->attempt($expense->id, $this->finance->id);
        $this->assertTrue($expense->fresh()->accounting_posted);
        $this->assertNull($expense->fresh()->posting_error);
    }

    public function test_required_cost_center_missing_does_not_undo_approval(): void
    {
        $this->cost->update(['cost_center_required' => true]);
        $expense = $this->draft();
        $instance = $this->approved($expense);
        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame('approved_pending_posting', $expense->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_review_mode_stays_pending_until_journal_post_and_retry_does_not_duplicate(): void
    {
        AutomaticPostingRule::where('source_module', 'Site Expense')->update(['auto_post' => false]);
        $expense = $this->draft();
        $this->approved($expense);
        $expense->refresh();
        $this->assertSame('draft', $expense->journalEntry->status);
        $this->assertFalse($expense->accounting_posted);
        $this->assertEquals(0, ProjectWorkspacePanels::finance($this->project)['cost']);
        app(SiteExpenseAccountingService::class)->attempt($expense->id, $this->finance->id);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->grant($this->finance, 'Journal Entries', ['view', 'edit', 'delete', 'post']);
        $this->actingAs($this->finance->fresh())->put(route('admin.accounting.journal-entries.update', $expense->journalEntry), [])->assertForbidden();
        $this->post(route('admin.accounting.journal-entries.post', $expense->journalEntry))->assertSessionHasNoErrors();
        $this->assertTrue($expense->fresh()->accounting_posted);
        $this->assertEquals(1000, ProjectWorkspacePanels::finance($this->project)['cost']);
    }

    public function test_view_has_no_side_effects_and_queue_contains_only_current_step(): void
    {
        $expense = $this->draft();
        $instance = $this->start($expense);
        $counts = [ActivityLog::count(), JournalEntry::count(), ApprovalInstance::count()];
        $this->actingAs($this->first)->get(route('admin.site-expenses.show', $expense))->assertOk()->assertSee('Approval history');
        $this->get(route('admin.my-approvals.index'))->assertOk()->assertSee($expense->expense_number);
        $this->actingAs($this->finance)->get(route('admin.my-approvals.index'))->assertOk()->assertDontSee($expense->expense_number);
        $this->assertSame($counts, [ActivityLog::count(), JournalEntry::count(), ApprovalInstance::count()]);
    }

    public function test_project_panel_scope_and_financial_summary_permissions(): void
    {
        $expense = $this->draft();
        $this->approved($expense);
        $this->grant($this->owner, 'Projects', ['view']);
        $this->actingAs($this->owner->fresh())->get(route('admin.master.projects.workspace.panel', [$this->project, 'site-expenses']))->assertOk()->assertSee($expense->expense_number)->assertSee('Restricted');
        $summary = ProjectWorkspacePanels::summary($this->project, $this->owner->fresh());
        $this->assertSame(1, $summary['Posted site expenses']);
        $this->assertArrayNotHasKey('Posted site expenses (SAR)', $summary);
        $this->grant($this->owner, 'Financial Reports', ['view']);
        $summary = ProjectWorkspacePanels::summary($this->project, $this->owner->fresh());
        $this->assertSame('1,150.00', $summary['Posted site expenses (SAR)']);
    }

    public function test_retry_and_create_require_write_permissions(): void
    {
        $expense = $this->draft();
        $this->actingAs($this->first)->postJson(route('admin.site-expenses.retry', $expense))->assertForbidden();
        $this->postJson(route('admin.site-expenses.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('site_expenses', 1);
    }

    public function test_threshold_and_no_auto_post_workflows_are_rejected_without_mutation(): void
    {
        $expense = $this->draft();
        $this->workflow->steps()->first()->update(['amount_limit' => 100]);
        $this->postJson(route('admin.site-expenses.submit', $expense), ['workflow_id' => $this->workflow->id])->assertUnprocessable();
        $this->workflow->steps()->update(['amount_limit' => null]);
        $this->workflow->update(['auto_posting' => 'No Auto Posting']);
        $this->postJson(route('admin.site-expenses.submit', $expense), ['workflow_id' => $this->workflow->id])->assertUnprocessable();
        $this->assertSame('draft', $expense->fresh()->status);
        $this->assertDatabaseCount('approval_instances', 0);
    }

    public function test_full_reimbursement_is_once_only_and_does_not_add_project_cost(): void
    {
        Employee::create(['employee_code' => 'SE-EMP', 'first_name' => 'Ahmed', 'user_id' => $this->owner->id, 'status' => 'active']);
        $expense = $this->draft(['payment_type' => 'Employee Reimbursement']);
        $this->approved($expense);
        $this->grant($this->finance, 'Site Expenses', ['process']);
        $this->actingAs($this->finance->fresh())->post(route('admin.site-expenses.settle', $expense), ['payment_account_id' => $this->bank->id])->assertSessionHasNoErrors();
        $this->post(route('admin.site-expenses.settle', $expense), ['payment_account_id' => $this->bank->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('journal_entries', 2);
        $settlement = $expense->fresh()->settlementJournal;
        $this->assertSame('posted', $settlement->status);
        $this->assertEquals(1150, $settlement->lines()->where('chart_of_account_id', $this->bank->id)->sum('credit'));
        $this->assertEquals(1000, ProjectWorkspacePanels::finance($this->project)['cost']);
        $this->postJson(route('admin.site-expenses.settle', $expense), ['payment_account_id' => $this->cash->id])->assertUnprocessable();
        $this->postJson(route('admin.site-expenses.reverse', $expense), ['reason' => 'Incorrect'])->assertUnprocessable();
    }

    public function test_reversal_is_reasoned_immutable_and_nets_project_cost(): void
    {
        $expense = $this->draft();
        $this->approved($expense);
        $original = $expense->fresh()->journalEntry->getAttributes();
        $this->grant($this->finance, 'Site Expenses', ['process']);
        $this->actingAs($this->finance->fresh())->postJson(route('admin.site-expenses.reverse', $expense), ['reason' => '  '])->assertUnprocessable();
        $this->post(route('admin.site-expenses.reverse', $expense), ['reason' => 'Duplicate receipt'])->assertSessionHasNoErrors();
        $this->post(route('admin.site-expenses.reverse', $expense), ['reason' => 'Duplicate receipt'])->assertSessionHasNoErrors();
        $expense->refresh();
        $this->assertSame('reversed', $expense->status);
        $this->assertSame($original, $expense->journalEntry->getAttributes());
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('vat_transactions', 0);
        $this->assertEquals(0, ProjectWorkspacePanels::finance($this->project)['cost']);
        $this->assertNotNull($expense->reversal_journal_id);
    }

    public function test_sealed_vat_period_blocks_posting_but_not_approval(): void
    {
        VatPeriod::create(['period_name' => 'Locked', 'start_date' => today()->startOfMonth(), 'end_date' => today()->endOfMonth(), 'status' => 'finalized']);
        $expense = $this->draft();
        $instance = $this->approved($expense);
        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame('approved_pending_posting', $expense->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('vat_transactions', 0);
    }

    public function test_posted_expense_cannot_be_deleted_or_edited(): void
    {
        $expense = $this->draft();
        $this->approved($expense);
        $this->actingAs($this->owner)->putJson(route('admin.site-expenses.update', $expense), $this->payload())->assertForbidden();
        $this->delete(route('admin.site-expenses.destroy', $expense))->assertForbidden();
        $this->assertDatabaseCount('site_expenses', 1);
    }

    public function test_foreign_approval_instance_is_refused(): void
    {
        $expense = $this->draft();
        $this->start($expense);
        $other = $this->draft();
        $otherInstance = $this->start($other);
        $this->actingAs($this->first)->postJson(route('admin.site-expenses.approve', $expense), ['instance_id' => $otherInstance->id, 'step_id' => $otherInstance->steps[0]->id])->assertNotFound();
        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_site_scoped_approver_cannot_approve_other_site_using_shared_role(): void
    {
        $other = Site::create(['code' => 'OTHER', 'name' => 'Other site', 'project_id' => $this->project->id]);
        $expense = $this->draft(['site_id' => $other->id]);
        $instance = $this->start($expense);
        $this->first->roles()->first()->update(['access_scope' => 'Site Level']);
        $this->actingAs($this->first->fresh())->postJson(route('admin.site-expenses.approve', $expense), ['instance_id' => $instance->id, 'step_id' => $instance->steps[0]->id])->assertNotFound();
    }

    public function test_inactive_category_and_mismatched_payment_account_refused(): void
    {
        $this->actingAs($this->owner)->postJson(route('admin.site-expenses.store'), $this->payload(['payment_account_id' => $this->bank->id]))->assertUnprocessable();
        $this->category->update(['status' => 'inactive']);
        $this->postJson(route('admin.site-expenses.store'), $this->payload())->assertNotFound();
    }

    public function test_non_finance_view_does_not_disclose_mapping_error_or_account_details(): void
    {
        $this->category->update(['chart_of_account_id' => null]);
        $expense = $this->draft();
        $this->approved($expense);
        $this->actingAs($this->owner)->get(route('admin.site-expenses.show', $expense))->assertOk()->assertDontSee('no active expense accounting account')->assertSee('awaiting Finance');
        $this->actingAs($this->finance)->get(route('admin.site-expenses.show', $expense))->assertOk()->assertSee('no active expense accounting account');
    }

    public function test_finance_vat_override_recalculates_not_client_totals(): void
    {
        $this->actingAs($this->finance)->post(route('admin.site-expenses.store'), $this->payload(['vat_rate' => 5, 'vat_amount' => 9999]))->assertSessionHasNoErrors();
        $expense = SiteExpense::latest('id')->first();
        $this->assertSame('50.00', $expense->vat_amount);
        $this->assertSame('1050.00', $expense->total_amount);
    }

    public function test_duplicate_submit_keeps_one_attempt(): void
    {
        $expense = $this->draft();
        $first = $this->start($expense);
        $again = $this->start($expense);
        $this->assertSame($first->id, $again->id);
        $this->assertDatabaseCount('approval_instances', 1);
    }

    public function test_missing_mapping_ignores_legacy_text_and_activity_is_recorded(): void
    {
        $this->category->update(['chart_of_account_id' => null, 'linked_account' => $this->cost->label()]);
        $expense = $this->draft();
        $this->approved($expense);
        $this->assertFalse($expense->fresh()->accounting_posted);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Site Expenses', 'action' => 'Accounting pending']);
    }

    public function test_explicit_project_cost_center_is_derived(): void
    {
        $center = CostCenter::create(['code' => 'CC-SE', 'name' => 'Project cost center', 'type' => 'project', 'linked_id' => $this->project->id, 'status' => 'active']);
        $this->cost->update(['cost_center_required' => true]);
        $expense = $this->draft(['cost_center_id' => 9999]);
        $this->approved($expense);
        $this->assertEquals($center->id, $expense->fresh()->journalEntry->cost_center_id);
    }

    public function test_additive_migration_can_be_retried_without_overwriting_accounts_or_data(): void
    {
        $expense = $this->draft();
        $before = $expense->getAttributes();
        $count = ChartOfAccount::count();
        $migration = require database_path('migrations/2026_10_01_000001_create_site_expenses.php');
        $migration->up();
        $this->assertSame($count, ChartOfAccount::count());
        $this->assertSame($before, $expense->fresh()->getAttributes());
    }

    public function test_create_only_submitter_can_correct_own_draft_but_not_another_users(): void
    {
        $own = $this->draft();
        $this->owner->roles()->first()->permissions()->detach(Permission::where('module', 'Site Expenses')->where('action', 'edit')->value('id'));
        $this->actingAs($this->owner->fresh())->get(route('admin.site-expenses.edit', $own))->assertOk();
        $this->put(route('admin.site-expenses.update', $own), $this->payload(['description' => 'Owner correction']))->assertSessionHasNoErrors();
        $other = $own->replicate();
        $other->expense_number = 'SE-FOREIGN';
        $other->submitted_by_user_id = $this->finance->id;
        $other->save();
        $this->get(route('admin.site-expenses.edit', $other))->assertForbidden();
        $this->putJson(route('admin.site-expenses.update', $other), $this->payload())->assertForbidden();
    }

    public function test_pdf_receipt_download_is_private_and_anonymous_access_denied(): void
    {
        $file = UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $expense = $this->draft(['receipt' => $file]);
        $receipt = $expense->receipts()->firstOrFail();
        $url = route('admin.site-expenses.receipt-file', [$expense, $receipt->id, 'download' => 1]);
        $this->get($url)->assertOk()->assertDownload('invoice.pdf');
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_cost_mapping_inactive_after_submission_fails_safely(): void
    {
        $expense = $this->draft();
        $instance = $this->start($expense);
        $this->cost->update(['status' => 'inactive']);
        $this->decide($expense, $instance, $this->first);
        $this->decide($expense, $instance, $this->finance, 1);
        $this->assertSame('approved', $instance->fresh()->status);
        $this->assertSame('approved_pending_posting', $expense->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
