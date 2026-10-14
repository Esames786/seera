<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline Mobile Sync, Phase 1 (GPS attendance + site expenses). Additive and
 * re-runnable:
 *  - offline_sync_receipts: one durable row per client-generated UUID. It is
 *    the idempotency authority: a replay returns the stored outcome, a
 *    conflicting payload under the same UUID is refused, and the row links to
 *    the business record it created. No sensitive payload is stored, only a
 *    hash and a small response snapshot.
 *  - attendance_records: the client capture time per step (kept apart from
 *    the server time in *_recorded_at) and the UUID of the queued action.
 *  - site_expenses: the UUID and capture time of an expense created by sync.
 * Nothing is seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offline_sync_receipts')) {
            Schema::create('offline_sync_receipts', function (Blueprint $table) {
                $table->id();
                $table->char('client_uuid', 36);
                $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('operation_type', 32);
                $table->string('action', 32);
                $table->char('payload_hash', 64);
                $table->string('business_record_type', 40)->nullable();
                $table->unsignedBigInteger('business_record_id')->nullable();
                $table->string('status', 24)->default('received');
                $table->string('error_code', 40)->nullable();
                $table->string('error_reason', 60)->nullable();
                $table->string('error_message', 500)->nullable();
                $table->dateTime('captured_at_client')->nullable();
                $table->dateTime('first_received_at');
                $table->dateTime('last_attempt_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->json('response')->nullable();
                $table->timestamps();

                $table->unique('client_uuid', 'osr_client_uuid_unique');
                $table->index(['user_id', 'operation_type', 'status'], 'osr_user_type_status_idx');
                $table->index(['business_record_type', 'business_record_id'], 'osr_record_idx');
            });
        }

        Schema::table('attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_records', 'check_in_captured_at')) {
                $table->dateTime('check_in_captured_at')->nullable()->after('check_in_recorded_at');
            }
            if (! Schema::hasColumn('attendance_records', 'check_in_client_uuid')) {
                $table->char('check_in_client_uuid', 36)->nullable()->after('check_in_captured_at');
            }
            if (! Schema::hasColumn('attendance_records', 'check_out_captured_at')) {
                $table->dateTime('check_out_captured_at')->nullable()->after('check_out_recorded_at');
            }
            if (! Schema::hasColumn('attendance_records', 'check_out_client_uuid')) {
                $table->char('check_out_client_uuid', 36)->nullable()->after('check_out_captured_at');
            }
        });

        Schema::table('site_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('site_expenses', 'sync_client_uuid')) {
                $table->char('sync_client_uuid', 36)->nullable()->after('posting_error');
                $table->unique('sync_client_uuid', 'se_sync_uuid_unique');
            }
            if (! Schema::hasColumn('site_expenses', 'captured_at_client')) {
                $table->dateTime('captured_at_client')->nullable()->after('sync_client_uuid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_expenses', function (Blueprint $table) {
            if (Schema::hasColumn('site_expenses', 'sync_client_uuid')) {
                $table->dropUnique('se_sync_uuid_unique');
                $table->dropColumn('sync_client_uuid');
            }
            if (Schema::hasColumn('site_expenses', 'captured_at_client')) {
                $table->dropColumn('captured_at_client');
            }
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (['check_in_captured_at', 'check_in_client_uuid', 'check_out_captured_at', 'check_out_client_uuid'] as $column) {
                if (Schema::hasColumn('attendance_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('offline_sync_receipts');
    }
};
