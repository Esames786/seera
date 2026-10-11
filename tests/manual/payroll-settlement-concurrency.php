<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials.
//
// Proves on a real InnoDB server that salary settlement is serialised on the
// payroll run row: two simultaneous payments, payment + replay (same key),
// payment + salary-payment reversal and payment + payroll-posting reversal
// each leave exactly one logical settlement and a consistent run state.
//
//   php tests/manual/payroll-settlement-concurrency.php <datadir>
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PayrollSettlement;
use App\Models\Project;
use App\Models\User;
use App\Services\Payroll\PayrollAccountingService;
use App\Services\Payroll\PayrollSettlementService;
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
$database = $action === 'parent' ? 'seera_settle_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_settle_probe_[a-f0-9]{12}$/', $database)) {
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
config(['database.default' => 'settle_probe', 'database.connections.settle_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$settlement = app(PayrollSettlementService::class);
$accounting = app(PayrollAccountingService::class);

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
        match ($action) {
            'pay' => $settlement->settle($ids['run'], ['payment_date' => '2026-10-05', 'payment_account_id' => $ids['bank'], 'idempotency_key' => $ids['key']], $ids['actor']),
            'reverse-payment' => $settlement->reverse($ids['run'], 'probe payment reversal', $ids['actor']),
            'reverse-posting' => $accounting->reverse($ids['run'], 'probe posting reversal', $ids['actor']),
        };
        fwrite($signal, "OK\n");
    } catch (ValidationException $e) {
        fwrite($signal, 'REFUSED '.json_encode($e->errors())."\n");
    }
    exit;
}

function checkSettleProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitSettleProbe($process, $signal, array $pipes, string $needle): string
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

// Baseline schema with a historical posted run, then the release migration (additive path), then a rerun.
$releaseFile = '2026_10_11_000001_add_payroll_settlement.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkSettleProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$legacy = PayrollRun::create(['code' => 'PR-LEGACY', 'payroll_month' => 1, 'payroll_year' => 2026, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => 'approved', 'total_employees' => 0, 'accounting_status' => 'posted']);
$before = $legacy->fresh()->getAttributes();
checkSettleProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
checkSettleProbe(array_diff_assoc($before, $legacy->fresh()->getAttributes()) === [], 'Historical run changed');
checkSettleProbe($legacy->fresh()->payment_status === 'not_paid' && (float) $legacy->fresh()->paid_amount === 0.0, 'Historical run default payment state missing');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
echo "PASS additive migration and rerun on MySQL; historical run preserved\n";

$expense = ChartOfAccount::create(['account_code' => '5100', 'account_name' => 'Probe salary expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'status' => 'active']);
$payable = ChartOfAccount::create(['account_code' => '2300', 'account_name' => 'Probe payroll payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
$deduction = ChartOfAccount::create(['account_code' => '2320', 'account_name' => 'Probe deduction liability', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
$bank = ChartOfAccount::create(['account_code' => '1120', 'account_name' => 'Probe bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'status' => 'active']);
AutomaticPostingRule::create(['source_module' => 'Payroll', 'trigger_event' => 'Payroll Approved', 'debit_account_id' => $expense->id, 'credit_account_id' => $payable->id, 'deduction_account_id' => $deduction->id, 'cost_center_rule' => 'Employee Project / Department', 'auto_post' => true, 'status' => 'active']);
$actor = User::create(['name' => 'probe finance', 'email' => 'probe-finance@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active']);
$project = Project::create(['code' => 'SET-PROBE', 'name' => 'Synthetic isolated project']);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkSettleProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

foreach (['pay_vs_pay', 'pay_vs_replay', 'pay_vs_payment_reversal', 'pay_vs_posting_reversal'] as $i => $scenario) {
    $run = PayrollRun::create(['code' => 'PR-PROBE-'.$i, 'payroll_month' => 2 + $i, 'payroll_year' => 2026, 'period_start' => '2026-0'.(2 + $i).'-01', 'period_end' => '2026-0'.(2 + $i).'-28',
        'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'total_employees' => 2, 'gross_amount' => 17000, 'total_deductions' => 500, 'net_amount' => 16500]);
    foreach ([['PROBE-A-'.$i, 8000, 300], ['PROBE-B-'.$i, 9000, 200]] as [$code, $basic, $ded]) {
        $employee = Employee::create(['employee_code' => $code, 'first_name' => 'Synthetic', 'last_name' => $code, 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $project->id]);
        PayrollRunItem::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'basic_salary' => $basic, 'total_allowances' => 0, 'overtime_amount' => 0,
            'total_deductions' => $ded, 'gross_amount' => $basic, 'net_amount' => $basic - $ded, 'present_days' => 20, 'leave_days' => 0]);
    }
    $accounting->attempt($run->id, $actor->id);   // posted payroll journal: the precondition of any payment
    checkSettleProbe($run->fresh()->accounting_status === 'posted', 'Pre-posting failed');
    $key = 'probe-key-'.$i;
    $ids = ['run' => $run->id, 'actor' => $actor->id, 'bank' => $bank->id, 'key' => $scenario === 'pay_vs_replay' ? $key : 'worker-key-'.$i];
    $workerAction = match ($scenario) {
        'pay_vs_payment_reversal' => 'reverse-payment',
        'pay_vs_posting_reversal' => 'reverse-posting',
        default => 'pay',
    };
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();   // parent holds the run mutex
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkSettleProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkSettleProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitSettleProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        checkSettleProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait on the run lock');
        // Parent records the payment while the worker queues, then commits.
        $settlement->settle($run->id, ['payment_date' => '2026-10-05', 'payment_account_id' => $bank->id, 'idempotency_key' => $key], $actor->id);
        DB::commit();
        $output = trim(waitSettleProbe($process, $signal, $pipes, "\n"));
        $run->refresh();
        $settlements = PayrollSettlement::where('payroll_run_id', $run->id)->get();
        $paymentJournals = JournalEntry::where('source_module', 'Payroll Settlement')->whereIn('source_id', $settlements->pluck('id'))->get();
        checkSettleProbe($settlements->count() === 1, 'Duplicate settlements: '.$settlements->count());
        checkSettleProbe($paymentJournals->count() === 1, 'Duplicate payment journals: '.$paymentJournals->count());
        switch ($scenario) {
            case 'pay_vs_pay':
                checkSettleProbe(str_starts_with($output, 'REFUSED') && str_contains($output, 'already paid'), 'Second payment should be refused: '.$output);
                checkSettleProbe($run->payment_status === 'paid' && (float) $run->paid_amount === 16500.0, 'Run should be paid once');
                break;
            case 'pay_vs_replay':
                checkSettleProbe(str_starts_with($output, 'OK'), 'Exact replay should answer with the existing settlement: '.$output);
                checkSettleProbe($run->payment_status === 'paid', 'Run should stay paid');
                break;
            case 'pay_vs_payment_reversal':
                checkSettleProbe(str_starts_with($output, 'OK'), 'Payment reversal should succeed after the parent released the lock: '.$output);
                checkSettleProbe($run->payment_status === 'not_paid' && $settlements->first()->reversal_journal_id !== null, 'Reversal state wrong');
                checkSettleProbe(JournalEntry::where('source_module', 'Manual')->where('source_id', $paymentJournals->first()->id)->count() === 1, 'Reversal journal count wrong');
                checkSettleProbe($run->accounting_status === 'posted', 'Payment reversal must not touch the payroll posting');
                break;
            case 'pay_vs_posting_reversal':
                checkSettleProbe(str_starts_with($output, 'REFUSED') && str_contains($output, 'Reverse the salary payment first'), 'Posting reversal must be refused while paid: '.$output);
                checkSettleProbe($run->accounting_status === 'posted' && $run->reversal_journal_id === null && $run->payment_status === 'paid', 'Run state changed by the refused reversal');
                break;
        }
        checkSettleProbe(DB::table('activity_logs')->where('action', 'Salary payment recorded')->where('description', 'like', $run->code.'%')->count() === 1, 'Duplicate settlement audit');
        echo 'PASS '.$scenario.': one settlement ('.$paymentJournals->first()->journal_number.'), worker '.$output."\n";
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
echo 'PASS 4 MySQL salary settlement concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
