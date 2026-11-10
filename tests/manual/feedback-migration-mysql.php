<?php

// Only the dedicated, disposable MySQL instance. Never reads application DB credentials.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = realpath($argv[1] ?? '');
if (! $root || ! preg_match('/^seera-feedback-mysql-[a-f0-9]{32}$/', basename($root))
    || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('An isolated datadir and uncached config are required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=33487;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== $root) {
    throw new RuntimeException('Refusing writes: disposable datadir mismatch.');
}
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'ZATCA_PRODUCTION_ENABLED' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$suffix = bin2hex(random_bytes(6));
$recoveryDatabase = 'seera_feedback_recovery_'.$suffix;
$freshDatabase = 'seera_feedback_fresh_'.$suffix;
config(['database.default' => 'feedback_probe', 'database.connections.feedback_probe' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33487,
    'database' => $recoveryDatabase, 'username' => 'root', 'password' => '',
    'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
]]);
$pdo->exec('CREATE DATABASE '.$recoveryDatabase);
$results = [];
$check = function (string $name, bool $ok) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => $ok];
    if (! $ok) {
        throw new RuntimeException($name);
    }
};
$migration = fn (int $number) => require database_path('migrations/'.($number === 1
    ? '2026_11_10_000001_add_profile_photos_and_party_documents.php'
    : '2026_11_10_000002_create_user_permission_overrides.php'));
$parents = ['users', 'employees', 'customers', 'suppliers', 'permissions'];
foreach ($parents as $name) {
    Schema::create($name, function (Blueprint $table) {
        $table->engine = 'MyISAM';
        $table->id();
        $table->string('name');
    });
    DB::table($name)->insert(['id' => 1, 'name' => 'Retained synthetic record']);
}
try {
    (require $root.'/legacy-feedback-migration.php')->up();
    $check('Legacy migration reproduces mixed-engine FK failure', false);
} catch (QueryException $exception) {
    $check('Legacy migration reproduces mixed-engine FK failure', true);
}
$check('Failure leaves both photo columns and a partial customer table', Schema::hasColumn('users', 'profile_photo_path') && Schema::hasColumn('employees', 'profile_photo_path') && Schema::hasTable('customer_documents') && count(Schema::getForeignKeys('customer_documents')) === 0);
DB::table('users')->where('id', 1)->update(['profile_photo_path' => 'private/retained.jpg']);
DB::table('customer_documents')->insert(['id' => 7, 'customer_id' => 1, 'document_type' => 'cr', 'original_filename' => 'training.pdf', 'path' => 'private/retained.pdf', 'mime_type' => 'application/pdf', 'size' => 50, 'uploaded_by' => 1]);
foreach ([1, 2] as $number) {
    try {
        $migration($number)->up();
        $check('Migration '.$number.' refuses MyISAM without deleting partial data', false);
    } catch (RuntimeException $exception) {
        $check('Migration '.$number.' refuses MyISAM without deleting partial data', str_contains($exception->getMessage(), 'require InnoDB') && DB::table('customer_documents')->where('id', 7)->exists() && ! Schema::hasTable('user_permission_overrides'));
    }
}

// Conversion is performed only on this attested disposable instance, never by the migrations.
foreach ($parents as $name) {
    DB::statement('ALTER TABLE `'.$name.'` ENGINE=InnoDB');
}
$migration(1)->up();
$migration(2)->up();
$check('Partial recovery restores all seven foreign keys', count(Schema::getForeignKeys('customer_documents')) === 2 && count(Schema::getForeignKeys('supplier_documents')) === 2 && count(Schema::getForeignKeys('user_permission_overrides')) === 3);
$check('Existing photo, document IDs and all parent rows survive', DB::table('users')->where('profile_photo_path', 'private/retained.jpg')->exists() && DB::table('customer_documents')->where('id', 7)->where('path', 'private/retained.pdf')->exists() && collect($parents)->every(fn ($table) => DB::table($table)->where('id', 1)->where('name', 'Retained synthetic record')->exists()));
DB::table('user_permission_overrides')->insert(['id' => 4, 'user_id' => 1, 'permission_id' => 1, 'effect' => 'deny', 'reason' => 'Preserved reviewed exception', 'changed_by' => 1]);
$migration(1)->up();
$migration(2)->up();
$check('Repeated DDL preserves the unique existing Deny exception', DB::table('user_permission_overrides')->where('id', 4)->where('effect', 'deny')->count() === 1 && Schema::hasIndex('user_permission_overrides', ['user_id', 'permission_id'], 'unique'));
try {
    DB::table('customers')->where('id', 1)->delete();
    $check('Recovered customer foreign key restricts parent deletion', false);
} catch (QueryException $exception) {
    $check('Recovered customer foreign key restricts parent deletion', DB::table('customers')->where('id', 1)->exists());
}
try {
    DB::transaction(function () {
        DB::table('users')->where('id', 1)->update(['name' => 'Must roll back']);
        DB::table('user_permission_overrides')->where('id', 4)->delete();
        throw new RuntimeException('Synthetic rollback');
    });
} catch (RuntimeException $exception) {
    $check('Converted synthetic tables support rollback', DB::table('users')->where('name', 'Retained synthetic record')->exists() && DB::table('user_permission_overrides')->where('id', 4)->exists());
}

$pdo->exec('CREATE DATABASE '.$freshDatabase);
config(['database.connections.feedback_probe.database' => $freshDatabase]);
DB::purge('feedback_probe');
DB::statement('SET SESSION default_storage_engine=MyISAM');
foreach ($parents as $name) {
    Schema::create($name, function (Blueprint $table) {
        $table->engine = 'InnoDB';
        $table->id();
    });
}
$migration(1)->up();
$migration(2)->up();
$check('Fresh installation explicitly uses InnoDB despite a MyISAM default', collect(Schema::getTables())->every(fn ($table) => $table['engine'] === 'InnoDB') && count(Schema::getForeignKeys('user_permission_overrides')) === 3);
echo json_encode(['synthetic' => true, 'production_access' => false, 'results' => $results, 'passed' => count($results)], JSON_PRETTY_PRINT).PHP_EOL;
