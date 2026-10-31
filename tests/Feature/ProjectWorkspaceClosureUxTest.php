<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkspaceClosureUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_english_project_view_links_to_existing_scoped_reports_and_budget(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $project = Project::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.projects.show', $project))
            ->assertOk()
            ->assertSee('Open Project Reports')
            ->assertSee(route('admin.reports.projects.index', ['search' => $project->code]), false)
            ->assertSee(route('admin.master.projects.budget.show', $project), false)
            ->assertSee('Related records save independently.')
            ->assertDontSee('name="_save_action"', false);
    }

    public function test_arabic_project_view_and_edit_keep_connected_workspaces_and_readonly_boundary(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $project = Project::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.projects.show', $project))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('نظرة عامة على المشروع (للقراءة فقط)')
            ->assertSee('فتح تقارير المشاريع')
            ->assertSee($project->code)
            ->assertDontSee('name="_save_action"', false);

        $this->actingAs($admin)->get(route('admin.master.projects.edit', $project))
            ->assertOk()
            ->assertSee('مساحة عمل المشروع')
            ->assertSee('name="_save_action"', false)
            ->assertSee('data-project-identity', false);
    }

    public function test_role_without_project_view_cannot_open_workspace_or_linked_reports(): void
    {
        $actor = Role::where('code', 'HR_MANAGER')->firstOrFail()->users()->firstOrFail();
        $project = Project::firstOrFail();

        $this->actingAs($actor)->get(route('admin.master.projects.show', $project))->assertForbidden();
        $this->actingAs($actor)->get(route('admin.reports.projects.index'))->assertForbidden();
    }
}
