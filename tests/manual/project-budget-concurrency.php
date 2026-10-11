<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials.
//
// Proves on a real InnoDB server that "Set as Current" is serialised on the
// project row: two users finalizing the same draft, and two revisions being
// finalized at the same moment, each leave exactly one current budget version.
//
//   php tests/manual/project-budget-concurrency.php <datadir>
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Services\Projects\ProjectBudgetService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-budget-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_budget_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_budget_probe_[a-f0-9]{12}$/', $database)) {
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
config(['database.default' => 'budget_probe', 'database.connections.budget_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$service = app(ProjectBudgetService::class);

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        if (! $announced && str_contains($sql, 'projects') && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    try {
        $result = $service->finalize($ids['budget'], $ids['actor']);
        fwrite($signal, 'OK '.$result->reference.' '.$result->status."\n");
    } catch (ValidationException $e) {
        fwrite($signal, 'REFUSED '.json_encode($e->errors())."\n");
    }
    exit;
}

function checkBudgetProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitBudgetProbe($process, $signal, array $pipes, string $needle): string
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

// Baseline schema with a project, then the release migration (additive path), then a rerun.
$releaseFile = '2026_10_11_000002_create_project_budgets.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkBudgetProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$project = Project::create(['code' => 'BOQ-PROBE', 'name' => 'Synthetic isolated project', 'budget' => 123456.78, 'status' => 'active']);
$before = $project->fresh()->getAttributes();
checkBudgetProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
checkBudgetProbe(array_diff_assoc($before, $project->fresh()->getAttributes()) === [], 'Project row changed by the migration');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
echo "PASS additive migration and rerun on MySQL; project master budget preserved\n";

$actor = User::create(['name' => 'probe planner', 'email' => 'probe-planner@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active']);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkBudgetProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

foreach (['finalize_same_draft_twice', 'two_revisions_finalized_together'] as $scenario) {
    if ($scenario === 'finalize_same_draft_twice') {
        $parentBudget = $service->createDraft($project, ['title' => 'Baseline'], $actor->id);
        $service->addLine($parentBudget, ['cost_code' => 'MAT-001', 'category' => 'materials', 'description' => 'Steel', 'quantity' => 10, 'rate' => 100], $actor->id);
        $workerBudget = $parentBudget;
    } else {
        $current = ProjectBudget::where('project_id', $project->id)->where('is_current', true)->firstOrFail();
        // Two independent revisions of the same current version (the one-draft rule is bypassed on purpose to race them).
        $parentBudget = $service->revise($current, $actor->id, 'Revision A');
        $workerBudget = ProjectBudget::create(['project_id' => $project->id, 'version' => $parentBudget->version + 1, 'reference' => 'BOQ-PROBE-B'.($parentBudget->version + 1),
            'title' => 'Revision B', 'status' => 'draft', 'is_current' => false, 'based_on_budget_id' => $current->id, 'created_by' => $actor->id]);
        foreach ($current->lines as $line) {
            $workerBudget->lines()->create($line->only(['sort_order', 'cost_code', 'category', 'description', 'site_id', 'item_id', 'unit_id', 'quantity', 'rate', 'amount', 'chart_of_account_id', 'notes']));
        }
        $service->recalculate($workerBudget);
    }
    $ids = ['budget' => $workerBudget->id, 'actor' => $actor->id];
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        Project::withoutGlobalScopes()->whereKey($project->id)->lockForUpdate()->firstOrFail();   // parent holds the project mutex
        $process = proc_open([PHP_BINARY, __FILE__, $expected, 'finalize', $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkBudgetProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkBudgetProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitBudgetProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        checkBudgetProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait on the project lock');
        $service->finalize($parentBudget->id, $actor->id);   // parent finalizes while the worker queues
        DB::commit();
        $output = trim(waitBudgetProbe($process, $signal, $pipes, "\n"));
        $current = ProjectBudget::where('project_id', $project->id)->where('is_current', true)->get();
        checkBudgetProbe($current->count() === 1, 'Expected exactly one current budget, found '.$current->count());
        checkBudgetProbe(ProjectBudget::where('project_id', $project->id)->where('status', 'current')->count() === 1, 'Status and flag disagree');
        if ($scenario === 'finalize_same_draft_twice') {
            checkBudgetProbe(str_starts_with($output, 'OK') && str_contains($output, 'current'), 'Second finalize of the same draft should be an idempotent OK: '.$output);
            checkBudgetProbe($current->first()->id === $parentBudget->id, 'The draft should be the current version');
        } else {
            checkBudgetProbe(str_starts_with($output, 'OK'), 'Worker revision should finalize after the lock is released: '.$output);
            checkBudgetProbe($current->first()->id === $workerBudget->id, 'The later finalization should be current');
            checkBudgetProbe($parentBudget->fresh()->status === 'superseded', 'Earlier revision should be superseded');
        }
        echo 'PASS '.$scenario.': one current budget ('.$current->first()->reference.'), worker '.$output."\n";
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
echo 'PASS 2 MySQL project budget concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
