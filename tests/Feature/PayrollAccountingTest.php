<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\OvertimeRecord;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Models\VatTransaction;
use App\Services\Accounting\ProjectCostReport;
use App\Services\Payroll\PayrollAccountingService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Payroll → GL, Phase 1. An approved payroll run posts one original journal
 * (Dr salary expense per employee with the employee's project, Cr payroll
 * payable for the net, Cr deduction liability) through the Finance-configured
 * Payroll posting rule; posting never undoes approval, is idempotent, keeps
 * payroll history immutable and never touches VAT.
 */
class PayrollAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected Project $mine;

    protected Project $theirs;

    protected CostCenter $mineCenter;

    protected Employee $ahmed;

    protected Employee $khalid;

    protected ChartOfAccount $expense;

    protected ChartOfAccount $payable;

    protected ChartOfAccount $deductionAccount;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // Only the two synthetic employees take part: everyone seeded is parked at a project outside the run scope.
        $this->mine = Project::create(['name' => 'Riyadh Commercial Tower', 'code' => 'PRJ-PAY-A', 'status' => 'active']);
        $this->theirs = Project::create(['name' => 'Jeddah Warehouse', 'code' => 'PRJ-PAY-B', 'status' => 'active']);
        $this->mineCenter = CostCenter::create(['code' => 'CC-PAY-A', 'name' => 'Riyadh Commercial Tower', 'type' => 'project', 'linked_id' => $this->mine->id, 'status' => 'active']);
        Employee::query()->update(['status' => 'inactive']);
        $this->ahmed = Employee::create(['employee_code' => 'EMP-PAY-A', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-03-01', 'project_id' => $this->mine->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500]);
        $this->khalid = Employee::create(['employee_code' => 'EMP-PAY-B', 'first_name' => 'Khalid', 'last_name' => 'Otaibi', 'status' => 'active', 'employee_classification' => 'Sponsorship',
            'joining_date' => '2024-01-01', 'project_id' => $this->mine->id, 'basic_salary' => 9000]);
        SalaryStructure::create(['employee_id' => $this->ahmed->id, 'basic_salary' => 6500, 'housing_allowance' => 1500, 'transport_allowance' => 500, 'fixed_deduction' => 300, 'effective_from' => '2026-01-01', 'status' => 'active']);
        OvertimeRecord::create(['employee_id' => $this->khalid->id, 'overtime_date' => '2026-09-15', 'hours' => 4, 'rate' => 50, 'amount' => 200, 'status' => 'approved']);
        OvertimeRecord::create(['employee_id' => $this->khalid->id, 'overtime_date' => '2026-09-16', 'hours' => 2, 'rate' => 50, 'amount' => 100, 'status' => 'pending']);

        $this->expense = ChartOfAccount::where('account_code', '5100')->firstOrFail();
        $this->payable = ChartOfAccount::where('account_code', '2300')->firstOrFail();
        $this->deductionAccount = ChartOfAccount::create(['account_code' => '2320', 'account_name' => 'Payroll Deductions Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
        $this->rule()->update(['deduction_account_id' => $this->deductionAccount->id, 'auto_post' => true, 'status' => 'active']);
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
        $role = Role::create(['name' => 'PAY role '.$suffix, 'code' => 'PAY_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'PAY '.$suffix, 'email' => 'pay-'.$suffix.'@example.test', 'username' => 'pay.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function rule(): AutomaticPostingRule
    {
        return AutomaticPostingRule::where('source_module', 'Payroll')->where('trigger_event', 'Payroll Approved')->firstOrFail();
    }

    /** A run for 2026-$month scoped to the Riyadh project unless $companyWide; processed / approved as asked. */
    protected function makeRun(int $month = 9, string $status = 'draft', bool $companyWide = false): PayrollRun
    {
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.store'), ['payroll_month' => $month, 'payroll_year' => 2026, 'project_id' => $companyWide ? null : $this->mine->id])->assertSessionHasNoErrors();
        $run = PayrollRun::where('payroll_month', $month)->where('payroll_year', 2026)->latest('id')->firstOrFail();
        if (in_array($status, ['processed', 'approved'], true)) {
            $this->actingAs($this->admin())->post(route('admin.hr.payroll.process', $run))->assertSessionHasNoErrors();
        }
        if ($status === 'approved') {
            $this->actingAs($this->admin())->post(route('admin.hr.payroll.approve', $run))->assertSessionHasNoErrors();
        }
        $this->flushSession();

        return $run->fresh();
    }

    protected function postRun(PayrollRun $run, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin())->post(route('admin.hr.payroll.post', $run));
    }

    protected function journalOf(PayrollRun $run): ?JournalEntry
    {
        return JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->first();
    }

    // ------------------------------------------------------------------ state gates

    public function test_draft_and_processed_runs_cannot_post_and_process_creates_no_journal(): void
    {
        $draft = $this->makeRun(1);
        $this->postRun($draft)->assertSessionHasErrors('posting');
        $processed = $this->makeRun(2, 'processed');
        $this->postRun($processed)->assertSessionHasErrors('posting');
        $this->assertSame(0, JournalEntry::where('source_module', 'Payroll')->whereIn('source_id', [$draft->id, $processed->id])->count());
        $this->assertSame(PayrollRun::ACCOUNTING_NOT_POSTED, $processed->fresh()->accounting_status);
        $this->assertSame('processed', $processed->fresh()->status);
    }

    public function test_approval_posts_one_balanced_journal_from_the_configured_rule(): void
    {
        $run = $this->makeRun(9, 'approved');
        $journal = $this->journalOf($run);

        $this->assertSame('approved', $run->status, 'HR status untouched by accounting');
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->accounting_status);
        $this->assertNotNull($journal);
        $this->assertSame($journal->id, $run->journal_entry_id);
        $this->assertSame('posted', $journal->status);
        $this->assertSame('2026-09-30', $journal->journal_date->toDateString(), 'posting date is the payroll period end');
        $this->assertSame($run->code, $journal->reference_number);

        // Ahmed: 6500 + 2000 allowances = 8500 gross, 300 deductions, 8200 net. Khalid: 9000 + 200 approved OT = 9200 gross, 9200 net.
        $lines = $journal->lines;
        $this->assertEqualsWithDelta(17700, (float) $journal->total_debit, 0.01);
        $this->assertEqualsWithDelta(17700, (float) $journal->total_credit, 0.01);
        $expenseLines = $lines->where('chart_of_account_id', $this->expense->id);
        $this->assertCount(2, $expenseLines);
        $this->assertEqualsWithDelta(8500, (float) $expenseLines->first(fn ($l) => str_contains($l->description, 'EMP-PAY-A'))->debit, 0.01, 'basic + allowances = gross');
        $this->assertEqualsWithDelta(9200, (float) $expenseLines->first(fn ($l) => str_contains($l->description, 'EMP-PAY-B'))->debit, 0.01, 'approved overtime only, pending excluded');
        $this->assertEqualsWithDelta(17400, (float) $lines->where('chart_of_account_id', $this->payable->id)->sum('credit'), 0.01, 'payable = total net');
        $this->assertEqualsWithDelta(300, (float) $lines->where('chart_of_account_id', $this->deductionAccount->id)->sum('credit'), 0.01, 'deductions to the mapped liability');
        foreach ($expenseLines as $line) {
            $this->assertSame($this->mine->id, $line->project_id, 'employee project on each expense line');
            $this->assertSame($this->mineCenter->id, $line->cost_center_id, 'single active project cost center attached');
        }
        $this->assertDatabaseHas('activity_logs', ['module' => 'Payroll', 'action' => 'Payroll accounting entry created']);
    }

    public function test_missing_rule_or_accounts_block_safely_and_keep_the_approval(): void
    {
        $this->rule()->update(['status' => 'inactive']);
        $run = $this->makeRun(3, 'approved');
        $this->assertSame('approved', $run->status);
        $this->assertSame(PayrollRun::ACCOUNTING_FAILED, $run->accounting_status);
        $this->assertStringContainsString('No active automatic posting rule', $run->posting_error);
        $this->assertNull($this->journalOf($run));

        $this->rule()->update(['status' => 'active', 'debit_account_id' => null]);
        $this->postRun($run)->assertSessionHasErrors('posting');
        $this->assertStringContainsString('Salary / payroll expense', $run->fresh()->posting_error);

        $this->rule()->update(['debit_account_id' => $this->expense->id, 'credit_account_id' => null]);
        $this->postRun($run)->assertSessionHasErrors('posting');
        $this->assertStringContainsString('Payroll payable', $run->fresh()->posting_error);

        // Wrong account type is refused too.
        $this->rule()->update(['credit_account_id' => $this->expense->id]);
        $this->postRun($run)->assertSessionHasErrors('posting');
        $this->assertStringContainsString('must be a liability account', $run->fresh()->posting_error);

        $this->rule()->update(['credit_account_id' => $this->payable->id]);
        $this->postRun($run)->assertSessionHasNoErrors();
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->fresh()->accounting_status);
        $this->assertNull($run->fresh()->posting_error);
        $this->assertSame(1, JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->count());
    }

    public function test_deductions_without_a_mapped_account_block_posting_with_a_readable_message(): void
    {
        $this->rule()->update(['deduction_account_id' => null]);
        $run = $this->makeRun(4, 'approved');
        $this->assertSame(PayrollRun::ACCOUNTING_FAILED, $run->accounting_status);
        $this->assertStringContainsString('deductions of SAR 300.00 have no accounting account configured', $run->posting_error);
        $this->assertNull($this->journalOf($run));

        $this->actingAs($this->admin())->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('Approved — posting failed')->assertSee('no accounting account configured')->assertSee('Retry Posting');

        $this->rule()->update(['deduction_account_id' => $this->deductionAccount->id]);
        $this->postRun($run)->assertSessionHasNoErrors();
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->fresh()->accounting_status);
    }

    public function test_posting_is_idempotent_for_repeated_post_and_retry(): void
    {
        $run = $this->makeRun(9, 'approved');
        $first = $run->journal_entry_id;
        $this->postRun($run)->assertSessionHasNoErrors();
        $this->postRun($run)->assertSessionHasNoErrors();
        app(PayrollAccountingService::class)->attempt($run->id, $this->admin()->id);
        $this->assertSame(1, JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->count());
        $this->assertSame($first, $run->fresh()->journal_entry_id);

        // An orphaned journal for this run (link lost) is reused instead of duplicated.
        $run->update(['journal_entry_id' => null, 'accounting_status' => PayrollRun::ACCOUNTING_FAILED]);
        $this->postRun($run)->assertSessionHasNoErrors();
        $this->assertSame($first, $run->fresh()->journal_entry_id);
        $this->assertSame(1, JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->count());

        // Approve is not repeatable either.
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.approve', $run))->assertSessionHasErrors('payroll');
    }

    public function test_review_mode_creates_a_draft_journal_that_is_not_called_posted_until_finance_posts_it(): void
    {
        $this->rule()->update(['auto_post' => false]);
        $run = $this->makeRun(9, 'approved');
        $journal = $this->journalOf($run);
        $this->assertSame('draft', $journal->status);
        $this->assertSame(PayrollRun::ACCOUNTING_REVIEW, $run->accounting_status);
        $this->assertNull($run->posted_at);
        $this->actingAs($this->admin())->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('accounting review required')->assertDontSee('Posted to accounting');

        // The payroll journal is source-controlled: no edit, delete or cancel from Accounting.
        $this->actingAs($this->admin())->get(route('admin.accounting.journal-entries.edit', $journal))->assertForbidden();
        $this->actingAs($this->admin())->delete(route('admin.accounting.journal-entries.destroy', $journal))->assertForbidden();

        $this->actingAs($this->admin())->post(route('admin.accounting.journal-entries.post', $journal))->assertSessionHasNoErrors();
        $this->assertSame('posted', $journal->fresh()->status);
        $this->assertSame(PayrollRun::ACCOUNTING_POSTED, $run->fresh()->accounting_status);
        $this->assertNotNull($run->fresh()->posted_at);
    }

    public function test_posted_payroll_is_immutable_and_historical_values_survive_salary_changes(): void
    {
        $run = $this->makeRun(9, 'approved');
        $item = $run->items()->where('employee_id', $this->ahmed->id)->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.hr.payroll.process', $run))->assertSessionHasErrors('payroll');
        $this->actingAs($this->admin())->put(route('admin.hr.payroll.update', $run), ['payroll_month' => 9, 'payroll_year' => 2026, 'notes' => 'changed'])->assertSessionHasErrors('payroll');
        $this->actingAs($this->admin())->delete(route('admin.hr.payroll.destroy', $run))->assertSessionHasErrors('payroll');

        SalaryStructure::where('employee_id', $this->ahmed->id)->update(['status' => 'inactive']);
        SalaryStructure::create(['employee_id' => $this->ahmed->id, 'basic_salary' => 12000, 'fixed_deduction' => 0, 'effective_from' => '2026-01-01', 'status' => 'active']);
        $this->ahmed->update(['basic_salary' => 12000]);

        $this->assertEqualsWithDelta(6500, (float) $item->fresh()->basic_salary, 0.01);
        $this->assertEqualsWithDelta(8200, (float) $item->fresh()->net_amount, 0.01);
        $this->assertEqualsWithDelta(17700, (float) $run->journalEntry->fresh()->total_debit, 0.01);
    }

    public function test_reversal_posts_an_opposite_journal_and_keeps_history(): void
    {
        $run = $this->makeRun(9, 'approved');
        $finance = $this->user(['Payroll' => ['view', 'post', 'approve'], 'Journal Entries' => ['view']], suffix: 'fin');

        $this->actingAs($this->user(['Payroll' => ['view', 'post']], suffix: 'postonly'))->post(route('admin.hr.payroll.reverse', $run), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($finance)->post(route('admin.hr.payroll.reverse', $run), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($finance)->post(route('admin.hr.payroll.reverse', $run), ['reason' => 'Wrong month approved'])->assertSessionHasNoErrors();

        $run->refresh();
        $this->assertSame(PayrollRun::ACCOUNTING_REVERSED, $run->accounting_status);
        $this->assertSame('approved', $run->status, 'the run stays approved; its figures are history');
        $this->assertNotNull($run->reversal_journal_id);
        $this->assertSame('posted', $run->journalEntry->fresh()->status, 'original journal remains');
        $reversal = $run->reversalJournal;
        $this->assertSame('posted', $reversal->status);
        $this->assertEqualsWithDelta((float) $run->journalEntry->total_debit, (float) $reversal->total_credit, 0.01);
        $this->assertEqualsWithDelta(17700, (float) $reversal->lines->where('chart_of_account_id', $this->expense->id)->sum('credit'), 0.01);

        // Same reason repeated = no-op; a different reason is refused; posting again is refused; the run cannot be reprocessed.
        $this->actingAs($finance)->post(route('admin.hr.payroll.reverse', $run), ['reason' => 'Wrong month approved'])->assertSessionHasNoErrors();
        $this->assertSame(1, JournalEntry::where('source_module', 'Manual')->where('source_id', $run->journal_entry_id)->count());
        $this->actingAs($finance)->post(route('admin.hr.payroll.reverse', $run), ['reason' => 'Another reason'])->assertSessionHasErrors('posting');
        $this->postRun($run, $finance)->assertSessionHasNoErrors();
        $this->assertSame(1, JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->count());
        $this->assertSame(PayrollRun::ACCOUNTING_REVERSED, $run->fresh()->accounting_status);
        $this->actingAs($this->admin())->post(route('admin.hr.payroll.process', $run))->assertSessionHasErrors('payroll');
        $this->actingAs($finance)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('Accounting reversed')->assertSee('Wrong month approved')->assertDontSee('Reverse Posting');
    }

    // ------------------------------------------------------------------ VAT, project cost, scope

    public function test_payroll_posting_never_touches_vat(): void
    {
        $vatRows = VatTransaction::count();
        $run = $this->makeRun(9, 'approved');
        $this->assertSame($vatRows, VatTransaction::count());
        $vatAccountIds = ChartOfAccount::whereIn('account_code', ['1300', '2210'])->pluck('id');
        $this->assertSame(0, $run->journalEntry->lines->whereIn('chart_of_account_id', $vatAccountIds)->count());

        // A VAT control account configured as the expense is refused.
        $this->rule()->update(['debit_account_id' => ChartOfAccount::where('account_code', '1300')->value('id')]);
        $other = $this->makeRun(10, 'approved');
        $this->assertSame(PayrollRun::ACCOUNTING_FAILED, $other->accounting_status);
        $this->assertStringContainsString('must be a expense account', $other->posting_error);
    }

    public function test_project_cost_report_picks_up_payroll_once_through_the_ledger(): void
    {
        // Open-ended from the payroll month: the reversing journal is dated the day it is made.
        $period = new ReportPeriod(CarbonImmutable::parse('2026-09-01'), null, 'custom');
        $before = app(ProjectCostReport::class)->rows(Project::whereKey($this->mine->id)->get(), $period)->first()['cost'];

        $run = $this->makeRun(9, 'approved');
        $after = app(ProjectCostReport::class)->rows(Project::whereKey($this->mine->id)->get(), $period)->first()['cost'];
        $this->assertEqualsWithDelta($before + 17700, $after, 0.01, 'gross payroll of the project employees counted exactly once');

        $this->postRun($run)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta($after, app(ProjectCostReport::class)->rows(Project::whereKey($this->mine->id)->get(), $period)->first()['cost'], 0.01, 'a retry adds nothing');

        app(PayrollAccountingService::class)->reverse($run->id, 'test', $this->admin()->id);
        $this->assertEqualsWithDelta($before, app(ProjectCostReport::class)->rows(Project::whereKey($this->mine->id)->get(), $period)->first()['cost'], 0.01, 'reversal removes the cost');
    }

    public function test_required_cost_center_without_a_single_project_center_blocks_posting(): void
    {
        $this->expense->update(['cost_center_required' => true]);
        $this->khalid->update(['project_id' => $this->theirs->id]);   // Theirs has no cost center
        $run = $this->makeRun(9, 'approved', true);
        $this->assertSame(PayrollRun::ACCOUNTING_FAILED, $run->accounting_status);
        $this->assertStringContainsString('requires a cost center', $run->posting_error);
        $this->assertNull($this->journalOf($run));
    }

    public function test_attendance_days_are_informational_and_do_not_change_pay(): void
    {
        $run = $this->makeRun(9, 'processed');
        $item = $run->items()->where('employee_id', $this->ahmed->id)->firstOrFail();
        $this->assertSame(0, $item->present_days);
        $this->assertEqualsWithDelta(8200, (float) $item->net_amount, 0.01, 'no proration without attendance');
        $this->actingAs($this->admin())->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('do not change pay');
    }

    public function test_run_page_shows_totals_pages_items_and_gates_journal_links(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            Employee::create(['employee_code' => 'EMP-PAY-X'.$i, 'first_name' => 'Worker', 'last_name' => (string) $i, 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $this->mine->id, 'basic_salary' => 1000]);
        }
        $run = $this->makeRun(9, 'approved');
        $this->assertSame(28, $run->items()->count());

        $viewer = $this->user(['Payroll' => ['view']], suffix: 'viewer');
        $page = $this->actingAs($viewer)->get(route('admin.hr.payroll.show', $run))->assertOk();
        $page->assertSee('SAR 43,700.00')->assertSee('SAR 43,400.00')->assertSee('Showing 1-25 of 28')->assertSee('page=2')->assertSee('Posted to accounting')
            ->assertDontSee('View Journal')->assertDontSee(route('admin.accounting.journal-entries.show', $run->journalEntry))->assertDontSee('Post to Accounting')->assertDontSee('Reverse Posting');
        $this->actingAs($viewer)->get(route('admin.hr.payroll.show', ['payroll_run' => $run, 'page' => 2]))->assertOk()->assertSee('Showing 26-28 of 28');
        $this->actingAs($viewer)->post(route('admin.hr.payroll.post', $run))->assertForbidden();

        $finance = $this->user(['Payroll' => ['view', 'post', 'approve'], 'Journal Entries' => ['view']], suffix: 'fin2');
        $this->actingAs($finance)->get(route('admin.hr.payroll.show', $run))->assertOk()->assertSee('View Journal')->assertSee($run->journalEntry->journal_number)->assertSee('Reverse Posting');
    }

    public function test_project_scoped_payroll_viewer_sees_only_own_employee_rows_and_cannot_post(): void
    {
        $this->khalid->update(['project_id' => $this->theirs->id]);
        CostCenter::create(['code' => 'CC-PAY-B', 'name' => 'Jeddah Warehouse', 'type' => 'project', 'linked_id' => $this->theirs->id, 'status' => 'active']);
        $run = $this->makeRun(9, 'approved', true);
        $this->assertSame(2, $run->items()->count());

        $scoped = $this->user(['Payroll' => ['view', 'post']], $this->mine, 'scoped');
        $page = $this->actingAs($scoped)->get(route('admin.hr.payroll.show', $run))->assertOk();
        $page->assertSee('EMP-PAY-A')->assertDontSee('EMP-PAY-B');
        // The company journal is already posted with both employees; a scoped viewer cannot create a subset posting.
        $this->assertSame(1, JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->count());
        $this->actingAs($scoped)->post(route('admin.hr.payroll.post', $run))->assertSessionHasNoErrors();
        $this->assertSame(1, JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->count());
        $this->assertEqualsWithDelta(17700, (float) $run->journalEntry->fresh()->total_debit, 0.01);
    }
}
