<?php
// Explicit opt-in; isolated local MySQL only. No application DB credentials, no ZATCA call.
//
// Proves on a real InnoDB server that customer credit / debit notes are safe
// under concurrency: two credit notes racing for the same remaining amount of
// an invoice line cannot over-credit; a credit and a debit note racing on one
// invoice both post with a consistent balance; the same note posted twice
// yields one journal and one VAT row; a note stamped while an invoice is
// stamped gets the next ICV on the shared chain; submit vs retry of one note
// sends one document identity.
// Runs against a disposable MySQL 8 server on 127.0.0.1:33479 whose datadir
// name starts with "seera-zatca-lock-test-". It refuses any other server.
//
//   php tests/manual/customer-notes-concurrency.php <datadir>
require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-zatca-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\User;
use App\Models\VatTransaction;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaInvoiceRecord;
use App\Services\Accounting\CustomerNoteService;
use App\Services\Zatca\ZatcaCrypto;
use App\Services\Zatca\ZatcaSnapshot;
use App\Services\Zatca\ZatcaSubmissionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_notes_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_notes_probe_[a-f0-9]{12}$/', $database)) {
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
config(['database.default' => 'notes_probe', 'database.connections.notes_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
config(['filesystems.disks.local.root' => sys_get_temp_dir().'/seera-notes-probe-files']);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$notes = app(CustomerNoteService::class);
$zatca = app(ZatcaSubmissionService::class);
Http::fake(fn ($request) => Http::response(['validationResults' => ['warningMessages' => [], 'errorMessages' => [], 'status' => 'PASS'], 'clearanceStatus' => 'CLEARED'], 200));

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        $lower = strtolower($sql);
        // The worker announces when it reaches the shared lock: the invoice row (notes) or the unit row (stamp), or the record claim (retry).
        if (! $announced && ((str_contains($lower, 'customer_invoices') || str_contains($lower, 'zatca_egs_units')) && str_contains($lower, 'for update') || str_starts_with($lower, 'update `zatca_invoice_records`'))) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    $user = User::findOrFail($ids['user']);
    try {
        $result = match ($action) {
            'post' => (function () use ($notes, $ids, $user) {
                $note = $notes->post(CustomerAdjustmentNote::withoutGlobalScopes()->findOrFail($ids['note']), $user);

                return ['status' => $note->status, 'journal' => $note->journal_entry_id, 'applied' => $note->applied_amount];
            })(),
            'stamp' => (function () use ($zatca, $ids, $user) {
                $record = $zatca->sign(CustomerAdjustmentNote::withoutGlobalScopes()->findOrFail($ids['note']), $user);

                return ['status' => $record->status, 'icv' => $record->icv, 'hash' => $record->invoice_hash, 'pih' => $record->previous_hash];
            })(),
            'retry' => (function () use ($zatca, $ids, $user) {
                $record = $zatca->submit(CustomerAdjustmentNote::withoutGlobalScopes()->findOrFail($ids['note'])->load('zatcaRecord'), $user);

                return ['status' => $record->status, 'icv' => $record->icv, 'attempts' => $record->submission_attempts];
            })(),
        };
        fwrite($signal, 'RESULT '.json_encode($result)."\n");
    } catch (Throwable $e) {
        fwrite($signal, 'RESULT '.json_encode(['error' => class_basename($e), 'message' => mb_substr($e->getMessage(), 0, 300)])."\n");
    }
    exit;
}

function checkNotesProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitNotesProbe($process, $signal, array $pipes, string $needle): string
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

// Full schema (including the notes migration) and the standard chart of accounts.
checkNotesProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Migration failed');
checkNotesProbe(Artisan::call('db:seed', ['--class' => 'ProductionChartOfAccountsSeeder', '--force' => true]) === 0, 'Chart seed failed');
// Rerun: additive, idempotent.
checkNotesProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Migration rerun failed');
$migration = require database_path('migrations/2026_10_22_000001_add_customer_adjustment_notes.php');
$migration->up();
echo "PASS additive notes migration and rerun on MySQL\n";

CompanyProfile::query()->delete();
CompanyProfile::create(['name' => 'Probe Seller Co.', 'name_ar' => 'شركة', 'vat_number' => '300123456700003', 'cr_number' => '1010123456', 'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya',
    'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN', 'default_vat_rate' => 15, 'invoice_mode' => 'x', 'certificate_status' => 'x', 'currency' => 'SAR', 'status' => 'active']);
$customer = Customer::create(['name' => 'Probe customer', 'code' => 'NP-CUS', 'type' => 'Company', 'vat_number' => '300987654300003', 'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
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
$project = Project::create(['code' => 'NP', 'name' => 'Probe project', 'status' => 'active']);
$makeInvoice = function (int $n) use ($customer, $project, $zatca): CustomerInvoice {
    $invoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'INV-NPROBE-'.$n, 'invoice_type' => 'standard', 'invoice_date' => now()->toDateString(), 'issue_time' => now()->format('H:i:s'),
        'supply_date' => now()->toDateString(), 'project_id' => $project->id, 'taxable_amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total_amount' => 1150, 'balance_amount' => 1150, 'payment_status' => 'unpaid', 'zatca_status' => 'not_generated']);
    CustomerInvoiceLine::create(['customer_invoice_id' => $invoice->id, 'description' => 'Probe line '.$n, 'quantity' => 10, 'unit_price' => 100, 'taxable_amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total_amount' => 1150]);
    app(ZatcaSnapshot::class)->freeze($invoice);
    $zatca->ensureRecord($invoice);

    return $invoice->fresh(['zatcaRecord', 'lines']);
};
$draft = fn (CustomerInvoice $invoice, string $type, float $qty, ?float $price = null) => $notes->createDraft($invoice, $type,
    ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'probe'],
    [['invoice_line_id' => $invoice->lines[0]->id, 'quantity' => $qty, 'unit_price' => $price ?? 100]], $user);

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkNotesProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

