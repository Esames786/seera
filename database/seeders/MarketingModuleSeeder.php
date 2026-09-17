<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Registers the Marketing permission module on an existing installation
 * (client change request NR-16). Safe to run repeatedly: permissions are
 * created once, Super Admin receives them all, and the Marketing Manager role
 * (when present) gets the standard working set without losing other grants.
 */
class MarketingModuleSeeder extends Seeder
{
    public const MANAGER_ACTIONS = ['view', 'create', 'edit', 'delete', 'approve', 'export'];

    public function run(): void
    {
        foreach (Permission::ACTIONS as $action) {
            Permission::firstOrCreate(['module' => 'Marketing', 'action' => $action]);
        }

        $marketing = Permission::where('module', 'Marketing');

        Role::where('code', 'SUPER_ADMIN')->first()
            ?->permissions()->syncWithoutDetaching($marketing->pluck('id')->all());

        Role::where('code', 'MARKETING_MANAGER')->first()
            ?->permissions()->syncWithoutDetaching((clone $marketing)->whereIn('action', self::MANAGER_ACTIONS)->pluck('id')->all());

        $this->command?->info('Marketing module permissions are in place.');
    }
}
