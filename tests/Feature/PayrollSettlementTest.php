<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Hr\PayrollRunController;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayrollSettlement;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ProjectCostReport;
use App\Services\Payroll\PayrollAccountingService;
use App\Services\Payroll\PayrollSettlementService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Payroll Phase 2 — salary settlement. A posted payroll run is paid once from
 * an authorized bank / cash account (Dr payroll payable, Cr bank / cash),
 * replay-safe and reversible; the expense, VAT and project cost never move.
 */
class PayrollSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Project $mine;

    protected Project $theirs;

    protected Employee $ahmed;

    protected Employee $khalid;

    protected ChartOfAccount $expense;

    protected ChartOfAccount $payable;

    protected ChartOfAccount $bank;

    protected ChartOfAccount $cash;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->mine = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-SET-A', 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-SET-B', 'status' => 'active']);
        foreach ([$this->mine, $this->theirs] as $project) {
            CostCenter::create(['code' => 'CC-'.$project->code, 'name' => $project->name, 'type' => 'project', 'linked_id' => $project->id, 'status' => 'active']);
        }
        Employee::query()->update(['status' => 'inactive']);
        $this->ahmed = Employee::create(['employee_code' => 'EMP-SET-A', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-03-01', 'project_id' => $this->mine->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500,
            'payment_method' => 'Bank Transfer', 'bank_name' => 'Al Rajhi Bank', 'iban' => 'SA0380000000608010167519']);
        $this->khalid = Employee::create(['employee_code' => 'EMP-SET-B', 'first_name' => 'Khalid', 'last_name' => 'Otaibi', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-01-01', 'project_id' => $this->theirs->id, 'basic_salary' => 9000, 'payment_method' => 'Cash']);
        SalaryStructure::create(['employee_id' => $this->ahmed->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500, 'fixed_deduction' => 300, 'effective_from' => '2026-01-01', 'status' => 'active']);

        $this->expense = ChartOfAccount::where('account_code', '5100')->firstOrFail();
        $this->payable = ChartOfAccount::where('account_code', '2300')->firstOrFail();
        $this->bank = ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail();
        $this->cash = ChartOfAccount::where('account_code', PostingService::CASH)->firstOrFail();
        $deductions = ChartOfAccount::create(['account_code' => '2320', 'account_name' => 'Payroll Deductions Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
        $this->rule()->update(['deduction_account_id' => $deductions->id, 'auto_post' => true, 'status' => 'active']);
    }

    // ---------------------------------------------------------------- helpers

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** @param array<string, array<int, string>> $grants */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'SET role '.$suffix, 'code' => 'SET_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'SET '.$suffix, 'email' => 'set-'.$suffix.'@example.test', 'username' => 'set.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    /** Payroll post + the Accounts Payable payment right + approve (for reversals): the Finance settlement role. */
    protected function finance(): User
    {
        return $this->user(['Payroll' => ['view', 'post', 'approve', 'export'], 'Accounts Payable' => ['view', 'process'], 'Journal Entries' => ['view']], suffix: 'fin');
    }

    protected function rule(): AutomaticPostingRule
    {
        return AutomaticPostingRule::where('source_module', 'Payroll')->where('trigger_event', 'Payroll Approved')->firstOrFail();
    }

    /** Company-wide run for 2026-$month: Ahmed (bank, net 8,200) + Khalid (cash, net 9,000) = 17,200 payable. */
    protected function makeRun(int $month = 9, string $status = 'approved'): PayrollRun
    {
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.store'), ['payroll_month' => $month, 'payroll_year' => 2026])->assertSessionHasNoErrors();
        $run = PayrollRun::where('payroll_month', $month)->where('payroll_year', 2026)->latest('id')->firstOrFail();
        if (in_array($status, ['processed', 'approved'], true)) {
            $this->actingAs($this->admin())->post(route('admin.hr.payroll.process', $run))->assertSessionHasNoErrors();
        }
        if ($status === 'approved') {
            $this->actingAs($this->admin())->post(route('admin.hr.payroll.approve', $run))->assertSessionHasNoErrors();
        }
        $this->flushSession();
        auth()->logout();

        return $run->fresh();
    }

    /** @param array<string, mixed> $overrides */
    protected function pay(PayrollRun $run, ?User $as = null, array $overrides = []): TestResponse
    {
        return $this->actingAs($as ?? $this->finance())->post(route('admin.hr.payroll.settle', $run), $overrides + [
            'payment_date' => '2026-10-05', 'payment_account_id' => $this->bank->id, 'reference_number' => 'BATCH-1', 'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    protected function settlementJournals(PayrollRun $run): Collection
    {
        return JournalEntry::withoutGlobalScopes()->where('source_module', 'Payroll Settlement')
            ->whereIn('source_id', PayrollSettlement::where('payroll_run_id', $run->id)->pluck('id'))->get();
    }

    protected function projectCost(Project $project): float
    {
        $period = new ReportPeriod(CarbonImmutable::parse('2026-09-01'), null, 'custom');

        return (float) app(ProjectCostReport::class)->rows(Project::whereKey($project->id)->get(), $period)->first()['cost'];
    }

    // ---------------------------------------------------------------- eligibility

    public function test_review_partial_scope_cannot_approve_and_auto_post_whole_payroll(): void
    {
        $run = $this->makeRun(9, 'processed');
        $scoped = $this->user(['Payroll' => ['view', 'approve']], $this->mine, 'partial-approver');
        $this->actingAs($scoped)->post(route('admin.hr.payroll.approve', $run))->assertForbidden();
        $this->assertSame('processed', $run->fresh()->status);
        $this->assertNull($run->fresh()->journal_entry_id);
    }

    public function test_review_stale_payroll_process_cannot_overwrite_committed_approval(): void
    {
        $run = $this->makeRun(9, 'processed');
        $stale = $run->fresh();
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.approve', $run))->assertSessionHasNoErrors();
        $itemIds = $run->items()->pluck('id')->all();
        $request = Request::create('/admin/hr/payroll/'.$run->id.'/process', 'POST');
        $request->setUserResolver(fn () => $this->admin());
        app(PayrollRunController::class)->process($request, $stale);
        $this->assertSame('approved', $run->fresh()->status);
        $this->assertSame($itemIds, $run->items()->pluck('id')->all());
    }

    public function test_review_stale_payroll_update_and_delete_preserve_approved_history(): void
    {
        $run = $this->makeRun(9, 'processed');
        $stale = $run->fresh();
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.approve', $run))->assertSessionHasNoErrors();
        $request = Request::create('/admin/hr/payroll/'.$run->id, 'PUT', ['payroll_month' => 8, 'payroll_year' => 2026]);
        $request->setUserResolver(fn () => $this->admin());
        $controller = app(PayrollRunController::class);
        $controller->update($request, $stale);
        $controller->destroy($request, $stale);
        $this->assertSame('approved', $run->fresh()->status);
        $this->assertEquals(9, $run->fresh()->payroll_month);
        $this->assertNotNull($run->fresh()->journal_entry_id);
    }

    public function test_review_partial_scope_cannot_reprocess_edit_or_delete_whole_payroll(): void
    {
        $run = $this->makeRun(9, 'processed');
        $scoped = $this->user(['Payroll' => ['view', 'process', 'edit', 'delete']], $this->mine, 'partial-writer');
        $itemIds = $run->items()->pluck('id')->all();
        $this->actingAs($scoped)->post(route('admin.hr.payroll.process', $run))->assertForbidden();
        $this->put(route('admin.hr.payroll.update', $run), ['payroll_month' => 8, 'payroll_year' => 2026])->assertForbidden();
        $this->delete(route('admin.hr.payroll.destroy', $run))->assertForbidden();
        $this->actingAs($this->admin());
        $this->assertSame($itemIds, $run->items()->pluck('id')->all());
        $this->assertEquals(9, $run->fresh()->payroll_month);
    }

    public function test_review_settlement_uses_original_payable_when_rule_changes_to_deductions_account(): void
    {
        $run = $this->makeRun();
        $this->rule()->update(['credit_account_id' => ChartOfAccount::where('account_code', '2320')->value('id')]);
        $this->pay($run)->assertSessionHasNoErrors();
        $journal = $this->settlementJournals($run)->sole();
        $this->assertEqualsWithDelta(17200, $journal->lines()->where('chart_of_account_id', $this->payable->id)->sum('debit'), 0.01);
        $this->assertEquals(0, $journal->lines()->where('chart_of_account_id', $this->rule()->deduction_account_id)->sum('debit'));
    }

    public function test_only_a_posted_payroll_run_can_be_paid(): void
    {
        $draft = $this->makeRun(1, 'draft');
        $this->pay($draft)->assertSessionHasErrors('payment');
        $processed = $this->makeRun(2, 'processed');
        $this->pay($processed)->assertSessionHasErrors('payment');

        // Approved but accounting failed (no deduction account) → not payable.
        $this->rule()->update(['deduction_account_id' => null]);
        $unposted = $this->makeRun(3, 'approved');
        $this->assertSame(PayrollRun::ACCOUNTING_FAILED, $unposted->accounting_status);
        $this->pay($unposted)->assertSessionHasErrors('payment');
        $this->rule()->update(['deduction_account_id' => ChartOfAccount::where('account_code', '2320')->value('id')]);

        // Review-mode draft journal is not enough.
        $this->rule()->update(['auto_post' => false]);
        $review = $this->makeRun(4, 'approved');
        $this->assertSame(PayrollRun::ACCOUNTING_REVIEW, $review->accounting_status);
        $this->pay($review)->assertSessionHasErrors('payment');
        $this->actingAs($this->finance())->get(route('admin.hr.payroll.show', $review))->assertOk()->assertSee('review-mode draft journal is not enough')->assertDontSee('Record Salary Payment');
        $this->rule()->update(['auto_post' => true]);

        // Reversed posting: nothing payable.
        $reversed = $this->makeRun(5, 'approved');
        app(PayrollAccountingService::class)->reverse($reversed->id, 'wrong month', $this->admin()->id);
        $this->pay($reversed)->assertSessionHasErrors('payment');

        $this->assertSame(0, PayrollSettlement::count());
        $this->assertSame(0, JournalEntry::where('source_module', 'Payroll Settlement')->count());
        foreach ([$draft, $processed, $unposted, $review, $reversed] as $run) {
            $this->assertSame(PayrollRun::PAYMENT_NOT_PAID, $run->fresh()->payment_status);
        }
    }

    public function test_posted_run_settles_payroll_payable_against_the_bank_once(): void
    {
        $payableBefore = $this->payable->postedBalance();
        $run = $this->makeRun();
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->accounting_status);
        $costBefore = $this->projectCost($this->mine);
        $vatBefore = VatTransaction::count();
        $expenseDebits = (float) $run->journalEntry->lines->where('chart_of_account_id', $this->expense->id)->sum('debit');

        $this->pay($run)->assertSessionHasNoErrors()->assertRedirect(route('admin.hr.payroll.show', $run).'#payment');

        $run->refresh();
        $this->assertSame(PayrollRun::PAYMENT_PAID, $run->payment_status);
        $this->assertSame('approved', $run->status, 'HR status is not touched by settlement');
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->accounting_status);
        $this->assertEqualsWithDelta(17200, (float) $run->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0, $run->remainingPayable(), 0.01);
        $this->assertNotNull($run->settled_at);

        $settlement = $run->activeSettlement;
        $this->assertNotNull($settlement);
        $this->assertSame($run->id, $settlement->payroll_run_id);
        $this->assertSame($this->bank->id, $settlement->payment_account_id);
        $this->assertSame('Bank Transfer', $settlement->payment_method);
        $this->assertSame('2026-10-05', $settlement->payment_date->toDateString());
        $journal = $settlement->journalEntry;
        $this->assertSame('posted', $journal->status);
        $this->assertSame('Payroll Settlement', $journal->source_module);
        $this->assertSame($settlement->id, $journal->source_id);
        $this->assertSame('2026-10-05', $journal->journal_date->toDateString(), 'journal dated on the payment date');
        $this->assertCount(2, $journal->lines);
        $this->assertEqualsWithDelta(17200, (float) $journal->lines->where('chart_of_account_id', $this->payable->id)->sum('debit'), 0.01, 'Dr payroll payable');
        $this->assertEqualsWithDelta(17200, (float) $journal->lines->where('chart_of_account_id', $this->bank->id)->sum('credit'), 0.01, 'Cr bank');
        $this->assertSame(0, $journal->lines->where('chart_of_account_id', $this->expense->id)->count(), 'expense is never posted again');

        // Expense, VAT and project cost unchanged.
        $this->assertEqualsWithDelta($expenseDebits, (float) JournalEntry::whereIn('id', [$run->journal_entry_id, $journal->id])->get()->flatMap->lines->where('chart_of_account_id', $this->expense->id)->sum('debit'), 0.01);
        $this->assertSame($vatBefore, VatTransaction::count());
        $this->assertSame(0, $journal->lines->whereIn('chart_of_account_id', ChartOfAccount::whereIn('account_code', ['1300', '2210'])->pluck('id'))->count());
        $this->assertEqualsWithDelta($costBefore, $this->projectCost($this->mine), 0.01, 'salary payment does not touch project cost');

        // Payable account balance returns to its opening level.
        $this->assertEqualsWithDelta($payableBefore, $this->payable->fresh()->postedBalance(), 0.01, 'payable back to its level before this run');
        $this->assertDatabaseHas('activity_logs', ['module' => 'Payroll', 'action' => 'Recorded salary payment']);

        $page = $this->actingAs($this->finance())->get(route('admin.hr.payroll.show', $run))->assertOk();
        $page->assertSee('Paid')->assertSee($journal->journal_number)->assertSee($this->bank->label())->assertSee('Reverse Salary Payment')->assertDontSee('Reverse Posting')->assertDontSee('Record Salary Payment');
    }

    public function test_cash_account_is_allowed_and_other_accounts_are_refused(): void
    {
        $run = $this->makeRun();
        $this->pay($run, overrides: ['payment_account_id' => $this->payable->id])->assertSessionHasErrors('payment');
        $this->pay($run, overrides: ['payment_account_id' => $this->expense->id])->assertSessionHasErrors('payment');
        $this->pay($run, overrides: ['payment_account_id' => ChartOfAccount::where('account_code', '1200')->value('id')])->assertSessionHasErrors('payment', 'a receivable is not a payment account');
        $this->cash->update(['status' => 'inactive']);
        $this->pay($run, overrides: ['payment_account_id' => $this->cash->id])->assertSessionHasErrors('payment');
        $this->cash->update(['status' => 'active']);
        $this->assertSame(0, PayrollSettlement::count());

        $this->pay($run, overrides: ['payment_account_id' => $this->cash->id])->assertSessionHasNoErrors();
        $settlement = $run->fresh()->activeSettlement;
        $this->assertSame('Cash', $settlement->payment_method);
        $this->assertEqualsWithDelta(17200, (float) $settlement->journalEntry->lines->where('chart_of_account_id', $this->cash->id)->sum('credit'), 0.01, 'Cr cash');
    }

    public function test_payment_date_is_server_validated_and_future_dates_are_refused(): void
    {
        $run = $this->makeRun();
        $this->pay($run, overrides: ['payment_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('payment_date');
        $this->pay($run, overrides: ['payment_date' => 'not-a-date'])->assertSessionHasErrors('payment_date');
        $this->assertSame(0, PayrollSettlement::count());

        // The only period lock in Seera is the VAT period; a salary payment touches no VAT account, so a sealed VAT
        // period does not block it (consistent with supplier payments), while the date itself is still the server-validated one.
        VatPeriod::create(['period_name' => 'Q4 2026 sealed', 'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'finalized']);
        $this->pay($run, overrides: ['payment_date' => '2026-10-05'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-05', $run->fresh()->activeSettlement->payment_date->toDateString());
    }

    // ---------------------------------------------------------------- idempotency and replay

    public function test_exact_replay_is_safe_conflicting_replay_and_double_payment_are_refused(): void
    {
        $payableBefore = $this->payable->postedBalance();
        $run = $this->makeRun();
        $key = (string) Str::uuid();
        $finance = $this->finance();
        $this->pay($run, $finance, ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $first = $run->fresh()->activeSettlement;

        // Same key, same details (double click / browser retry): nothing added, friendly message.
        $this->pay($run, $finance, ['idempotency_key' => $key])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($m) => str_contains($m, 'already recorded'));
        $this->assertSame(1, PayrollSettlement::count());
        $this->assertCount(1, $this->settlementJournals($run));

        // Same key, different account / date: refused, nothing recorded.
        $this->pay($run, $finance, ['idempotency_key' => $key, 'payment_account_id' => $this->cash->id])->assertSessionHasErrors('idempotency_key');
        $this->pay($run, $finance, ['idempotency_key' => $key, 'payment_date' => '2026-10-06'])->assertSessionHasErrors('idempotency_key');
        // Same key reused for another run: refused.
        $other = $this->makeRun(10);
        $this->pay($other, $finance, ['idempotency_key' => $key])->assertSessionHasErrors('idempotency_key');
        $this->assertSame(PayrollRun::PAYMENT_NOT_PAID, $other->fresh()->payment_status);

        // New key on an already paid run: refused (a run never clears its payable twice).
        $this->pay($run, $finance)->assertSessionHasErrors('payment');
        $this->assertSame(1, PayrollSettlement::count());
        $this->assertSame($first->id, $run->fresh()->activeSettlement->id);
        $this->assertEqualsWithDelta(17200, (float) $run->fresh()->paid_amount, 0.01);
        $this->assertEqualsWithDelta($payableBefore + (float) $other->net_amount, $this->payable->fresh()->postedBalance(), 0.01, 'payable cleared exactly once (the second, unpaid run still owes its net)');
    }

    // ---------------------------------------------------------------- reversal and ordering

    public function test_salary_payment_reversal_makes_the_run_unpaid_and_keeps_history(): void
    {
        $run = $this->makeRun();
        $finance = $this->finance();
        $this->pay($run, $finance)->assertSessionHasNoErrors();
        $settlement = $run->fresh()->activeSettlement;
        $costBefore = $this->projectCost($this->mine);

        // Permission: post + AP process alone cannot reverse; reason required.
        $this->actingAs($this->user(['Payroll' => ['view', 'post'], 'Accounts Payable' => ['process']], suffix: 'noapprove'))->post(route('admin.hr.payroll.settlement-reverse', $run), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($finance)->post(route('admin.hr.payroll.settlement-reverse', $run), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($finance)->post(route('admin.hr.payroll.settlement-reverse', $run), ['reason' => 'Bank batch rejected'])->assertSessionHasNoErrors();

        $run->refresh();
        $settlement->refresh();
        $this->assertSame(PayrollRun::PAYMENT_NOT_PAID, $run->payment_status);
        $this->assertEqualsWithDelta(0, (float) $run->paid_amount, 0.01);
        $this->assertNull($run->settled_at);
        $this->assertNull($run->activeSettlement);
        $this->assertNotNull($settlement->reversal_journal_id, 'original settlement retained with its reversal link');
        $this->assertSame('Bank batch rejected', $settlement->reversal_reason);
        $this->assertSame('posted', $settlement->journalEntry->fresh()->status, 'original payment journal stays');
        $reversal = $settlement->reversalJournal;
        $this->assertSame('posted', $reversal->status);
        $this->assertEqualsWithDelta(17200, (float) $reversal->lines->where('chart_of_account_id', $this->bank->id)->sum('debit'), 0.01, 'Dr bank');
        $this->assertEqualsWithDelta(17200, (float) $reversal->lines->where('chart_of_account_id', $this->payable->id)->sum('credit'), 0.01, 'Cr payroll payable');
        $this->assertEqualsWithDelta($costBefore, $this->projectCost($this->mine), 0.01, 'payment reversal does not touch project cost');
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->accounting_status, 'the payroll expense posting is NOT reversed by a payment reversal');
        $this->assertSame('posted', $run->journalEntry->fresh()->status);

        // Same reason repeated = no-op; a different reason with nothing to reverse is refused; paying again is allowed.
        $this->actingAs($finance)->post(route('admin.hr.payroll.settlement-reverse', $run), ['reason' => 'Bank batch rejected'])->assertSessionHasNoErrors();
        $this->assertSame(1, JournalEntry::where('source_module', 'Manual')->where('source_id', $settlement->journal_entry_id)->count());
        $this->actingAs($finance)->post(route('admin.hr.payroll.settlement-reverse', $run), ['reason' => 'Other'])->assertSessionHasErrors('payment');
        $this->pay($run, $finance, ['payment_date' => '2026-10-07'])->assertSessionHasNoErrors();
        $this->assertSame(PayrollRun::PAYMENT_PAID, $run->fresh()->payment_status);
        $this->assertSame(2, PayrollSettlement::where('payroll_run_id', $run->id)->count());
        $this->actingAs($finance)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('Reversed payment')->assertSee('Bank batch rejected');
    }

    public function test_payroll_posting_reversal_is_blocked_while_a_salary_payment_is_active(): void
    {
        $run = $this->makeRun();
        $finance = $this->finance();
        $this->pay($run, $finance)->assertSessionHasNoErrors();

        $this->actingAs($finance)->post(route('admin.hr.payroll.reverse', $run), ['reason' => 'Wrong month'])->assertSessionHasErrors('posting');
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->fresh()->accounting_status);
        $this->assertNull($run->fresh()->reversal_journal_id);
        $this->actingAs($finance)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertDontSee('Reverse Posting')->assertSee('reverse the salary payment first');

        // Reverse the payment first, then the posting is permitted.
        $this->actingAs($finance)->post(route('admin.hr.payroll.settlement-reverse', $run), ['reason' => 'Paid in error'])->assertSessionHasNoErrors();
        $this->actingAs($finance)->post(route('admin.hr.payroll.reverse', $run), ['reason' => 'Wrong month'])->assertSessionHasNoErrors();
        $run->refresh();
        $this->assertSame(PayrollRun::ACCOUNTING_REVERSED, $run->accounting_status);
        $this->assertSame(PayrollRun::PAYMENT_NOT_PAID, $run->payment_status);
        // And a reversed run can no longer be paid.
        $this->pay($run, $finance)->assertSessionHasErrors('payment');
    }

    public function test_settlement_journals_are_source_controlled_and_the_run_stays_immutable(): void
    {
        $run = $this->makeRun();
        $this->pay($run)->assertSessionHasNoErrors();
        $journal = $run->fresh()->activeSettlement->journalEntry;
        $this->actingAs($this->admin())->get(route('admin.accounting.journal-entries.edit', $journal))->assertForbidden();
        $this->actingAs($this->admin())->delete(route('admin.accounting.journal-entries.destroy', $journal))->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.process', $run))->assertSessionHasErrors('payroll');
        $this->actingAs($this->admin())->delete(route('admin.hr.payroll.destroy', $run))->assertSessionHasErrors('payroll');
        $this->assertSame('posted', $journal->fresh()->status);
    }

    // ---------------------------------------------------------------- permissions and scope

    public function test_unauthorized_roles_cannot_pay_and_payroll_view_sees_state_only(): void
    {
        $run = $this->makeRun();
        $viewer = $this->user(['Payroll' => ['view']], suffix: 'viewer');
        $this->pay($run, $viewer)->assertForbidden();
        $this->pay($run, $this->user(['Payroll' => ['view', 'post']], suffix: 'postonly'))->assertForbidden();   // no AP payment right
        $this->pay($run, $this->user(['Payroll' => ['view'], 'Accounts Payable' => ['process']], suffix: 'aponly'))->assertForbidden();   // no Payroll post
        $this->pay($run, $this->user(['Payroll' => ['view', 'approve']], suffix: 'approver'))->assertForbidden();
        $this->assertSame(0, PayrollSettlement::count());

        $this->pay($run)->assertSessionHasNoErrors();
        $page = $this->actingAs($viewer)->get(route('admin.hr.payroll.show', $run))->assertOk();
        $page->assertSee('Salary Payment')->assertSee('Paid')->assertDontSee($this->bank->label())->assertDontSee('Record Salary Payment')->assertDontSee('Reverse Salary Payment')->assertDontSee('BATCH-1');
    }

    public function test_project_scoped_user_cannot_settle_a_company_wide_run(): void
    {
        $run = $this->makeRun();   // Ahmed (mine) + Khalid (theirs)
        $scoped = $this->user(['Payroll' => ['view', 'post', 'approve', 'export'], 'Accounts Payable' => ['view', 'process']], $this->mine, 'scoped');
        $this->assertSame(1, $this->actingAs($scoped)->app->make(PayrollRun::class)->newQuery()->whereKey($run->id)->first()->items()->count(), 'the scoped user sees one of the two rows');
        $this->assertFalse(PayrollSettlementService::coversWholeRun($scoped, $run));

        $this->pay($run, $scoped)->assertForbidden();
        $this->actingAs($scoped)->post(route('admin.hr.payroll.settlement-reverse', $run), ['reason' => 'x'])->assertForbidden();
        $this->assertSame(0, PayrollSettlement::count());
        $this->actingAs($scoped)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('outside your scope')->assertDontSee('Record Salary Payment');

        // A run whose employees are all within the user's project is coverable.
        $this->khalid->update(['project_id' => $this->mine->id]);
        $own = $this->makeRun(10);
        $this->assertTrue(PayrollSettlementService::coversWholeRun($scoped, $own));
        $this->pay($own, $scoped)->assertSessionHasNoErrors();
        $this->assertSame(PayrollRun::PAYMENT_PAID, $own->fresh()->payment_status);
    }

    public function test_employee_workspace_shows_the_payment_state_without_bank_details(): void
    {
        $run = $this->makeRun();
        $this->pay($run)->assertSessionHasNoErrors();
        $page = $this->actingAs($this->user(['HR' => ['view'], 'Payroll' => ['view']], suffix: 'hrpay'))->get(route('admin.hr.employees.show', $this->ahmed))->assertOk();
        $page->assertSee('Salary payment')->assertSee('Paid')->assertSee('Posted to accounting')->assertDontSee($this->bank->label())->assertDontSee('BATCH-1');
        $this->actingAs($this->user(['HR' => ['view']], suffix: 'hronly'))->get(route('admin.hr.employees.show', $this->ahmed))->assertOk()->assertDontSee('id="payroll"', false);
        // Payslip shows the payment state and a masked IBAN only.
        $item = $run->items()->where('employee_id', $this->ahmed->id)->firstOrFail();
        $this->actingAs($this->admin())->get(route('admin.hr.payroll.payslip', [$run, $item->id]))->assertOk()->assertSee('Salary Payment')->assertSee('Paid')->assertSee('SA•• •••• 7519')->assertDontSee('SA0380000000608010167519');
    }
}
