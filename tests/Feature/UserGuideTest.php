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
        $this->actingAs($user)->get('/user-guide/SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html')->assertOk()->assertSee('Goods Received Not Invoiced');

        $this->actingAs($user)->get('/user-guide/build-html.php')->assertNotFound();
        $this->actingAs($user)->get('/user-guide/../production-deployment-guide.md')->assertNotFound();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee(route('user-guide'));
    }
}
