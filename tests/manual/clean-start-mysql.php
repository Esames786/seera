<?php

// Only a dedicated, disposable local MySQL instance. Never loads live credentials.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Employee;
use App\Models\User;
use App\Services\Operations\CleanStartPreparation;
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
    throw new RuntimeException('Disposable server identity mismatch.');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'ZATCA_PRODUCTION_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
    exit(1);
});
$suffix = bin2hex(random_bytes(6));
$sourceName = 'seera_clean_source_'.$suffix;
$targetName = 'seera_clean_target_'.$suffix;
foreach ([$sourceName, $targetName] as $name) {
    $pdo->exec('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
config(['database.default' => 'clean_probe', 'database.connections.clean_probe' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33487, 'database' => $sourceName,
    'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'engine' => 'InnoDB', 'strict' => true,
]]);
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Synthetic source migration failed.');
}
config(['seera.admin.name' => 'Synthetic Admin', 'seera.admin.email' => 'admin@clean-test.invalid', 'seera.admin.username' => 'clean-admin',
    'seera.admin.password' => bin2hex(random_bytes(20)), 'seera.organization.email_domain' => 'clean-test.invalid']);
if (Artisan::call('db:seed', ['--class' => 'Database\Seeders\ProductionBootstrapSeeder', '--force' => true]) !== 0) {
    throw new RuntimeException('Synthetic setup failed.');
}
$admin = User::where('email', 'admin@clean-test.invalid')->firstOrFail();
$old = User::where('id', '!=', $admin->id)->firstOrFail();
$client = User::create(['name' => 'Synthetic Client', 'email' => 'client@clean-test.invalid', 'password' => bin2hex(random_bytes(16)), 'status' => 'active']);
$finance = DB::table('roles')->where('code', 'FINANCE_MANAGER')->value('id');
$client->roles()->attach($finance, ['is_primary' => true]);
$employee = Employee::create(['employee_code' => 'SP-001', 'first_name' => 'Retained', 'last_name' => 'Employee', 'user_id' => $client->id,
    'manager_id' => $old->id, 'basic_salary' => 3500, 'status' => 'active']);
$customer = Customer::create(['name' => 'Retained Customer', 'code' => 'CUS-001', 'type' => 'Company', 'status' => 'active']);
$invoice = CustomerInvoice::create(['customer_id' => $customer->id, 'invoice_number' => 'DISCARD-INV', 'invoice_date' => '2026-11-11',
    'due_date' => '2026-12-11', 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115,
    'balance_amount' => 115, 'payment_status' => 'unpaid']);
DB::table('document_sequences')->updateOrInsert(['key' => 'CUS'], ['last_value' => 44]);
$document = ['customer_id' => $customer->id, 'document_type' => 'CR', 'original_filename' => 'synthetic.pdf',
    'path' => 'customer-documents/synthetic.pdf', 'mime_type' => 'application/pdf', 'size' => 123, 'uploaded_by' => $old->id];
DB::table('customer_documents')->insert($document);
$asset = DB::table('equipment_assets')->insertGetId(['asset_code' => 'EQ-001', 'name' => 'Retained machine', 'created_by' => $old->id]);
DB::table('equipment_documents')->insert(['equipment_asset_id' => $asset, 'document_type' => 'insurance',
    'original_filename' => 'synthetic.pdf', 'path' => 'equipment-documents/synthetic.pdf', 'mime_type' => 'application/pdf', 'size' => 123, 'uploaded_by' => $old->id]);
