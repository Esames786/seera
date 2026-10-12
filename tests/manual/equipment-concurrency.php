<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials.
//
// Proves on a real InnoDB server that equipment actions are serialised on
// their rows: two users assigning one asset at once leave one active
// assignment; two Finance users posting one fuel log leave one journal; two
// posts of a supplier-credit record create one supplier bill.
//
//   php tests/manual/equipment-concurrency.php <datadir>
use App\Models\ChartOfAccount;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentFuelLog;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Equipment\EquipmentAssignmentService;
use App\Services\Equipment\EquipmentCostService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-equipment-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_equipment_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_equipment_probe_[a-f0-9]{12}$/', $database)) {
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
config(['database.default' => 'equipment_probe', 'database.connections.equipment_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$assignments = app(EquipmentAssignmentService::class);
$costs = app(EquipmentCostService::class);

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $actor = User::find($ids['actor']);
    auth()->login($actor);
    $announced = false;
    $table = $action === 'assign' ? 'equipment_assets' : 'equipment_fuel_logs';
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal, $table) {
        if (! $announced && str_contains($sql, $table) && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    try {
        if ($action === 'assign') {
            $assignments->assign(EquipmentAsset::withoutGlobalScopes()->findOrFail($ids['asset']), ['project_id' => $ids['project']], $actor);
        } else {
            $costs->post(EquipmentFuelLog::withoutGlobalScopes()->findOrFail($ids['fuel']), $actor);
        }
        fwrite($signal, "OK\n");
    } catch (ValidationException $e) {
        fwrite($signal, 'REFUSED '.json_encode($e->errors())."\n");
    }
    exit;
}

function checkEquipmentProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitEquipmentProbe($process, $signal, array $pipes, string $needle): string
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

// Baseline schema, then the release migration (additive path), then a rerun.
$releaseFile = '2026_10_12_000001_create_equipment_module.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkEquipmentProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$permissionsBefore = DB::table('permissions')->count();
checkEquipmentProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
checkEquipmentProbe(DB::table('permissions')->where('module', 'Equipment')->count() === count(\App\Models\Permission::ACTIONS), 'Equipment permission rows incomplete');
echo "PASS additive migration and rerun on MySQL; Equipment permission rows completed idempotently (".($permissionsBefore)." → ".DB::table('permissions')->count().")\n";

$actor = User::create(['name' => 'probe fleet', 'email' => 'probe-fleet@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active']);
$role = \App\Models\Role::create(['name' => 'Probe fleet role', 'code' => 'PROBE_FLEET', 'level' => 1, 'access_scope' => 'All Company', 'status' => 'active']);
$actor->roles()->attach($role, ['is_primary' => true]);
auth()->login($actor->fresh());   // company scope so scoped relations (projects, sites, employees) resolve in the services
$projectA = Project::create(['code' => 'EQ-PROBE-A', 'name' => 'Synthetic project A', 'status' => 'active']);
$projectB = Project::create(['code' => 'EQ-PROBE-B', 'name' => 'Synthetic project B', 'status' => 'active']);
$fuelAccount = ChartOfAccount::create(['account_code' => '5300', 'account_name' => 'Probe fuel expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'status' => 'active']);
$cash = ChartOfAccount::create(['account_code' => '1110', 'account_name' => 'Probe cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'status' => 'active']);
ChartOfAccount::create(['account_code' => '2100', 'account_name' => 'Probe accounts payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'status' => 'active']);
$category = ExpenseCategory::create(['name' => 'Probe fuel', 'code' => 'EQ-PROBE-FUEL', 'chart_of_account_id' => $fuelAccount->id, 'payment_type' => 'Cash', 'status' => 'active']);
$supplier = Supplier::create(['name' => 'Probe fuel station', 'code' => 'EQ-PROBE-SUP', 'status' => 'active']);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkEquipmentProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

foreach (['assign_vs_assign', 'post_vs_post', 'bill_vs_bill'] as $i => $scenario) {
    $asset = EquipmentAsset::create(['asset_code' => 'EQ-PROBE-'.$i, 'name' => 'Synthetic excavator '.$i, 'asset_type' => 'equipment', 'status' => 'available', 'meter_type' => 'hours', 'created_by' => $actor->id]);
    $fuel = null;
    if ($scenario !== 'assign_vs_assign') {
        $assignments->assign($asset, ['project_id' => $projectA->id], $actor);
        $fuel = $costs->recordFuel($asset, ['fuel_date' => '2026-10-01', 'quantity' => 100, 'rate' => 3, 'expense_category_id' => $category->id,
            'payment_type' => $scenario === 'bill_vs_bill' ? 'Supplier Credit' : 'Cash', 'payment_account_id' => $scenario === 'bill_vs_bill' ? null : $cash->id, 'supplier_id' => $scenario === 'bill_vs_bill' ? $supplier->id : null], $actor);
    }
    $ids = ['actor' => $actor->id, 'asset' => $asset->id, 'project' => $projectB->id, 'fuel' => $fuel?->id];
    $workerAction = $scenario === 'assign_vs_assign' ? 'assign' : 'post';
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        if ($scenario === 'assign_vs_assign') {
            EquipmentAsset::withoutGlobalScopes()->whereKey($asset->id)->lockForUpdate()->firstOrFail();   // parent holds the asset mutex
        } else {
            EquipmentFuelLog::withoutGlobalScopes()->whereKey($fuel->id)->lockForUpdate()->firstOrFail();   // parent holds the record mutex
        }
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkEquipmentProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkEquipmentProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitEquipmentProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        checkEquipmentProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait on the row lock');
        if ($scenario === 'assign_vs_assign') {
            $assignments->assign($asset, ['project_id' => $projectA->id], $actor);   // parent assigns to A while the worker (B) queues
        } else {
            $costs->post($fuel, $actor);   // parent posts while the worker queues
        }
        DB::commit();
        $output = trim(waitEquipmentProbe($process, $signal, $pipes, "\n"));
        checkEquipmentProbe(str_starts_with($output, 'OK'), 'Worker should finish after the lock is released: '.$output);
        if ($scenario === 'assign_vs_assign') {
            $active = EquipmentAssignment::where('equipment_asset_id', $asset->id)->where('is_active', true)->get();
            checkEquipmentProbe($active->count() === 1, 'Expected one active assignment, found '.$active->count());
            checkEquipmentProbe($active->first()->project_id === $projectB->id, 'The later assignment (worker, project B) should be the active one');
            checkEquipmentProbe(EquipmentAssignment::where('equipment_asset_id', $asset->id)->count() === 2, 'Both assignments must be kept in the history');
            checkEquipmentProbe($asset->fresh()->current_project_id === $projectB->id, 'Asset snapshot must follow the active assignment');
            echo 'PASS '.$scenario.": one active assignment (project B), two rows in history, worker $output\n";
        } elseif ($scenario === 'post_vs_post') {
            $journals = JournalEntry::where('source_module', EquipmentFuelLog::SOURCE_MODULE)->where('source_id', $fuel->id)->get();
            checkEquipmentProbe($journals->count() === 1, 'Duplicate fuel journals: '.$journals->count());
            checkEquipmentProbe($fuel->fresh()->accounting_status === 'posted', 'Fuel log should be posted once');
            echo 'PASS '.$scenario.': one fuel journal ('.$journals->first()->journal_number."), worker $output\n";
        } else {
            $bills = SupplierBill::withoutGlobalScopes()->where('bill_number', $fuel->fuel_number)->get();
            checkEquipmentProbe($bills->count() === 1, 'Duplicate supplier bills: '.$bills->count());
            checkEquipmentProbe(JournalEntry::where('source_module', EquipmentFuelLog::SOURCE_MODULE)->where('source_id', $fuel->id)->count() === 0, 'A supplier-credit record must not post its own journal');
            checkEquipmentProbe($fuel->fresh()->supplier_bill_id === $bills->first()->id && $fuel->fresh()->accounting_status === 'bill_pending', 'Fuel log should link the one draft bill');
            echo 'PASS '.$scenario.': one draft supplier bill ('.$bills->first()->bill_number."), no equipment journal, worker $output\n";
        }
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
echo 'PASS 3 MySQL equipment concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
