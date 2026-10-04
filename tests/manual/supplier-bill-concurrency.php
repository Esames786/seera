<?php

// Opt-in, synthetic data ONLY. A private disposable MySQL server on loopback
// must match the explicit datadir before any write. No .env credentials used.
use App\Http\Controllers\Admin\Accounting\AccountsPayableController;
use App\Models\ApprovalWorkflow;
use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\SupplierBillPostingService;
use App\Services\Approvals\ApprovalRuntimeService;
use App\Services\Approvals\SupplierBillApprovalSubject;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-bill-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
if ($action === 'shutdown') {
    $pdo->exec('SHUTDOWN'); // Datadir identity was verified above; no other server is touched.
    echo "Disposable Supplier Bill probe server stopped.\n";
    exit;
}
$database = $action === 'parent' ? 'seera_bill_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_bill_probe_[a-f0-9]{12}$/', $database)) {
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
config(['database.default' => 'bill_probe', 'database.connections.bill_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$runtime = app(ApprovalRuntimeService::class);
$subject = app(SupplierBillApprovalSubject::class);
$posting = app(SupplierBillPostingService::class);

function billProbeCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function billProbeRequest(User $actor, array $data): Request
{
    Auth::login($actor);
    $request = Request::create('/synthetic-probe', 'POST', $data);
    $request->setUserResolver(fn () => $actor);
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);

    return $request;
}
function billProbeWait($process, $signal, array $pipes, string $needle): string
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

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    billProbeCheck(is_resource($signal), 'IPC connection failed');
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal, $action) {
        $table = $action === 'compete' ? 'goods_receipt_lines' : 'supplier_bills';
        if (! $announced && str_contains($sql, $table) && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    $actor = User::findOrFail($ids['actor']);
    $bill = SupplierBill::findOrFail($ids['bill']);
    try {
        if ($action === 'approve') {
            $runtime->decide($subject, $bill->id, $ids['instance'], $ids['step'], $actor, 'approve');
        } elseif ($action === 'compete') {
            $posting->approveLegacy($ids['competitor'], $actor->id);
            throw new RuntimeException('Competing bill consumed reserved quantity');
        } elseif ($action === 'edit') {
            // Reproduce a stale draft object loaded before submission. The POST's
            // current locked re-check, not just its optimistic check, must refuse.
            $bill->approval_status = null;
            app(AccountsPayableController::class)->update(billProbeRequest($actor, ['supplier_id' => $bill->supplier_id,
                'bill_number' => $bill->bill_number, 'bill_date' => today()->toDateString(), 'project_id' => $bill->project_id, 'site_id' => $bill->site_id,
                'vat_rate' => 15, 'lines' => [['description' => 'Stale edit', 'quantity' => 1, 'unit_price' => 999]]]), $bill);
            throw new RuntimeException('Stale edit succeeded');
        } elseif ($action === 'reopen') {
            app(AccountsPayableController::class)->reopen(billProbeRequest($actor, ['reason' => 'Synthetic concurrent correction']), $bill);
        } elseif ($action === 'payment') {
            app(AccountsPayableController::class)->storePayment(billProbeRequest($actor, ['payment_date' => today()->toDateString(),
                'amount' => 230, 'payment_account_id' => $ids['cash'], 'idempotency_key' => 'probe-pending']), $bill);
            throw new RuntimeException('Payment was accepted before posting');
        } else {
            $posting->attempt($bill->id, $actor->id);
        }
        fwrite($signal, "OK\n");
    } catch (ValidationException $e) {
        billProbeCheck(in_array($action, ['edit', 'compete', 'reopen', 'payment'], true) || ($action === 'approve' && ($ids['expect_conflict'] ?? false)), 'Unexpected validation failure: '.$e->getMessage());
        $key = ['edit' => 'bill', 'compete' => 'matching', 'reopen' => 'bill', 'payment' => 'payment', 'approve' => 'approval'][$action];
        billProbeCheck(array_key_exists($key, $e->errors()), 'Wrong refusal reason: '.$e->getMessage());
        if ($action === 'approve') {
            billProbeCheck(str_contains($e->getMessage(), 'already has a decision'), 'Expected competing-actor conflict, not another validation failure');
        }
        fwrite($signal, "OK REFUSED\n");
    }
    exit;
}

// Apply old schema, preserve a historical bill, apply release and safely rerun.
$release = '2026_10_03_000001_add_supplier_bill_approval_state.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $release) {
        continue;
    }
    billProbeCheck(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$supplier = Supplier::create(['code' => 'BR-PROBE', 'name' => 'Synthetic supplier', 'status' => 'active']);
$historical = SupplierBill::create(['supplier_id' => $supplier->id, 'bill_number' => 'LEGACY', 'bill_date' => today(), 'status' => 'paid', 'total_amount' => 115, 'paid_amount' => 115]);
$before = $historical->fresh()->getAttributes();
billProbeCheck(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
(require database_path('migrations/'.$release))->up();
billProbeCheck(array_diff_assoc($before, $historical->fresh()->getAttributes()) === [], 'Historical bill changed');
billProbeCheck($historical->fresh()->approval_mode === 'legacy' && $historical->approvals()->count() === 0, 'Fabricated history');
echo "PASS additive MySQL migration, rerun, historical bill preserved\n";
$project = Project::create(['code' => 'BR-PROBE', 'name' => 'Synthetic project']);
$site = Site::create(['code' => 'BR-PROBE', 'name' => 'Synthetic site', 'project_id' => $project->id]);
$warehouse = Warehouse::create(['code' => 'BR-PROBE', 'name' => 'Synthetic warehouse', 'project_id' => $project->id, 'site_id' => $site->id]);
$item = Item::create(['item_code' => 'BR-PROBE', 'name' => 'Synthetic material']);
$actors = [];
foreach (['requester', 'reviewer', 'finance'] as $name) {
    $role = Role::create(['code' => $name === 'finance' ? 'SUPER_ADMIN' : strtoupper($name), 'name' => $name, 'access_scope' => 'Company Level', 'level' => 4, 'status' => 'active']);
    foreach (['view', 'create', 'edit', 'approve', 'reject', 'post', 'retry', 'process'] as $permission) {
        $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['module' => 'Accounts Payable', 'action' => $permission])->id]);
    }
    $actor = User::create(['name' => $name, 'email' => $name.'@example.invalid', 'password' => 'isolated-probe-only', 'status' => 'active']);
    $actor->roles()->attach($role, ['is_primary' => true]);
    $actors[$name] = $actor;
}
$alternate = User::create(['name' => 'Alternate Finance', 'email' => 'alternate@example.invalid', 'password' => 'isolated-probe-only', 'status' => 'active']);
$alternate->roles()->attach($actors['finance']->roles()->first()->id, ['is_primary' => true]);
$accounts = [];
foreach (['1110' => 'asset', '1300' => 'asset', '2100' => 'liability', '2150' => 'liability', '5200' => 'expense'] as $code => $type) {
    $accounts[$code] = ChartOfAccount::firstOrCreate(['account_code' => $code], ['account_name' => 'Probe '.$code, 'account_type' => $type, 'normal_balance' => $type === 'liability' ? 'credit' : 'debit', 'status' => 'active']);
}
AutomaticPostingRule::create(['source_module' => 'Supplier Bill', 'trigger_event' => 'Bill Approved', 'auto_post' => true, 'status' => 'active']);
$workflow = ApprovalWorkflow::create(['name' => 'Probe workflow', 'module' => 'Supplier Bill', 'trigger_action' => 'Bill Submitted', 'scope' => 'All Projects', 'auto_posting' => 'Create Accounting Entry', 'status' => 'active']);
foreach (['reviewer', 'finance'] as $i => $name) {
    $workflow->steps()->create(['step_no' => $i + 1, 'approver_role_id' => $actors[$name]->roles()->first()->id, 'is_required' => true, 'can_reject' => true]);
}
$makeBill = function ($number, $grn, $qty, $mode = 'runtime') use ($supplier, $project, $site, $actors) {
    $bill = SupplierBill::create(['supplier_id' => $supplier->id, 'bill_number' => $number, 'bill_date' => today(), 'project_id' => $project->id, 'site_id' => $site->id,
        'taxable_amount' => $qty * 100, 'vat_rate' => 15, 'vat_amount' => $qty * 15, 'total_amount' => $qty * 115, 'status' => 'draft', 'approval_mode' => $mode,
        'requested_by' => $actors['requester']->id, 'last_edited_by' => $actors['requester']->id]);
    $line = $bill->lines()->create(['description' => 'Probe match', 'quantity' => $qty, 'unit_price' => 100, 'taxable_amount' => $qty * 100, 'vat_rate' => 15, 'vat_amount' => $qty * 15, 'total_amount' => $qty * 115]);
    $bill->grnMatches()->create(['supplier_bill_line_id' => $line->id, 'goods_receipt_id' => $grn->id, 'goods_receipt_line_id' => $grn->lines[0]->id, 'matched_quantity' => $qty, 'matched_taxable_amount' => $qty * 100]);

    return $bill;
};
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
billProbeCheck(is_resource($server), 'IPC listener failed');
$address = stream_socket_get_name($server, false);
$scenarios = ['duplicate_final_approval' => 'approve', 'final_approval_by_two_users' => 'approve', 'final_approval_vs_retry' => 'retry', 'final_approval_vs_edit' => 'edit',
    'final_approval_vs_competing_grn_bill' => 'compete', 'duplicate_retry' => 'retry', 'final_approval_vs_reopen' => 'reopen', 'payment_while_posting_pending' => 'payment'];
