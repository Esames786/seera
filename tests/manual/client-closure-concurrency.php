<?php

// All writes are confined to a uniquely named synthetic database on the attested disposable server.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierAdvance;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SupplierRecoverables;
use App\Services\Notifications\CustomerOverdueReminders;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$root = realpath($argv[1] ?? '');
if (! $root || ! preg_match('/^seera-feedback-mysql-[a-f0-9]{32}$/', basename($root)) || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Disposable server identity required');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33487;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $root) {
    throw new RuntimeException('Wrong server');
}
$mode = $argv[2] ?? 'parent';
$database = $mode === 'parent' ? 'seera_closure_probe_'.bin2hex(random_bytes(6)) : ($argv[3] ?? '');
if (! preg_match('/^seera_closure_probe_[a-f0-9]{12}$/', $database)) {
    throw new RuntimeException('Wrong database');
}
if ($mode === 'parent') {
    $pdo->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'ZATCA_PRODUCTION_ENABLED' => 'false'] as $k => $v) {
    putenv($k.'='.$v);
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage().PHP_EOL);
    exit(1);
});
config(['database.default' => 'closure_probe', 'database.connections.closure_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33487, 'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'engine' => 'InnoDB', 'strict' => true], 'seera.overdue_reminders.enabled' => true]);
DB::statement('SET SESSION innodb_lock_wait_timeout=20');

if ($mode !== 'parent') {
    $signal = stream_socket_client('tcp://'.$argv[4], $errno, $error, 10);
    $ids = json_decode($argv[5], true, flags: JSON_THROW_ON_ERROR);
    $announced = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$announced, $signal) {
        if (! $announced && str_contains(strtolower($sql), 'for update')) {
            $announced = true;
            fwrite($signal, "ATTEMPT\n");
        }
    });
    try {
        $actor = User::findOrFail($ids['actor']);
        if ($mode === 'record') {
            app(SupplierRecoverables::class)->record(Supplier::findOrFail($ids['supplier']), $ids['payload'], $actor);
        } elseif ($mode === 'remind') {
            app(CustomerOverdueReminders::class)->publish();
        } else {
            app(SupplierRecoverables::class)->settle(SupplierAdvance::findOrFail($ids['advance']), 'refund', $ids['payload'], $actor);
        }
        fwrite($signal, "OK\n");
    } catch (ValidationException $e) {
        fwrite($signal, "REFUSED\n");
    }
    fclose($signal);
    exit;
}