$schema = DB::connection()->getSchemaBuilder();
foreach ($schema->getTables($sourceName) as $table) {
    foreach ($schema->getForeignKeys($table['name']) as $key) {
        DB::statement('ALTER TABLE `'.$table['name'].'` DROP FOREIGN KEY `'.$key['name'].'`');
    }
}
foreach (['users', 'employees', 'customers', 'customer_invoices', 'document_sequences'] as $table) {
    DB::statement('ALTER TABLE `'.$table.'` ENGINE=MyISAM');
}
foreach (['supplier_advance_movements', 'supplier_advances', 'user_permission_overrides', 'supplier_documents'] as $table) {
    DB::statement('DROP TABLE `'.$table.'`');
}
DB::table('migrations')->where('migration', 'like', '2026_11_1%')->delete();
$checks = [];
$check = function ($name, $ok) use (&$checks) {
    $checks[] = ['name' => $name, 'ok' => (bool) $ok];
    if (! $ok) {
        throw new RuntimeException($name);
    }
};
$service = app(CleanStartPreparation::class);
$ids = [$admin->id, $client->id];
$beforeUsers = DB::table('users')->count();
$mode = $argv[2] ?? 'positive';
if (! in_array($mode, ['positive', 'orphan', 'workflow', 'changed', 'unknown', 'production-zatca', 'assigned-asset'], true)) {
    throw new RuntimeException('Unknown probe mode.');
}
if ($mode === 'orphan') {
    DB::table('employees')->where('id', $employee->id)->update(['department_id' => 999999]);
}
if ($mode === 'workflow') {
    $workflow = DB::table('approval_workflows')->insertGetId(['name' => 'Explicit approver', 'module' => 'purchase_requests']);
    DB::table('approval_workflow_steps')->insert(['approval_workflow_id' => $workflow, 'step_no' => 1, 'approver_user_id' => $old->id]);
}
if ($mode === 'unknown') {
    DB::statement('CREATE TABLE unknown_master (id BIGINT PRIMARY KEY)');
}
if ($mode === 'production-zatca') {
    config(['zatca.production_enabled' => true]);
}
if ($mode === 'assigned-asset') {
    DB::table('equipment_assets')->where('id', $asset)->update(['current_assignment_id' => 777]);
}
$writes = [];
DB::listen(function (QueryExecuted $query) use (&$writes) {
    if ($query->connectionName === 'clean_probe' && ! preg_match('/^\s*(select|show)\b/i', $query->sql)) {
        $writes[] = substr($query->sql, 0, 20);
    }
});
if ($mode === 'changed') {
    $changed = false;
    DB::listen(function (QueryExecuted $query) use (&$changed, $pdo, $sourceName, $customer) {
        if (! $changed && $query->connectionName === CleanStartPreparation::CONNECTION && str_starts_with(strtolower($query->sql), 'insert into `customers`')) {
            $changed = true;
            $pdo->exec('UPDATE '.$sourceName.'.customers SET name=\'Concurrent source writer\' WHERE id='.(int) $customer->id);
        }
    });
}
if ($mode !== 'positive') {
    try {
        $service->prepare($targetName, $ids, $admin->id);
        throw new RuntimeException('Unsafe source accepted: '.$mode);
    } catch (RuntimeException $error) {
        $message = match ($mode) {
            'orphan' => 'Orphan reference', 'workflow' => 'refers to a removed login', 'unknown' => 'Unclassified source tables',
            'production-zatca' => 'ZATCA must remain disabled', 'changed' => 'Source changed during preparation', 'assigned-asset' => 'current assignment',
        };
        $check('Unsafe '.$mode.' state refused without activation', str_contains($error->getMessage(), $message));
        $check('No writes issued to source DB', $writes === []);
        $check('Source users and invoice remain', DB::table('users')->count() === $beforeUsers && DB::table('customer_invoices')->count() === 1);
        $check('Application source connection unchanged', DB::getDefaultConnection() === 'clean_probe');
        echo json_encode(['synthetic' => true, 'mode' => $mode, 'checks' => $checks, 'passed' => count($checks)], JSON_PRETTY_PRINT).PHP_EOL;
        exit(0);
    }
}
$check('Dry run leaves destination empty', $service->inspect($targetName, $ids, $admin->id)['dry_run'] && count($pdo->query('SHOW TABLES FROM '.$targetName)->fetchAll()) === 0);
foreach ([['name' => $sourceName, 'ids' => $ids, 'admin' => $admin->id], ['name' => $targetName, 'ids' => [$client->id, $client->id], 'admin' => $client->id],
    ['name' => $targetName, 'ids' => $ids, 'admin' => $client->id], ['name' => $targetName, 'ids' => [$admin->id, 999999], 'admin' => $admin->id]] as $case) {
    try {
        $service->inspect($case['name'], $case['ids'], $case['admin']);
        $check('Unsafe identity rejected', false);
    } catch (RuntimeException $error) {
        $check('Unsafe same-source/duplicate/Finance-admin/missing identity refused', true);
    }
}
$report = $service->prepare($targetName, $ids, $admin->id);
$target = DB::connection(CleanStartPreparation::CONNECTION);
$check('Only exact two retained users exist in destination', $target->table('users')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all() === $ids);
$check('Client password hash and Finance role unchanged', $target->table('users')->where('id', $client->id)->value('password') === DB::table('users')->where('id', $client->id)->value('password')
    && $target->table('user_roles')->where('user_id', $client->id)->pluck('role_id')->all() === [$finance]);
$check('Employee salary and retained client link preserved; removed manager detached', $target->table('employees')->where('id', $employee->id)->value('user_id') === $client->id
    && $target->table('employees')->where('id', $employee->id)->value('manager_id') === null
    && $target->table('employees')->where('id', $employee->id)->value('basic_salary') === '3500.00');
$check('Customer attachment and asset document preserved', $target->table('customer_documents')->where('path', $document['path'])->exists() && $target->table('equipment_documents')->count() === 1);
$check('Business documents and runtime data empty', $target->table('customer_invoices')->count() === 0 && $target->table('journal_entries')->count() === 0 && $target->table('notifications')->count() === 0);
$check('Master sequence remains at 44 instead of resetting', (int) $target->table('document_sequences')->where('key', 'CUS')->value('last_value') === 44);
$check('All setup roles and chart of accounts preserved', $target->table('roles')->count() === DB::table('roles')->count() && $target->table('chart_of_accounts')->count() === DB::table('chart_of_accounts')->count());
$check('Source users, invoice and storage remain untouched', $writes === [] && DB::table('users')->count() === $beforeUsers && DB::table('customer_invoices')->count() === 1
    && $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='$sourceName' AND TABLE_NAME='users'")->fetchColumn() === 'MyISAM');
$check('Destination fully InnoDB and master hash/FK verification passes', $report['all_destination_tables_innodb'] && $report['verified_foreign_keys'] > 250 && count($report['verified_master_hashes']) > 35);
$check('Nullable user detaches reported and source never auto-promoted', $report['nullable_removed_login_references_detached']['employees.manager_id'] === 1 && ! $report['automatic_promotion']);
try {
    $service->inspect($targetName, $ids, $admin->id);
    $check('Nonempty target refused', false);
} catch (RuntimeException $error) {
    $check('Nonempty destination refused', str_contains($error->getMessage(), 'completely empty'));
}
echo json_encode(['synthetic' => true, 'production_access' => false, 'mode' => $mode, 'checks' => $checks, 'passed' => count($checks)], JSON_PRETTY_PRINT).PHP_EOL;
