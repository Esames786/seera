<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One command to rebuild a production database after `migrate:fresh`:
 *
 *   php artisan db:seed --class=ProductionBootstrapSeeder --force
 *
 * Runs ProductionSeeder (permission catalogue, Super Admin, the bootstrap
 * administrator from SEERA_ADMIN_* in .env, company profile), then
 * ProductionChartOfAccountsSeeder (standard chart of accounts with zero
 * balances, posting rules, current VAT period), then
 * OrganizationHierarchySeeder (departments, roles, designations and the twelve
 * organisation-chart accounts on SEERA_ORG_EMAIL_DOMAIN with the shared default
 * password and a forced change at first login). No demo data is created and
 * every step is idempotent, so it can be re-run after a partial setup.
 */
class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $domain = trim((string) config('seera.organization.email_domain', 'seera.local'));

        if ($domain === '' || $domain === 'seera.local') {
            throw new RuntimeException(
                'Set SEERA_ORG_EMAIL_DOMAIN in .env (for example SEERA_ORG_EMAIL_DOMAIN=seera.com) before running the bootstrap, '
                .'otherwise the staff accounts are created on the seera.local placeholder.'
            );
        }

        $this->call(ProductionSeeder::class);
        $this->call(ProductionChartOfAccountsSeeder::class);
        $this->call(ProductionHrDefaultsSeeder::class);
        $this->call(OrganizationHierarchySeeder::class);

        $this->command?->newLine();
        $this->command?->info('Production bootstrap complete: '.User::count().' login accounts.');
        $this->command?->table(
            ['Email', 'Role', 'Password'],
            User::with('roles')->orderBy('id')->get()->map(fn (User $user) => [
                $user->email,
                $user->roles->first()?->name ?? '-',
                $user->must_change_password ? OrganizationHierarchySeeder::DEFAULT_PASSWORD.' (must change at first login)' : 'as set in SEERA_ADMIN_PASSWORD',
            ])->all()
        );
        $this->command?->warn('Remove SEERA_ADMIN_PASSWORD from .env now that the administrator exists.');
    }
}
