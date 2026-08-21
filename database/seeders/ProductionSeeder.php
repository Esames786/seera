<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('seera.admin.name'));
        $email = trim((string) config('seera.admin.email'));
        $username = trim((string) config('seera.admin.username'));
        $password = (string) config('seera.admin.password');

        if ($name === '' || $email === '' || $username === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set valid SEERA_ADMIN_NAME, SEERA_ADMIN_EMAIL, and SEERA_ADMIN_USERNAME values before running ProductionSeeder.');
        }

        if (strlen($password) < 16) {
            throw new RuntimeException('SEERA_ADMIN_PASSWORD must contain at least 16 characters before running ProductionSeeder.');
        }

        DB::transaction(function () use ($name, $email, $username, $password) {
            $department = Department::updateOrCreate(
                ['code' => 'ADMIN'],
                [
                    'name' => 'Administration',
                    'description' => 'General management and access control',
                    'status' => 'active',
                ]
            );

            foreach (Permission::MODULES as $module) {
                foreach (Permission::ACTIONS as $action) {
                    Permission::firstOrCreate(['module' => $module, 'action' => $action]);
                }
            }

            $role = Role::updateOrCreate(
                ['code' => 'SUPER_ADMIN'],
                [
                    'name' => 'Super Admin',
                    'department_id' => $department->id,
                    'level' => 1,
                    'access_scope' => 'All Company',
                    'default_dashboard' => 'Admin Dashboard',
                    'mobile_app_access' => true,
                    'can_approve_child_requests' => true,
                    'is_system' => true,
                    'description' => 'Production bootstrap administrator with full access.',
                    'status' => 'active',
                ]
            );
            $role->permissions()->sync(Permission::pluck('id')->all());

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $username,
                    'password' => $password,
                    'department_id' => $department->id,
                    'language' => 'English',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->wasRecentlyCreated && $user->username !== $username) {
                throw new RuntimeException('The production administrator email already exists with a different username. Resolve it manually; the seeder did not reset its password.');
            }

            $user->forceFill([
                'name' => $name,
                'department_id' => $department->id,
                'status' => 'active',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
            $user->roles()->syncWithoutDetaching([$role->id => ['is_primary' => true]]);

            CompanyProfile::updateOrCreate(
                ['id' => 1],
                [
                    'name' => (string) config('seera.company_name'),
                    'country' => 'Saudi Arabia',
                    'currency' => 'SAR',
                    'default_vat_rate' => 15,
                    'certificate_status' => 'Pending',
                    'status' => 'active',
                ]
            );
        });
    }
}
