<?php

// Explicit opt-in; isolated local MySQL only. No application DB credentials, no ZATCA call.
//
// Proves on a real InnoDB server that the EGS unit row is the mutex of the
// hash chain: two invoices stamped at the same moment get consecutive
// counter values and a linked PIH; the same invoice stamped twice at once
// yields one stamp; a submit racing a retry sends one document identity; a
// request that fails at ZATCA (faked) leaves the chain consistent.
// Runs against a disposable MySQL 8 server on 127.0.0.1:33479 whose datadir
// name starts with "seera-zatca-lock-test-". It refuses any other server.
//
//   php tests/manual/zatca-concurrency.php <datadir>
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\Project;
use App\Models\User;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaInvoiceRecord;
use App\Services\Zatca\ZatcaCrypto;
use App\Services\Zatca\ZatcaSnapshot;
use App\Services\Zatca\ZatcaSubmissionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-zatca-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}
$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_zatca_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_zatca_probe_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Invalid probe database.');
}
if ($action === 'parent') {
    $pdo->exec('CREATE DATABASE '.$database);
}
foreach (['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'FILESYSTEM_DISK' => 'local', 'ZATCA_ENVIRONMENT' => 'sandbox', 'ZATCA_SANDBOX_BASE_URL' => 'https://zatca.invalid/sandbox'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
    exit(1);
});
config(['database.default' => 'zatca_probe', 'database.connections.zatca_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
config(['filesystems.disks.local.root' => sys_get_temp_dir().'/seera-zatca-probe-files']);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$service = app(ZatcaSubmissionService::class);
// Every ZATCA call is faked: the first call per worker answers "cleared", a 500 variant is selected by the scenario.
Http::fake(function ($request) {
    return str_contains((string) getenv('ZATCA_PROBE_FAIL'), '1') ? Http::response(['message' => 'boom'], 500)
        : Http::response(['validationResults' => ['warningMessages' => [], 'errorMessages' => [], 'status' => 'PASS'], 'clearanceStatus' => 'CLEARED', 'clearedInvoice' => $request->data()['invoice']], 200);
});

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        $lower = strtolower($sql);
        if (! $announced && ((str_contains($sql, 'zatca_egs_units') && str_contains($lower, 'for update')) || str_starts_with($lower, 'update `zatca_invoice_records`'))) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    $user = User::findOrFail($ids['user']);
    $invoice = CustomerInvoice::withoutGlobalScopes()->findOrFail($ids['invoice'])->load('zatcaRecord');
    try {
        $record = match ($action) {
            'stamp' => $service->sign($invoice, $user),
            'submit' => $service->process($invoice, $user),
            'retry' => $service->submit($invoice, $user),
        };
        fwrite($signal, 'RESULT '.json_encode(['status' => $record->status, 'icv' => $record->icv, 'hash' => $record->invoice_hash, 'pih' => $record->previous_hash, 'attempts' => $record->submission_attempts])."\n");
    } catch (Throwable $e) {
        fwrite($signal, 'RESULT '.json_encode(['error' => class_basename($e), 'message' => $e->getMessage()])."\n");
    }
    exit;
}

function checkZatcaProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitZatcaProbe($process, $signal, array $pipes, string $needle): string
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

// Baseline schema, then the release migration, then a rerun: proves the additive path on MySQL.
$releaseFile = '2026_10_17_000001_add_zatca_phase2.php';
foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $file) {
    if (basename($file) === $releaseFile) {
        continue;
    }
    checkZatcaProbe(Artisan::call('migrate', ['--path' => 'database/migrations/'.basename($file), '--force' => true]) === 0, 'Baseline migration failed');
}
$customer = Customer::create(['name' => 'Probe customer', 'code' => 'ZP-CUS', 'type' => 'Company', 'vat_number' => '300987654300003', 'status' => 'active']);
$legacyInvoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'INV-LEGACY-1', 'invoice_date' => '2026-01-10', 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115, 'balance_amount' => 115, 'payment_status' => 'unpaid', 'zatca_status' => 'pending_clearance']);
$legacyRecord = ZatcaInvoiceRecord::create(['customer_invoice_id' => $legacyInvoice->id, 'uuid' => (string) Str::uuid(), 'clearance_status' => 'cleared', 'digital_signature_status' => 'signed', 'qr_code_data' => 'legacy', 'tamper_proof_hash' => 'legacy']);
$beforeInvoice = $legacyInvoice->fresh()->getAttributes();
checkZatcaProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Release migration failed');
$migration = require database_path('migrations/'.$releaseFile);
$migration->up();
$legacyRecord->refresh();
$legacyInvoice->refresh();
checkZatcaProbe($legacyRecord->status === 'not_generated' && $legacyRecord->clearance_status === 'cleared' && $legacyRecord->uuid !== null, 'Legacy record not normalised');
checkZatcaProbe($legacyInvoice->zatca_status === 'not_generated' && (float) $legacyInvoice->total_amount === 115.0 && $legacyInvoice->invoice_number === $beforeInvoice['invoice_number'], 'Legacy invoice changed');
echo "PASS additive migration and rerun on MySQL; legacy local record normalised, invoice figures preserved\n";

