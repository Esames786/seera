<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectReportsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_can_filter_real_project_portfolio_without_financial_recalculation(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $project = Project::firstOrFail();

        $this->actingAs($admin)->get(route('admin.reports.projects.index', ['search' => $project->code]))
            ->assertOk()
            ->assertSee($project->code)
            ->assertSee('Project portfolio')
            ->assertSee(route('admin.accounting.reports.project-cost-report'))
            ->assertSee('name="search"', false)
            ->assertSee('name="status"', false);
    }

    public function test_arabic_portfolio_translates_labels_without_changing_status_values(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.reports.projects.index'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('التقارير / تقارير المشاريع')
            ->assertSee('محفظة المشاريع')
            ->assertSee('name="status"', false)
            ->assertSee('value="active"', false);
    }

    public function test_project_level_user_sees_only_accessible_projects(): void
    {
        $project = Project::firstOrFail();
        $outside = Project::create([
            'name' => 'Private Outside Project',
            'code' => 'TEST-OUTSIDE-PORTFOLIO',
            'status' => 'active',
        ]);
        $role = Role::where('code', 'PROJECT_MANAGER')->firstOrFail();
        $actor = $role->users()->firstOrFail();
        $actor->update(['project_id' => $project->id]);
        $project->update(['manager_id' => $actor->id]);
        // The seeded Project Manager role holds Financial Reports view, so the ledger-report link is legitimately shown.
        $this->assertTrue($actor->fresh()->hasPermission('Financial Reports', 'view'));

        $response = $this->actingAs($actor)->get(route('admin.reports.projects.index'));
        $response->assertOk()
            ->assertSee($project->code)
            ->assertDontSee($outside->code)
            ->assertSee(route('admin.accounting.reports.project-cost-report'));
        $visible = Project::withoutGlobalScopes()->whereKey(app(\App\Services\UserAccessScopeService::class)->projectIdsFor($actor->fresh()))->pluck('code');
        foreach (Project::withoutGlobalScopes()->whereNotIn('code', $visible)->pluck('code') as $hidden) {
            $response->assertDontSee($hidden);
        }

        // A forged search for an out-of-scope project reveals no row (the typed term is only echoed back in the search box).
        $this->actingAs($actor)->get(route('admin.reports.projects.index', ['search' => $outside->code]))
            ->assertOk()->assertDontSee('<td>'.$outside->code.'</td>', false)->assertSee('No projects match your access scope and selected filters.');

        // Without Financial Reports view the same user sees the hidden-figures note instead of the ledger-report link.
        $role->permissions()->detach(Permission::where('module', 'Financial Reports')->pluck('id'));
        $this->actingAs($actor->fresh())->get(route('admin.reports.projects.index'))
            ->assertOk()
            ->assertSee($project->code)
            ->assertDontSee(route('admin.accounting.reports.project-cost-report'))
            ->assertSee('Financial figures are hidden because you do not have Financial Reports permission.');
    }

    public function test_company_level_user_without_financial_reports_sees_all_projects_but_no_ledger_link(): void
    {
        $actor = Role::where('code', 'MARKETING_MANAGER')->firstOrFail()->users()->firstOrFail();
        $this->assertFalse($actor->hasPermission('Financial Reports', 'view'));

        $response = $this->actingAs($actor)->get(route('admin.reports.projects.index'))->assertOk();
        foreach (Project::withoutGlobalScopes()->pluck('code') as $code) {
            $response->assertSee($code);
        }
        $response->assertDontSee(route('admin.accounting.reports.project-cost-report'))
            ->assertSee('Financial figures are hidden because you do not have Financial Reports permission.');
    }

    public function test_site_level_user_cannot_reveal_projects_outside_the_assigned_site(): void
    {
        $actor = Role::where('code', 'SITE_SUPERVISOR')->firstOrFail()->users()->firstOrFail();
        $site = \App\Models\Site::firstOrFail();
        $actor->update(['project_id' => $site->project_id, 'site_id' => $site->id]);
        $outside = Project::withoutGlobalScopes()->whereKeyNot($site->project_id)->firstOrFail();

        // The seeded Site In-Charge role holds Projects view; the site scope still limits the listing to the site's project.
        $this->assertTrue($actor->fresh()->hasPermission('Projects', 'view'));
        $response = $this->actingAs($actor->fresh())->get(route('admin.reports.projects.index'));
        $response->assertOk()->assertSee($site->project->code)->assertDontSee($outside->code);
        $this->actingAs($actor->fresh())->get(route('admin.reports.projects.index', ['search' => $outside->code]))
            ->assertOk()->assertDontSee('<td>'.$outside->code.'</td>', false)->assertSee('No projects match your access scope and selected filters.');
    }

    public function test_status_filter_rejects_unknown_values_and_filters_real_statuses(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $project = Project::firstOrFail();
        $project->update(['status' => 'on hold']);

        $this->actingAs($admin)->get(route('admin.reports.projects.index', ['status' => 'on hold']))
            ->assertOk()->assertSee($project->code);
        $other = Project::withoutGlobalScopes()->whereKeyNot($project->id)->where('status', 'active')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.reports.projects.index', ['status' => 'on hold']))
            ->assertOk()->assertDontSee($other->code);
        $this->actingAs($admin)->get(route('admin.reports.projects.index', ['status' => 'forged']))
            ->assertSessionHasErrors('status');
    }

    public function test_user_without_projects_view_cannot_open_report(): void
    {
        $actor = Role::where('code', 'HR_MANAGER')->firstOrFail()->users()->firstOrFail();
        $this->actingAs($actor)->get(route('admin.reports.projects.index'))->assertForbidden();
    }
}
