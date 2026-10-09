<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials.
//
// Proves on a real InnoDB server that two simultaneous mobile check-ins create
// one attendance row and two simultaneous check-outs produce one check-out.
// SQLite cannot demonstrate row locking, so this probe runs against a
// disposable MySQL 8 server on 127.0.0.1:33479 whose datadir name starts with
// "seera-attendance-lock-test-". It refuses any other server.
//
//   php tests/manual/mobile-attendance-concurrency.php <datadir>
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\Hr\MobileAttendanceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-attendance-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_attendance_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_attendance_probe_[a-f0-9]{12}$/', $database)) {
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
config(['database.default' => 'attendance_probe', 'database.connections.attendance_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$service = app(MobileAttendanceService::class);
$position = ['latitude' => 24.7136, 'longitude' => 46.6753, 'accuracy' => 9.0];

$requestFor = function (int $userId) use ($position): Request {
    $user = User::findOrFail($userId);
    Auth::setUser($user);
    $request = Request::create('/admin/hr/attendance/mobile/check-in', 'POST', $position);
    $request->setUserResolver(fn () => $user);

    return $request;
};

if ($action !== 'parent') {
    // Worker: announce the moment it waits on the employee row lock, then report the outcome.
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        if (! $announced && str_contains($sql, 'employees') && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    try {
        $request = $requestFor($ids['user']);
        $action === 'check-out' ? $service->checkOut($request, $position) : $service->checkIn($request, $position);
        fwrite($signal, "OK\n");
    } catch (ValidationException $e) {
        fwrite($signal, 'REFUSED '.json_encode($e->errors())."\n");
    }
    exit;
}

function checkAttendanceProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitAttendanceProbe($process, $signal, array $pipes, string $needle): string
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

// Baseline schema first, a historical manual row, then the release migration: proves the additive path on MySQL.
$releaseFile = '2026_10_09_000001_add_gps_evidence_to_attendance_records.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkAttendanceProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$project = Project::create(['code' => 'ATT-PROBE', 'name' => 'Synthetic isolated project']);
$site = Site::create(['code' => 'ATT-PROBE', 'name' => 'Synthetic isolated site', 'project_id' => $project->id, 'status' => 'active', 'latitude' => 24.7136, 'longitude' => 46.6753, 'geofence_radius' => 300, 'geofence_enabled' => true, 'attendance_inside_only' => true]);
$role = Role::create(['code' => 'ATT_PROBE', 'name' => 'probe', 'access_scope' => 'Company Level', 'level' => 4, 'status' => 'active']);
foreach (['view', 'mobile'] as $permission) {
    $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Attendance', 'action' => $permission])->id]);
}
$user = User::create(['name' => 'probe worker', 'email' => 'probe-worker@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active', 'mobile_access' => true]);
$user->roles()->attach($role, ['is_primary' => true]);
$legacyEmployee = Employee::create(['employee_code' => 'PROBE-LEGACY', 'first_name' => 'Legacy', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $project->id, 'site_id' => $site->id]);
$legacy = AttendanceRecord::create(['employee_id' => $legacyEmployee->id, 'project_id' => $project->id, 'site_id' => $site->id, 'attendance_date' => '2026-01-05', 'check_in' => '08:00', 'check_out' => '17:00', 'status' => 'present', 'source' => 'manual', 'geofence_status' => 'inside']);
$before = $legacy->fresh()->getAttributes();   // as stored (TIME/DATE formatting), not the raw create() input
checkAttendanceProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
checkAttendanceProbe(array_diff_assoc($before, $legacy->fresh()->getAttributes()) === [], 'Historical manual row changed');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
checkAttendanceProbe(DB::getSchemaBuilder()->hasColumn('attendance_records', 'check_out_recorded_at'), 'Evidence columns missing');
echo "PASS additive migration and rerun on MySQL; historical manual row preserved\n";

$employee = Employee::create(['employee_code' => 'PROBE-EMP', 'first_name' => 'Synthetic', 'status' => 'active', 'employee_classification' => 'Sponsorship', 'project_id' => $project->id, 'site_id' => $site->id, 'user_id' => $user->id]);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkAttendanceProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

foreach (['check-in', 'check-out'] as $scenario) {
    $ids = ['user' => $user->id, 'employee' => $employee->id];
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        // Parent holds the employee row lock (the runtime's mutex) while the worker queues on it.
        Employee::withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $scenario, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkAttendanceProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkAttendanceProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitAttendanceProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        checkAttendanceProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait on the employee lock');
        // Parent performs the same transition inside its transaction, then commits; the worker must then find it done.
        $request = $requestFor($user->id);
        $scenario === 'check-out' ? $service->checkOut($request, $position) : $service->checkIn($request, $position);
        DB::commit();
        $output = waitAttendanceProbe($process, $signal, $pipes, "\n");
        checkAttendanceProbe(str_starts_with(trim($output), 'REFUSED'), 'Worker should have been refused after the parent won: '.$output);
        $rows = AttendanceRecord::withoutGlobalScopes()->where('employee_id', $employee->id)->get();
        checkAttendanceProbe($rows->count() === 1, 'Duplicate attendance rows: '.$rows->count());
        $row = $rows->first();
        if ($scenario === 'check-in') {
            checkAttendanceProbe($row->check_in !== null && $row->check_out === null && $row->source === 'gps', 'Check-in row wrong');
        } else {
            checkAttendanceProbe($row->check_out !== null && $row->check_out_recorded_at !== null, 'Check-out missing');
            checkAttendanceProbe(DB::table('activity_logs')->where('action', 'Mobile check-out')->count() === 1, 'Duplicate check-out audit');
        }
        checkAttendanceProbe(DB::table('activity_logs')->where('action', 'Mobile check-in')->count() === 1, 'Duplicate check-in audit');
        echo 'PASS concurrent '.$scenario.': one logical transition, worker refused ('.trim($output).")\n";
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
echo 'PASS 2 MySQL mobile attendance concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