// Masters, a unit with a self-signed test certificate (no ZATCA credential), and posted invoices.
CompanyProfile::query()->delete();
CompanyProfile::create(['name' => 'Probe Seller Co.', 'name_ar' => 'شركة', 'vat_number' => '300123456700003', 'cr_number' => '1010123456', 'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya',
    'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN', 'default_vat_rate' => 15, 'invoice_mode' => 'x', 'certificate_status' => 'x', 'currency' => 'SAR', 'status' => 'active']);
$customer->update(['street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA']);
$user = User::create(['name' => 'probe finance', 'email' => 'probe-finance@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active']);
$crypto = app(ZatcaCrypto::class);
$keys = $crypto->generateKeyPair();
$key = openssl_pkey_get_private($keys['private_key']);
$csr = openssl_csr_new(['C' => 'SA', 'O' => 'Probe CA', 'CN' => 'PROBE-SubCA'], $key, ['config' => $crypto->opensslConfig(), 'digest_alg' => 'sha256']);
$cert = openssl_csr_sign($csr, null, $key, 30, ['config' => $crypto->opensslConfig(), 'digest_alg' => 'sha256']);
openssl_x509_export($cert, $pem);
$certificate = preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem);
$unit = ZatcaEgsUnit::create(['name' => 'Probe unit', 'environment' => 'sandbox', 'status' => 'production', 'invoice_types' => '1100', 'vat_number' => '300123456700003', 'organization_name' => 'Probe Seller Co.', 'organization_unit' => 'Riyadh']);
$unit->setSecret('private_key_encrypted', $keys['private_key']);
$unit->setSecret('production_token_encrypted', base64_encode($certificate));
$unit->setSecret('production_secret_encrypted', 'probe-secret');
$unit->forceFill(['public_key_pem' => $keys['public_key'], 'production_issued_at' => now()])->save();
$project = Project::create(['code' => 'ZP', 'name' => 'Probe project', 'status' => 'active']);
$makeInvoice = function (int $n) use ($customer, $project, $service): CustomerInvoice {
    $invoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'INV-PROBE-'.$n, 'invoice_type' => 'standard', 'invoice_date' => now()->toDateString(), 'issue_time' => now()->format('H:i:s'),
        'supply_date' => now()->toDateString(), 'project_id' => $project->id, 'taxable_amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total_amount' => 1150, 'balance_amount' => 1150, 'payment_status' => 'unpaid', 'zatca_status' => 'not_generated']);
    CustomerInvoiceLine::create(['customer_invoice_id' => $invoice->id, 'description' => 'Probe line '.$n, 'quantity' => 1, 'unit_price' => 1000, 'taxable_amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total_amount' => 1150]);
    app(ZatcaSnapshot::class)->freeze($invoice);
    $service->ensureRecord($invoice);

    return $invoice->fresh(['zatcaRecord', 'lines']);
};

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkZatcaProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

// Scenario runner: the parent holds the unit row lock, the worker queues on it, the parent then performs its own action and commits.
$run = function (string $workerAction, CustomerInvoice $workerInvoice, ?callable $parentAction, string $label, ?callable $lock = null) use ($server, $address, $expected, $database, $user, $unit, $service) {
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        $lock ? $lock() : ZatcaEgsUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode(['user' => $user->id, 'invoice' => $workerInvoice->id])], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkZatcaProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkZatcaProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitZatcaProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(200000);
        checkZatcaProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', "Worker did not wait on the unit lock in $label");
        $parentResult = $parentAction ? $parentAction() : null;
        DB::commit();
        $output = waitZatcaProbe($process, $signal, $pipes, 'RESULT ');
        $workerResult = json_decode(trim(substr($output, strpos($output, 'RESULT ') + 7)), true, flags: JSON_THROW_ON_ERROR);

        return [$parentResult, $workerResult];
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
};

// 1. Invoice A vs invoice B stamped concurrently: consecutive ICVs, B's PIH = A's hash (or vice versa), no gap, no fork.
$a = $makeInvoice(1);
$b = $makeInvoice(2);
[$parent, $worker] = $run('stamp', $b, fn () => $service->sign($a, $user), 'A vs B');
$first = $parent->icv < $worker['icv'] ? $parent : null;
checkZatcaProbe($parent->icv === 1 && $worker['icv'] === 2, 'Counter values: '.$parent->icv.' / '.json_encode($worker));
checkZatcaProbe($worker['pih'] === $parent->invoice_hash, 'Chain fork: worker PIH does not equal the parent hash');
checkZatcaProbe($unit->fresh()->icv_counter === 2 && $unit->fresh()->last_invoice_hash === $worker['hash'], 'Unit chain state wrong after two stamps');
echo "PASS concurrent stamp A vs B: ICV 1 then 2, PIH chained, unit state consistent\n";

