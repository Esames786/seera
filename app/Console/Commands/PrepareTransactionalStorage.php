<?php

namespace App\Console\Commands;

use App\Services\Operations\TransactionalStorageCopy;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use RuntimeException;

class PrepareTransactionalStorage extends Command
{
    protected $signature = 'seera:prepare-storage-copy
        {--target= : Existing EMPTY database on the same server; source database is never modified}
        {--execute : Build the verified destination; otherwise inspect only}
        {--backup-file= : Private full database backup file, outside the application folder}
        {--backup-sha256= : SHA-256 of that full backup}
        {--restore-verified : Operator attests that backup restore was successfully tested}
        {--writers-stopped : Operator attests source cron/queue/external writers are stopped}
        {--force : Explicitly permit destination preparation in production}';

    protected $description = 'Prepare a data-preserving InnoDB copy in a different empty DB; no reset, source write or automatic promotion';

    public function handle(TransactionalStorageCopy $copy): int
    {
        try {
            $target = (string) $this->option('target');
            if (! $this->option('execute')) {
                $this->line(json_encode($copy->inspect($target), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            if (! $this->option('writers-stopped') || ! $this->option('restore-verified')
                || (app()->environment('production') && (! $this->option('force') || ! app()->isDownForMaintenance()))) {
                throw new RuntimeException('Execution requires verified restore and stopped-writer attestations; production also requires maintenance and --force.');
            }
            $file = realpath((string) $this->option('backup-file'));
            $root = realpath(base_path());
            $sha = strtolower((string) $this->option('backup-sha256'));
            if (! $file || ! is_file($file) || filesize($file) === 0 || str_starts_with(strtolower($file), strtolower($root).DIRECTORY_SEPARATOR)
                || ! preg_match('/^[a-f0-9]{64}$/D', $sha) || ! hash_equals($sha, hash_file('sha256', $file))) {
                throw new RuntimeException('A nonempty full backup outside the application folder with a matching SHA-256 is required.');
            }
            if ($this->ask('Type the EMPTY destination database name to confirm') !== $target) {
                throw new RuntimeException('Destination confirmation did not match.');
            }
            $report = $copy->copy($target);
            $report['operator_attestations'] = ['restore_verified' => true, 'writers_stopped' => true,
                'backup_file' => $file, 'backup_sha256' => $sha];
            $path = storage_path('app/private/operations/storage-copy-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            chmod($path, 0600);
            $this->info('Destination prepared for operator review; source database and application configuration are unchanged.');
            $this->line('Private verification report: '.$path);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception instanceof QueryException
                ? 'Destination preparation stopped on a database constraint/schema error. Source is unchanged. No automatic promotion occurred; inspect the destination privately.'
                : $exception->getMessage());

            return self::FAILURE;
        }
    }
}
