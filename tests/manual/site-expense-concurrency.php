<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials.
use App\Models\ApprovalWorkflow;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SiteExpenseApprovalSubject;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-site-expense-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_expense_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_expense_probe_[a-f0-9]{12}$/', $database)) {
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
config(['database.default' => 'expense_probe', 'database.connections.expense_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$runtime = app(ApprovalRuntimeService::class);
$subject = app(SiteExpenseApprovalSubject::class);
$accounting = app(SiteExpenseAccountingService::class);

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        if (! $announced && str_contains($sql, 'site_expenses') && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    if ($action === 'approve') {
        $runtime->decide($subject, $ids['expense'], $ids['instance'], $ids['step'], User::findOrFail($ids['actor']), 'approve');
    } elseif ($action === 'settle') {
        $accounting->settle($ids['expense'], $ids['account'], $ids['actor']);
    } else {
        $accounting->attempt($ids['expense'], $ids['actor']);
    }
    fwrite($signal, "OK\n");
    exit;
}

function checkExpenseProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitExpenseProbe($process, $signal, array $pipes, string $needle): void
{
    $output = '';
    $deadline = microtime(true) + 30;
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
    throw new RuntimeException('Worker timed out: '.$output);
}

// Migrate the existing schema first, populate a historical master, then apply
// the release migration: proves both additive and fresh install paths.
$releaseFile = '2026_10_01_000001_create_site_expenses.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkExpenseProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$legacy = ExpenseCategory::create(['code' => 'LEGACY', 'name' => 'Historical category', 'linked_account' => 'Unmapped text', 'payment_type' => 'Both', 'vat_treatment' => 'Non-VAT']);
$before = $legacy->getAttributes();
checkExpenseProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
checkExpenseProbe(array_diff_assoc($before, $legacy->fresh()->getAttributes()) === [], 'Historical category changed');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
checkExpenseProbe(ChartOfAccount::where('account_code', '2310')->count() === 1, 'Account duplicated on retry');
echo "PASS additive migration and rerun; historical master preserved\n";
$project = Project::create(['code' => 'SE-PROBE', 'name' => 'Synthetic isolated project']);
$site = Site::create(['code' => 'SE-PROBE', 'name' => 'Synthetic isolated site', 'project_id' => $project->id]);
$actors = [];
foreach (['requester', 'approver'] as $name) {
    $role = Role::create(['code' => strtoupper($name), 'name' => $name, 'access_scope' => 'Company Level', 'level' => 4, 'status' => 'active']);
    foreach (['view', 'create', 'edit', 'approve', 'reject', 'post', 'retry'] as $permission) {
        $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Site Expenses', 'action' => $permission])->id]);
    }
    $actor = User::create(['name' => $name, 'email' => $name.'@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active']);
    $actor->roles()->attach($role, ['is_primary' => true]);
    $actors[$name] = $actor;
}
$accounts = [];
foreach (['1110' => 'asset', '1300' => 'asset', '5200' => 'expense'] as $code => $type) {
    $accounts[$code] = ChartOfAccount::create(['account_code' => $code, 'account_name' => 'Probe '.$code, 'account_type' => $type, 'normal_balance' => 'debit', 'status' => 'active']);
}
$category = ExpenseCategory::create(['code' => 'PROBE', 'name' => 'Synthetic fuel', 'chart_of_account_id' => $accounts['5200']->id,
    'payment_type' => 'Both', 'vat_treatment' => 'VAT 15%', 'invoice_photo_required' => false, 'status' => 'active']);
$supplier = Supplier::create(['code' => 'SE-PROBE', 'name' => 'Synthetic supplier', 'status' => 'active']);
AutomaticPostingRule::create(['source_module' => 'Site Expense', 'trigger_event' => 'Site Expense Approved', 'auto_post' => true, 'status' => 'active']);
$workflow = ApprovalWorkflow::create(['name' => 'Probe expense workflow', 'module' => 'Site Expenses', 'trigger_action' => 'Expense Submitted', 'scope' => 'All Projects', 'auto_posting' => 'Create Accounting Entry', 'status' => 'active']);
$workflow->steps()->create(['step_no' => 1, 'approver_role_id' => $actors['approver']->roles()->first()->id, 'is_required' => true, 'can_reject' => true]);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkExpenseProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);
$scenarios = ['final_approval_vs_retry', 'duplicate_final_approval', 'duplicate_posting', 'duplicate_supplier_bill', 'duplicate_reimbursement'];
foreach ($scenarios as $i => $scenario) {
    $credit = $scenario === 'duplicate_supplier_bill';
    $settle = $scenario === 'duplicate_reimbursement';
    $employee = $settle ? Employee::create(['employee_code' => 'PROBE-EMP', 'first_name' => 'Synthetic', 'user_id' => $actors['requester']->id]) : null;
    $expense = SiteExpense::create(['expense_number' => 'PROBE-'.$i, 'expense_date' => today(), 'submitted_by_user_id' => $actors['requester']->id,
        'employee_id' => $employee?->id, 'project_id' => $project->id, 'site_id' => $site->id, 'expense_category_id' => $category->id,
        'supplier_id' => $credit ? $supplier->id : null, 'payment_type' => $credit ? 'Supplier Credit' : ($settle ? 'Employee Reimbursement' : 'Cash'),
        'payment_account_id' => $accounts['1110']->id, 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115, 'description' => 'Synthetic expense', 'status' => 'draft']);
    $instance = $runtime->start($subject, $expense->id, $workflow->id, $actors['requester']);
    $ids = ['expense' => $expense->id, 'instance' => $instance->id, 'step' => $instance->steps[0]->id, 'actor' => $actors['approver']->id, 'account' => $accounts['1110']->id];
    if (in_array($scenario, ['duplicate_posting', 'duplicate_supplier_bill'], true)) {
        // Simulate a recoverable post-commit interruption before any accounting.
        DB::table('approval_instances')->where('id', $instance->id)->update(['status' => 'approved', 'completed_at' => now()]);
        $instance->steps()->update(['status' => 'approved', 'decided_by' => $actors['approver']->id, 'decided_at' => now()]);
        $expense->update(['status' => 'approved_pending_posting', 'approved_at' => now()]);
    } elseif ($settle) {
        $runtime->decide($subject, $expense->id, $instance->id, $ids['step'], $actors['approver'], 'approve');
    }
    $workerAction = $scenario === 'duplicate_final_approval' ? 'approve' : ($settle ? 'settle' : 'retry');
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        SiteExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkExpenseProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkExpenseProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitExpenseProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        checkExpenseProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait on source lock');
        if (str_starts_with($scenario, 'final_') || $scenario === 'duplicate_final_approval') {
            $runtime->decide($subject, $expense->id, $instance->id, $ids['step'], $actors['approver'], 'approve');
        } elseif ($settle) {
            $accounting->settle($expense->id, $ids['account'], $ids['actor']);
        } else {
            $accounting->attempt($expense->id, $ids['actor']);
        }
        DB::commit();
        waitExpenseProbe($process, $signal, $pipes, "OK\n");
        $expense->refresh();
        checkExpenseProbe($instance->fresh()->status === 'approved', 'Approval lost');
        checkExpenseProbe(DB::table('supplier_bills')->where('site_expense_id', $expense->id)->count() === ($credit ? 1 : 0), 'Missing or duplicate bill');
        checkExpenseProbe(DB::table('journal_entries')->where('source_module', 'Site Expense')->where('source_id', $expense->id)->count() === ($credit ? 0 : 1), 'Missing or duplicate expense journal');
        checkExpenseProbe($expense->status === ($credit ? 'approved_pending_posting' : 'posted'), 'Wrong accounting status: '.$expense->posting_error);
        if ($settle) {
            checkExpenseProbe(DB::table('journal_entries')->where('source_module', 'Site Expense Reimbursement')->where('source_id', $expense->id)->count() === 1, 'Duplicate settlement');
        }
        echo 'PASS '.$scenario."\n";
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
echo 'PASS 5 MySQL Site Expense concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.")\n";