// 2. Same invoice stamped twice concurrently: one stamp, one counter value.
$c = $makeInvoice(3);
[$parent, $worker] = $run('stamp', $c, fn () => $service->sign($c, $user), 'same invoice double stamp');
$workerReplayed = ($worker['icv'] ?? null) === 3 && ($worker['hash'] ?? null) === $parent->invoice_hash;
$workerRefused = ($worker['error'] ?? null) === 'ZatcaException' && str_contains((string) ($worker['message'] ?? ''), 'already stamped');
checkZatcaProbe($parent->icv === 3 && ($workerReplayed || $workerRefused), 'Double stamp produced two identities: '.json_encode($worker));
checkZatcaProbe($unit->fresh()->icv_counter === 3, 'Counter advanced twice for one invoice');
echo "PASS same invoice stamped twice: one identity (ICV 3), counter advanced once\n";

// 3. Submit vs retry on the same signed invoice: the parent holds the record row (claim) while the worker's retry queues on it; one identity, one decision, the loser is refused.
$recordId = $c->fresh(['zatcaRecord'])->zatcaRecord->id;
[$parent, $worker] = $run('retry', $c, fn () => $service->submit($c->fresh(['zatcaRecord']), $user), 'submit vs retry', fn () => ZatcaInvoiceRecord::whereKey($recordId)->lockForUpdate()->firstOrFail());
$record = $c->fresh(['zatcaRecord'])->zatcaRecord;
checkZatcaProbe($record->icv === 3 && $record->invoice_hash === $parent->invoice_hash, 'Retry changed the document identity');
checkZatcaProbe($record->status === 'cleared', 'Expected cleared after submit/retry, got '.$record->status);
checkZatcaProbe(($worker['error'] ?? null) === 'ZatcaException' || ($worker['status'] ?? null) === 'cleared', 'Worker outcome unexpected: '.json_encode($worker));
checkZatcaProbe($record->submission_attempts === 1, 'Two submissions of one document ran: attempts '.$record->submission_attempts);
checkZatcaProbe($unit->fresh()->icv_counter === 3, 'Retry consumed a counter value');
echo "PASS submit vs retry: one identity, status ".$record->status.", attempts ".$record->submission_attempts.", worker ".($worker['message'] ?? $worker['status'] ?? '')."\n";

// 4. A failed request (faked 500) after stamping leaves the chain consistent: the stamped document keeps its ICV and the next stamp chains to it.
putenv('ZATCA_PROBE_FAIL=1');
$d = $makeInvoice(4);
$failed = $service->process($d, $user);
checkZatcaProbe($failed->status === 'signed' && $failed->icv === 4 && $failed->last_error_category === 'server_error', 'Failed request state wrong: '.$failed->status);
putenv('ZATCA_PROBE_FAIL=0');
$e = $makeInvoice(5);
$next = $service->sign($e, $user);
checkZatcaProbe($next->icv === 5 && $next->previous_hash === $failed->invoice_hash, 'Chain broken after a failed request');
$retried = $service->submit($d->fresh(['zatcaRecord']), $user);
checkZatcaProbe($retried->status === 'cleared' && $retried->icv === 4 && $retried->submission_attempts === 2, 'Retry after failure wrong: '.$retried->status);
echo "PASS failed request keeps the chain: ICV 4 kept, ICV 5 chains to it, retry cleared with the same identity\n";

// A genuinely fresh worker enters after the first request has committed its
// pending claim and while its HTTP response is still outstanding.
app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
Http::clearResolvedInstances();
$overlapInvoice = $makeInvoice(6);
$service->sign($overlapInvoice, $user);
$overlapCalls = 0;
Http::fake(function ($request) use (&$overlapCalls, $server, $address, $expected, $database, $user, $overlapInvoice) {
    $overlapCalls++;
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, $expected, 'retry', $database, $address, json_encode(['user' => $user->id, 'invoice' => $overlapInvoice->id])], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    $signal = stream_socket_accept($server, 30);
    checkZatcaProbe(is_resource($signal), 'Overlap worker did not connect');
    try {
        stream_set_blocking($signal, false);
        stream_set_blocking($pipes[2], false);
        $output = waitZatcaProbe($process, $signal, $pipes, 'RESULT ');
        checkZatcaProbe(str_contains($output, 'ZatcaException') && str_contains($output, 'in progress'), 'Fresh pending caller was not refused: '.$output);
    } finally {
        fclose($signal);
        foreach ($pipes as $pipe) { fclose($pipe); }
        proc_close($process);
    }
    return Http::response(['validationResults' => ['status' => 'PASS'], 'clearanceStatus' => 'CLEARED', 'clearedInvoice' => $request->data()['invoice']], 200);
});
$overlapResult = $service->submit($overlapInvoice->fresh(['zatcaRecord']), $user);
checkZatcaProbe($overlapCalls === 1 && $overlapResult->status === 'cleared' && (int) $overlapResult->submission_attempts === 1, 'Overlap lost its accepted result');
echo "PASS fresh worker during in-flight HTTP is refused; one attempt and one accepted result\n";
fclose($server);
echo 'PASS 5 MySQL ZATCA hash-chain/concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