foreach ($scenarios as $scenario => $workerAction) {
    Auth::forgetGuards();
    $grn = GoodsReceipt::create(['grn_number' => 'GRN-'.$scenario, 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'received_date' => today(), 'status' => 'posted']);
    $grn->lines()->create(['item_id' => $item->id, 'received_quantity' => 10, 'accepted_quantity' => 10, 'unit_cost' => 100, 'taxable_amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total_amount' => 1150]);
    $grn->load('lines');
    $bill = $makeBill('BILL-'.$scenario, $grn, 2);
    $competitor = $workerAction === 'compete' ? $makeBill('COMPETITOR', $grn, 9, 'legacy') : null;
    $instance = $runtime->start($subject, $bill->id, $workflow->id, $actors['requester']);
    $runtime->decide($subject, $bill->id, $instance->id, $instance->steps[0]->id, $actors['reviewer'], 'approve');
    $ids = ['bill' => $bill->id, 'instance' => $instance->id, 'step' => $instance->steps[1]->id, 'actor' => $actors['finance']->id, 'competitor' => $competitor?->id, 'cash' => $accounts['1110']->id];
    if ($scenario === 'final_approval_by_two_users') {
        $ids['actor'] = $alternate->id;
        $ids['expect_conflict'] = true;
    }
    if (in_array($scenario, ['duplicate_retry', 'payment_while_posting_pending'], true)) {
        $accounts['2100']->update(['status' => 'inactive']);
        $runtime->decide($subject, $bill->id, $instance->id, $ids['step'], $actors['finance'], 'approve');
        billProbeCheck($bill->fresh()->approval_status === 'approved' && ! $bill->fresh()->journal_entry_id, 'Expected recoverable posting failure');
        if ($scenario === 'duplicate_retry') {
            $accounts['2100']->update(['status' => 'active']);
        }
    }
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        SupplierBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
        if ($workerAction === 'compete') {
            GoodsReceiptLine::whereKey($grn->lines[0]->id)->lockForUpdate()->firstOrFail();
        }
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        billProbeCheck(is_resource($process), 'Worker did not start');
        $signal = stream_socket_accept($server, 15);
        billProbeCheck(is_resource($signal), 'No worker IPC');
        stream_set_blocking($signal, false);
        billProbeWait($process, $signal, $pipes, "ATTEMPT\n");
        usleep(150000);
        billProbeCheck(proc_get_status($process)['running'] && stream_get_contents($signal) === '', 'Worker did not block on real InnoDB mutex');
        if (str_starts_with($scenario, 'final_') || $scenario === 'duplicate_final_approval') {
            $runtime->decide($subject, $bill->id, $instance->id, $ids['step'], $actors['finance'], 'approve');
        } else {
            $posting->attempt($bill->id, $ids['actor']);
        }
        DB::commit();
        billProbeWait($process, $signal, $pipes, 'OK');
        $bill->refresh();
        $pending = $scenario === 'payment_while_posting_pending';
        $reopened = $bill->approval_status === 'correction';
        billProbeCheck($instance->fresh()->status === 'approved', 'Approved history lost');
        billProbeCheck($bill->approvals()->count() === 1 && $instance->steps()->where('status', 'approved')->count() === 2, 'Duplicate or missing approvals');
        billProbeCheck((float) $bill->total_amount === 230.0, 'Stale edit changed values');
        $journalCount = DB::table('journal_entries')->where('source_module', 'Supplier Bill')->where('source_id', $bill->id)->count();
        billProbeCheck($journalCount === ($pending ? 0 : 1), 'Missing or duplicate source journal');
        billProbeCheck((float) $grn->lines[0]->fresh()->invoiced_quantity === ($pending || $reopened ? 0.0 : 2.0), 'Duplicate or early GRN consumption');
        billProbeCheck(DB::table('vat_transactions')->where('source_module', 'Supplier Bill')->where('source_id', $bill->id)->count() === ($pending || $reopened ? 0 : 1), 'Duplicate/early VAT');
        billProbeCheck($bill->payments()->count() === 0, 'Unexpected payment');
        if (! $pending && ! $reopened) {
            billProbeCheck($bill->isPayable() && (float) $bill->journalEntry->lines()->where('chart_of_account_id', $accounts['2100']->id)->sum('credit') === 230.0, 'AP missing/duplicated');
        }
        if ($competitor) {
            billProbeCheck(! $competitor->fresh()->journal_entry_id && $competitor->fresh()->status === 'draft', 'Competing bill stole quantity');
        }
        echo 'PASS '.$scenario.($reopened ? ' (eligible correction reversed once; old approval retained)' : '')."\n";
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
        $accounts['2100']->update(['status' => 'active']);
    }
}
fclose($server);
echo 'PASS 8 Supplier Bill MySQL concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.")\n";
