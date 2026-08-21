<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\SupplierBill;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_only_the_production_foundation(): void
    {
        config()->set('seera.company_name', 'Production Company');
        config()->set('seera.admin', [
            'name' => 'Production Owner',
            'email' => 'owner@example.com',
            'username' => 'owner',
            'password' => 'Unique-Production-Password-2026!',
        ]);

        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);

        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $role = Role::where('code', 'SUPER_ADMIN')->firstOrFail();

        $this->assertTrue(Hash::check('Unique-Production-Password-2026!', $admin->password));
        $this->assertTrue($admin->roles()->whereKey($role->id)->exists());
        $this->assertSame(Permission::count(), $role->permissions()->count());
        $this->assertSame(1, User::count());
        $this->assertSame(0, Project::count());
        $this->assertSame(0, SupplierBill::count());
        $this->assertDatabaseHas('company_profiles', ['name' => 'Production Company']);
    }
}
