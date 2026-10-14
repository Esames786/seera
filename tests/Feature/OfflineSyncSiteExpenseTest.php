<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\OfflineSyncReceipt;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SiteExpenseApprovalSubject;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use App\Support\Workspace\ProjectWorkspacePanels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Offline Site Expense entry, synced later. Sync creates the same draft the
 * online form creates (same rules, private receipt, atomic with its file),
 * never approves or posts, and the existing runtime continues online.
 */
class OfflineSyncSiteExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $first;

    private User $finance;

    private Project $project;

    private Project $otherProject;

    private Site $site;

    private Site $otherSite;

    private ExpenseCategory $category;

    private ChartOfAccount $cash;

    private ChartOfAccount $bank;

    private ApprovalWorkflow $workflow;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->project = Project::create(['code' => 'OSE-A', 'name' => 'Offline Expense Project A', 'status' => 'active']);
        $this->otherProject = Project::create(['code' => 'OSE-B', 'name' => 'Offline Expense Project B', 'status' => 'active']);
        $this->site = Site::create(['code' => 'OSE-S-A', 'name' => 'Offline Site A', 'project_id' => $this->project->id, 'status' => 'active']);
        $this->otherSite = Site::create(['code' => 'OSE-S-B', 'name' => 'Offline Site B', 'project_id' => $this->otherProject->id, 'status' => 'active']);
        $this->owner = $this->actor('Owner', ['view', 'create', 'edit', 'delete'], 'Project Level');
        $this->first = $this->actor('Supervisor', ['view', 'approve', 'reject']);
        $this->finance = $this->actor('Finance', ['view', 'create', 'edit', 'approve', 'reject', 'post', 'retry']);
        $this->cash = $this->account('1110', 'Cash', 'asset');
        $this->bank = $this->account('1120', 'Bank', 'asset');
        $cost = $this->account('5200', 'Site consumables', 'expense');
        $this->account('1300', 'Input VAT', 'asset');
        $this->account('2100', 'Accounts Payable', 'liability');
        $this->category = ExpenseCategory::create(['name' => 'Fuel', 'code' => 'OSE-FUEL', 'chart_of_account_id' => $cost->id,
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

    private function actor(string $name, array $actions, string $scope = 'Company Level', bool $mobile = true): User
    {
        $role = Role::create(['name' => $name.' '.++$this->sequence, 'code' => 'OSE_ROLE_'.$this->sequence, 'level' => 4, 'access_scope' => $scope, 'status' => 'active']);
        foreach ($actions as $action) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Site Expenses', 'action' => $action])->id]);
        }
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'mobile_access' => $mobile]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    private function account(string $code, string $name, string $type): ChartOfAccount
    {
        return ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $type === 'liability' ? 'credit' : 'debit', 'status' => 'active']);
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['expense_date' => today()->toDateString(), 'project_id' => $this->project->id, 'site_id' => $this->site->id,
            'expense_category_id' => $this->category->id, 'payment_type' => 'Cash', 'payment_account_id' => $this->cash->id,
            'taxable_amount' => '1000.00', 'vat_applicable' => '1', 'description' => 'Diesel for the generator, captured offline'];
    }

    private function item(array $payload = [], array $extra = []): array
    {
        return $extra + ['client_uuid' => (string) Str::uuid(), 'action' => 'create', 'captured_at' => Carbon::now()->subHours(3)->toIso8601String(), 'payload' => $this->payload($payload)];
    }

    /** Multipart like the device sends it (JSON fields plus the receipt file per item). */
    private function sync(array $items, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->owner)->post(route('admin.mobile-sync.site-expenses'), ['items' => $items], ['Accept' => 'application/json']);
    }

    private function receiptFile(string $name = 'receipt.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 640, 480);
    }

    // ----------------------------------------------------------------- tests

    public function test_offline_expense_sync_creates_one_draft_document_without_approval_or_accounting(): void
    {
        $item = $this->item();
        $result = $this->sync([$item])->assertOk()->json('results.0');
        $this->assertSame('synced', $result['status']);
        $this->assertSame('site_expense', $result['record']['type']);
        $this->assertStringStartsWith('SE-'.now()->year.'-', $result['record']['reference']);
        $this->assertSame('draft', $result['record']['status']);

        $expense = SiteExpense::withoutGlobalScopes()->sole();
        $this->assertSame('draft', $expense->status);
        $this->assertSame($item['client_uuid'], $expense->sync_client_uuid);
        $this->assertNotNull($expense->captured_at_client);
        $this->assertSame($this->owner->id, $expense->submitted_by_user_id);
        $this->assertSame('1000.00', (string) $expense->taxable_amount);
        $this->assertSame('15.00', (string) $expense->vat_rate);
        $this->assertSame('1150.00', (string) $expense->total_amount);
        $this->assertSame(0, $expense->approvals()->count());
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('supplier_bills', 0);
        $this->assertFalse($expense->accounting_posted);
        $this->assertDatabaseHas('activity_logs', ['module' => 'Site Expenses', 'action' => 'Created draft (offline sync)']);
        $receipt = OfflineSyncReceipt::where('client_uuid', $item['client_uuid'])->sole();
        $this->assertSame(['site_expense', $expense->id, 'synced'], [$receipt->business_record_type, $receipt->business_record_id, $receipt->status]);
    }

    public function test_exact_retry_does_not_duplicate_and_a_conflicting_uuid_is_refused(): void
    {
        $item = $this->item();
        $first = $this->sync([$item])->json('results.0');
        $replay = $this->sync([$item])->json('results.0');
        $this->assertSame('synced', $replay['status']);
        $this->assertSame($first['record']['id'], $replay['record']['id']);
        $this->assertTrue($replay['data']['replay']);
        $this->assertDatabaseCount('site_expenses', 1);

        $changed = $item;
        $changed['payload']['taxable_amount'] = '2000.00';
        $conflict = $this->sync([$changed])->json('results.0');
        $this->assertSame(['needs_attention', 'BUSINESS_CONFLICT', 'payload_mismatch'], [$conflict['status'], $conflict['code'], $conflict['reason']]);
        $this->assertDatabaseCount('site_expenses', 1);
        $this->assertSame('1000.00', (string) SiteExpense::withoutGlobalScopes()->sole()->taxable_amount);
    }

    public function test_amount_date_and_shape_are_validated_server_side(): void
    {
        $results = collect($this->sync([
            $this->item(['taxable_amount' => '0']),
            $this->item(['taxable_amount' => 'abc']),
            $this->item(['payment_type' => 'Crypto']),
            $this->item(['expense_date' => today()->subDays(61)->toDateString()]),
        ])->assertOk()->json('results'))->pluck('reason');
        $this->assertSame(['invalid_payload', 'invalid_payload', 'invalid_payload', 'expense_date_too_old'], $results->all());
        $this->assertDatabaseCount('site_expenses', 0);
        $this->assertSame(1, OfflineSyncReceipt::count()); // Only the date refusal earned a (failed) receipt.
        $this->assertSame('failed', OfflineSyncReceipt::sole()->status);

        $this->sync(array_map(fn () => $this->item(), range(1, 6)))->assertUnprocessable(); // bounded batch with files
    }

    public function test_project_site_category_supplier_and_account_are_revalidated_at_sync(): void
    {
        $foreign = $this->sync([$this->item(['project_id' => $this->otherProject->id, 'site_id' => $this->otherSite->id])])->json('results.0');
        $this->assertSame(['needs_attention', 'STALE_REFERENCE', 'project_unavailable'], [$foreign['status'], $foreign['code'], $foreign['reason']]);
        $mismatch = $this->sync([$this->item(['site_id' => $this->otherSite->id])], $this->finance)->json('results.0');
        $this->assertSame('project_unavailable', $mismatch['reason']);

        $this->category->update(['status' => 'inactive']);
        $this->assertSame('category_unavailable', $this->sync([$this->item()])->json('results.0.reason'));
        $this->category->update(['status' => 'active', 'mobile_visible' => false]);
        $this->assertSame('category_unavailable', $this->sync([$this->item()])->json('results.0.reason'));
        $this->assertSame('synced', $this->sync([$this->item()], $this->finance)->json('results.0.status')); // Finance may use hidden categories.
        $this->category->update(['mobile_visible' => true]);

        $supplier = Supplier::create(['code' => 'OSE-SUP', 'name' => 'Inactive supplier', 'status' => 'inactive']);
        $this->assertSame('supplier_unavailable', $this->sync([$this->item(['supplier_id' => $supplier->id])])->json('results.0.reason'));
        $this->assertSame('rule_supplier_id', $this->sync([$this->item(['payment_type' => 'Supplier Credit'])])->json('results.0.reason'));

        $this->assertSame('payment_account_unavailable', $this->sync([$this->item(['payment_account_id' => 999999])])->json('results.0.reason'));
        $this->assertSame('payment_account_unavailable', $this->sync([$this->item(['payment_type' => 'Bank', 'payment_account_id' => $this->cash->id])])->json('results.0.reason'));
        $this->assertSame(1, SiteExpense::withoutGlobalScopes()->count());
    }

    public function test_receipt_is_uploaded_privately_and_an_invalid_receipt_creates_no_expense_even_on_retry(): void
    {
        $item = $this->item();
        $result = $this->actingAs($this->owner)->post(route('admin.mobile-sync.site-expenses'), ['items' => [$item + ['receipt' => $this->receiptFile()]]], ['Accept' => 'application/json'])->assertOk()->json('results.0');
        $this->assertSame('synced', $result['status']);
        $this->assertTrue($result['data']['receipt_attached']);
        $expense = SiteExpense::withoutGlobalScopes()->sole();
        $receipt = $expense->receipts()->sole();
        $this->assertSame('receipt.jpg', $receipt->original_filename);
        Storage::disk('local')->assertExists($receipt->path);
        $this->assertStringStartsWith('site-expense-receipts/', $receipt->path);
        $this->actingAs($this->owner)->get(route('admin.site-expenses.receipt-file', [$expense, $receipt->id]))->assertOk();

        // Replay with the same file: still one expense, one receipt.
        $this->actingAs($this->owner)->post(route('admin.mobile-sync.site-expenses'), ['items' => [$item + ['receipt' => $this->receiptFile()]]], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('results.0.data.replay', true);
        $this->assertDatabaseCount('site_expense_receipts', 1);

        // Dangerous or oversize receipts refuse the whole item atomically; the retry fails the same way.
        $bad = $this->item();
        foreach (range(1, 2) as $attempt) {
            $this->actingAs($this->owner)->post(route('admin.mobile-sync.site-expenses'), ['items' => [$bad + ['receipt' => UploadedFile::fake()->create('attack.html', 20, 'text/html')]]], ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath('results.0.status', 'failed')->assertJsonPath('results.0.reason', 'invalid_receipt');
        }
        $large = $this->item();
        $this->actingAs($this->owner)->post(route('admin.mobile-sync.site-expenses'), ['items' => [$large + ['receipt' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')]]], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('results.0.reason', 'invalid_receipt');
        $this->assertDatabaseCount('site_expenses', 1);
        $this->assertDatabaseCount('site_expense_receipts', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('site-expense-receipts'));

        // A category that requires a receipt still accepts the draft; the gap is reported for submission time.
        $this->category->update(['invoice_photo_required' => true]);
        $this->assertTrue($this->sync([$this->item()])->json('results.0.data.receipt_required'));
    }

    public function test_synced_expense_continues_through_the_existing_runtime_and_posts_only_after_approval(): void
    {
        $this->sync([$this->item()])->assertJsonPath('results.0.status', 'synced');
        $expense = SiteExpense::withoutGlobalScopes()->sole();
        $this->assertEquals(0, ProjectWorkspacePanels::finance($this->project)['cost']);

        $this->actingAs($this->owner)->post(route('admin.site-expenses.submit', $expense), ['workflow_id' => $this->workflow->id])->assertRedirect();
        $this->assertSame('pending', $expense->fresh()->status);
        $instance = $expense->approvals()->latest('id')->firstOrFail();
        $runtime = app(ApprovalRuntimeService::class);
        $subject = app(SiteExpenseApprovalSubject::class);
        $runtime->decide($subject, $expense->id, $instance->id, $instance->steps[0]->id, $this->first, 'approve');
        $this->assertDatabaseCount('journal_entries', 0);
        $runtime->decide($subject, $expense->id, $instance->id, $instance->steps[1]->id, $this->finance, 'approve');
        app(SiteExpenseAccountingService::class)->attempt($expense->id, $this->finance->id);
        $expense->refresh();
        $this->assertSame('posted', $expense->status);
        $this->assertTrue($expense->accounting_posted);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(1000, ProjectWorkspacePanels::finance($this->project)['cost']);
    }

    public function test_supplier_credit_sync_creates_no_bill_until_approval_and_never_a_second_one(): void
    {
        $supplier = Supplier::create(['code' => 'OSE-SUP-OK', 'name' => 'Synthetic supplier', 'status' => 'active']);
        $item = $this->item(['payment_type' => 'Supplier Credit', 'supplier_id' => $supplier->id, 'payment_account_id' => null]);
        $this->sync([$item])->assertJsonPath('results.0.status', 'synced');
        $expense = SiteExpense::withoutGlobalScopes()->sole();
        $this->assertNull($expense->payment_account_id);
        $this->assertDatabaseCount('supplier_bills', 0);

        $runtime = app(ApprovalRuntimeService::class);
        $subject = app(SiteExpenseApprovalSubject::class);
        $instance = $runtime->start($subject, $expense->id, $this->workflow->id, $this->owner);
        $runtime->decide($subject, $expense->id, $instance->id, $instance->steps[0]->id, $this->first, 'approve');
        $runtime->decide($subject, $expense->id, $instance->id, $instance->steps[1]->id, $this->finance, 'approve');
        app(SiteExpenseAccountingService::class)->attempt($expense->id, $this->finance->id);
        $this->assertDatabaseCount('supplier_bills', 1);
        $this->assertDatabaseCount('journal_entries', 0);

        // Replaying the original sync after approval changes nothing.
        $this->sync([$item])->assertJsonPath('results.0.data.replay', true);
        app(SiteExpenseAccountingService::class)->attempt($expense->id, $this->finance->id);
        $this->assertDatabaseCount('site_expenses', 1);
        $this->assertDatabaseCount('supplier_bills', 1);
    }

    public function test_sync_requires_login_and_the_create_permission_and_the_reference_lists_only_safe_lookups(): void
    {
        $this->post(route('admin.mobile-sync.site-expenses'), ['items' => [$this->item()]], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->sync([$this->item()], $this->first)->assertForbidden();
        $this->assertDatabaseCount('site_expenses', 0);

        $reference = $this->actingAs($this->owner)->getJson(route('admin.mobile-sync.reference'))->assertOk()->json();
        $this->assertNull($reference['attendance']);
        $this->assertSame([$this->project->id], collect($reference['site_expense']['projects'])->pluck('id')->all());
        $this->assertSame([['id' => $this->category->id, 'name' => 'Fuel', 'vat_rate' => 15, 'payment_type' => 'Both', 'invoice_photo_required' => false]], $reference['site_expense']['categories']);
        $this->assertSame('Cash', collect($reference['site_expense']['accounts'])->firstWhere('id', $this->cash->id)['channel']);
        $this->assertFalse($reference['site_expense']['can_override_vat']);
        $this->assertFalse($reference['site_expense']['employee_linked']);
        $this->assertArrayNotHasKey('password', json_decode(json_encode($reference), true)['user']);
        $this->assertSame(5, $reference['policy']['batch_size_with_files']);
    }

    public function test_online_form_still_validates_the_same_way_after_the_shared_rules_extraction(): void
    {
        $this->actingAs($this->owner)->post(route('admin.site-expenses.store'), $this->payload(['_save_action' => 'stay']))->assertSessionHasNoErrors()->assertRedirect();
        $expense = SiteExpense::withoutGlobalScopes()->sole();
        $this->assertSame('1150.00', (string) $expense->total_amount);
        $this->assertNull($expense->sync_client_uuid);
        // Project-level users are stopped by the request-scope guard (403); a company-level user reaches the shared rule (404: site not in project).
        $this->actingAs($this->owner)->post(route('admin.site-expenses.store'), $this->payload(['site_id' => $this->otherSite->id]))->assertForbidden();
        $this->actingAs($this->finance)->post(route('admin.site-expenses.store'), $this->payload(['site_id' => $this->otherSite->id]))->assertNotFound();
        $this->category->update(['mobile_visible' => false]);
        $this->actingAs($this->owner)->post(route('admin.site-expenses.store'), $this->payload())->assertForbidden();
        Employee::create(['employee_code' => 'OSE-EMP', 'first_name' => 'Linked', 'last_name' => 'User', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $this->project->id, 'site_id' => $this->site->id, 'user_id' => $this->finance->id]);
        $this->actingAs($this->finance)->post(route('admin.site-expenses.store'), $this->payload(['payment_type' => 'Employee Reimbursement', 'payment_account_id' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('site_expenses', 2);
    }
}