// Scenario runner: the parent holds the shared lock, the worker queues on it, the parent performs its own action and commits.
$run = function (string $workerAction, int $noteId, ?callable $parentAction, string $label, callable $lock) use ($server, $address, $expected, $database, $user) {
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        $lock();
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode(['user' => $user->id, 'note' => $noteId])], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkNotesProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkNotesProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitNotesProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(200000);
        checkNotesProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', "Worker did not wait on the lock in $label");
        $parentResult = $parentAction ? $parentAction() : null;
        DB::commit();
        $output = waitNotesProbe($process, $signal, $pipes, 'RESULT ');
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
$lockInvoice = fn (CustomerInvoice $invoice) => fn () => CustomerInvoice::withoutGlobalScopes()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

// 1. Credit vs credit: two drafts of 600 each against a 1,000 line; both pass the save-time check, only one can post.
$a = $makeInvoice(1);
$c1 = $draft($a, 'credit', 6);
$c2 = $draft($a, 'credit', 6);
[$parent, $worker] = $run('post', $c2->id, fn () => $notes->post($c1, $user), 'credit vs credit', $lockInvoice($a));
checkNotesProbe($parent->status === 'posted', 'Parent credit did not post');
checkNotesProbe(($worker['error'] ?? null) === 'ValidationException' && str_contains((string) $worker['message'], 'can still be credited'), 'Worker over-credited: '.json_encode($worker));
checkNotesProbe($c2->fresh()->status === 'draft' && $c2->fresh()->journal_entry_id === null, 'Refused note changed state');
checkNotesProbe((float) $a->fresh()->balance_amount === 1150.0 - 690.0, 'Balance after one credit wrong: '.$a->fresh()->balance_amount);
checkNotesProbe(JournalEntry::where('source_module', 'Customer Credit Note')->count() === 1 && VatTransaction::where('source_module', 'Customer Credit Note')->count() === 1, 'Journal / VAT rows wrong after credit race');
echo "PASS credit vs credit: one of two 600-credits posts, the other is refused under lock, balance 460.00, one journal, one VAT row\n";

