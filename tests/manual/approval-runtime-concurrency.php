<?php

// Opt-in, disposable MySQL only. Never uses application DB credentials.
// php tests/manual/approval-runtime-concurrency.php <seera-approval-lock-test-* datadir>
use App\Models\ActivityLog;
use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\PurchaseRequestApprovalSubject;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-approval-lock-test-')) {
    throw new RuntimeException('Disposable datadir required.');
}
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached config refused.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Datadir mismatch; refusing writes.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_approval_lock_test_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_approval_lock_test_[a-f0-9]{12}$/', $database)) {
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
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
    exit(1);
});
config(['database.default' => 'approval_lock_probe', 'database.connections.approval_lock_probe' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479, 'database' => $database,
    'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$runtime = app(ApprovalRuntimeService::class);
$subject = app(PurchaseRequestApprovalSubject::class);

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $actor = User::findOrFail($ids['actor']);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        if (! $announced && str_contains($sql, 'purchase_requests') && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    try {
        if ($action === 'submit') {
            $runtime->start($subject, $ids['pr'], $ids['workflow'], $actor, $ids['previous'] ?? null);
        } else {
            $runtime->decide($subject, $ids['pr'], $ids['instance'], $ids['step'], $actor, $action, $action === 'reject' ? 'Concurrent rejection' : null);
        }
        fwrite($signal, "OK\n");
    } catch (ValidationException $e) {
        if (! isset($e->errors()['approval'])) {
            throw $e;
        }
        fwrite($signal, "REFUSED\n");
    } catch (HttpException $e) {
        if ($e->getStatusCode() !== 403) {
            throw $e;
        }
        fwrite($signal, "REFUSED\n");
    }
    exit;
}

function checkProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitProbe($process, $signal, array $pipes, string $needle): void
{
    $output = '';
    $deadline = microtime(true) + 25;
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

checkProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Fresh migration failed');
echo "PASS fresh MySQL migration\n";
$project = Project::create(['code' => 'PROBE', 'name' => 'Disposable synthetic project', 'status' => 'active']);
$actors = [];
foreach (['requester', 'first', 'second', 'alternate'] as $i => $name) {
    $role = $name === 'alternate' ? $actors['first']->roles()->first() : Role::create(['name' => $name, 'code' => strtoupper($name), 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
    foreach (['view', 'create', 'edit', 'approve', 'reject'] as $permission) {
        $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Purchase Requests', 'action' => $permission])->id]);
    }
    $actor = User::create(['name' => $name, 'email' => $name.'@example.invalid', 'password' => 'test-only-not-a-production-account', 'status' => 'active']);
    $actor->roles()->attach($role, ['is_primary' => true]);
    $actors[$name] = $actor;
}
$unit = Unit::create(['code' => 'EA', 'name' => 'Each', 'status' => 'active']);
// Additive path with existing approved data, using only this disposable database.
$migration = require dirname(__DIR__, 2).'/database/migrations/2026_09_30_000001_create_approval_runtime_tables.php';
$migration->down();
$legacy = PurchaseRequest::create(['pr_number' => 'LEGACY-PROBE', 'request_date' => today(), 'requested_by' => $actors['requester']->id, 'status' => 'approved', 'approved_by' => $actors['first']->id, 'approved_at' => now(), 'estimated_total' => 50]);
$before = $legacy->fresh()->getAttributes();
$migration->up();
checkProbe($legacy->fresh()->approval_mode === 'legacy', 'Additive migration enrolled legacy document');
checkProbe(array_diff_assoc($before, $legacy->fresh()->getAttributes()) === [], 'Additive migration changed legacy data');
checkProbe(ApprovalInstance::count() === 0, 'Fake history was backfilled');
checkProbe($actors['requester']->fresh()->hasPermission('Approval History', 'view'), 'History permission grant missing');
echo "PASS additive MySQL migration; legacy approved data unchanged, no backfill\n";
$item = Item::create(['item_code' => 'PROBE', 'name' => 'Synthetic item', 'unit_id' => $unit->id, 'status' => 'active']);
$workflow = ApprovalWorkflow::create(['name' => 'Probe workflow', 'module' => 'Purchase Request', 'trigger_action' => 'Request Created', 'scope' => 'All Projects', 'auto_posting' => 'No Auto Posting', 'status' => 'active']);
foreach (['first', 'second'] as $i => $name) {
    $workflow->steps()->create(['step_no' => $i + 1, 'approver_role_id' => $actors[$name]->roles()->first()->id, 'is_required' => true, 'can_reject' => true]);
}
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkProbe(is_resource($server), 'IPC failed: '.$error);
$address = stream_socket_get_name($server, false);

$scenarios = ['duplicate_submit', 'duplicate_step', 'competing_role_members', 'approve_vs_reject', 'reject_vs_approve', 'next_step_after_first', 'duplicate_final', 'duplicate_resubmit', 'permission_revoked_while_waiting'];
foreach ($scenarios as $i => $scenario) {
    $pr = PurchaseRequest::create(['pr_number' => 'PROBE-'.$i, 'request_date' => today(), 'requested_by' => $actors['requester']->id, 'project_id' => $project->id, 'status' => 'draft', 'priority' => 'normal']);
    $pr->forceFill(['approval_mode' => 'runtime'])->save();
    $pr->lines()->create(['item_id' => $item->id, 'quantity' => 1, 'estimated_unit_cost' => 10, 'estimated_total' => 10]);
    $instance = $scenario === 'duplicate_submit' ? null : $runtime->start($subject, $pr->id, $workflow->id, $actors['requester']);
    if ($scenario === 'duplicate_final') {
        $runtime->decide($subject, $pr->id, $instance->id, $instance->steps[0]->id, $actors['first'], 'approve');
    }
    if ($scenario === 'duplicate_resubmit') {
        $runtime->decide($subject, $pr->id, $instance->id, $instance->steps[0]->id, $actors['first'], 'reject', 'Fix');
    }
    $submit = in_array($scenario, ['duplicate_submit', 'duplicate_resubmit'], true);
    $workerAction = $submit ? 'submit' : ($scenario === 'approve_vs_reject' ? 'reject' : 'approve');
    $workerActor = $submit ? 'requester' : match ($scenario) {
        'competing_role_members' => 'alternate', 'next_step_after_first', 'duplicate_final' => 'second', default => 'first'
    };
    $workerStep = in_array($scenario, ['next_step_after_first', 'duplicate_final'], true) ? 1 : 0;
    $ids = ['pr' => $pr->id, 'workflow' => $workflow->id, 'instance' => $instance?->id, 'step' => $instance?->steps[$workerStep]->id, 'actor' => $actors[$workerActor]->id, 'previous' => $scenario === 'duplicate_resubmit' ? $instance->id : null];
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        PurchaseRequest::whereKey($pr->id)->lockForUpdate()->firstOrFail();
        // Worker reaches the source mutex BEFORE the winner modifies the rows.
        // Authorization must be refreshed after the wait, not before it.
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkProbe(is_resource($process), 'Could not start worker');
        $signal = stream_socket_accept($server, 15);
        checkProbe(is_resource($signal), 'Worker IPC connect failed');
        stream_set_blocking($signal, false);
        waitProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        checkProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not wait for source lock');
        if ($scenario === 'permission_revoked_while_waiting') {
            $actors['first']->roles()->first()->permissions()->detach(Permission::where('module', 'Purchase Requests')->where('action', 'approve')->value('id'));
        } elseif ($submit) {
            $runtime->start($subject, $pr->id, $workflow->id, $actors['requester'], $ids['previous']);
        } else {
            $runtime->decide($subject, $pr->id, $instance->id, $instance->steps[$scenario === 'duplicate_final' ? 1 : 0]->id, $actors[$scenario === 'duplicate_final' ? 'second' : 'first'], $scenario === 'reject_vs_approve' ? 'reject' : 'approve', $scenario === 'reject_vs_approve' ? 'Concurrent rejection' : null);
        }
        DB::commit();
        $refused = in_array($scenario, ['competing_role_members', 'approve_vs_reject', 'reject_vs_approve', 'permission_revoked_while_waiting'], true);
        waitProbe($process, $signal, $pipes, $refused ? "REFUSED\n" : "OK\n");
        $history = ApprovalInstance::where('source_id', $pr->id)->orderBy('attempt')->get();
        checkProbe($history->count() === ($scenario === 'duplicate_resubmit' ? 2 : 1), 'Duplicate/lost instance');
        $latest = $history->last();
        $status = match ($scenario) {
            'reject_vs_approve' => 'rejected', 'duplicate_final', 'next_step_after_first' => 'approved', default => 'pending'
        };
        checkProbe($latest->status === $status && $pr->fresh()->status === $status, 'Inconsistent document/instance status');
        $decisions = $latest->steps()->whereNotNull('decided_at')->count();
        checkProbe($decisions === ($submit || $scenario === 'permission_revoked_while_waiting' ? 0 : ($status === 'approved' ? 2 : 1)), 'Lost/duplicated/skipped decision');
        $events = ActivityLog::where('module', 'Approvals')->where('description', 'like', $pr->pr_number.' |%')->count();
        $expectedEvents = $submit ? ($scenario === 'duplicate_resubmit' ? 3 : 1) : ($status === 'approved' ? 4 : 2);
        if ($scenario === 'permission_revoked_while_waiting') {
            $expectedEvents = 1;
        }
        checkProbe($events === $expectedEvents, 'Duplicate/missing audit events');
        if ($scenario === 'duplicate_resubmit') {
            checkProbe($history->first()->status === 'rejected', 'Old rejection lost');
        }
        echo 'PASS '.$scenario."\n";
    } finally {
        if (DB::transactionLevel() > 0) {
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
checkProbe(DB::table('journal_entries')->count() === 0 && DB::table('vat_transactions')->count() === 0, 'Approval unexpectedly posted accounting');
echo 'PASS 9 MySQL runtime concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.")\n";
echo 'Disposable database retained for inspection: '.$database."\n";
