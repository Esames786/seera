<?php

// Explicit disposable MySQL only. Source/destination DBs are synthetic, not the application's DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Services\Operations\TransactionalStorageCopy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$root = realpath($argv[1] ?? '');
if (! $root || ! preg_match('/^seera-feedback-mysql-[a-f0-9]{32}$/', basename($root)) || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Dedicated disposable MySQL datadir required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33487;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $root) {
    throw new RuntimeException('Refusing writes: disposable server identity mismatch.');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'ZATCA_PRODUCTION_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
$suffix = bin2hex(random_bytes(6));
$sourceName = 'seera_storage_source_'.$suffix;
$targetName = 'seera_storage_target_'.$suffix;
foreach ([$sourceName, $targetName] as $name) {
    $pdo->exec('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
config(['database.default' => 'storage_probe', 'database.connections.storage_probe' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33487, 'database' => $sourceName, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'engine' => 'InnoDB', 'strict' => true]]);
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Synthetic source migrations failed.');
}
config(['seera.admin.name' => 'Synthetic Owner', 'seera.admin.email' => 'owner@storage-test.invalid', 'seera.admin.username' => 'storage-owner', 'seera.admin.password' => bin2hex(random_bytes(20)), 'seera.organization.email_domain' => 'storage-test.invalid']);
if (Artisan::call('db:seed', ['--class' => 'Database\Seeders\ProductionBootstrapSeeder', '--force' => true]) !== 0) {
    throw new RuntimeException('Synthetic setup seeding failed.');
}
$owner = User::where('email', 'owner@storage-test.invalid')->firstOrFail();
$customer = Customer::create(['name' => 'Real-entry simulation retained', 'code' => 'COPY-C', 'type' => 'Company', 'status' => 'active']);
$invoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'COPY-INV', 'invoice_date' => '2026-11-11', 'due_date' => '2026-12-11', 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115, 'balance_amount' => 115, 'payment_status' => 'unpaid']);
$invoice->lines()->create(['description' => 'Retained synthetic service', 'quantity' => 1, 'unit_price' => 100, 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115]);
$journal = app(PostingService::class)->postCustomerInvoice($invoice, $owner->id);
$invoice->update(['journal_entry_id' => $journal->id]);
$schema = DB::connection()->getSchemaBuilder();
foreach ($schema->getTables($sourceName) as $table) {
    foreach ($schema->getForeignKeys($table['name']) as $key) {
        DB::statement('ALTER TABLE `'.$table['name'].'` DROP FOREIGN KEY `'.$key['name'].'`');
    }
}
foreach (['users', 'employees', 'customers', 'suppliers', 'journal_entries', 'journal_entry_lines', 'customer_invoices', 'customer_invoice_lines', 'document_sequences'] as $table) {
    DB::statement('ALTER TABLE `'.$table.'` ENGINE=MyISAM');
}
// Reproduce a partially attempted November rollout: photo columns and the
// customer document table exist, later tables and migration ledger rows do not.
foreach (['supplier_advance_movements', 'supplier_advances', 'user_permission_overrides', 'supplier_documents'] as $table) {
    DB::statement('DROP TABLE `'.$table.'`'); // These tables are empty in this disposable source.
}
DB::table('migrations')->where('migration', 'like', '2026_11_1%')->delete();
$mode = $argv[2] ?? 'positive';
if (! in_array($mode, ['positive', 'orphan', 'changed', 'unknown'], true)) {
    throw new RuntimeException('Unknown probe mode.');
}
if ($mode === 'orphan') {
    DB::table('customer_invoices')->where('id', $invoice->id)->update(['customer_id' => 999999]);
}
if ($mode === 'unknown') {
    DB::statement('ALTER TABLE customers ADD COLUMN unreviewed_legacy_value VARCHAR(20) NULL');
}
$checks = [];
$check = function ($name, $ok) use (&$checks) {
    $checks[] = ['name' => $name, 'ok' => (bool) $ok];
    if (! $ok) {
        throw new RuntimeException($name);
    }
};
$service = app(TransactionalStorageCopy::class);
$check('Dry run reports different empty destination without changing it', $service->inspect($targetName)['target_empty'] && count($pdo->query('SHOW TABLES FROM '.$targetName)->fetchAll()) === 0);
try {
    $service->inspect($sourceName);
    $check('Same database refused', false);
} catch (RuntimeException $e) {
    $check('Same database refused', true);
}
$sourceWrites = [];
DB::listen(function (QueryExecuted $query) use (&$sourceWrites) {
    if ($query->connectionName === 'storage_probe' && ! preg_match('/^\s*(select|show)\b/i', $query->sql)) {
        $sourceWrites[] = preg_split('/\s+/', trim($query->sql))[0];
    }
});
if ($mode === 'changed') {
    $changed = false;
    DB::listen(function (QueryExecuted $query) use (&$changed, $pdo, $sourceName, $customer) {
        if (! $changed && $query->connectionName === 'seera_storage_target' && str_starts_with(strtolower($query->sql), 'insert into `customers`')) {
            $changed = true;
            $pdo->exec('UPDATE '.$sourceName.'.customers SET name=\'Concurrent external writer\' WHERE id='.(int) $customer->id);
        }
    });
}
if ($mode !== 'positive') {
    try {
        $service->copy($targetName);
        throw new RuntimeException('Unsafe source accepted: '.$mode);
    } catch (RuntimeException $error) {
        $expected = match ($mode) {
            'orphan' => 'Orphan reference', 'changed' => 'Row digest mismatch/source changed', 'unknown' => 'cannot preserve every source field'
        };
        $check('Unsafe '.$mode.' source is refused without promotion', str_contains($error->getMessage(), $expected));
        $check('Preparation performs no source write SQL', $sourceWrites === []);
        $check('Original invoice and posted journal still exist', (int) $pdo->query('SELECT COUNT(*) FROM '.$sourceName.'.customer_invoices')->fetchColumn() === 1 && $pdo->query('SELECT status FROM '.$sourceName.'.journal_entries WHERE id='.(int) $journal->id)->fetchColumn() === 'posted');
        $check('Active connection remains the original source', DB::getDefaultConnection() === 'storage_probe');
        echo json_encode(['synthetic' => true, 'production_access' => false, 'mode' => $mode, 'checks' => $checks, 'passed' => count($checks)], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }
}
$report = $service->copy($targetName);
$check('Source receives no write SQL during destination preparation', $sourceWrites === []);
$check('Every source table and field is row-hash verified', $report['all_source_fields_preserved'] && $report['source_unchanged'] && count($report['copied_tables']) > 95);
$check('Fresh InnoDB schema restores missing foreign keys', $report['verified_foreign_keys'] > 250);
$check('New destination migration ledger retained and source ledger untouched', $report['source_migration_ledger_unchanged']);
$check('Existing business invoice and GL journal survive', $pdo->query('SELECT invoice_number FROM '.$targetName.'.customer_invoices WHERE id='.(int) $invoice->id)->fetchColumn() === 'COPY-INV' && $pdo->query('SELECT status FROM '.$targetName.'.journal_entries WHERE id='.(int) $journal->id)->fetchColumn() === 'posted');
$check('No source engine or active connection changes', DB::getDefaultConnection() === 'storage_probe' && $schema->getTables($sourceName)[0]['schema'] === $sourceName && $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='$sourceName' AND TABLE_NAME='users'")->fetchColumn() === 'MyISAM');
$check('Destination is reviewable but never automatically promoted', $report['ready_for_operator_review'] && ! $report['automatic_promotion']);
try {
    $service->copy($targetName);
    $check('Nonempty destination refused', false);
} catch (RuntimeException $e) {
    $check('Nonempty destination refused', str_contains($e->getMessage(), 'completely empty'));
}
echo json_encode(['synthetic' => true, 'production_access' => false, 'checks' => $checks, 'copied_tables' => count($report['copied_tables']), 'foreign_keys' => $report['verified_foreign_keys'], 'passed' => count($checks)], JSON_PRETTY_PRINT).PHP_EOL;
