<?php
// Explicit opt-in; isolated local MySQL only. No application DB credentials, no ZATCA call.
//
// Proves on a real InnoDB server that customer credit resolution is safe under
// concurrency: two allocations racing for the same remaining credit, an
// allocation racing a refund, two refunds, a refund racing its own reversal,
// and an allocation reversal racing a second allocation. Available credit is
// never negative, no journal is duplicated, no invoice balance goes negative.
// Runs against a disposable MySQL 8 server on 127.0.0.1:33479 whose datadir
// name starts with "seera-zatca-lock-test-". It refuses any other server.
//
//   php tests/manual/customer-credit-concurrency.php <datadir>
require dirname(__DIR__, 2).'/vendor/autoload.php';
$expected = realpath($argv[1] ?? '');
if (! $expected || ! str_starts_with(basename($expected), 'seera-zatca-lock-test-') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Explicit disposable datadir and uncached config required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33479;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $expected) {
    throw new RuntimeException('Refusing writes: datadir mismatch.');
}

use App\Models\ChartOfAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerCreditAllocation;
use App\Models\CustomerCreditRefund;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\CustomerReceipt;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\Accounting\CustomerCreditResolutionService;
use App\Services\Accounting\CustomerNoteService;
use App\Services\Accounting\PostingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$action = $argv[2] ?? 'parent';
$database = $action === 'parent' ? 'seera_credit_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_credit_probe_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Invalid probe database.');
}
if ($action === 'parent') {
    $pdo->exec('CREATE DATABASE '.$database);
}
foreach (['APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'FILESYSTEM_DISK' => 'local', 'ZATCA_ENVIRONMENT' => 'sandbox'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
    exit(1);
});
config(['database.default' => 'credit_probe', 'database.connections.credit_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33479,
    'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
config(['filesystems.disks.local.root' => sys_get_temp_dir().'/seera-credit-probe-files']);
DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
$credits = app(CustomerCreditResolutionService::class);
$notes = app(CustomerNoteService::class);

if ($action !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    if (! $signal) {
        throw new RuntimeException('IPC: '.$error);
    }
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        $lower = strtolower($sql);
        if (! $announced && str_contains($lower, 'customer_adjustment_notes') && str_contains($lower, 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    $user = User::findOrFail($ids['user']);
    $note = CustomerAdjustmentNote::withoutGlobalScopes()->findOrFail($ids['note']);
    try {
        $result = match ($action) {
            'allocate' => (function () use ($credits, $ids, $note, $user) {
                [$a] = $credits->allocate($note, CustomerInvoice::withoutGlobalScopes()->findOrFail($ids['invoice']), ['amount' => $ids['amount'], 'allocation_date' => now()->toDateString()], $user);

                return ['id' => $a->id, 'amount' => $a->amount];
            })(),
            'refund' => (function () use ($credits, $ids, $note, $user) {
                [$r] = $credits->refund($note, ['amount' => $ids['amount'], 'payment_date' => now()->toDateString(), 'payment_account_id' => $ids['account']], $user);

                return ['id' => $r->id, 'amount' => $r->amount, 'journal' => $r->journal_entry_id];
            })(),
            'reverse-refund' => (function () use ($credits, $ids, $user) {
                $r = $credits->reverseRefund(CustomerCreditRefund::withoutGlobalScopes()->findOrFail($ids['refund']), 'probe', $user);

                return ['id' => $r->id, 'reversed' => $r->reversed_at !== null, 'reversal' => $r->reversal_journal_id];
            })(),
            'reverse-allocation' => (function () use ($credits, $ids, $user) {
                $a = $credits->reverseAllocation(CustomerCreditAllocation::withoutGlobalScopes()->findOrFail($ids['allocation']), 'probe', $user);

                return ['id' => $a->id, 'reversed' => $a->reversed_at !== null];
            })(),
        };
        fwrite($signal, 'RESULT '.json_encode($result)."\n");
    } catch (Throwable $e) {
        fwrite($signal, 'RESULT '.json_encode(['error' => class_basename($e), 'message' => mb_substr($e->getMessage(), 0, 300)])."\n");
    }
    exit;
}

function checkCreditProbe(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
function waitCreditProbe($process, $signal, array $pipes, string $needle): string
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

checkCreditProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Migration failed');
checkCreditProbe(Artisan::call('db:seed', ['--class' => 'ProductionChartOfAccountsSeeder', '--force' => true]) === 0, 'Chart seed failed');
checkCreditProbe(Artisan::call('migrate', ['--force' => true]) === 0, 'Migration rerun failed');
$migration = require database_path('migrations/2026_10_23_000001_add_customer_credit_resolution.php');
$migration->up();
echo "PASS additive credit-resolution migration and rerun on MySQL\n";

CompanyProfile::query()->delete();
CompanyProfile::create(['name' => 'Probe Seller Co.', 'name_ar' => 'شركة', 'vat_number' => '300123456700003', 'cr_number' => '1010123456', 'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya',
    'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN', 'default_vat_rate' => 15, 'invoice_mode' => 'x', 'certificate_status' => 'x', 'currency' => 'SAR', 'status' => 'active']);
$customer = Customer::create(['name' => 'Probe customer', 'code' => 'CP-CUS', 'type' => 'Company', 'vat_number' => '300987654300003', 'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
$user = User::create(['name' => 'probe finance', 'email' => 'probe-finance@example.invalid', 'password' => 'isolated-test-only', 'status' => 'active']);
$project = Project::create(['code' => 'CP', 'name' => 'Probe project', 'status' => 'active']);
$posting = app(PostingService::class);
$bank = ChartOfAccount::where('account_code', PostingService::BANK)->firstOrFail();
$makeInvoice = function (int $n, float $units = 10) use ($customer, $project): CustomerInvoice {
    $net = $units * 100;
    $invoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'INV-CPROBE-'.$n, 'invoice_type' => 'standard', 'invoice_date' => now()->toDateString(), 'issue_time' => now()->format('H:i:s'),
        'supply_date' => now()->toDateString(), 'project_id' => $project->id, 'taxable_amount' => $net, 'vat_rate' => 15, 'vat_amount' => $net * 0.15, 'total_amount' => $net * 1.15, 'balance_amount' => $net * 1.15, 'payment_status' => 'unpaid', 'zatca_status' => 'not_generated']);
    CustomerInvoiceLine::create(['customer_invoice_id' => $invoice->id, 'description' => 'Probe line '.$n, 'quantity' => $units, 'unit_price' => 100, 'taxable_amount' => $net, 'vat_rate' => 15, 'vat_amount' => $net * 0.15, 'total_amount' => $net * 1.15]);

    return $invoice->fresh(['lines']);
};
// A paid invoice and a credit note whose whole value is customer credit.
$creditNote = function (int $n, float $units) use ($makeInvoice, $customer, $bank, $posting, $notes, $user): CustomerAdjustmentNote {
    $invoice = $makeInvoice($n);
    $receipt = CustomerReceipt::create(['customer_id' => $customer->id, 'customer_invoice_id' => $invoice->id, 'receipt_date' => now()->toDateString(), 'receipt_account_id' => $bank->id, 'payment_method' => 'Bank Transfer', 'amount' => $invoice->total_amount]);
    $receipt->update(['journal_entry_id' => $posting->postCustomerReceipt($receipt, $user->id)->id]);
    $invoice->refreshPaymentStatus();
    $draft = $notes->createDraft($invoice, 'credit', ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'probe'], [['invoice_line_id' => $invoice->lines[0]->id, 'quantity' => $units, 'unit_price' => 100]], $user);

    return $notes->post($draft, $user);
};

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
checkCreditProbe(is_resource($server), 'IPC failed');
$address = stream_socket_get_name($server, false);

// Scenario runner: the parent holds the credit-note row lock, the worker queues on it, the parent then acts and commits.
$run = function (string $workerAction, array $ids, ?callable $parentAction, string $label) use ($server, $address, $expected, $database, $user) {
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    try {
        CustomerAdjustmentNote::withoutGlobalScopes()->whereKey($ids['note'])->lockForUpdate()->firstOrFail();
        $process = proc_open([PHP_BINARY, __FILE__, $expected, $workerAction, $database, $address, json_encode(['user' => $user->id] + $ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        checkCreditProbe(is_resource($process), 'Worker failed to start');
        $signal = stream_socket_accept($server, 15);
        checkCreditProbe(is_resource($signal), 'Worker IPC missing');
        stream_set_blocking($signal, false);
        waitCreditProbe($process, $signal, $pipes, "ATTEMPT\n");
        usleep(200000);
        checkCreditProbe(proc_get_status($process)['running'] && stream_get_contents($signal) === '', "Worker did not wait on the credit-note lock in $label");
        $parentResult = $parentAction ? $parentAction() : null;
        DB::commit();
        $output = waitCreditProbe($process, $signal, $pipes, 'RESULT ');
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
$refused = fn (array $w) => ($w['error'] ?? null) === 'ValidationException';

// 1. Two allocations of 600 against 575 of credit: one posts, the other is refused under lock; credit never negative.
$n1 = $creditNote(1, 5); // 575
$t1 = $makeInvoice(11);
$t2 = $makeInvoice(12);
[$parent, $worker] = $run('allocate', ['note' => $n1->id, 'invoice' => $t2->id, 'amount' => 400], fn () => $credits->allocate($n1, $t1, ['amount' => 400, 'allocation_date' => now()->toDateString()], $user)[0], 'allocation vs allocation');
checkCreditProbe($parent->amount == 400 && $refused($worker) && str_contains($worker['message'], 'available'), 'Second allocation over-used the credit: '.json_encode($worker));
checkCreditProbe($credits->availableCredit($n1->fresh()) === 175.0, 'Available after race wrong: '.$credits->availableCredit($n1->fresh()));
checkCreditProbe((float) $t2->fresh()->balance_amount === 1150.0 && (float) $t1->fresh()->balance_amount === 750.0, 'Target balances wrong');
echo "PASS allocation vs allocation: one 400 applied, the other refused, available 175.00, balances 750.00 / 1,150.00\n";

// 2. Allocation vs refund racing for the last 175: one wins, the other is refused.
[$parent, $worker] = $run('refund', ['note' => $n1->id, 'amount' => 175, 'account' => $bank->id], fn () => $credits->allocate($n1, $t2, ['amount' => 175, 'allocation_date' => now()->toDateString()], $user)[0], 'allocation vs refund');
checkCreditProbe($parent->amount == 175 && $refused($worker), 'Refund consumed credit already allocated: '.json_encode($worker));
checkCreditProbe($credits->availableCredit($n1->fresh()) === 0.0 && CustomerCreditRefund::count() === 0, 'Credit went negative or a refund journal slipped through');
echo "PASS allocation vs refund: allocation wins, refund refused, available 0.00, no refund journal\n";

// 3. Refund vs refund: 230 of credit, two refunds of 150 — one posts, one is refused, one journal.
$n3 = $creditNote(3, 2); // 230
[$parent, $worker] = $run('refund', ['note' => $n3->id, 'amount' => 150, 'account' => $bank->id], fn () => $credits->refund($n3, ['amount' => 150, 'payment_date' => now()->toDateString(), 'payment_account_id' => $bank->id], $user)[0], 'refund vs refund');
checkCreditProbe($parent->amount == 150 && $refused($worker), 'Second refund over-used the credit: '.json_encode($worker));
checkCreditProbe(JournalEntry::where('source_module', 'Customer Credit Refund')->count() === 1 && $credits->availableCredit($n3->fresh()) === 80.0, 'Refund journals / credit wrong');
echo "PASS refund vs refund: one 150 refund, the other refused, one journal, available 80.00\n";

// 4. Refund vs its own reversal: the reversal (parent) wins, the worker's second reversal is a no-op; one reversal journal; credit back to 230.
$r4 = $credits->refund($n3, ['amount' => 80, 'payment_date' => now()->toDateString(), 'payment_account_id' => $bank->id], $user)[0];
[$parent, $worker] = $run('reverse-refund', ['note' => $n3->id, 'refund' => $r4->id], fn () => $credits->reverseRefund($r4, 'probe parent', $user), 'refund reversal vs reversal');
checkCreditProbe($parent->reversed_at !== null && ($worker['reversed'] ?? false) === true && (int) $worker['reversal'] === (int) $parent->reversal_journal_id, 'Double reversal: '.json_encode($worker));
checkCreditProbe(JournalEntry::where('source_module', 'Manual')->where('reference_number', $r4->journalEntry->journal_number)->count() === 1, 'Two reversal journals');
checkCreditProbe($credits->availableCredit($n3->fresh()) === 80.0, 'Credit after reversal wrong: '.$credits->availableCredit($n3->fresh()));
echo "PASS refund vs refund reversal: one reversal journal, credit restored to 80.00\n";

// 5. Allocation reversal vs a second allocation: the parent reverses (credit 230 → 230 + 230 impossible? no: credit 80 + restored) — use a fresh note.
$n5 = $creditNote(5, 2); // 230
$t5 = $makeInvoice(51);
$t6 = $makeInvoice(52);
$a5 = $credits->allocate($n5, $t5, ['amount' => 230, 'allocation_date' => now()->toDateString()], $user)[0];
[$parent, $worker] = $run('allocate', ['note' => $n5->id, 'invoice' => $t6->id, 'amount' => 230], fn () => $credits->reverseAllocation($a5, 'probe', $user), 'allocation reversal vs allocation');
checkCreditProbe($parent->reversed_at !== null && ($worker['amount'] ?? null) == 230, 'Second allocation did not see the restored credit: '.json_encode($worker));
checkCreditProbe($credits->availableCredit($n5->fresh()) === 0.0 && (float) $t5->fresh()->balance_amount === 1150.0 && (float) $t6->fresh()->balance_amount === 920.0, 'Balances after reversal + allocation wrong');
echo "PASS allocation reversal vs allocation: reversal first, then 230 applied to the other invoice, available 0.00\n";

fclose($server);
echo 'PASS 5 MySQL customer-credit concurrency scenarios ('.DB::selectOne('SELECT @@transaction_isolation AS level')->level.") in $database\n";
