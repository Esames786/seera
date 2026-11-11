<?php

namespace Tests\Feature;

use App\Services\Operations\CleanStartPreparation;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PrepareCleanStartTest extends TestCase
{
    public function test_read_only_mode_does_not_prepare_or_switch_the_database(): void
    {
        $service = Mockery::mock(CleanStartPreparation::class);
        $service->shouldReceive('inspect')->once()->with('seera_clean', ['1', '15'], 1, [])
            ->andReturn(['dry_run' => true, 'source_writes' => false, 'retained_user_ids' => [1, 15]]);
        $service->shouldNotReceive('prepare');
        $this->app->instance(CleanStartPreparation::class, $service);
        $connection = DB::getDefaultConnection();

        $this->artisan('seera:prepare-clean-start --target=seera_clean --keep-users=1,15 --admin-id=1')
            ->expectsOutputToContain('"dry_run": true')->assertSuccessful();

        $this->assertSame($connection, DB::getDefaultConnection());
    }

    public function test_malformed_account_list_is_refused_before_any_database_preparation(): void
    {
        $service = Mockery::mock(CleanStartPreparation::class);
        $service->shouldNotReceive('inspect');
        $service->shouldNotReceive('prepare');
        $this->app->instance(CleanStartPreparation::class, $service);
        foreach (['', '1', '1,15,16', '0,15', '1,15 OR 1=1', '-1,15'] as $ids) {
            $this->artisan('seera:prepare-clean-start', ['--target' => 'seera_clean', '--keep-users' => $ids, '--admin-id' => '1'])
                ->assertFailed();
        }
    }

    public function test_execution_requires_explicit_stopped_writer_attestation(): void
    {
        $service = Mockery::mock(CleanStartPreparation::class);
        $service->shouldNotReceive('prepare');
        $this->app->instance(CleanStartPreparation::class, $service);

        $this->artisan('seera:prepare-clean-start --target=seera_clean --keep-users=1,15 --admin-id=1 --execute')
            ->expectsOutputToContain('Execution requires stopped writers')->assertFailed();
    }

    public function test_wrong_destination_confirmation_prevents_preparation(): void
    {
        $service = Mockery::mock(CleanStartPreparation::class);
        $service->shouldNotReceive('prepare');
        $this->app->instance(CleanStartPreparation::class, $service);

        $this->artisan('seera:prepare-clean-start --target=seera_clean --keep-users=1,15 --admin-id=1 --execute --writers-stopped')
            ->expectsQuestion('Type the EMPTY destination database name to prepare', 'seera_live')
            ->expectsOutputToContain('Destination confirmation did not match')->assertFailed();
    }

    public function test_production_execution_without_force_is_refused(): void
    {
        $this->app->instance('env', 'production');
        $service = Mockery::mock(CleanStartPreparation::class);
        $service->shouldNotReceive('prepare');
        $this->app->instance(CleanStartPreparation::class, $service);

        $this->artisan('seera:prepare-clean-start --target=seera_clean --keep-users=1,15 --admin-id=1 --execute --writers-stopped')
            ->assertFailed();
    }

    public function test_destination_errors_are_reported_without_credential_disclosure(): void
    {
        $service = Mockery::mock(CleanStartPreparation::class);
        $service->shouldReceive('inspect')->once()->andThrow(new \RuntimeException('Destination must be completely empty.'));
        $this->app->instance(CleanStartPreparation::class, $service);

        $this->artisan('seera:prepare-clean-start --target=seera_clean --keep-users=1,15 --admin-id=1')
            ->expectsOutputToContain('Destination must be completely empty')->assertFailed();
    }

    public function test_service_refuses_an_unsupported_source_without_creating_a_destination_connection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DIFFERENT empty MySQL database');
        app(CleanStartPreparation::class)->inspect('seera_clean', [1, 15], 1);
    }
}
