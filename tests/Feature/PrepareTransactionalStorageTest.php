<?php

namespace Tests\Feature;

use App\Services\Operations\TransactionalStorageCopy;
use Mockery\MockInterface;
use Tests\TestCase;

class PrepareTransactionalStorageTest extends TestCase
{
    public function test_command_defaults_to_inspection_without_any_copy(): void
    {
        $this->mock(TransactionalStorageCopy::class, function (MockInterface $mock) {
            $mock->shouldReceive('inspect')->once()->with('empty_target')->andReturn(['dry_run' => true, 'source_writes' => false]);
            $mock->shouldNotReceive('copy');
        });
        $this->artisan('seera:prepare-storage-copy', ['--target' => 'empty_target'])->expectsOutputToContain('"dry_run": true')->assertSuccessful();
    }

    public function test_execution_without_restore_and_writer_attestations_is_refused(): void
    {
        $this->mock(TransactionalStorageCopy::class, fn (MockInterface $mock) => $mock->shouldNotReceive('copy'));
        $this->artisan('seera:prepare-storage-copy', ['--target' => 'empty_target', '--execute' => true])->expectsOutputToContain('verified restore')->assertFailed();
    }

    public function test_missing_or_mismatched_backup_prevents_copy(): void
    {
        $this->mock(TransactionalStorageCopy::class, fn (MockInterface $mock) => $mock->shouldNotReceive('copy'));
        $this->artisan('seera:prepare-storage-copy', ['--target' => 'empty_target', '--execute' => true, '--restore-verified' => true, '--writers-stopped' => true, '--backup-file' => '/missing/full-backup.sql', '--backup-sha256' => str_repeat('a', 64)])->assertFailed();
    }

    public function test_a_file_in_the_application_even_with_a_matching_hash_is_not_an_acceptable_backup(): void
    {
        $this->mock(TransactionalStorageCopy::class, fn (MockInterface $mock) => $mock->shouldNotReceive('copy'));
        $file = base_path('composer.json');
        $this->artisan('seera:prepare-storage-copy', ['--target' => 'empty_target', '--execute' => true, '--restore-verified' => true, '--writers-stopped' => true, '--backup-file' => $file, '--backup-sha256' => hash_file('sha256', $file)])->assertFailed();
    }

    public function test_production_execution_requires_force_and_maintenance(): void
    {
        $this->app->instance('env', 'production');
        $this->mock(TransactionalStorageCopy::class, fn (MockInterface $mock) => $mock->shouldNotReceive('copy'));
        $this->artisan('seera:prepare-storage-copy', ['--target' => 'empty_target', '--execute' => true, '--restore-verified' => true, '--writers-stopped' => true])->expectsOutputToContain('maintenance and --force')->assertFailed();
    }
}