if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Synthetic migrations failed');
}
$role = Role::create(['name' => 'Probe Finance', 'code' => 'FINANCE_MANAGER', 'status' => 'active', 'access_scope' => 'Company Level']);
foreach (['view', 'approve', 'process'] as $action) {
    $role->permissions()->attach(Permission::firstOrCreate(['module' => 'Accounts Payable', 'action' => $action]));
}
$role->permissions()->attach(Permission::firstOrCreate(['module' => 'Accounts Receivable', 'action' => 'view']));
$actor = User::create(['name' => 'Synthetic Finance', 'email' => 'closure-probe@example.invalid', 'password' => bin2hex(random_bytes(20)), 'status' => 'active']);
$actor->roles()->attach($role, ['is_primary' => true]);
$accounts = [];
foreach (['1120' => 'Bank', '1500' => 'Advances', '2100' => 'AP'] as $code => $name) {
    $accounts[$code] = ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type' => $code === 2100 ? 'liability' : 'asset', 'normal_balance' => $code === 2100 ? 'credit' : 'debit', 'status' => 'active']);
}
AutomaticPostingRule::create(['source_module' => 'Supplier Advance', 'trigger_event' => 'Payment Recorded', 'debit_account_id' => $accounts[1500]->id, 'auto_post' => true, 'approval_required' => false, 'status' => 'active', 'cost_center_rule' => 'None']);
$supplier = Supplier::create(['name' => 'Probe supplier', 'code' => 'PROBE-S', 'status' => 'active', 'allowed_payment_types' => 'Both']);
$service = app(SupplierRecoverables::class);
$payment = fn () => ['payment_date' => today()->toDateString(), 'purpose' => 'advance', 'amount' => '100.00', 'reference' => 'Probe contract', 'reason' => 'Synthetic recoverable payment', 'recoverable_confirmed' => 1, 'payment_account_id' => $accounts[1120]->id, 'idempotency_key' => (string) Str::uuid()];
$refund = fn ($amount) => ['movement_date' => today()->toDateString(), 'amount' => $amount, 'reference' => 'Probe supplier return', 'payment_account_id' => $accounts[1120]->id, 'idempotency_key' => (string) Str::uuid()];
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($server, false);
$checks = [];
$race = function (string $name, string $action, array $ids, callable $lock, callable $operation, string $expected, callable $verify) use ($root, $database, $server, $address, &$checks) {
    DB::beginTransaction();
    $process = null;
    $signal = null;
    $pipes = [];
    $wait = function ($needle) use (&$signal, &$process, &$pipes) {
        $output = '';
        $deadline = microtime(true) + 30;
        while (microtime(true) < $deadline) {
            $output .= stream_get_contents($signal);
            if (str_contains($output, $needle)) {
                return;
            }
            if (! proc_get_status($process)['running']) {
                throw new RuntimeException('Worker ended: '.$output.stream_get_contents($pipes[2]));
            }
            usleep(20000);
        }
        throw new RuntimeException('Worker timed out: '.$output);
    };
    try {
        $lock();
        $process = proc_open([PHP_BINARY, __FILE__, $root, $action, $database, $address, json_encode($ids)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        $signal = stream_socket_accept($server, 15);
        if (! $signal) {
            throw new RuntimeException('No IPC');
        }
        stream_set_blocking($signal, false);
        $wait("ATTEMPT\n");
        usleep(120000);
        if (! proc_get_status($process)['running'] || stream_get_contents($signal) !== '') {
            throw new RuntimeException('Worker did not block on InnoDB lock');
        }
        $operation();
        DB::commit();
        $wait($expected."\n");
        if (! $verify()) {
            throw new RuntimeException('Reconciliation failed: '.$name);
        }
        $checks[] = ['name' => $name, 'ok' => true];
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        if ($signal) {
            fclose($signal);
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        if ($process) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            } proc_close($process);
        }
    }
};
$payload = $payment();
$base = ['actor' => $actor->id, 'supplier' => $supplier->id];
$race('Concurrent duplicate advance posts once', 'record', $base + ['payload' => $payload],
    fn () => Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail(),
    fn () => $service->record($supplier, $payload, $actor), 'OK', fn () => SupplierAdvance::where('idempotency_key', $payload['idempotency_key'])->count() === 1 && JournalEntry::where('source_module', 'Supplier Advance')->count() === 1);
$advance = $service->record($supplier, $payment(), $actor);
$second = $refund(40);
$race('Competing partial refunds cannot overspend available funds', 'refund', $base + ['advance' => $advance->id, 'payload' => $second],
    fn () => SupplierAdvance::whereKey($advance->id)->lockForUpdate()->firstOrFail(),
    fn () => $service->settle($advance, 'refund', $refund(70), $actor), 'REFUSED', fn () => $advance->fresh()->available() === 30.0 && $advance->movements()->count() === 1);
$advance = $service->record($supplier, $payment(), $actor);
$second = $refund(40);
$race('Reversal wins before a blocked refund without consuming money twice', 'refund', $base + ['advance' => $advance->id, 'payload' => $second],
    fn () => SupplierAdvance::whereKey($advance->id)->lockForUpdate()->firstOrFail(),
    fn () => $service->reverse($advance, null, 'Synthetic payment cancelled', $actor), 'REFUSED', fn () => $advance->fresh()->available() === 0.0 && $advance->movements()->count() === 0);
$customer = Customer::create(['name' => 'Probe customer', 'code' => 'PROBE-C', 'type' => 'Company', 'status' => 'active']);
$entry = JournalEntry::create(['journal_number' => 'PROBE-INV-J', 'journal_date' => today()->toDateString(), 'status' => 'posted']);
$invoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'PROBE-INV', 'invoice_date' => today()->subDays(10)->toDateString(), 'due_date' => today()->subDay()->toDateString(), 'total_amount' => 115, 'balance_amount' => 115, 'payment_status' => 'unpaid', 'journal_entry_id' => $entry->id]);
$race('Concurrent overdue publishers produce one user/invoice/day notice', 'remind', $base,
    fn () => CustomerInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail(),
    fn () => app(CustomerOverdueReminders::class)->publish(), 'OK', fn () => DB::table('notifications')->count() === 1);
