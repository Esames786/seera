<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance correctness sprint F02: every payment / receipt form carries a
 * one-time key, and the database refuses a second row with the same key. A
 * double click, a refresh, a browser retry or a replayed request therefore
 * cannot record the same settlement, its journal, twice. Additive: existing
 * rows keep a null key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('journal_entry_id');
        });

        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
