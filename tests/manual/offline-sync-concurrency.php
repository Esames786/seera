<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials.
//
// Proves on a real InnoDB server that offline sync is idempotent under
// concurrency: the receipt row (client UUID) and the employee row are the
// mutexes, so two requests carrying the same UUID, an offline replay arriving
// while the first request is still open, and an offline check-in racing an
// online one each leave exactly one logical business record. Also replays a
// Supplier Credit expense twice around its approval: one expense, one bill.
// Runs against a disposable MySQL 8 server on 127.0.0.1:33479 whose datadir
// name starts with "seera-offline-lock-test-". It refuses any other server.
//
//   php tests/manual/offline-sync-concurrency.php <datadir>
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceRecord;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\OfflineSyncReceipt;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SiteExpenseApprovalSubject;
use App\Services\Hr\MobileAttendanceService;
use App\Services\SiteExpenses\SiteExpenseAccountingService;
use App\Services\Sync\AttendanceSyncHandler;
use App\Services\Sync\OfflineSyncService;
use App\Services\Sync\SiteExpenseSyncHandler;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-offline-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_offline_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_offline_probe_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Invalid probe database.');
}
if ($action === 'parent') {
    $pdo->exec('CREATE DATABASE '.$database);
}
foreach (['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'FILESYSTEM_DISK' => 'local'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
    exit(1);
});
config(['database.default' => 'offline_probe', 'database.connections.offline_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
config(['filesystems.disks.local.root' => sys_get_temp_dir().'/seera-offline-probe-files']);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$sync = app(OfflineSyncService::class);
$attendanceHandler = app(AttendanceSyncHandler::class);
$expenseHandler = app(SiteExpenseSyncHandler::class);
$mobile = app(MobileAttendanceService::class);
$position = ['latitude' => 24.7136, 'longitude' => 46.6753, 'accuracy' => 9.0];

$requestFor = function (int $userId, string $path = '/admin/mobile-sync/attendance'): Request {
    $user = User::findOrFail($userId);
    Auth::setUser($user);
    $request = Request::create($path, 'POST', []);
    $request->setUserResolver(fn () => $user);

    return $request;
};
$attendanceItem = fn (string $uuid, array $ids, string $action = 'check_in') => ['client_uuid' => $uuid, 'action' => $action, 'captured_at' => Carbon::now()->subMinutes(25),
    'payload' => $position + ['site_id' => $ids['site'], 'project_id' => $ids['project']], 'file' => null];
$expenseItem = function (string $uuid, array $ids, ?string $filePath = null, array $extra = []) {
    return ['client_uuid' => $uuid, 'action' => 'create', 'captured_at' => Carbon::now()->subHours(2),
        'payload' => $extra + ['expense_date' => today()->toDateString(), 'project_id' => $ids['project'], 'site_id' => $ids['site'], 'expense_category_id' => $ids['category'],
            'payment_type' => 'Cash', 'payment_account_id' => $ids['cash'], 'taxable_amount' => '250.00', 'vat_applicable' => '1', 'description' => 'Probe diesel receipt'],
        'file' => $filePath ? new UploadedFile($filePath, 'receipt.jpg', 'image/jpeg', null, true) : null];
};

if ($action !== 'parent') {
    // Worker: announce the moment it waits on the mutex, then report the outcome.
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    $needle = $action === 'attendance-vs-online' ? 'employees' : 'offline_sync_receipts';
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal, $needle) {
        if (! $announced && str_contains($sql, $needle) && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    $request = $requestFor($ids['user']);
    $results = match ($action) {
        'attendance-same-uuid', 'attendance-vs-online' => $sync->process($request, $attendanceHandler, [$attendanceItem($ids['uuid'], $ids)]),
        'expense-same-uuid' => $sync->process($request, $expenseHandler, [$expenseItem($ids['uuid'], $ids)]),
        'expense-retry-with-file' => $sync->process($request, $expenseHandler, [$expenseItem($ids['uuid'], $ids, $ids['file'])]),
    };
    fwrite($signal, 'RESULT '.json_encode($results[0])."\n");
    exit;
}

function checkOfflineProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitOfflineProbe($process, $signal, array $pipes, string $needle): string
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

// Additive path on MySQL: everything before the release migration, then the release migration, then a rerun.
$releaseFile = '2026_10_15_000001_create_offline_sync_receipts.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkOfflineProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$project = Project::create(['code' => 'OFF-PROBE', 'name' => 'Synthetic isolated project', 'status' => 'active']);
$site = Site::create(['code' => 'OFF-PROBE', 'name' => 'Synthetic isolated site', 'project_id' => $project->id, 'status' => 'active', 'latitude' => 24.7136, 'longitude' => 46.6753, 'geofence_radius' => 300, 'geofence_enabled' => true, 'attendance_inside_only' => true]);
$legacyEmployee = Employee::create(['employee_code' => 'PROBE-LEGACY', 'first_name' => 'Legacy', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $project->id, 'site_id' => $site->id]);
$legacy = AttendanceRecord::create(['employee_id' => $legacyEmployee->id, 'project_id' => $project->id, 'site_id' => $site->id, 'attendance_date' => '2026-01-05', 'check_in' => '08:00', 'check_out' => '17:00', 'status' => 'present', 'source' => 'gps', 'geofence_status' => 'inside']);
$before = $legacy->fresh()->getAttributes();
checkOfflineProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
checkOfflineProbe(array_diff_assoc($before, $legacy->fresh()->getAttributes()) === [], 'Historical GPS row changed');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
checkOfflineProbe(DB::getSchemaBuilder()->hasTable('offline_sync_receipts') && DB::getSchemaBuilder()->hasColumn('site_expenses', 'sync_client_uuid'), 'Release schema missing');
echo "PASS additive migration and rerun on MySQL; historical GPS row preserved\n";

// Masters: one worker user holding both mobile capabilities, two reviewers, accounts, category, workflow, posting rule.
$roles = [];
foreach (['worker' => ['Attendance' => ['view', 'mobile'], 'Site Expenses' => ['view', 'create']], 'first' => ['Site Expenses' => ['view', 'approve', 'reject']], 'finance' => ['Site Expenses' => ['view', 'approve', 'reject', 'post', 'retry'], 'Accounts Payable' => ['view']]] as $code => $grants) {
    $role = Role::create(['code' => 'OFF_'.strtoupper($code), 'name' => 'probe '.$code, 'access_scope' => 'Company Level', 'level' => 4, 'status' => 'active']);
    foreach ($grants as $module => $actions) {
        foreach ($actions as $permission) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => $module, 'action' => $permission])->id]);
        }
    }
    $roles[$code] = $role;
}
$users = [];
foreach ($roles as $code => $role) {
    $user = User::create(['name' => 'probe '.$code, 'email' => 'probe-'.$code.'@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active', 'mobile_access' => true]);
    $user->roles()->attach($role, ['is_primary' => true]);
    $users[$code] = $user->fresh();
}
$employee = Employee::create(['employee_code' => 'PROBE-EMP', 'first_name' => 'Synthetic', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $project->id, 'site_id' => $site->id, 'user_id' => $users['worker']->id]);
$account = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(['account_code' => $code], ['account_name' => $name, 'account_type' => $type, 'normal_balance' => $type === 'liability' ? 'credit' : 'debit', 'status' => 'active']);
$cash = $account('1110', 'Cash', 'asset');
$account('1120', 'Bank', 'asset');
$cost = $account('5200', 'Site consumables', 'expense');
$account('1300', 'Input VAT', 'asset');
$account('2100', 'Accounts Payable', 'liability');
$category = ExpenseCategory::create(['name' => 'Fuel', 'code' => 'OFF-FUEL', 'chart_of_account_id' => $cost->id, 'payment_type' => 'Both', 'vat_treatment' => 'VAT 15%', 'invoice_photo_required' => false, 'mobile_visible' => true, 'status' => 'active']);
$workflow = ApprovalWorkflow::create(['name' => 'Probe reviewers', 'module' => 'Site Expenses', 'trigger_action' => 'Expense Submitted', 'scope' => 'All Projects', 'auto_posting' => 'Create Accounting Entry', 'status' => 'active']);
foreach ([$roles['first'], $roles['finance']] as $i => $role) {
    $workflow->steps()->create(['step_no' => $i + 1, 'approver_role_id' => $role->id, 'is_required' => true, 'can_reject' => true]);
}
foreach (['Site Expense' => 'Site Expense Approved', 'Supplier Bill' => 'Bill Approved'] as $module => $event) {
    AutomaticPostingRule::firstOrCreate(['source_module' => $module, 'trigger_event' => $event], ['auto_post' => true, 'status' => 'active']);
}
$supplier = Supplier::create(['code' => 'OFF-SUP', 'name' => 'Synthetic supplier', 'status' => 'active']);
@mkdir(sys_get_temp_dir().'/seera-offline-probe-files', 0777, true);
$receiptPath = sys_get_temp_dir().'/seera-offline-probe-receipt.jpg';
$image = imagecreatetruecolor(64, 64);
imagejpeg($image, $receiptPath);
imagedestroy($image);

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkOfflineProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);
$base = ['user' => $users['worker']->id, 'employee' => $employee->id, 'site' => $site->id, 'project' => $project->id, 'category' => $category->id, 'cash' => $cash->id, 'file' => $receiptPath];
$uuid = fn () => (string) \Illuminate\Support\Str::uuid();

