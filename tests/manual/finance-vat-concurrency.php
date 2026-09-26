<?php

// Opt-in integration probe. ONLY a newly initialized, disposable MySQL instance
// on loopback:33479 whose @@datadir exactly matches the supplied temporary path.
// Never reads application database credentials or migrates an existing database.
// Usage: php tests/manual/finance-vat-concurrency.php <temporary-mysqld-datadir>

use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Services\Accounting\PostingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-finance-lock-test-')) {
    throw new RuntimeException('Supply a disposable seera-finance-lock-test-* datadir.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$actual = realpath($pdo->query('SELECT @@datadir')->fetchColumn());
if ($actual !== $expected) {
    throw new RuntimeException('Server datadir mismatch; refusing all writes.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_finance_lock_test_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_finance_lock_test_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Invalid disposable database name.');
}
if ($action === 'parent') {
    // No IF NOT EXISTS: an existing test database is deliberately refused.
    $pdo->exec('CREATE DATABASE '.$database);
}
foreach (['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(1);
});
config(['database.default' => 'finance_lock_probe', 'database.connections.finance_lock_probe' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479, 'database' => $database,
    'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '', 'strict' => true,
]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$posting = app(PostingService::class);
$record = fn () => $posting->recordVat('input', 15, 100, 15, '2026-09-26', 'Review', 1, 'LOCK-PROBE', 'supplier', null, 'Synthetic');

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('Worker IPC connection failed: '.$error);
    }
    // Intentionally loaded before waiting: catches stale-model recalculation bugs.
    $stale = VatPeriod::findOrFail(1);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        if (! $announced && str_contains($sql, 'vat_periods') && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    try {
        match ($action) {
            'record' => $record(),
            'withdraw' => $posting->withdrawVat('Review', 1),
            'recalculate' => $stale->recalculate(),
            'finalize' => DB::transaction(function () {
                $period = VatPeriod::whereKey(1)->lockForUpdate()->firstOrFail();
                $period->recalculate();
                $period->update(['status' => 'finalized']);
            }),
            default => throw new RuntimeException('Unknown action'),
        };
        fwrite($signal, "OK\n");
    } catch (ValidationException $exception) {
        if (! isset($exception->errors()['vat'])) {
            throw $exception;
        }
        fwrite($signal, "REFUSED\n");
    }
    exit;
}

Schema::create('vat_periods', function ($table) {
    $table->id();
    $table->string('period_name');
    $table->date('start_date');
    $table->date('end_date');
    $table->string('status')->default('draft');
    foreach (['sales_taxable_amount', 'output_vat', 'purchase_taxable_amount', 'input_vat', 'vat_payable'] as $field) {
        $table->decimal($field, 15, 2)->default(0);
    }
    $table->timestamps();
});
Schema::create('vat_transactions', function ($table) {
    $table->id();
    $table->date('transaction_date');
    foreach (['source_module', 'source_reference', 'party_type', 'party_name', 'vat_type', 'status'] as $field) {
        $table->string($field)->nullable();
    }
    foreach (['source_id', 'party_id'] as $field) {
        $table->unsignedBigInteger($field)->nullable();
    }
    $table->foreignId('vat_period_id')->nullable()->constrained('vat_periods');
    foreach (['taxable_amount', 'vat_rate', 'vat_amount'] as $field) {
        $table->decimal($field, 15, 2);
    }
    $table->timestamps();
});
VatPeriod::create(['period_name' => 'Synthetic Q3', 'start_date' => '2026-07-01', 'end_date' => '2026-09-30', 'status' => 'draft']);

function expectProbe(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function waitForProbe($process, $signal, array $pipes, string $needle): void
{
    $output = '';
    $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline) {
        $output .= stream_get_contents($signal);
        if (str_contains($output, $needle)) {
            return;
        }
        if (! proc_get_status($process)['running']) {
            throw new RuntimeException('Worker exited: '.$output.stream_get_contents($pipes[2]));
        }
        usleep(20000);
    }
    throw new RuntimeException('Worker timed out: '.$output.stream_get_contents($pipes[2]));
}

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
expectProbe(is_resource($server), 'Could not start loopback IPC listener: '.$error);
$address = stream_socket_get_name($server, false);
foreach (['record', 'withdraw', 'recalculate', 'finalize_after_record', 'finalize_after_withdraw'] as $scenario) {
    VatTransaction::query()->delete();
    VatPeriod::whereKey(1)->update(['status' => 'draft']);
    $period = VatPeriod::findOrFail(1);
    $period->recalculate();
    if (in_array($scenario, ['withdraw', 'recalculate', 'finalize_after_withdraw'], true)) {
        $record();
    }
    $finalizeWins = ! str_starts_with($scenario, 'finalize_after_');
    $workerAction = $finalizeWins ? $scenario : 'finalize';
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        if ($finalizeWins) {
            $period = VatPeriod::whereKey(1)->lockForUpdate()->firstOrFail();
            $period->recalculate();
            $period->update(['status' => 'finalized']);
        } elseif ($scenario === 'finalize_after_record') {
            $record();
        } else {
            $posting->withdrawVat('Review', 1);
        }
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        expectProbe(is_resource($process), 'Could not start worker');
        // Windows process pipes do not reliably support non-blocking reads;
        // socket IPC proves the worker reached the lock before we release it.
        $signal = stream_socket_accept($server, 10);
        expectProbe(is_resource($signal), 'Worker failed to connect to IPC');
        stream_set_blocking($signal, false);
        waitForProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(300000);
        expectProbe(proc_get_status($process)['running'], 'Writer did not wait for the period lock');
        expectProbe(stream_get_contents($signal) === '', 'Worker finished before period lock release');
        DB::commit();
        waitForProbe($process, $signal, $pipes, $finalizeWins ? "REFUSED\n" : "OK\n");
        $period = VatPeriod::findOrFail(1);
        expectProbe($period->status === 'finalized', 'Period unexpectedly reopened');
        $expectedVat = in_array($scenario, ['withdraw', 'recalculate', 'finalize_after_record'], true) ? 15.0 : 0.0;
        expectProbe((float) $period->input_vat === $expectedVat, 'Frozen totals are incorrect');
        expectProbe((float) VatTransaction::sum('vat_amount') === $expectedVat, 'VAT rows differ from frozen totals');
        echo 'PASS '.$scenario."\n";
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        if (is_resource($signal)) {
            fclose($signal);
        }
        if (is_resource($process)) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
    }
}
fclose($server);
echo 'PASS 5 MySQL concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.")\n";
