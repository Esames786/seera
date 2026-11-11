<?php

namespace App\Console\Commands;

use App\Services\Operations\CleanStartPreparation;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use RuntimeException;

class PrepareCleanStart extends Command
{
    protected $signature = 'seera:prepare-clean-start
        {--target= : A separately named completely EMPTY destination database}
        {--keep-users= : Exactly two approved User IDs, comma separated}
        {--admin-id= : Retained User ID with existing effective Super Admin access}
        {--target-user= : Optional different destination DB username; password is prompted privately}
        {--execute : Prepare the destination; default is read-only inspection}
        {--writers-stopped : Source web/cron/queue/external writes are stopped}
        {--force : Permit preparation while production is in maintenance}';

    protected $description = 'Prepare masters and two existing logins in a different empty InnoDB DB; source untouched, no automatic live switch';

    public function handle(CleanStartPreparation $service): int
    {
        try {
            $raw = (string) $this->option('keep-users');
            if (! preg_match('/^[1-9][0-9]*,[1-9][0-9]*$/D', $raw) || ! ctype_digit((string) $this->option('admin-id'))) {
                throw new RuntimeException('Supply --keep-users=ID,ID and --admin-id=ID.');
            }
            $credentials = [];
            if ($username = (string) $this->option('target-user')) {
                $credentials = ['username' => $username, 'password' => (string) $this->secret('Destination database password (not printed or stored in report)')];
            }
            $arguments = [(string) $this->option('target'), explode(',', $raw), (int) $this->option('admin-id'), $credentials];
            if (! $this->option('execute')) {
                $this->line(json_encode($service->inspect(...$arguments), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            if (! $this->option('writers-stopped') || (app()->environment('production') && (! $this->option('force') || ! app()->isDownForMaintenance()))) {
                throw new RuntimeException('Execution requires stopped writers; production also requires maintenance and --force.');
            }
            if ($this->ask('Type the EMPTY destination database name to prepare') !== $arguments[0]) {
                throw new RuntimeException('Destination confirmation did not match.');
            }
            $report = $service->prepare(...$arguments);
            $directory = storage_path('app/private/operations');
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            $path = $directory.'/clean-start-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
            if (file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
                throw new RuntimeException('Destination prepared but private report could not be saved. Do not activate until reviewed.');
            }
            chmod($path, 0600);
            $this->info('Clean destination prepared for operator review. Source DB/config/files unchanged; website NOT switched.');
            $this->line('Private report: '.$path);

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error instanceof QueryException
                ? 'Database preparation refused on a schema/constraint/access error. Source is unchanged. Inspect destination privately; do not activate.'
                : $error->getMessage());

            return self::FAILURE;
        }
    }
}
