<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates the next free code for master records created inline.
 *
 * The full master forms still ask for a code, but the quick-create dialogs
 * that open from a dropdown only ask for the fields a user actually knows at
 * that moment. When the code is left blank the controller fills it in here.
 */
class CodeGenerator
{
    /**
     * Sequential code such as CUS-001, CUS-002 for a table/column/prefix.
     */
    public static function sequential(string $table, string $column, string $prefix, int $pad = 3): string
    {
        $max = DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->pluck($column)
            ->map(fn ($code) => (int) preg_replace('/\D/', '', Str::after($code, $prefix)))
            ->max() ?? 0;

        do {
            $max++;
            $candidate = $prefix.str_pad((string) $max, $pad, '0', STR_PAD_LEFT);
        } while (DB::table($table)->where($column, $candidate)->exists());

        return $candidate;
    }

    /**
     * Short uppercase abbreviation derived from a name (Human Resource => HUMA),
     * with a numeric suffix if that abbreviation is already taken.
     */
    public static function fromName(string $table, string $column, string $name, int $length = 4): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, $length));
        $base = $base !== '' ? $base : 'CODE';

        $candidate = $base;
        $suffix = 1;
        while (DB::table($table)->where($column, $candidate)->exists()) {
            $suffix++;
            $candidate = $base.$suffix;
        }

        return $candidate;
    }

    /**
     * Role codes are stable identifiers, so they are derived once from the
     * name (Purchase Assistant => PURCHASE_ASSISTANT) and never rewritten.
     */
    public static function roleCode(string $name): string
    {
        $code = Str::upper(Str::snake(Str::ascii(trim($name))));
        $code = preg_replace('/[^A-Z0-9_]+/', '_', $code);
        $code = trim(preg_replace('/_+/', '_', $code), '_');

        return $code !== '' ? $code : 'ROLE';
    }
}
