<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPanelArabicClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_arabic_project_tabs_and_financial_summary_labels_keep_existing_values(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $project = Project::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.projects.show', $project))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('المواقع / مواقع العمل')
            ->assertSee('فريق المشروع / الموظفون')
            ->assertSee('التكاليف المرحلة (ريال)')
            ->assertSee('data-project-identity', false)
            ->assertDontSee('name="_save_action"', false);
    }

    public function test_project_related_panel_translates_columns_actions_and_back_link(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $project = Project::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.projects.workspace.panel', [$project, 'sites']))
            ->assertOk()
            ->assertSee('العودة إلى المشروع')
            ->assertSee('المشرف')
            ->assertSee('الإجراءات');
    }

    public function test_english_related_panel_retains_original_labels_and_route(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $project = Project::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.projects.workspace.panel', [$project, 'sites']))
            ->assertOk()
            ->assertSee('Back to Project')
            ->assertSee('Supervisor')
            ->assertSee('Actions');
    }

    public function test_non_project_role_cannot_open_panel_or_view(): void
    {
        $user = Role::where('code', 'HR_MANAGER')->firstOrFail()->users()->firstOrFail();
        $project = Project::firstOrFail();
        $this->actingAs($user)->get(route('admin.master.projects.show', $project))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.projects.workspace.panel', [$project, 'sites']))->assertForbidden();
    }
}
