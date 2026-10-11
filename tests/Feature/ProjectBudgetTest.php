<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetLine;
use App\Models\Role;
use App\Models\Site;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Project Budget / BOQ Phase 1: draft → lines → Set as Current → revision.
 * Planning only: no accounting or stock side effects, project-scoped,
 * server-calculated amounts, immutable history, permission-gated values.
 */
class ProjectBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected Project $other;

    protected Site $siteA;

    protected Site $siteB;

    protected Site $foreignSite;

    protected Item $item;

    protected ChartOfAccount $material;

    protected ChartOfAccount $salary;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->project = Project::create(['code' => 'BOQ-A', 'name' => 'Riyadh Commercial Tower', 'budget' => 500000, 'status' => 'active']);
        $this->other = Project::create(['code' => 'BOQ-B', 'name' => 'Jeddah Warehouse', 'budget' => 90000, 'status' => 'active']);
        $this->siteA = Site::create(['name' => 'Tower Main Site', 'code' => 'BOQ-A-S1', 'project_id' => $this->project->id, 'status' => 'active']);
        $this->siteB = Site::create(['name' => 'Tower Annex', 'code' => 'BOQ-A-S2', 'project_id' => $this->project->id, 'status' => 'active']);
        $this->foreignSite = Site::create(['name' => 'Jeddah Site', 'code' => 'BOQ-B-S1', 'project_id' => $this->other->id, 'status' => 'active']);
        $unit = Unit::create(['code' => 'BOQ-T', 'name' => 'Tonne']);
        $this->material = ChartOfAccount::where('account_code', '5200')->firstOrFail();
        $this->salary = ChartOfAccount::where('account_code', '5100')->firstOrFail();
        $this->item = Item::create(['item_code' => 'BOQ-STEEL', 'name' => 'Reinforcement steel 16mm', 'unit_id' => $unit->id, 'status' => 'active', 'expense_account_id' => $this->material->id]);
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, array<int, string>> $grants */
    protected function user(array $grants, ?Project $project = null, string $suffix = 'u'): User
    {
        $suffix .= '-'.++$this->seq;
        $role = Role::create(['name' => 'BOQ role '.$suffix, 'code' => 'BOQ_ROLE_'.strtoupper(str_replace('-', '_', $suffix)), 'level' => 4, 'access_scope' => $project ? 'Project Level' : 'Company Level', 'status' => 'active']);
        $ids = collect();
        foreach ($grants as $module => $actions) {
            $ids = $ids->merge(Permission::where('module', $module)->whereIn('action', $actions)->pluck('id'));
        }
        $role->permissions()->sync($ids->all());
        $user = User::create(['name' => 'BOQ '.$suffix, 'email' => 'boq-'.$suffix.'@example.test', 'username' => 'boq.'.$suffix, 'password' => 'a-strong-password-123', 'status' => 'active', 'project_id' => $project?->id]);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user->fresh();
    }

    protected function draft(?Project $project = null, ?User $as = null): ProjectBudget
    {
        $project ??= $this->project;
        $this->actingAs($as ?? $this->admin)->post(route('admin.master.projects.budget.store', $project), ['title' => 'Baseline'])->assertSessionHasNoErrors();

        return $project->budgets()->where('status', ProjectBudget::STATUS_DRAFT)->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    protected function addLine(ProjectBudget $budget, array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.master.projects.budget.lines.store', [$budget->project, $budget->id]), $overrides + [
            'cost_code' => 'MAT-001', 'category' => 'materials', 'description' => 'Reinforcement steel', 'quantity' => 100, 'rate' => 12.5, 'chart_of_account_id' => $this->material->id,
        ]);
    }

    protected function finalize(ProjectBudget $budget, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.master.projects.budget.finalize', [$budget->project, $budget->id]));
    }

    protected function show(?User $as = null, array $query = []): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->get(route('admin.master.projects.budget.show', [$this->project, ...$query]));
    }

    // ---------------------------------------------------------------- lifecycle

    public function test_create_draft_is_project_scoped_and_one_draft_at_a_time(): void
    {
        $this->show()->assertOk()->assertSee('No detailed budget has been created for this project yet.')->assertSee('Create Detailed Budget');
        $budget = $this->draft();
        $this->assertSame('BOQ-A-B01', $budget->reference);
        $this->assertSame(1, $budget->version);
        $this->assertTrue($budget->isDraft());
        $this->assertFalse($budget->is_current);
        $this->assertSame($this->admin->id, $budget->created_by);
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.store', $this->project))->assertSessionHasErrors('budget');
        $this->assertSame(1, ProjectBudget::count());

        // A user scoped to the other project cannot reach this project's budget at all.
        $scoped = $this->user(['Projects' => ['view', 'create', 'edit', 'approve']], $this->other, 'scoped');
        $this->actingAs($scoped)->get(route('admin.master.projects.budget.show', $this->project))->assertNotFound();
        $this->actingAs($scoped)->post(route('admin.master.projects.budget.store', $this->project))->assertNotFound();
        $this->actingAs($scoped)->post(route('admin.master.projects.budget.lines.store', [$this->project, $budget->id]), ['cost_code' => 'X-1', 'category' => 'other', 'description' => 'x', 'amount' => 1])->assertNotFound();
        $this->actingAs($scoped)->get(route('admin.master.projects.budget.show', $this->other))->assertOk();
        $this->assertSame(1, ProjectBudget::count());
    }

    public function test_line_relations_are_validated_and_amount_is_calculated_on_the_server(): void
    {
        $budget = $this->draft();
        $this->addLine($budget, ['cost_code' => 'mat-001', 'site_id' => $this->siteA->id, 'item_id' => $this->item->id, 'amount' => 999999])->assertSessionHasNoErrors();
        $line = $budget->lines()->firstOrFail();
        $this->assertSame('MAT-001', $line->cost_code, 'cost code normalised');
        $this->assertEqualsWithDelta(1250, (float) $line->amount, 0.001, 'quantity × rate on the server; the submitted total is ignored');
        $this->assertSame($this->item->unit_id, $line->unit_id, 'unit taken from the item');
        $this->assertFalse($line->isLumpSum());

        // Site must belong to this project; a forged or foreign site is refused.
        $this->addLine($budget, ['cost_code' => 'MAT-002', 'site_id' => $this->foreignSite->id])->assertSessionHasErrors('site_id');
        $this->addLine($budget, ['cost_code' => 'MAT-002', 'site_id' => 999999])->assertSessionHasErrors('site_id');
        // Item must exist and be active.
        $this->item->update(['status' => 'inactive']);
        $this->addLine($budget, ['cost_code' => 'MAT-002', 'item_id' => $this->item->id])->assertSessionHasErrors('item_id');
        $this->item->update(['status' => 'active']);
        $this->addLine($budget, ['cost_code' => 'MAT-002', 'item_id' => 999999])->assertSessionHasErrors('item_id');
        // Account must be an active expense account.
        $this->addLine($budget, ['cost_code' => 'MAT-002', 'chart_of_account_id' => ChartOfAccount::where('account_code', '2300')->value('id')])->assertSessionHasErrors('chart_of_account_id');
        $inactive = ChartOfAccount::create(['account_code' => '5999', 'account_name' => 'Old expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'status' => 'inactive']);
        $this->addLine($budget, ['cost_code' => 'MAT-002', 'chart_of_account_id' => $inactive->id])->assertSessionHasErrors('chart_of_account_id');
        // One account belongs to one cost code within the version.
        $this->addLine($budget, ['cost_code' => 'MAT-002'])->assertSessionHasErrors('chart_of_account_id');
        $this->addLine($budget, ['cost_code' => 'MAT-001', 'description' => 'More steel', 'quantity' => 10, 'rate' => 5])->assertSessionHasNoErrors();
        // Lump sum: no quantity / rate, explicit amount; neither → refused; negative → refused.
        $this->addLine($budget, ['cost_code' => 'SUB-001', 'category' => 'subcontract', 'description' => 'Facade subcontract', 'quantity' => null, 'rate' => null, 'amount' => 50000, 'chart_of_account_id' => null])->assertSessionHasNoErrors();
        $lump = $budget->lines()->where('cost_code', 'SUB-001')->firstOrFail();
        $this->assertTrue($lump->isLumpSum());
        $this->assertNull($lump->quantity);
        $this->assertEqualsWithDelta(50000, (float) $lump->amount, 0.001);
        $this->addLine($budget, ['cost_code' => 'SUB-002', 'quantity' => null, 'rate' => null, 'amount' => null, 'chart_of_account_id' => null])->assertSessionHasErrors('amount');
        $this->addLine($budget, ['cost_code' => 'SUB-002', 'quantity' => -1, 'chart_of_account_id' => null])->assertSessionHasErrors('quantity');
        $this->addLine($budget, ['cost_code' => 'bad code!', 'chart_of_account_id' => null])->assertSessionHasErrors('cost_code');
        $this->addLine($budget, ['cost_code' => 'OTH-001', 'category' => 'imaginary', 'chart_of_account_id' => null])->assertSessionHasErrors('category');

        $budget->refresh();
        $this->assertSame(3, $budget->line_count);
        $this->assertEqualsWithDelta(1250 + 50 + 50000, (float) $budget->total_amount, 0.001);
        $this->assertEqualsWithDelta(500000, (float) $this->project->fresh()->budget, 0.001, 'master budget untouched');
    }

    public function test_draft_is_editable_and_current_budget_is_immutable(): void
    {
        $budget = $this->draft();
        $this->addLine($budget)->assertSessionHasNoErrors();
        $line = $budget->lines()->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.master.projects.budget.lines.edit', [$this->project, $budget->id, $line->id]))->assertOk()->assertSee('Save Line');
        $this->actingAs($this->admin)->put(route('admin.master.projects.budget.lines.update', [$this->project, $budget->id, $line->id]), ['cost_code' => 'MAT-001', 'category' => 'materials', 'description' => 'Steel (revised)', 'quantity' => 120, 'rate' => 12.5, 'chart_of_account_id' => $this->material->id])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1500, (float) $line->fresh()->amount, 0.001);
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.lines.copy', [$this->project, $budget->id, $line->id]))->assertSessionHasNoErrors();
        $this->assertSame(2, $budget->lines()->count());
        $copy = $budget->lines()->reorder('id', 'desc')->first();
        $this->actingAs($this->admin)->delete(route('admin.master.projects.budget.lines.destroy', [$this->project, $budget->id, $copy->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, $budget->lines()->count());

        $this->finalize($budget)->assertSessionHasNoErrors();
        $budget->refresh();
        $this->assertTrue($budget->isCurrent());
        $this->assertTrue($budget->is_current);
        $this->assertNotNull($budget->finalized_at);
        $this->assertSame($this->admin->id, $budget->finalized_by);
        $this->assertSame($budget->id, $this->project->fresh()->currentBudget->id);

        $this->addLine($budget, ['cost_code' => 'MAT-009', 'chart_of_account_id' => null])->assertSessionHasErrors('budget');
        $this->actingAs($this->admin)->put(route('admin.master.projects.budget.lines.update', [$this->project, $budget->id, $line->id]), ['cost_code' => 'MAT-001', 'category' => 'materials', 'description' => 'Hack', 'amount' => 1])->assertSessionHasErrors('budget');
        $this->actingAs($this->admin)->delete(route('admin.master.projects.budget.lines.destroy', [$this->project, $budget->id, $line->id]))->assertSessionHasErrors('budget');
        $this->actingAs($this->admin)->delete(route('admin.master.projects.budget.destroy', [$this->project, $budget->id]))->assertSessionHasErrors('budget');
        $this->actingAs($this->admin)->get(route('admin.master.projects.budget.lines.edit', [$this->project, $budget->id, $line->id]))->assertRedirect();
        $this->assertSame('Steel (revised)', $line->fresh()->description);
        $this->assertSame(1, $budget->lines()->count());
        $this->show()->assertOk()->assertSee('Current budget')->assertDontSee('Add Line')->assertDontSee('Set as Current Budget');
        $this->finalize($budget)->assertSessionHasNoErrors();   // repeated: idempotent
        $this->assertSame(1, ProjectBudget::where('project_id', $this->project->id)->where('is_current', true)->count());
    }

    public function test_finalize_needs_lines_valid_relations_and_approve_permission(): void
    {
        $budget = $this->draft();
        $this->finalize($budget)->assertSessionHasErrors('budget');
        $this->addLine($budget, ['site_id' => $this->siteB->id])->assertSessionHasNoErrors();
        // A site that later moved to another project invalidates the line at finalization.
        $this->siteB->update(['project_id' => $this->other->id]);
        $this->finalize($budget)->assertSessionHasErrors('budget');
        $this->assertTrue($budget->fresh()->isDraft());
        $this->siteB->update(['project_id' => $this->project->id]);

        $editor = $this->user(['Projects' => ['view', 'edit']], suffix: 'editor');
        $this->finalize($budget, $editor)->assertForbidden();
        $viewer = $this->user(['Projects' => ['view']], suffix: 'viewer');
        $this->addLine($budget, ['cost_code' => 'MAT-002', 'chart_of_account_id' => null], $viewer)->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.master.projects.budget.store', $this->other))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.master.projects.budget.revise', [$this->project, $budget->id]))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.master.projects.budget.destroy', [$this->project, $budget->id]))->assertForbidden();
        $this->show($viewer)->assertOk()->assertSee('MAT-001')->assertDontSee('Set as Current Budget');

        $approver = $this->user(['Projects' => ['view', 'approve']], suffix: 'approver');
        $this->finalize($budget, $approver)->assertSessionHasNoErrors();
        $this->assertTrue($budget->fresh()->isCurrent());
    }

    public function test_revision_copies_lines_preserves_history_and_keeps_one_current_version(): void
    {
        $v1 = $this->draft();
        $this->addLine($v1)->assertSessionHasNoErrors();
        $this->addLine($v1, ['cost_code' => 'LAB-001', 'category' => 'labour', 'description' => 'Site labour', 'quantity' => 200, 'rate' => 40, 'chart_of_account_id' => $this->salary->id])->assertSessionHasNoErrors();
        $this->finalize($v1)->assertSessionHasNoErrors();
        $v1->refresh();
        $this->assertEqualsWithDelta(1250 + 8000, (float) $v1->total_amount, 0.001);

        // Only the current version can be revised; one draft at a time.
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.revise', [$this->project, $v1->id]))->assertSessionHasNoErrors();
        $v2 = $this->project->budgets()->where('version', 2)->firstOrFail();
        $this->assertTrue($v2->isDraft());
        $this->assertSame('BOQ-A-B02', $v2->reference);
        $this->assertSame($v1->id, $v2->based_on_budget_id);
        $this->assertSame(2, $v2->lines()->count());
        $this->assertEqualsWithDelta((float) $v1->total_amount, (float) $v2->total_amount, 0.001);
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.revise', [$this->project, $v1->id]))->assertSessionHasErrors('budget');

        // Edit the revision: v1 stays exactly as it was.
        $v2Lab = $v2->lines()->where('cost_code', 'LAB-001')->firstOrFail();
        $this->actingAs($this->admin)->put(route('admin.master.projects.budget.lines.update', [$this->project, $v2->id, $v2Lab->id]), ['cost_code' => 'LAB-001', 'category' => 'labour', 'description' => 'Site labour', 'quantity' => 250, 'rate' => 40, 'chart_of_account_id' => $this->salary->id])->assertSessionHasNoErrors();
        $this->addLine($v2, ['cost_code' => 'EQP-001', 'category' => 'equipment', 'description' => 'Crane hire', 'quantity' => null, 'rate' => null, 'amount' => 30000, 'chart_of_account_id' => null])->assertSessionHasNoErrors();
        $this->assertSame(2, $v1->lines()->count());
        $this->assertEqualsWithDelta(8000, (float) $v1->lines()->where('cost_code', 'LAB-001')->value('amount'), 0.001);
        $this->assertEqualsWithDelta(1250 + 8000, (float) $v1->fresh()->total_amount, 0.001);
        $this->assertTrue($v1->fresh()->isCurrent(), 'v1 stays current while v2 is a draft');

        $this->finalize($v2)->assertSessionHasNoErrors();
        $v1->refresh();
        $v2->refresh();
        $this->assertTrue($v2->isCurrent());
        $this->assertSame(ProjectBudget::STATUS_SUPERSEDED, $v1->status);
        $this->assertFalse($v1->is_current);
        $this->assertNotNull($v1->superseded_at);
        $this->assertSame(2, $v1->lines()->count(), 'old version keeps its lines');
        $this->assertSame(1, ProjectBudget::where('project_id', $this->project->id)->where('is_current', true)->count());
        $this->assertEqualsWithDelta(1250 + 10000 + 30000, (float) $v2->total_amount, 0.001);
        $this->assertSame($v2->id, $this->project->fresh()->currentBudget->id);

        // A superseded version cannot be made current again or revised; it stays readable.
        $this->finalize($v1)->assertSessionHasErrors('budget');
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.revise', [$this->project, $v1->id]))->assertSessionHasErrors('budget');
        $this->actingAs($this->admin)->delete(route('admin.master.projects.budget.destroy', [$this->project, $v1->id]))->assertSessionHasErrors('budget');
        $this->show(query: ['version' => 1])->assertOk()->assertSee('Superseded')->assertSee('BOQ-A-B01')->assertSee('8,000.00');
        $this->show(query: ['version' => 7])->assertNotFound();
        $this->assertEqualsWithDelta(500000, (float) $this->project->fresh()->budget, 0.001, 'master budget never overwritten');
        $this->show()->assertOk()->assertSee('Master budget')->assertSee('500,000.00')->assertSee('Detailed current budget')->assertSee('41,250.00');
    }

    public function test_budget_actions_never_create_accounting_stock_or_documents(): void
    {
        $counts = fn () => [DB::table('journal_entries')->count(), DB::table('journal_entry_lines')->count(), DB::table('stock_ledger_entries')->count(), DB::table('supplier_bills')->count(), DB::table('customer_invoices')->count(), DB::table('payroll_runs')->count(), DB::table('warehouse_stocks')->sum('quantity')];
        $before = $counts();
        $v1 = $this->draft();
        $this->addLine($v1)->assertSessionHasNoErrors();
        $this->finalize($v1)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.revise', [$this->project, $v1->id]))->assertSessionHasNoErrors();
        $v2 = $this->project->budgets()->where('version', 2)->firstOrFail();
        $this->finalize($v2)->assertSessionHasNoErrors();
        $this->assertSame($before, $counts());
    }

    public function test_draft_can_be_deleted_but_history_cannot(): void
    {
        $v1 = $this->draft();
        $this->addLine($v1)->assertSessionHasNoErrors();
        $this->finalize($v1)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.revise', [$this->project, $v1->id]))->assertSessionHasNoErrors();
        $v2 = $this->project->budgets()->where('version', 2)->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.master.projects.budget.destroy', [$this->project, $v2->id]))->assertSessionHasNoErrors();
        // Discarded, not destroyed: the version stays readable in history and its number is never reused.
        $v2->refresh();
        $this->assertSame(ProjectBudget::STATUS_DISCARDED, $v2->status);
        $this->assertFalse($v2->is_current);
        $this->assertSame(1, ProjectBudgetLine::where('project_budget_id', $v2->id)->count());
        $this->assertTrue($v1->fresh()->isCurrent());
        $this->assertSame(1, $v1->lines()->count());
        $this->addLine($v2, ['cost_code' => 'MAT-009', 'chart_of_account_id' => null])->assertSessionHasErrors('budget');
        $this->finalize($v2)->assertSessionHasErrors('budget');
        $this->show(query: ['version' => 2])->assertOk()->assertSee('Discarded draft');
        $this->show()->assertOk()->assertSee('BOQ-A-B01')->assertSee('Current budget');
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.revise', [$this->project, $v1->id]))->assertSessionHasNoErrors();
        $this->assertSame(3, (int) $this->project->budgets()->max('version'));
        $this->assertSame(3, ProjectBudget::where('project_id', $this->project->id)->count());
    }

    // ---------------------------------------------------------------- views, permissions, security

    public function test_view_is_read_only_paged_and_query_bounded(): void
    {
        $budget = $this->draft();
        foreach (range(1, 30) as $i) {
            $budget->lines()->create(['sort_order' => $i, 'cost_code' => 'MAT-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'category' => 'materials', 'description' => 'Line '.$i, 'quantity' => $i, 'rate' => 10, 'amount' => $i * 10, 'chart_of_account_id' => $i === 1 ? $this->material->id : null, 'item_id' => $i % 2 ? $this->item->id : null]);
        }
        app(\App\Services\Projects\ProjectBudgetService::class)->recalculate($budget);

        $writes = [];
        $queries = 0;
        DB::listen(function ($query) use (&$writes, &$queries) {
            $queries++;
            if (preg_match('/^\s*(insert|update|delete)/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $page = $this->show()->assertOk()->assertSee('Showing 1-25 of 30');
        $this->assertSame([], $writes, 'GET view performs no insert / update / delete');
        $this->assertLessThan(45, $queries, 'the page runs a bounded number of queries regardless of line count');
        $this->assertSame(25, $page->viewData('lines')->count());
        $this->assertSame('MAT-001', $page->viewData('lines')->first()->cost_code);
        $this->assertSame('MAT-025', $page->viewData('lines')->last()->cost_code);
        $this->assertCount(30, $page->viewData('report')['rows'], 'Budget vs Actual groups every cost code of the version');
        $second = $this->show(query: ['page' => 2])->assertOk()->assertSee('Showing 26-30 of 30');
        $this->assertSame(['MAT-026', 'MAT-027', 'MAT-028', 'MAT-029', 'MAT-030'], $second->viewData('lines')->pluck('cost_code')->all());
        $filtered = $this->show(query: ['cost_code' => 'MAT-03'])->assertOk();
        $this->assertSame(['MAT-030'], $filtered->viewData('lines')->pluck('cost_code')->all());
        $this->show(query: ['site' => $this->foreignSite->id])->assertNotFound();
        $this->show(query: ['category' => 'labour'])->assertOk()->assertSee('No lines match the filter.');
    }

    public function test_financial_values_are_hidden_without_financial_reports_view(): void
    {
        $budget = $this->draft();
        $this->addLine($budget)->assertSessionHasNoErrors();
        $this->finalize($budget)->assertSessionHasNoErrors();
        $planner = $this->user(['Projects' => ['view', 'edit']], suffix: 'planner');
        $page = $this->show($planner)->assertOk();
        $page->assertSee('1,250.00')->assertSee('Financial values are shown to users with Financial Reports view')->assertDontSee('Actual cost (posted)')->assertDontSee('Committed (open POs)')->assertDontSee('Export CSV');
        $this->actingAs($planner)->get(route('admin.master.projects.budget.export', [$this->project, $budget->id]))->assertForbidden();

        $finance = $this->user(['Projects' => ['view', 'export'], 'Financial Reports' => ['view']], suffix: 'finance');
        $this->show($finance)->assertOk()->assertSee('Actual cost (posted)')->assertSee('Committed (open POs)')->assertSee('Export CSV');
        $csv = $this->actingAs($finance)->get(route('admin.master.projects.budget.export', [$this->project, $budget->id]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Category,"Cost Code",Description', $csv);
        $this->assertStringContainsString('Materials,MAT-001,"Reinforcement steel"', $csv);
        $this->assertStringContainsString('Total,', $csv);
        $exportOnly = $this->user(['Projects' => ['view', 'export']], suffix: 'exportonly');
        $this->actingAs($exportOnly)->get(route('admin.master.projects.budget.export', [$this->project, $budget->id]))->assertForbidden();
    }

    public function test_activity_entries_and_permission(): void
    {
        $v1 = $this->draft();
        $this->addLine($v1)->assertSessionHasNoErrors();
        $this->finalize($v1)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.revise', [$this->project, $v1->id]))->assertSessionHasNoErrors();
        foreach (['Created project budget', 'Added budget line', 'Set current project budget', 'Created budget revision'] as $action) {
            $this->assertTrue(ActivityLog::where('module', 'Projects')->where('action', $action)->where('description', 'like', '[Project #'.$this->project->id.'] BOQ-A%')->exists(), $action);
        }
        $this->show()->assertOk()->assertSee('Set current project budget');
        $this->show($this->user(['Projects' => ['view']], suffix: 'noact'))->assertOk()->assertDontSee('id="activity"', false);
        // The Project workspace Activity panel (entity token) also carries the budget events.
        $html = $this->actingAs($this->admin)->getJson(route('admin.master.projects.workspace.panel', [$this->project, 'activity']))->assertOk()->json('html');
        $this->assertStringContainsString('Set current project budget', $html);
    }

    public function test_project_workspace_shows_budget_tab_header_and_finance_rows(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.projects.show', $this->project))->assertOk()->assertSee('Budget / BOQ')->assertSee('Detailed current budget: none yet');
        $v1 = $this->draft();
        $this->addLine($v1)->assertSessionHasNoErrors();
        $this->finalize($v1)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('admin.master.projects.show', $this->project))->assertOk()->assertSee('Detailed current budget: SAR 1,250.00 (BOQ-A-B01)')->assertSee('Remaining detailed budget (SAR)');
        $html = $this->actingAs($this->admin)->getJson(route('admin.master.projects.workspace.panel', [$this->project, 'finance']))->assertOk()->json('html');
        $this->assertStringContainsString('Detailed current budget', $html);
        $this->assertStringContainsString('Open Budget / BOQ', $html);
        $this->assertStringContainsString('Master budget', $html);
    }

    public function test_budget_and_line_ids_from_another_project_are_not_found_through_this_project(): void
    {
        $mine = $this->draft();
        $this->addLine($mine)->assertSessionHasNoErrors();
        $theirs = $this->draft($this->other);
        $this->addLine($theirs, ['chart_of_account_id' => null])->assertSessionHasNoErrors();
        $theirLine = $theirs->lines()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.lines.store', [$this->project, $theirs->id]), ['cost_code' => 'X-1', 'category' => 'other', 'description' => 'x', 'amount' => 1])->assertNotFound();
        $this->actingAs($this->admin)->put(route('admin.master.projects.budget.lines.update', [$this->project, $mine->id, $theirLine->id]), ['cost_code' => 'X-1', 'category' => 'other', 'description' => 'x', 'amount' => 1])->assertNotFound();
        $this->actingAs($this->admin)->delete(route('admin.master.projects.budget.lines.destroy', [$this->project, $mine->id, $theirLine->id]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('admin.master.projects.budget.finalize', [$this->project, $theirs->id]))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.master.projects.budget.export', [$this->project, $theirs->id]))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('admin.master.projects.budget.destroy', [$this->project, $theirs->id]))->assertNotFound();
        $this->assertTrue($theirs->fresh()->isDraft());
        $this->assertSame(1, $theirs->lines()->count());
        // A payload project_id is prohibited; the route project stays authoritative.
        $this->addLine($mine, ['cost_code' => 'MAT-002', 'chart_of_account_id' => null, 'project_id' => $this->other->id])->assertSessionHasErrors('project_id');
    }
}
