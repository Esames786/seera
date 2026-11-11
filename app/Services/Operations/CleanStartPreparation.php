<?php

namespace App\Services\Operations;

use App\Console\Commands\GoLiveReset;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Prepare a separate database; never delete or update the serving source. */
class CleanStartPreparation
{
    public const CONNECTION = 'seera_clean_start';

    private const RUNTIME = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'failed_jobs', 'job_batches', 'password_reset_tokens'];

    // These are retained master relationships/files, even though the old reset removes them.
    private const MASTER_RELATIONS = ['supplier_projects', 'equipment_documents'];

    public function inspect(string $database, array $keepIds, int $adminId, array $credentials = []): array
    {
        [$source, $target] = $this->connections($database, $credentials);
        $ids = array_values(array_unique(array_map('intval', $keepIds)));
        sort($ids);
        if (count($ids) !== 2 || min($ids) < 1 || ! in_array($adminId, $ids, true)) {
            throw new RuntimeException('Exactly two distinct positive User IDs are required, including the retained Admin ID.');
        }
        $users = User::on($source->getName())->whereIn('id', $ids)->get();
        if ($users->count() !== 2 || $users->contains(fn ($user) => $user->status !== 'active')) {
            throw new RuntimeException('Both retained login accounts must exist and be active.');
        }
        if (! $users->firstWhere('id', $adminId)->isSuperAdmin()) {
            throw new RuntimeException('The retained Admin must have an effective active Super Admin role. No role is granted by this tool.');
        }
        if (config('zatca.production_enabled')) {
            throw new RuntimeException('Production ZATCA must remain disabled.');
        }
        $schema = $source->getSchemaBuilder();
        $tables = array_column($schema->getTables($source->getDatabaseName()), 'name');
        $classified = array_unique(array_merge(GoLiveReset::PROTECTED, GoLiveReset::TRANSACTIONS, ...array_values(GoLiveReset::ENTRY_GROUPS)));
        if ($unknown = array_diff($tables, $classified)) {
            throw new RuntimeException('Unclassified source tables require review: '.implode(', ', $unknown));
        }
        foreach (['zatca_invoice_records', 'zatca_submissions'] as $table) {
            if (in_array($table, $tables, true) && $schema->hasColumn($table, 'environment')
                && $source->table($table)->where('environment', 'production')->exists()) {
                throw new RuntimeException('Production ZATCA history exists; clean start is refused.');
            }
        }
        if ($schema->hasTable('equipment_assets') && $source->table('equipment_assets')->whereNotNull('current_assignment_id')->exists()) {
            throw new RuntimeException('An asset has a current assignment. Review its operational state before discarding assignment history.');
        }
        if ($schema->hasColumn('approval_workflow_steps', 'approver_user_id')
            && $source->table('approval_workflow_steps')->whereNotNull('approver_user_id')->whereNotIn('approver_user_id', $ids)->exists()) {
            throw new RuntimeException('Retained master refers to a removed login; review approval_workflow_steps.approver_user_id before activation.');
        }
        $masters = array_values(array_intersect($tables, array_diff(array_unique(array_merge(
            GoLiveReset::PROTECTED, self::MASTER_RELATIONS, ...array_values(GoLiveReset::ENTRY_GROUPS)
        )), self::RUNTIME)));
        $counts = [];
        foreach ($masters as $table) {
            $counts[$table] = $this->rows($source, $table, $ids)->count();
        }
        $openings = [];
        foreach (['customers' => 'opening_receivable', 'suppliers' => 'opening_balance', 'chart_of_accounts' => 'opening_balance'] as $table => $column) {
            if (in_array($table, $masters, true) && $schema->hasColumn($table, $column)) {
                $openings[$table.'.'.$column] = $source->table($table)->where($column, '!=', 0)->count();
            }
        }

        return ['dry_run' => true, 'source_database' => $source->getDatabaseName(), 'target_database' => $target->getDatabaseName(),
            'source_writes' => false, 'automatic_promotion' => false, 'target_empty' => true,
            'retained_user_ids' => $ids, 'admin_user_id' => $adminId, 'retained_master_counts' => $counts,
            'nonzero_master_opening_balance_counts' => $openings,
            'not_copied' => array_values(array_diff($tables, $masters, ['migrations'])),
            'notes' => ['Existing passwords, permissions and master sequences are preserved.',
                'Removed-login references may be detached only in nullable fields, and are reported.',
                'An explicit workflow approver being removed requires review; it is never silently replaced.',
                'Business history is empty; master opening balances are preserved, not silently zeroed.',
                'No files are removed. The serving source DB remains intact; a separate SQL backup is not required by this clean-start command.']];
    }

    public function prepare(string $database, array $ids, int $adminId, array $credentials = []): array
    {
        if (app()->environment('production') && ! app()->isDownForMaintenance()) {
            throw new RuntimeException('The source application must be in maintenance during final preparation.');
        }
        $plan = $this->inspect($database, $ids, $adminId, $credentials);
        $source = DB::connection();
        $target = DB::connection(self::CONNECTION);
        $sourceConnection = DB::getDefaultConnection();
        $sourceSchema = $source->getSchemaBuilder();
        $tables = array_column($sourceSchema->getTables($source->getDatabaseName()), 'name');
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $this->digest($source->table($table), $sourceSchema->getColumnListing($table));
        }
        $lock = 'seera_clean_start_'.sha1($database);
        if ((int) $target->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired !== 1) {
            throw new RuntimeException('Another preparation holds this destination lock.');
        }
        try {
            $this->assertEmpty($target, $database);
            if (Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]) !== 0) {
                throw new RuntimeException('Destination migrations failed. Source is unchanged; do not activate the partial destination.');
            }
            // Framework migrations may temporarily switch the default connection.
            DB::setDefaultConnection($sourceConnection);
            $targetSchema = $target->getSchemaBuilder();
            $detached = [];
            $checks = [];
            $target->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
            foreach (array_keys($plan['retained_master_counts']) as $table) {
                $this->assertIdentity($source, $target, $database);
                $columns = $sourceSchema->getColumnListing($table);
                if (! $targetSchema->hasTable($table) || array_diff($columns, $targetSchema->getColumnListing($table))) {
                    throw new RuntimeException('Cannot preserve all master fields: '.$table.'. Source is unchanged.');
                }
                $keys = $targetSchema->getForeignKeys($table);
                $nullable = collect($targetSchema->getColumns($table))->keyBy('name')->map(fn ($column) => $column['nullable'])->all();
                $target->table($table)->truncate();
                $expected = [];
                foreach ($this->ordered($this->rows($source, $table, $plan['retained_user_ids']), $columns)->cursor() as $record) {
                    $row = (array) $record;
                    foreach ($keys as $key) {
                        if (count($key['columns']) !== 1) {
                            throw new RuntimeException('Composite master reference requires review: '.$table.'.'.$key['name']);
                        }
                        $column = $key['columns'][0];
                        if ($row[$column] === null || $key['foreign_table'] !== 'users'
                            || in_array((int) $row[$column], $plan['retained_user_ids'], true)) {
                            continue;
                        }
                        if (! ($nullable[$column] ?? false) || ($table === 'approval_workflow_steps' && $column === 'approver_user_id')) {
                            throw new RuntimeException('Retained master refers to a removed login; review '.$table.'.'.$column.' before activation.');
                        }
                        $row[$column] = null;
                        $detached[$table.'.'.$column] = ($detached[$table.'.'.$column] ?? 0) + 1;
                    }
                    $target->table($table)->insert($row);
                    $expected[] = $row;
                }
                // Hash comparison includes values and IDs; only the reported nullable detaches differ.
                $expectedHash = $this->arrayDigest($expected, $columns);
                $actual = $this->digest($target->table($table), $columns);
                if ($expectedHash !== $actual) {
                    throw new RuntimeException('Retained master verification failed: '.$table);
                }
                $checks[$table] = $actual;
                $next = $source->selectOne('SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->next_id ?? null;
                if ($next !== null && ctype_digit((string) $next)) {
                    $target->statement('ALTER TABLE '.$target->getQueryGrammar()->wrapTable($table).' AUTO_INCREMENT = '.(string) $next);
                }
            }
            // New migration defaults must never accidentally introduce business/demo documents.
            $empty = array_values(array_diff(array_unique(array_merge(GoLiveReset::TRANSACTIONS, self::RUNTIME)), self::MASTER_RELATIONS, ['migrations']));
            foreach ($empty as $table) {
                if ($targetSchema->hasTable($table) && $target->table($table)->exists()) {
                    throw new RuntimeException('Destination contains unexpected runtime/business data: '.$table);
                }
            }
            $target->statement('SET SESSION FOREIGN_KEY_CHECKS=1');
            $foreignKeys = 0;
            foreach ($targetSchema->getTables($database) as $metadata) {
                if (strcasecmp($metadata['engine'] ?? '', 'InnoDB') !== 0) {
                    throw new RuntimeException('Destination is not fully InnoDB: '.$metadata['name']);
                }
                foreach ($targetSchema->getForeignKeys($metadata['name']) as $key) {
                    if (($key['foreign_schema'] ?? $database) !== $database) {
                        throw new RuntimeException('Cross-database reference refused.');
                    }
                    $query = $target->table($metadata['name'].' as child')->leftJoin($key['foreign_table'].' as parent', function ($join) use ($key) {
                        foreach ($key['columns'] as $i => $column) {
                            $join->on('child.'.$column, '=', 'parent.'.$key['foreign_columns'][$i]);
                        }
                    });
                    foreach ($key['columns'] as $column) {
                        $query->whereNotNull('child.'.$column);
                    }
                    if ($query->whereNull('parent.'.$key['foreign_columns'][0])->exists()) {
                        throw new RuntimeException('Orphan reference in retained master '.$metadata['name'].'.'.$key['name']);
                    }
                    $foreignKeys++;
                }
            }
            foreach ($before as $table => $hash) {
                if ($hash !== $this->digest($source->table($table), $sourceSchema->getColumnListing($table))) {
                    throw new RuntimeException('Source changed during preparation: '.$table.'. Do not activate this destination.');
                }
            }
            if ($target->table('users')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all() !== $plan['retained_user_ids']) {
                throw new RuntimeException('Destination login set differs from the approved two IDs.');
            }

            return array_merge($plan, ['dry_run' => false, 'source_unchanged' => true, 'ready_for_operator_review' => true,
                'verified_master_hashes' => $checks, 'nullable_removed_login_references_detached' => $detached,
                'verified_foreign_keys' => $foreignKeys, 'all_destination_tables_innodb' => true,
                'business_tables_empty' => true, 'master_sequences_preserved' => true]);
        } finally {
            DB::setDefaultConnection($sourceConnection);
            try {
                $target->statement('SET SESSION FOREIGN_KEY_CHECKS=1');
            } finally {
                $target->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            }
        }
    }

    private function connections(string $database, array $credentials): array
    {
        $source = DB::connection();
        if (! in_array($source->getDriverName(), ['mysql', 'mariadb'], true) || ! preg_match('/^[a-zA-Z0-9_]+$/D', $database)
            || strcasecmp($database, $source->getDatabaseName()) === 0 || $source->getTablePrefix() !== ''
            || $source->getConfig('read') || $source->getConfig('write')) {
            throw new RuntimeException('Use a DIFFERENT empty MySQL database, no prefixes or split read/write connection.');
        }
        foreach (['VIEWS' => 'TABLE_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $table => $column) {
            if ((int) $source->selectOne("SELECT COUNT(*) AS n FROM information_schema.$table WHERE $column=DATABASE()")->n > 0) {
                throw new RuntimeException('Additional source SQL objects require review before clean start.');
            }
        }
        $config = $source->getConfig();
        unset($config['url'], $config['name'], $config['read'], $config['write'], $config['sticky']);
        $config['database'] = $database;
        $config['engine'] = 'InnoDB';
        foreach (['username', 'password'] as $key) {
            if (array_key_exists($key, $credentials)) {
                $config[$key] = $credentials[$key];
            }
        }
        config(['database.connections.'.self::CONNECTION => $config]);
        DB::purge(self::CONNECTION);
        $target = DB::connection(self::CONNECTION);
        $this->assertIdentity($source, $target, $database);
        $this->assertEmpty($target, $database);

        return [$source, $target];
    }

    private function assertIdentity(Connection $source, Connection $target, string $database): void
    {
        if ($target->selectOne('SELECT DATABASE() AS name')->name !== $database
            || strcasecmp($database, $source->getDatabaseName()) === 0) {
            throw new RuntimeException('Source/destination identity mismatch.');
        }
    }

    private function assertEmpty(Connection $target, string $database): void
    {
        if ($target->getSchemaBuilder()->getTables($database) || $target->getSchemaBuilder()->getViews($database)) {
            throw new RuntimeException('Destination must be completely empty. No overwrite or automatic cleanup is performed.');
        }
        foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $table => $column) {
            if ((int) $target->selectOne("SELECT COUNT(*) AS n FROM information_schema.$table WHERE $column=DATABASE()")->n > 0) {
                throw new RuntimeException('Destination contains SQL objects; refused.');
            }
        }
    }

    private function rows(Connection $source, string $table, array $ids)
    {
        $query = $source->table($table);
        if ($table === 'users') {
            $query->whereIn('id', $ids);
        } elseif (in_array($table, ['user_roles', 'user_permission_overrides'], true)) {
            $query->whereIn('user_id', $ids);
        }

        return $query;
    }

    private function ordered($query, array $columns)
    {
        foreach ($columns as $column) {
            $query->orderBy($column);
        }

        return $query->select($columns);
    }

    private function digest($query, array $columns): array
    {
        return $this->arrayDigest($this->ordered($query, $columns)->cursor(), $columns);
    }

    private function arrayDigest(iterable $rows, array $columns): array
    {
        $hashes = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $hashes[] = hash('sha256', serialize(array_map(fn ($key) => $row[$key], $columns)));
        }
        sort($hashes, SORT_STRING);

        return ['rows' => count($hashes), 'sha256' => hash('sha256', implode('', $hashes))];
    }
}
