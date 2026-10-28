<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarAvailabilityUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_english_topbar_links_real_approvals_without_fake_notification_controls(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('href="'.route('admin.my-approvals.index').'"', false)
            ->assertSee('My Approvals')
            ->assertDontSee('title="Notifications"', false)
            ->assertDontSee('title="Search"', false);
    }

    public function test_arabic_coming_soon_is_truthful_and_does_not_claim_obsolete_rollout_phases(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.coming-soon', 'hr-reports'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('غير متاح بعد')
            ->assertSee('العودة إلى لوحة التحكم')
            ->assertDontSee('The current release covers Phase 1', false);
    }

    public function test_english_coming_soon_keeps_fallback_without_claiming_eta(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);

        $this->actingAs($admin)->get(route('admin.coming-soon', 'hr-reports'))->assertOk()
            ->assertSee('Coming Soon')
            ->assertDontSee('The current release covers Phase 1', false);
    }

    public function test_my_approvals_translates_arabic_filters_and_preserves_submit_values(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.my-approvals.index'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('طلبات الموافقة المتاحة')
            ->assertSee('جميع المشاريع المسموح بها')
            ->assertSee('معلق (قابل للإجراء فقط)')
            ->assertSee('value="pending"', false)
            ->assertSee('value="Purchase Requests"', false);
    }

    public function test_my_approvals_keeps_english_labels(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);

        $this->actingAs($admin)->get(route('admin.my-approvals.index'))->assertOk()
            ->assertSee('Actionable approvals')
            ->assertSee('All integrated modules')
            ->assertSee('Review each document before deciding.');
    }
}
