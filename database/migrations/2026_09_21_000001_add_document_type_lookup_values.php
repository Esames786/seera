<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Document Type becomes an extensible catalogue (client feedback FR-02): the six
 * standard names are seeded so nothing changes on screen, and any type already
 * stored on an employee document is carried over so no existing value is lost.
 */
return new class extends Migration
{
    private const STANDARD = ['IQAMA', 'Passport', 'Contract', 'Medical Insurance', 'Driving License', 'Other'];

    public function up(): void
    {
        $existing = DB::table('lookup_values')->where('type', 'document_type')->pluck('value')->all();

        $stored = DB::table('employee_documents')
            ->whereNotNull('document_type')
            ->where('document_type', '!=', '')
            ->distinct()
            ->pluck('document_type')
            ->all();

        $sort = 10;
        $rows = [];

        foreach (self::STANDARD as $value) {
            if (! in_array($value, $existing, true)) {
                $rows[] = ['type' => 'document_type', 'value' => $value, 'sort_order' => $sort, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
            }
            $sort += 10;
        }

        foreach ($stored as $value) {
            if (! in_array($value, self::STANDARD, true) && ! in_array($value, $existing, true)) {
                $rows[] = ['type' => 'document_type', 'value' => $value, 'sort_order' => 100, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
            }
        }

        if ($rows !== []) {
            DB::table('lookup_values')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('lookup_values')->where('type', 'document_type')->delete();
    }
};
