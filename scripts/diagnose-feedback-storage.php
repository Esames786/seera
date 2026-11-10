<?php

use Illuminate\Contracts\Console\Kernel;

// Read-only CLI schema inventory. Does not convert engines, migrate, seed or scan business rows.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
if (! in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('This read-only diagnostic requires MySQL/MariaDB.');
}
$tables = $db->select('SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS estimated_rows, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME', ['BASE TABLE']);
$schema = $db->getSchemaBuilder();
$newTables = [];
foreach (['customer_documents', 'supplier_documents', 'user_permission_overrides'] as $name) {
    $exists = $schema->hasTable($name);
    $newTables[$name] = ['exists' => $exists, 'foreign_keys' => $exists ? $schema->getForeignKeys($name) : []];
}
echo json_encode([
    'read_only' => true,
    'database' => $db->getDatabaseName(),
    'queue' => config('queue.default'),
    'maintenance' => $app->isDownForMaintenance(),
    'production_zatca_enabled' => (bool) config('zatca.production_enabled'),
    'table_row_counts_are_estimates' => true,
    'tables' => $tables,
    'feedback_tables' => $newTables,
    'completed_feedback_migrations' => $db->table('migrations')->where('migration', 'like', '2026_11_10_%')->pluck('migration')->all(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
