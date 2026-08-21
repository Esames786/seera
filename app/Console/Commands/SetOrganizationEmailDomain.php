<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\OrganizationHierarchySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves the organization chart accounts onto a different email domain.
 *
 * Needed when the accounts were seeded before SEERA_ORG_EMAIL_DOMAIN was set,
 * because the seeder matches on email and would otherwise create a second set.
 */
class SetOrganizationEmailDomain extends Command
{
    protected $signature = 'seera:org-domain
                            {domain : The email domain to move the organization accounts onto}
                            {--dry-run : Show what would change without writing anything}';

    protected $description = 'Move the organization chart login accounts onto a different email domain';

    public function handle(): int
    {
        $domain = ltrim(trim((string) $this->argument('domain')), '@');
        $dryRun = (bool) $this->option('dry-run');

        if ($domain === '' || ! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            $this->error("'{$domain}' is not a valid email domain.");

            return self::FAILURE;
        }

        $usernames = array_column(OrganizationHierarchySeeder::ROSTER, 1);
        $accounts = User::whereIn('username', $usernames)->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->warn('No organization chart accounts found. Run the OrganizationHierarchySeeder first.');

            return self::SUCCESS;
        }

        $planned = [];
        $blocked = [];

        foreach ($accounts as $account) {
            $target = $account->username.'@'.$domain;

            if ($account->email === $target) {
                continue;
            }

            // Refuse to move onto an address somebody else already holds.
            $taken = User::where('email', $target)->whereKeyNot($account->getKey())->exists();

            if ($taken) {
                $blocked[] = [$account->name, $account->email, $target];

                continue;
            }

            $planned[] = [$account, $target];
        }

        if ($blocked !== []) {
            $this->error('These accounts cannot be moved because the target address is already in use:');
            $this->table(['Name', 'Current', 'Target'], $blocked);
        }

        if ($planned === []) {
            $this->info($blocked === [] ? "Every organization account is already on @{$domain}." : 'Nothing else to change.');

            return $blocked === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Name', 'Current', 'New'],
            array_map(fn (array $row) => [$row[0]->name, $row[0]->email, $row[1]], $planned)
        );

        if ($dryRun) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($planned) {
            foreach ($planned as [$account, $target]) {
                $account->update(['email' => $target]);
            }
        });

        $this->info(count($planned).' account(s) moved onto @'.$domain.'.');

        return $blocked === [] ? self::SUCCESS : self::FAILURE;
    }
}