$scenarios = [
    // Parent runs the first request inside an open transaction (receipt + record uncommitted); the worker with the same UUID waits on the receipt lock, then replays.
    'attendance-same-uuid' => ['hold' => 'process-attendance', 'expect' => 'synced', 'replay' => true],
    // Parent holds the employee lock and performs the ONLINE check-in; the worker's offline check-in for the same day must report the collision.
    'attendance-vs-online' => ['hold' => 'employee-lock-online', 'expect' => 'needs_attention', 'reason' => 'already_checked_in'],
    'expense-same-uuid' => ['hold' => 'process-expense', 'expect' => 'synced', 'replay' => true],
    'expense-retry-with-file' => ['hold' => 'process-expense-file', 'expect' => 'synced', 'replay' => true],
];
foreach ($scenarios as $scenario => $spec) {
    AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->delete();
    $ids = $base + ['uuid' => $uuid()];
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        $request = $requestFor($users['worker']->id);
        $parentResult = null;
        if ($spec['hold'] === 'process-attendance') {
            $parentResult = $sync->process($request, $attendanceHandler, [$attendanceItem($ids['uuid'], $ids)])[0];
        } elseif ($spec['hold'] === 'process-expense') {
            $parentResult = $sync->process($request, $expenseHandler, [$expenseItem($ids['uuid'], $ids)])[0];
        } elseif ($spec['hold'] === 'process-expense-file') {
            $parentResult = $sync->process($request, $expenseHandler, [$expenseItem($ids['uuid'], $ids, $receiptPath)])[0];
        } else {
            Employee::withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
        }
        if ($parentResult !== null) {
            checkOfflineProbe($parentResult['status'] === 'synced', 'Parent first request failed: '.json_encode($parentResult));
        }
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $scenario, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkOfflineProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkOfflineProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitOfflineProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(200000);
        checkOfflineProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait on the lock in '.$scenario);
        if ($spec['hold'] === 'employee-lock-online') {
            $online = Request::create('/admin/hr/attendance/mobile/check-in', 'POST', $position);
            $online->setUserResolver(fn () => $users['worker']);
            $mobile->checkIn($online, $position);
        }
        DB::commit();
        $output = waitOfflineProbe($process, $signal, $pipes, "RESULT ");
        $result = json_decode(trim(substr($output, strpos($output, 'RESULT ') + 7)), true, flags: JSON_THROW_ON_ERROR);
        checkOfflineProbe($result['status'] === $spec['expect'], "Worker status in $scenario: ".json_encode($result));
        if (isset($spec['reason'])) {
            checkOfflineProbe($result['reason'] === $spec['reason'], "Worker reason in $scenario: ".json_encode($result));
        }
        if ($spec['replay'] ?? false) {
            checkOfflineProbe(($result['data']['replay'] ?? false) === true && $result['record']['id'] === $parentResult['record']['id'], "Worker should have replayed the parent's record in $scenario: ".json_encode($result));
        }
        if (str_starts_with($scenario, 'attendance')) {
            $rows = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->get();
            checkOfflineProbe($rows->count() === 1, 'Duplicate attendance rows: '.$rows->count());
            checkOfflineProbe($rows->first()->source === ($scenario === 'attendance-vs-online' ? 'gps' : 'gps_offline'), 'Unexpected source '.$rows->first()->source);
        } else {
            $rows = SiteExpense::withoutGlobalScopes()->where('sync_client_uuid', $ids['uuid'])->get();
            checkOfflineProbe($rows->count() === 1, 'Duplicate expenses: '.$rows->count());
            if ($scenario === 'expense-retry-with-file') {
                checkOfflineProbe($rows->first()->receipts()->count() === 1, 'Receipt rows: '.$rows->first()->receipts()->count());
            }
        }
        checkOfflineProbe(OfflineSyncReceipt::where('client_uuid', $ids['uuid'])->count() === ($scenario === 'attendance-vs-online' ? 1 : 1), 'Receipt rows wrong');
        echo "PASS concurrent $scenario: one logical record, worker ".$result['status'].(isset($result['reason']) && $result['reason'] ? ' ('.$result['reason'].')' : ($spec['replay'] ?? false ? ' (replay)' : ''))."\n";
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

// Supplier Credit synchronized twice around approval: one expense, one draft bill, no journal from the expense itself.
$ids = $base + ['uuid' => $uuid()];
$request = $requestFor($users['worker']->id);
$credit = fn () => $sync->process($request, $expenseHandler, [$expenseItem($ids['uuid'], $ids, null, ['payment_type' => 'Supplier Credit', 'supplier_id' => $supplier->id, 'payment_account_id' => null])])[0];
$first = $credit();
$second = $credit();
checkOfflineProbe($first['status'] === 'synced' && $second['status'] === 'synced' && ($second['data']['replay'] ?? false), 'Supplier Credit replay: '.json_encode([$first, $second]));
$expense = SiteExpense::withoutGlobalScopes()->where('sync_client_uuid', $ids['uuid'])->sole();
$runtime = app(ApprovalRuntimeService::class);
$subject = app(SiteExpenseApprovalSubject::class);
$instance = $runtime->start($subject, $expense->id, $workflow->id, $users['worker']);
$runtime->decide($subject, $expense->id, $instance->id, $instance->steps[0]->id, $users['first'], 'approve');
$runtime->decide($subject, $expense->id, $instance->id, $instance->steps[1]->id, $users['finance'], 'approve');
app(SiteExpenseAccountingService::class)->attempt($expense->id, $users['finance']->id);
$third = $credit();
app(SiteExpenseAccountingService::class)->attempt($expense->id, $users['finance']->id);
checkOfflineProbe(($third['data']['replay'] ?? false) === true, 'Third Supplier Credit sync should replay');
checkOfflineProbe(SiteExpense::withoutGlobalScopes()->where('sync_client_uuid', $ids['uuid'])->count() === 1, 'Duplicate Supplier Credit expense');
checkOfflineProbe(DB::table('supplier_bills')->where('site_expense_id', $expense->id)->count() === 1, 'Supplier bills: '.DB::table('supplier_bills')->count());
checkOfflineProbe($expense->fresh()->journal_entry_id === null, 'Supplier Credit expense must not post its own journal');
echo "PASS Supplier Credit synchronized twice: one expense, one draft bill\n";
echo 'PASS 5 MySQL offline sync concurrency / replay scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
