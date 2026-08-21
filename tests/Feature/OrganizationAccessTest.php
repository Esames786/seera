<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OrganizationHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds onto an empty database, exactly as production will run it. */
    private function seedOrganization(): void
    {
        config(['seera.organization.email_domain' => 'seera.local']);
        $this->seed(OrganizationHierarchySeeder::class);
    }

    public function test_it_creates_every_organization_chart_account_on_an_empty_database(): void
    {
        $this->seedOrganization();

        $this->assertSame(12, User::count());
        $this->assertSame(7, Department::count());
        $this->assertSame(14, Role::count());
        $this->assertSame(12, Designation::count());

        $expected = [
            'omar@seera.local' => ['Omar Mukhtar', 'Super Admin', 'General Manager'],
            'nabeel@seera.local' => ['Nabeel Mukhtar', 'Project Manager', 'Project Manager'],
            'zubair@seera.local' => ['Zubair Ahmed', 'Accounts Manager', 'Accounts Manager'],
            'zulfiqar@seera.local' => ['Zulfiqar', 'Purchase Manager', 'Purchase Manager'],
            'waleed@seera.local' => ['Waleed', 'HR Manager', 'HR Manager'],
            'abdullah@seera.local' => ['Abdullah Mukhtar', 'Marketing Manager', 'Marketing Manager'],
            'zafar@seera.local' => ['Zafar Ali', 'Site In-Charge', 'Site In-Charge'],
            'shahmeer@seera.local' => ['Abdullah Shahmeer', 'Account Assistant', 'Account Assistant'],
            'ayaz@seera.local' => ['Ayaz', 'Purchase Assistant', 'Purchase Assistant'],
            'kamran@seera.local' => ['Kamran', 'Mechanic', 'Mechanic'],
            'shaban@seera.local' => ['Shaban', 'Operator', 'Operator'],
            'rizwan@seera.local' => ['Rizwan', 'Operator', 'Operator'],
        ];

        foreach ($expected as $email => [$name, $roleName, $designation]) {
            $user = User::with(['roles', 'designation'])->where('email', $email)->first();

            $this->assertNotNull($user, "Missing account {$email}");
            $this->assertSame($name, $user->name);
            $this->assertSame($roleName, $user->roles->first()?->name);
            $this->assertSame($designation, $user->designation?->name);
            $this->assertSame('active', $user->status);
        }
    }

    public function test_every_seeded_account_uses_the_default_password_and_must_change_it(): void
    {
        $this->seedOrganization();

        foreach (User::all() as $user) {
            $this->assertTrue(
                Hash::check(OrganizationHierarchySeeder::DEFAULT_PASSWORD, $user->password),
                "{$user->email} does not use the default password"
            );
            $this->assertTrue($user->must_change_password, "{$user->email} is not forced to change its password");
            $this->assertNull($user->password_changed_at);
        }
    }

    public function test_seeding_twice_does_not_duplicate_accounts_or_reset_a_chosen_password(): void
    {
        $this->seedOrganization();

        $user = User::where('email', 'zubair@seera.local')->firstOrFail();
        $user->update([
            'password' => 'a-password-the-holder-chose',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $this->seedOrganization();

        $this->assertSame(12, User::count());

        $user->refresh();
        $this->assertTrue(Hash::check('a-password-the-holder-chose', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_a_default_password_account_is_forced_to_the_change_screen(): void
    {
        $this->seedOrganization();
        $user = User::where('email', 'omar@seera.local')->firstOrFail();

        // Every admin screen bounces to the password prompt.
        foreach (['/admin/dashboard', '/admin/users', '/admin/hr/employees'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect(route('admin.password.change'));
        }

        $this->actingAs($user)
            ->get(route('admin.password.change'))
            ->assertOk()
            ->assertSee('Set Your Password');
    }

    public function test_changing_the_password_clears_the_flag_and_restores_access(): void
    {
        $this->seedOrganization();
        $user = User::where('email', 'omar@seera.local')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.password.change.update'), [
                'current_password' => OrganizationHierarchySeeder::DEFAULT_PASSWORD,
                'password' => 'my-own-strong-password',
                'password_confirmation' => 'my-own-strong-password',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('my-own-strong-password', $user->password));

        $this->actingAs($user)->get('/admin/dashboard')->assertOk();
    }

    public function test_the_change_is_rejected_without_the_correct_current_password(): void
    {
        $this->seedOrganization();
        $user = User::where('email', 'omar@seera.local')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.password.change.update'), [
                'current_password' => 'not-the-default',
                'password' => 'my-own-strong-password',
                'password_confirmation' => 'my-own-strong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_the_default_password_cannot_be_kept(): void
    {
        $this->seedOrganization();
        $user = User::where('email', 'omar@seera.local')->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.password.change.update'), [
                'current_password' => OrganizationHierarchySeeder::DEFAULT_PASSWORD,
                'password' => OrganizationHierarchySeeder::DEFAULT_PASSWORD,
                'password_confirmation' => OrganizationHierarchySeeder::DEFAULT_PASSWORD,
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_an_account_with_its_own_password_is_never_prompted(): void
    {
        $this->seedOrganization();
        $user = User::where('email', 'nabeel@seera.local')->firstOrFail();
        $user->update(['must_change_password' => false, 'password_changed_at' => now()]);

        $this->actingAs($user)->get('/admin/dashboard')->assertOk();
    }

    /** Every seeded account must land somewhere useful, not a wall of 403s. */
    public function test_each_seeded_account_can_reach_its_own_area(): void
    {
        $this->seedOrganization();

        $expectations = [
            'omar@seera.local' => ['/admin/dashboard', '/admin/users', '/admin/accounting/dashboard', '/admin/inventory/dashboard'],
            'zubair@seera.local' => ['/admin/dashboard', '/admin/accounting/dashboard', '/admin/accounting/journal-entries'],
            'shahmeer@seera.local' => ['/admin/dashboard', '/admin/accounting/accounts-payable'],
            'waleed@seera.local' => ['/admin/dashboard', '/admin/hr/employees', '/admin/hr/attendance'],
            'nabeel@seera.local' => ['/admin/dashboard', '/admin/master/projects', '/admin/inventory/purchase-requests'],
            'zulfiqar@seera.local' => ['/admin/dashboard', '/admin/inventory/purchase-orders', '/admin/inventory/items'],
            'ayaz@seera.local' => ['/admin/dashboard', '/admin/inventory/purchase-requests'],
            'abdullah@seera.local' => ['/admin/dashboard', '/admin/master/customers'],
            'zafar@seera.local' => ['/admin/dashboard', '/admin/hr/attendance'],
            'kamran@seera.local' => ['/admin/dashboard'],
            'shaban@seera.local' => ['/admin/dashboard'],
            'rizwan@seera.local' => ['/admin/dashboard'],
        ];

        foreach ($expectations as $email => $urls) {
            $user = User::where('email', $email)->firstOrFail();
            // Clear the first-login prompt so the permission check is what is tested.
            $user->update(['must_change_password' => false, 'password_changed_at' => now()]);

            foreach ($urls as $url) {
                $this->actingAs($user)
                    ->get($url)
                    ->assertOk("{$email} could not open {$url}");
            }
        }
    }
}