DB::table('notifications')->delete();
$race('Concurrent paid invoice is excluded before reminder insertion', 'remind', $base,
    fn () => CustomerInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail(),
    fn () => $invoice->update(['payment_status' => 'paid', 'balance_amount' => 0]), 'OK', fn () => DB::table('notifications')->count() === 0);
$unbalanced = DB::table('journal_entries')->where('total_debit', '!=', DB::raw('total_credit'))->count();
if ($unbalanced || DB::table('vat_transactions')->count() !== 0) {
    throw new RuntimeException('Unexpected GL/VAT changes');
}
$checks[] = ['name' => 'All financial journals balance; no VAT invented', 'ok' => true];
$vatAccount = ChartOfAccount::create(['account_code' => '1300', 'account_name' => 'Input VAT', 'account_type' => 'asset', 'normal_balance' => 'debit', 'status' => 'active']);
$expenseAccount = ChartOfAccount::create(['account_code' => '5200', 'account_name' => 'Training purchase expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'status' => 'active']);
AutomaticPostingRule::create(['source_module' => 'Supplier Bill', 'trigger_event' => 'Bill Approved', 'auto_post' => true, 'status' => 'active', 'cost_center_rule' => 'None']);
$bill = SupplierBill::create(['supplier_id' => $supplier->id, 'bill_number' => 'PROBE-POSTED-BILL', 'bill_date' => today()->toDateString(), 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115, 'balance_amount' => 115, 'status' => 'unpaid']);
$bill->lines()->create(['description' => 'Synthetic purchase', 'chart_of_account_id' => $expenseAccount->id, 'quantity' => 1, 'unit_price' => 100, 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115]);
$posting = app(PostingService::class);
$billJournal = $posting->postSupplierBill($bill, $actor->id);
$bill->update(['journal_entry_id' => $billJournal->id]);
$vatBefore = DB::table('vat_transactions')->get()->map(fn ($row) => (array) $row)->all();
$advance = $service->record($supplier, $payment(), $actor);
$bankBefore = $accounts[1120]->postedBalance();
$service->settle($advance, 'apply', ['movement_date' => today()->toDateString(), 'amount' => 40, 'reference' => 'Explicit bill clearing', 'supplier_bill_id' => $bill->id, 'idempotency_key' => (string) Str::uuid()], $actor);
if ($accounts[2100]->postedBalance() !== 75.0 || (float) $bill->fresh()->balance_amount !== 75.0
    || $advance->fresh()->available() !== 60.0 || $accounts[1120]->postedBalance() !== $bankBefore
    || $vatBefore !== DB::table('vat_transactions')->get()->map(fn ($row) => (array) $row)->all()
    || count($vatBefore) !== 1 || $vatAccount->postedBalance() !== 15.0) {
    throw new RuntimeException('Posted bill/AP/VAT reconciliation failed');
}
foreach (JournalEntry::with('lines')->get() as $journal) {
    if (round((float) $journal->lines->sum('debit'), 2) !== (float) $journal->total_debit || round((float) $journal->lines->sum('credit'), 2) !== (float) $journal->total_credit) {
        throw new RuntimeException('Journal line/header mismatch');
    }
}
$checks[] = ['name' => 'Real posted bill reconciles AP and advance clearing; VAT row unchanged; no extra cash; every journal line matches its header', 'ok' => true];
echo json_encode(['synthetic' => true, 'production_access' => false, 'passed' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT).PHP_EOL;
