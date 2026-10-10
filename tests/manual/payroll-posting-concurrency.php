<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials.
//
// Proves on a real InnoDB server that payroll posting is serialised on the
// payroll run row: two simultaneous Post requests, Post + Retry, two Retry
// requests and Post + Reverse each leave exactly one original journal.
//
//   php tests/manual/payroll-posting-concurrency.php <datadir>
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Project;
use App\Models\User;
use App\Services\Payroll\PayrollAccountingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-payroll-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_payroll_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_payroll_probe_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Invalid probe database.');
}
if ($action === 'parent') {
    $pdo->exec('CREATE DATABASE '.$database);
}
foreach (['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
    exit(1);
});
config(['database.default' => 'payroll_probe', 'database.connections.payroll_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$service = app(PayrollAccountingService::class);

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        if (! $announced && str_contains($sql, 'payroll_runs') && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    try {
        if ($action === 'reverse') {
            $service->reverse($ids['run'], 'probe reversal', $ids['actor']);
        } else {
            $service->attempt($ids['run'], $ids['actor']);
        }
        fwrite($signal, "OK\n");
    } catch (ValidationException $e) {
        fwrite($signal, 'REFUSED '.json_encode($e->errors())."\n");
    }
    exit;
}

function checkPayrollProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitPayrollProbe($process, $signal, array $pipes, string $needle): string
{
    $output = '';
    $deadline = microtime(true) + 30;
    while (microtime(true) < $deadline) {
        $output .= stream_get_contents($signal);
        if (str_contains($output, $needle)) {
            return $output;
        }
        if (! proc_get_status($process)['running']) {
            throw new RuntimeException('Worker exited: '.$output.stream_get_contents($pipes[2]));
        }
        usleep(20000);
    }
    throw new RuntimeException('Worker timed out: '.$output);
}

// Baseline schema, a historical run, then the release migration (additive path), then a rerun.
$releaseFile = '2026_10_10_000001_add_payroll_accounting_state.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkPayrollProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$legacy = PayrollRun::create(['code' => 'PR-LEGACY', 'payroll_month' => 1, 'payroll_year' => 2026, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'approved', 'total_employees' => 0]);
$before = $legacy->fresh()->getAttributes();
checkPayrollProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
checkPayrollProbe(array_diff_assoc($before, $legacy->fresh()->getAttributes()) === [], 'Historical run changed');
checkPayrollProbe($legacy->fresh()->accounting_status === 'not_posted', 'Historical run default accounting state missing');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
echo "PASS additive migration and rerun on MySQL; historical run preserved\n";

$expense = ChartOfAccount::create(['account_code' => '5100', 'account_name' => 'Probe salary expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'status' => 'active']);
$payable = ChartOfAccount::create(['account_code' => '2300', 'account_name' => 'Probe payroll payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
$deduction = ChartOfAccount::create(['account_code' => '2320', 'account_name' => 'Probe deduction liability', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
AutomaticPostingRule::create(['source_module' => 'Payroll', 'trigger_event' => 'Payroll Approved', 'debit_account_id' => $expense->id, 'credit_account_id' => $payable->id, 'deduction_account_id' => $deduction->id, 'cost_center_rule' => 'Employee Project / Department', 'auto_post' => true, 'status' => 'active']);
$actor = User::create(['name' => 'probe finance', 'email' => 'probe-finance@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active']);
$project = Project::create(['code' => 'PAY-PROBE', 'name' => 'Synthetic isolated project']);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkPayrollProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

foreach (['post_vs_post', 'post_vs_retry', 'retry_vs_retry', 'post_vs_reverse'] as $i => $scenario) {
    $run = PayrollRun::create(['code' => 'PR-PROBE-'.$i, 'payroll_month' => 2 + $i, 'payroll_year' => 2026, 'period_start' => '2026-0'.(2 + $i).'-01', 'period_end' => '2026-0'.(2 + $i).'-28',
        'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'total_employees' => 2, 'gross_amount' => 17000, 'total_deductions' => 500, 'net_amount' => 16500]);
    foreach ([['PROBE-A-'.$i, 8000, 300], ['PROBE-B-'.$i, 9000, 200]] as [$code, $basic, $ded]) {
        $employee = Employee::create(['employee_code' => $code, 'first_name' => 'Synthetic', 'last_name' => $code, 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $project->id]);
        PayrollRunItem::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'basic_salary' => $basic, 'total_allowances' => 0, 'overtime_amount' => 0,
            'total_deductions' => $ded, 'gross_amount' => $basic, 'net_amount' => $basic - $ded, 'present_days' => 20, 'leave_days' => 0]);
    }
    if (in_array($scenario, ['post_vs_retry', 'retry_vs_retry'], true)) {
        // Simulate an earlier failed attempt (approved, nothing linked, error kept).
        $run->update(['accounting_status' => 'failed', 'posting_error' => 'simulated earlier failure']);
    }
    if ($scenario === 'post_vs_reverse') {
        $service->attempt($run->id, $actor->id);   // posted before the race; the race is Post(retry) vs Reverse
        checkPayrollProbe($run->fresh()->accounting_status === 'posted', 'Pre-posting failed');
    }
    $ids = ['run' => $run->id, 'actor' => $actor->id];
    $workerAction = $scenario === 'post_vs_reverse' ? 'reverse' : 'post';
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();   // parent holds the run mutex
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkPayrollProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkPayrollProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitPayrollProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        checkPayrollProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait on the run lock');
        // Parent performs its own transition while the worker queues, then commits.
        if ($scenario === 'post_vs_reverse') {
            $service->attempt($run->id, $actor->id);   // a retry on an already posted run: no-op
        } else {
            $service->attempt($run->id, $actor->id);
        }
        DB::commit();
        $output = trim(waitPayrollProbe($process, $signal, $pipes, "\n"));
        $journals = JournalEntry::where('source_module', 'Payroll')->where('source_id', $run->id)->get();
        $run->refresh();
        checkPayrollProbe($journals->count() === 1, 'Duplicate payroll journals: '.$journals->count());
        checkPayrollProbe((int) $run->journal_entry_id === (int) $journals->first()->id, 'Run not linked to its journal');
        if ($scenario === 'post_vs_reverse') {
            checkPayrollProbe(str_starts_with($output, 'OK'), 'Reverse should succeed after the parent released the lock: '.$output);
            checkPayrollProbe($run->accounting_status === 'reversed' && $run->reversal_journal_id !== null, 'Reversal state wrong');
            checkPayrollProbe(JournalEntry::where('source_module', 'Manual')->where('source_id', $journals->first()->id)->count() === 1, 'Reversal journal count wrong');
        } else {
            checkPayrollProbe(str_starts_with($output, 'OK'), 'Worker should finish as an idempotent no-op: '.$output);
            checkPayrollProbe($run->accounting_status === 'posted' && $run->posting_error === null, 'Run should be posted with no error');
        }
        checkPayrollProbe(DB::table('activity_logs')->where('action', 'Payroll accounting entry created')->where('description', 'like', $run->code.'%')->count() === 1, 'Duplicate posting audit');
        echo 'PASS '.$scenario.': one original journal ('.$journals->first()->journal_number.'), worker '.$output."\n";
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        if (is_resource($process) && proc_get_status($process)['running']) {
            proc_terminate($process);
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        if (is_resource($signal)) {
            fclose($signal);
        }
        if (is_resource($process)) {
            proc_close($process);
        }
    }
}
fclose($server);
echo 'PASS 4 MySQL payroll posting concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
