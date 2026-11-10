<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The HTML user guide is served to signed-in users from one URL family. */
class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_guide_is_served_to_signed_in_users_and_hidden_from_guests(): void
    {
        $this->get('/user-guide')->assertRedirect(route('login'));

        $user = User::where('email', 'zubair@example.com')->firstOrFail();
        $this->actingAs($user)->get('/user-guide')->assertRedirect('/user-guide/index.html');
        $this->actingAs($user)->get('/user-guide/index.html')->assertOk()->assertSee('Current System User Guide');
        $this->actingAs($user)->get('/user-guide/SCREEN-INDEX.html')->assertOk()->assertSee('SUP-004');
        $this->actingAs($user)->get('/user-guide/WORKFLOW-INDEX')->assertOk()->assertSee('WF-010');
        $this->get('/user-guide/WORKFLOW-INDEX')->assertOk()->assertSee('WF-017');
        $this->get('/user-guide/WORKFLOW-INDEX')->assertOk()->assertSee('WF-018');
        $this->get('/user-guide/SCREEN-INDEX.html')->assertOk()->assertSee('EXP-SE-001')->assertSee('EXP-SE-004');
        $this->get('/user-guide/SCREEN-INDEX.html')->assertOk()->assertSee('APR-001')->assertSee('APR-002');
        $this->actingAs($user)->get('/user-guide/SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html')->assertOk()->assertSee('Goods Received Not Invoiced');

        $this->actingAs($user)->get('/user-guide/build-html.php')->assertNotFound();
        $this->actingAs($user)->get('/user-guide/../production-deployment-guide.md')->assertNotFound();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee(route('user-guide'))
            ->assertSee(route('user-guide.page', 'CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html'));
    }

    public function test_visual_training_pages_are_authenticated_and_have_embedded_images(): void
    {
        $this->get('/user-guide/VISUAL-EXAMPLES.html')->assertRedirect(route('login'));
        $this->get('/user-guide/VISUAL-EXAMPLES-AR.html')->assertRedirect(route('login'));
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        foreach (['VISUAL-EXAMPLES' => 'en', 'VISUAL-EXAMPLES-AR' => 'ar'] as $page => $lang) {
            $response = $this->actingAs($user)->get('/user-guide/'.$page.'.html')->assertOk();
            $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
            $response->assertSee('lang="'.$lang.'"', false)
                ->assertSee('data:image/jpeg;base64,', false)
                ->assertSee('TRAIN-INV-01')
                ->assertSee('employee-to-user', false);
            $this->assertStringNotContainsString('src="visual-examples/', $response->getContent());
        }
    }

    public function test_client_feedback_guides_are_discoverable_and_require_an_active_login(): void
    {
        $pages = ['CLIENT-ENTRY-UPDATES-2026-11-10', 'CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10'];
        foreach ($pages as $page) {
            $this->get('/user-guide/'.$page.'.html')->assertRedirect(route('login'));
        }

        $user = User::where('email', 'zubair@example.com')->firstOrFail();
        $this->actingAs($user);
        foreach ($pages as $page) {
            $this->get('/user-guide/index.html')->assertOk()->assertSee($page.'.html', false);
            $this->get('/user-guide/'.$page.'.html')->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        }
        $response = $this->get('/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10');
        $response->assertOk()->assertSee('NEEDS CONFIRMATION')->assertSee('PENDING')->assertSee('need to be checked on your portal');
        for ($number = 1; $number <= 13; $number++) {
            $response->assertSee(sprintf('R10-%02d', $number));
        }
        $this->get('/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.md')->assertNotFound();
        $user->update(['status' => 'inactive']);
        foreach ($pages as $page) {
            $this->get('/user-guide/'.$page.'.html')->assertRedirect(route('login'));
        }
    }

    public function test_visual_guide_does_not_expose_arbitrary_files_or_asset_directories(): void
    {
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($user);
        foreach (['user-guide-screenshots.cjs', 'fixture.private.json', 'VISUAL-EXAMPLES-UNAPPROVED.html', 'visual-examples/en-dashboard.jpg'] as $path) {
            $this->get('/user-guide/'.$path)->assertNotFound();
        }
        $user->update(['status' => 'inactive']);
        $this->get('/user-guide/VISUAL-EXAMPLES-AR.html')->assertRedirect(route('login'));
    }
}
