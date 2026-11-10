<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Resume additive feedback DDL without dropping records or weakening foreign keys. */
final class FeedbackMigrationSchema
{
    public static function assertTransactionalTables(array $required, array $optional = []): void
    {
        $connection = Schema::getConnection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $invalid = [];
        foreach (array_unique(array_merge($required, $optional)) as $table) {
            $metadata = $connection->selectOne(
                'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$connection->getTablePrefix().$table]
            );
            if (! $metadata && ! in_array($table, $required, true)) {
                continue;
            }
            if (! $metadata || strcasecmp($metadata->engine ?? '', 'InnoDB') !== 0) {
                $invalid[] = $table.'='.($metadata->engine ?? 'MISSING');
            }
        }
        if ($invalid) {
            throw new RuntimeException('STOP: feedback migrations require InnoDB tables: '.implode(', ', $invalid).'. No engine conversion was performed. Review a backed-up storage-engine conversion before retrying.');
        }
    }

    public static function requireColumns(string $table, array $columns): void
    {
        $missing = array_diff($columns, Schema::getColumnListing($table));
        if ($missing) {
            throw new RuntimeException('STOP: existing '.$table.' is missing columns: '.implode(', ', $missing).'. Existing data was not dropped; review this schema before retrying.');
        }
    }

    public static function ensureForeignKey(string $table, string $column, string $parent, string $delete): void
    {
        foreach (Schema::getForeignKeys($table) as $key) {
            if ($key['columns'] !== [$column]) {
                continue;
            }
            if ($key['foreign_table'] !== Schema::getConnection()->getTablePrefix().$parent
                || $key['foreign_columns'] !== ['id'] || $key['on_delete'] !== $delete) {
                throw new RuntimeException('STOP: unexpected foreign key on '.$table.'.'.$column.'. Existing constraint was not replaced.');
            }

            return;
        }

        $orphans = Schema::getConnection()->table($table.' as child')
            ->leftJoin($parent.' as parent', 'child.'.$column, '=', 'parent.id')
            ->whereNotNull('child.'.$column)->whereNull('parent.id')->exists();
        if ($orphans) {
            throw new RuntimeException('STOP: orphan references on '.$table.'.'.$column.'. Existing rows were not deleted.');
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $parent, $delete) {
            $blueprint->foreign($column)->references('id')->on($parent)->onDelete($delete);
        });
    }
}
