<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['approval_mode', 'approval_status', 'requested_by', 'last_edited_by', 'rejection_reason', 'posting_error'] as $column) {
            if (! Schema::hasColumn('supplier_bills', $column)) {
                Schema::table('supplier_bills', function (Blueprint $table) use ($column) {
                    match ($column) {
                        'approval_mode' => $table->string($column, 20)->default('legacy'),
                        'approval_status' => $table->string($column, 30)->nullable()->index('sb_approval_status_idx'),
                        'requested_by', 'last_edited_by' => $table->foreignId($column)->nullable()->constrained('users')->restrictOnDelete(),
                        default => $table->text($column)->nullable(),
                    };
                });
            }
        }
        if (! Schema::hasColumn('supplier_bill_grn_matches', 'reserved_at')) {
            Schema::table('supplier_bill_grn_matches', fn (Blueprint $table) => $table->timestamp('reserved_at')->nullable());
        }
        foreach (['view', 'create', 'edit', 'approve', 'reject', 'post', 'retry', 'process'] as $action) {
            DB::table('permissions')->insertOrIgnore(['module' => 'Accounts Payable', 'module_group' => 'Finance', 'action' => $action, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Supplier Bill approval history is forward-only. Use an approved backup recovery plan, not automatic schema rollback.');
    }
};
