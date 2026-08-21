<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OrganizationHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationDomainCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedOnLocalDomain(): void
    {
        config(['seera.organization.email_domain' => 'seera.local']);
        $this->seed(OrganizationHierarchySeeder::class);
    }

    public function test_it_moves_every_organization_account_onto_the_new_domain(): void
    {
        $this->seedOnLocalDomain();

        $this->artisan('seera:org-domain', ['domain' => 'seera.com'])
            ->assertExitCode(0);

        foreach (array_column(OrganizationHierarchySeeder::ROSTER, 1) as $username) {
            $this->assertDatabaseHas('users', ['username' => $username, 'email' => $username.'@seera.com']);
            $this->assertDatabaseMissing('users', ['email' => $username.'@seera.local']);
        }

        $this->assertSame(12, User::count());
    }

    public function test_it_leaves_passwords_and_the_forced_change_flag_untouched(): void
    {
        $this->seedOnLocalDomain();

        $this->artisan('seera:org-domain', ['domain' => 'seera.com'])->assertExitCode(0);

        $user = User::where('email', 'omar@seera.com')->firstOrFail();
        $this->assertTrue(Hash::check(OrganizationHierarchySeeder::DEFAULT_PASSWORD, $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->seedOnLocalDomain();

        $this->artisan('seera:org-domain', ['domain' => 'seera.com', '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'omar@seera.local']);
        $this->assertDatabaseMissing('users', ['email' => 'omar@seera.com']);
    }

    public function test_running_it_twice_is_harmless(): void
    {
        $this->seedOnLocalDomain();

        $this->artisan('seera:org-domain', ['domain' => 'seera.com'])->assertExitCode(0);
        $this->artisan('seera:org-domain', ['domain' => 'seera.com'])->assertExitCode(0);

        $this->assertSame(12, User::count());
        $this->assertDatabaseHas('users', ['email' => 'omar@seera.com']);
    }

    /** It must never take an address that belongs to somebody else. */
    public function test_it_refuses_to_overwrite_an_address_owned_by_another_account(): void
    {
        $this->seedOnLocalDomain();

        $outsider = User::create([
            'name' => 'Someone Else',
            'email' => 'omar@seera.com',
            'username' => 'someone.else',
            'password' => 'a-completely-different-password',
            'status' => 'active',
        ]);

        $this->artisan('seera:org-domain', ['domain' => 'seera.com'])->assertExitCode(1);

        // The outsider keeps the address; Omar stays where he was.
        $this->assertSame('omar@seera.com', $outsider->refresh()->email);
        $this->assertDatabaseHas('users', ['username' => 'omar', 'email' => 'omar@seera.local']);

        // Everyone else still moved.
        $this->assertDatabaseHas('users', ['username' => 'nabeel', 'email' => 'nabeel@seera.com']);
    }

    public function test_it_rejects_a_malformed_domain(): void
    {
        $this->seedOnLocalDomain();

        $this->artisan('seera:org-domain', ['domain' => 'not a domain'])->assertExitCode(1);

        $this->assertDatabaseHas('users', ['email' => 'omar@seera.local']);
    }
}
