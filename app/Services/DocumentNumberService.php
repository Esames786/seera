<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $key, string $prefix, string $table, string $column, int $padding = 4): string
    {
        return DB::transaction(function () use ($key, $prefix, $table, $column, $padding) {
            DB::table('document_sequences')->insertOrIgnore([
                'key' => $key,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();
            $lastValue = (int) $sequence->last_value;

            if ($lastValue === 0) {
                $latest = DB::table($table)->where($column, 'like', $prefix.'%')->orderByDesc($column)->value($column);
                $lastValue = $latest ? (int) substr((string) $latest, strlen($prefix)) : 0;
            }

            $nextValue = $lastValue + 1;
            DB::table('document_sequences')->where('key', $key)->update([
                'last_value' => $nextValue,
                'updated_at' => now(),
            ]);

            return $prefix.str_pad((string) $nextValue, $padding, '0', STR_PAD_LEFT);
        });
    }
}
