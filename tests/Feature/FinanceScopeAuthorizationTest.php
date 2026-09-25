<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\PostingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance correctness sprint F07: journals, the general ledger, the financial
 * reports and their exports show a project-scoped user only their own
 * project's figures, never company openings or another project's lines, and
 * VAT returns and CSV exports need their own rights.
 */
class FinanceScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Project $mine;

    private Project $other;

    private User $scoped;

    private ChartOfAccount $cash;

    private ChartOfAccount $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->mine = Project::create(['name' => 'Scoped Project', 'code' => 'PRJ-F07-A', 'status' => 'active']);
        $this->other = Project::create(['name' => 'Other Project', 'code' => 'PRJ-F07-B', 'status' => 'active']);

        $role = Role::create(['name' => 'Project accountant', 'code' => 'PROJECT_ACCOUNTANT_F07', 'level' => 3, 'access_scope' => 'Project Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::whereIn('module', ['Dashboard', 'Journal Entries', 'General Ledger', 'Financial Reports', 'VAT Management'])->pluck('id'));
        $this->scoped = User::create(['name' => 'Scoped Accountant', 'email' => 'scoped-accountant@example.test', 'username' => 'scoped.accountant', 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $this->mine->id]);
        $this->scoped->roles()->attach($role, ['is_primary' => true]);
        $this->scoped = $this->scoped->fresh();

        $this->cash = ChartOfAccount::where('account_code', PostingService::CASH)->firstOrFail();
        $this->expense = ChartOfAccount::create(['account_code' => '5998', 'account_name' => 'Scoped Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'opening_balance' => 0, 'status' => 'active']);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function postedJournal(float $amount, int $projectId, string $date = '2026-02-10'): JournalEntry
    {
        $entry = JournalEntry::create([
            'journal_number' => JournalEntry::nextNumber(2026), 'journal_date' => $date, 'source_module' => 'Manual',
            'description' => 'F07 fixture', 'total_debit' => $amount, 'total_credit' => $amount, 'status' => 'posted', 'posted_at' => now(),
        ]);
        $entry->lines()->createMany([
            ['chart_of_account_id' => $this->expense->id, 'debit' => $amount, 'credit' => 0, 'project_id' => $projectId],
            ['chart_of_account_id' => $this->cash->id, 'debit' => 0, 'credit' => $amount, 'project_id' => $projectId],
        ]);

        return $entry;
    }

    public function test_journals_outside_the_users_projects_are_not_reachable_by_list_url_or_post(): void
    {
        $visible = $this->postedJournal(100, $this->mine->id);
        $hidden = $this->postedJournal(999, $this->other->id);
        $hiddenDraft = $this->postedJournal(5, $this->other->id);
        $hiddenDraft->update(['status' => 'draft']);

        $this->actingAs($this->scoped)->get(route('admin.accounting.journal-entries.index'))
            ->assertOk()->assertSee($visible->journal_number)->assertDontSee($hidden->journal_number);

        $this->actingAs($this->scoped)->get(route('admin.accounting.journal-entries.show', $visible))->assertOk();
        $this->actingAs($this->scoped)->get(route('admin.accounting.journal-entries.show', $hidden))->assertNotFound();
        $this->actingAs($this->scoped)->post(route('admin.accounting.journal-entries.post', $hiddenDraft))->assertNotFound();
        $this->assertSame('draft', $hiddenDraft->fresh()->status);

        // The list totals count only what the user may see.
        $index = $this->actingAs($this->scoped)->get(route('admin.accounting.journal-entries.index'));
        $this->assertSame(1, $index->viewData('postedCount'));
        $this->assertSame(0, $index->viewData('draftCount'));

        // The company-level administrator still sees both.
        $this->actingAs($this->admin())->get(route('admin.accounting.journal-entries.show', $hidden))->assertOk();
    }

    public function test_ledger_and_reports_exclude_other_projects_and_company_openings_for_a_scoped_user(): void
    {
        $this->postedJournal(100, $this->mine->id);
        $this->postedJournal(999, $this->other->id);

        $ledger = $this->actingAs($this->scoped)->get(route('admin.accounting.general-ledger', ['account' => $this->expense->id]))->assertOk();
        $this->assertSame(100.0, $ledger->viewData('totalDebit'));
        $this->assertSame(1, $ledger->viewData('lines')->total());

        $cashLedger = $this->actingAs($this->scoped)->get(route('admin.accounting.general-ledger', ['account' => $this->cash->id]))->assertOk();
        $this->assertSame(0.0, $cashLedger->viewData('openingBalance'), 'company opening balances are not shown to a scoped user');

        $trial = $this->actingAs($this->scoped)->get(route('admin.accounting.reports.trial-balance'))->assertOk();
        $rows = $trial->viewData('rows')->keyBy('account_code');
        $this->assertSame(100.0, $rows['5998']['debit_balance']);
        $this->assertSame(round($trial->viewData('totalDebit'), 2), round($trial->viewData('totalCredit'), 2), 'the scoped ledger still balances because every posting line carries its project');

        $adminTrial = $this->actingAs($this->admin())->get(route('admin.accounting.reports.trial-balance'))->assertOk();
        $this->assertSame(1099.0, $adminTrial->viewData('rows')->keyBy('account_code')['5998']['debit_balance']);
    }

    public function test_exports_need_the_export_right_and_vat_returns_need_company_scope(): void
    {
        $role = Role::create(['name' => 'Report viewer', 'code' => 'REPORT_VIEWER_F07', 'level' => 3, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->sync(Permission::where('module', 'Financial Reports')->where('action', 'view')->orWhere(fn ($q) => $q->where('module', 'Dashboard')->where('action', 'view'))->pluck('id'));
        $viewer = User::create(['name' => 'Report Viewer', 'email' => 'report-viewer@example.test', 'username' => 'report.viewer', 'password' => 'a-strong-password-123', 'status' => 'active']);
        $viewer->roles()->attach($role, ['is_primary' => true]);
        $viewer = $viewer->fresh();

        $this->actingAs($viewer)->get(route('admin.accounting.reports.trial-balance'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.accounting.reports.trial-balance', ['export' => 'csv']))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.accounting.reports.trial-balance', ['export' => 'csv']))->assertOk();

        // VAT is a company return: the scoped accountant holds the module permission but not the scope.
        $this->actingAs($this->scoped)->get(route('admin.accounting.vat.index'))->assertForbidden();
        $this->actingAs($this->scoped)->get(route('admin.accounting.reports.vat-report'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.accounting.vat.index'))->assertOk();
    }

    public function test_a_scoped_user_cannot_write_journal_lines_into_another_project(): void
    {
        $payload = fn (int $projectId) => [
            'journal_date' => '2026-02-11', 'source_module' => 'Manual', 'status' => 'draft', 'description' => 'Scoped entry',
            'lines' => [
                ['chart_of_account_id' => $this->expense->id, 'debit' => 40, 'credit' => 0, 'project_id' => $projectId],
                ['chart_of_account_id' => $this->cash->id, 'debit' => 0, 'credit' => 40, 'project_id' => $projectId],
            ],
        ];

        $before = JournalEntry::withoutGlobalScopes()->count();
        $this->actingAs($this->scoped)->post(route('admin.accounting.journal-entries.store'), $payload($this->other->id))->assertForbidden();
        $this->assertSame($before, JournalEntry::withoutGlobalScopes()->count());

        $this->actingAs($this->scoped)->post(route('admin.accounting.journal-entries.store'), $payload($this->mine->id))->assertSessionHasNoErrors();
        $this->assertSame($before + 1, JournalEntry::withoutGlobalScopes()->count());

        // Lines left without a project default to the user's own project rather than escaping scope.
        $blank = $payload($this->mine->id);
        $blank['lines'][0]['project_id'] = null;
        $blank['lines'][1]['project_id'] = null;
        $this->actingAs($this->scoped)->post(route('admin.accounting.journal-entries.store'), $blank)->assertSessionHasNoErrors();
        $this->assertSame($this->mine->id, JournalEntry::withoutGlobalScopes()->latest('id')->first()->lines()->first()->project_id);
    }
}