// 2. Credit vs debit on one invoice: both post; the balance is total + debit − credit.
$b = $makeInvoice(2);
$cr = $draft($b, 'credit', 2);          // 230
$db = $draft($b, 'debit', 1, 300);      // 345
[$parent, $worker] = $run('post', $db->id, fn () => $notes->post($cr, $user), 'credit vs debit', $lockInvoice($b));
checkNotesProbe($parent->status === 'posted' && ($worker['status'] ?? null) === 'posted', 'Credit / debit race did not post both: '.json_encode($worker));
checkNotesProbe((float) $b->fresh()->balance_amount === 1150.0 + 345.0 - 230.0, 'Balance after credit + debit wrong: '.$b->fresh()->balance_amount);
$entries = JournalEntry::whereIn('source_module', ['Customer Credit Note', 'Customer Debit Note'])->whereIn('source_id', [$cr->id, $db->id])->get();
checkNotesProbe($entries->count() === 2 && $entries->every(fn ($e) => round((float) $e->total_debit, 2) === round((float) $e->total_credit, 2)), 'Note journals unbalanced or missing');
echo "PASS credit vs debit: both post, balance 1,265.00, two balanced journals\n";

// 3. Post vs post of the same note: one journal, one VAT row, applied once.
$c = $makeInvoice(3);
$n3 = $draft($c, 'credit', 1);
[$parent, $worker] = $run('post', $n3->id, fn () => $notes->post($n3, $user), 'post vs post', $lockInvoice($c));
checkNotesProbe($parent->status === 'posted' && ($worker['status'] ?? null) === 'posted' && (int) $worker['journal'] === (int) $parent->journal_entry_id, 'Double post produced two results: '.json_encode($worker));
checkNotesProbe(JournalEntry::where('source_module', 'Customer Credit Note')->where('source_id', $n3->id)->count() === 1, 'Two journals for one note');
checkNotesProbe(VatTransaction::where('source_module', 'Customer Credit Note')->where('source_id', $n3->id)->count() === 1, 'Two VAT rows for one note');
checkNotesProbe((float) $c->fresh()->balance_amount === 1150.0 - 115.0, 'Applied twice: '.$c->fresh()->balance_amount);
checkNotesProbe(ZatcaInvoiceRecord::where('customer_adjustment_note_id', $n3->id)->count() === 1, 'Two ZATCA records for one note');
echo "PASS post vs post: one journal, one VAT row, applied once, one ZATCA record\n";

// 4. Note stamp vs invoice stamp on the shared EGS chain: consecutive ICVs, chained PIH.
$d = $makeInvoice(4);
$n4 = $notes->post($draft($d, 'credit', 1), $user);
[$parent, $worker] = $run('stamp', $n4->id, fn () => $zatca->sign($d, $user), 'note vs invoice stamp', fn () => ZatcaEgsUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail());
checkNotesProbe($parent->icv === 1 && ($worker['icv'] ?? null) === 2 && $worker['pih'] === $parent->invoice_hash, 'Chain wrong: '.json_encode($worker));
checkNotesProbe($unit->fresh()->icv_counter === 2 && $unit->fresh()->last_invoice_hash === $worker['hash'], 'Unit chain state wrong');
echo "PASS note stamp vs invoice stamp: ICV 1 (invoice) then 2 (note), PIH chained\n";

// 5. Submit vs retry of one note: one identity, one decision, attempts 1.
$recordId = $n4->fresh(['zatcaRecord'])->zatcaRecord->id;
[$parent, $worker] = $run('retry', $n4->id, fn () => $zatca->submit($n4->fresh(['zatcaRecord']), $user), 'submit vs retry', fn () => ZatcaInvoiceRecord::whereKey($recordId)->lockForUpdate()->firstOrFail());
$record = $n4->fresh(['zatcaRecord'])->zatcaRecord;
checkNotesProbe($record->status === 'cleared' && $record->icv === 2 && $record->submission_attempts === 1, 'Submit / retry wrong: '.$record->status.' attempts '.$record->submission_attempts);
checkNotesProbe(($worker['error'] ?? null) === 'ZatcaException' || ($worker['status'] ?? null) === 'cleared', 'Worker outcome unexpected: '.json_encode($worker));
checkNotesProbe($unit->fresh()->icv_counter === 2, 'Retry consumed a counter value');
echo "PASS note submit vs retry: one identity, cleared, attempts 1\n";

fclose($server);
echo 'PASS 5 MySQL customer-note concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
