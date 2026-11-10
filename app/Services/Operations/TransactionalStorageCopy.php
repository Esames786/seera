<?php

namespace App\Services\Operations;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Build a verified InnoDB copy in a DIFFERENT empty database. Source receives SELECTs only. */
class TransactionalStorageCopy
{
    private const TARGET_CONNECTION = 'seera_storage_target';

    public function inspect(string $database): array
    {
        [$source, $target] = $this->connections($database);
        $tables = $source->getSchemaBuilder()->getTables($source->getDatabaseName());

        return [
            'dry_run' => true, 'source_database' => $source->getDatabaseName(),
            'target_database' => $target->getDatabaseName(), 'target_empty' => true,
            'source_tables' => array_map(fn ($table) => ['name' => $table['name'], 'engine' => $table['engine'], 'estimated_bytes' => $table['size']], $tables),
            'source_writes' => false, 'automatic_promotion' => false,
        ];
    }

    /** Caller verifies the backup and stopped writers. A failed copy is NEVER promoted. */
    public function copy(string $database): array
    {
        if (app()->environment('production') && ! app()->isDownForMaintenance()) {
            throw new RuntimeException('Production source must be in maintenance before preparing a copy.');
        }
        [$source, $target] = $this->connections($database);
        $sourceName = $source->getName();
        $tables = array_column($source->getSchemaBuilder()->getTables($source->getDatabaseName()), 'name');
        if (! in_array('migrations', $tables, true) || ! in_array('users', $tables, true)) {
            throw new RuntimeException('Source is not a recognized Seera database.');
        }
        $migrationColumns = $source->getSchemaBuilder()->getColumnListing('migrations');
        $sourceMigrations = $this->digest($source, 'migrations', $migrationColumns);
        $columns = [];
        $before = [];
        foreach ($tables as $table) {
            if ($table === 'migrations') {
                continue; // The destination must keep the newly generated migration ledger.
            }
            $columns[$table] = $source->getSchemaBuilder()->getColumnListing($table);
            $before[$table] = $this->digest($source, $table, $columns[$table]);
        }

        $report = ['source_database' => $source->getDatabaseName(), 'target_database' => $database,
            'source_writes' => false, 'automatic_promotion' => false, 'ready_for_operator_review' => false];
        $lock = 'seera_storage_copy_'.sha1($database);
        if ((int) $target->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired !== 1) {
            throw new RuntimeException('Another preparation is using this destination.');
        }
        try {
            if ($target->getSchemaBuilder()->getTables($database) || $target->getSchemaBuilder()->getViews($database)) {
                throw new RuntimeException('Destination became nonempty; existing destination data is never overwritten.');
            }
            if (Artisan::call('migrate', ['--database' => self::TARGET_CONNECTION, '--force' => true]) !== 0) {
                throw new RuntimeException('Destination migrations failed. Source was not changed. Inspect the new destination separately.');
            }
            foreach ($columns as $table => $names) {
                if (! $target->getSchemaBuilder()->hasTable($table)
                    || array_diff($names, $target->getSchemaBuilder()->getColumnListing($table))) {
                    throw new RuntimeException('Destination cannot preserve every source field in '.$table.'. No source row was changed.');
                }
            }

            // Only this explicit, separate target connection is affected. Unique checks remain ON.
            $target->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
            foreach ($columns as $table => $names) {
                $this->assertDestination($source, $target, $database);
                $target->table($table)->truncate();
                $batch = [];
                foreach ($source->table($table)->select($names)->cursor() as $row) {
                    $batch[] = (array) $row;
                    if (count($batch) === 200) {
                        $target->table($table)->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch) {
                    $target->table($table)->insert($batch);
                }
                $nextId = $source->selectOne('SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->next_id ?? null;
                if ($nextId !== null && ctype_digit((string) $nextId)) {
                    $target->statement('ALTER TABLE '.$target->getQueryGrammar()->wrapTable($table).' AUTO_INCREMENT = '.(string) $nextId);
                }
            }
            $target->statement('SET SESSION FOREIGN_KEY_CHECKS=1');

            $checks = [];
            if ($sourceMigrations !== $this->digest($source, 'migrations', $migrationColumns)) {
                throw new RuntimeException('Source migration ledger changed during preparation. Destination is not ready.');
            }
            foreach ($columns as $table => $names) {
                $currentSource = $this->digest($source, $table, $names);
                $destination = $this->digest($target, $table, $names, $this->orderColumns($source, $table, $names));
                if ($before[$table] !== $currentSource || $before[$table] !== $destination) {
                    throw new RuntimeException('Row digest mismatch/source changed during copy: '.$table.'. Destination is not ready; source was not modified by this operation.');
                }
                $checks[$table] = $destination;
            }
            $foreignKeys = 0;
            foreach ($target->getSchemaBuilder()->getTables($database) as $metadata) {
                $table = $metadata['name'];
                if (strcasecmp($metadata['engine'] ?? '', 'InnoDB') !== 0) {
                    throw new RuntimeException('Destination table is not InnoDB: '.$table);
                }
                foreach ($target->getSchemaBuilder()->getForeignKeys($table) as $key) {
                    if (($key['foreign_schema'] ?? $database) !== $database) {
                        throw new RuntimeException('Unexpected cross-database foreign key: '.$table);
                    }
                    $query = $target->table($table.' as child')->leftJoin($key['foreign_table'].' as parent', function ($join) use ($key) {
                        foreach ($key['columns'] as $i => $column) {
                            $join->on('child.'.$column, '=', 'parent.'.$key['foreign_columns'][$i]);
                        }
                    });
                    foreach ($key['columns'] as $column) {
                        $query->whereNotNull('child.'.$column);
                    }
                    if ($query->whereNull('parent.'.$key['foreign_columns'][0])->exists()) {
                        throw new RuntimeException('Orphan reference in '.$table.'.'.$key['name'].'. No source data was deleted.');
                    }
                    $foreignKeys++;
                }
            }

            return array_merge($report, ['copied_tables' => $checks, 'verified_foreign_keys' => $foreignKeys,
                'migration_ledger' => 'New destination ledger retained; historical source ledger untouched',
                'source_migration_ledger_unchanged' => true,
                'all_source_fields_preserved' => true, 'source_unchanged' => true, 'ready_for_operator_review' => true]);
        } finally {
            try {
                $target->statement('SET SESSION FOREIGN_KEY_CHECKS=1');
            } finally {
                DB::setDefaultConnection($sourceName);
                $target->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            }
        }
    }

    private function connections(string $database): array
    {
        $source = DB::connection();
        if (! in_array($source->getDriverName(), ['mysql', 'mariadb'], true)
            || ! preg_match('/^[a-zA-Z0-9_]+$/D', $database)
            || strcasecmp($database, $source->getDatabaseName()) === 0
            || $source->getTablePrefix() !== '' || $source->getConfig('read') || $source->getConfig('write')) {
            throw new RuntimeException('Use MySQL/MariaDB with no table prefix and a DIFFERENT explicitly named empty database.');
        }
        $config = $source->getConfig();
        unset($config['url'], $config['name'], $config['read'], $config['write'], $config['sticky']);
        $config['database'] = $database;
        $config['engine'] = 'InnoDB';
        config(['database.connections.'.self::TARGET_CONNECTION => $config]);
        DB::purge(self::TARGET_CONNECTION);
        $target = DB::connection(self::TARGET_CONNECTION);
        $this->assertDestination($source, $target, $database);
        if ($target->getSchemaBuilder()->getTables($database) || $target->getSchemaBuilder()->getViews($database)) {
            throw new RuntimeException('Destination must be completely empty. Existing destination data is never overwritten.');
        }

        return [$source, $target];
    }

    private function assertDestination(Connection $source, Connection $target, string $expected): void
    {
        $actual = $target->selectOne('SELECT DATABASE() AS name')->name;
        if ($actual !== $expected || strcasecmp($actual, $source->getDatabaseName()) === 0) {
            throw new RuntimeException('Destination identity mismatch. Refusing any destination DDL.');
        }
    }

    private function orderColumns(Connection $connection, string $table, array $columns): array
    {
        foreach ($connection->getSchemaBuilder()->getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        return $columns;
    }

    private function digest(Connection $connection, string $table, array $columns, ?array $order = null): array
    {
        $query = $connection->table($table)->select($columns);
        foreach ($order ?? $this->orderColumns($connection, $table, $columns) as $column) {
            $query->orderBy($column);
        }
        $hash = hash_init('sha256');
        $rows = 0;
        foreach ($query->cursor() as $row) {
            hash_update($hash, serialize((array) $row));
            $rows++;
        }

        return ['rows' => $rows, 'sha256' => hash_final($hash)];
    }
}
