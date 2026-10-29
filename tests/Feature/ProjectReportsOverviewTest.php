<?php

namespace Tests\Feature;

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

    public function test_project_level_user_sees_only_accessible_projects_and_no_financial_link_without_permission(): void
    {
        $project = Project::firstOrFail();
        $outside = Project::create([
            'name' => 'Private Outside Project',
            'code' => 'TEST-OUTSIDE-PORTFOLIO',
            'status' => 'active',
        ]);
        $actor = Role::where('code', 'PROJECT_MANAGER')->firstOrFail()->users()->firstOrFail();
        $actor->update(['project_id' => $project->id]);
        $project->update(['manager_id' => $actor->id]);

        $response = $this->actingAs($actor)->get(route('admin.reports.projects.index'));
        $response->assertOk()
            ->assertSee($project->code)
            ->assertDontSee($outside->code)
            ->assertDontSee(route('admin.accounting.reports.project-cost-report'));

        $this->actingAs($actor)->get(route('admin.reports.projects.index', ['search' => $outside->code]))
            ->assertOk()->assertDontSee($outside->code);
    }

    public function test_user_without_projects_view_cannot_open_report(): void
    {
        $actor = Role::where('code', 'HR_MANAGER')->firstOrFail()->users()->firstOrFail();
        $this->actingAs($actor)->get(route('admin.reports.projects.index'))->assertForbidden();
    }
}
