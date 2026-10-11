<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll Phase 2 — salary settlement + bank export foundation. Additive and
 * re-runnable: a settlement row links the payroll run to its payment journal
 * (Dr payroll payable, Cr bank / cash), a bank export row freezes the rows of
 * a generated transfer file, and the run carries its payment state. Existing
 * runs keep their figures and read "Not paid".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_settlements')) {
            Schema::create('payroll_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
                $table->date('payment_date');
                $table->foreignId('payment_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->string('payment_method', 32)->default('Bank Transfer');
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('reference_number', 100)->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->foreignId('reversal_journal_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_reason', 500)->nullable();
                $table->string('idempotency_key', 64)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique('idempotency_key', 'payroll_settlements_idem_unique');
            });
        }

        if (! Schema::hasTable('payroll_bank_exports')) {
            Schema::create('payroll_bank_exports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
                $table->unsignedSmallInteger('sequence')->default(1);
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('excluded_count')->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('file_name', 120);
                $table->string('file_hash', 64);
                $table->json('rows');
                $table->timestamps();
                $table->unique(['payroll_run_id', 'sequence'], 'payroll_bank_exports_run_seq_unique');
            });
        }

        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'payment_status')) {
                $table->string('payment_status', 16)->default('not_paid')->after('reversal_reason');
            }
            if (! Schema::hasColumn('payroll_runs', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_status');
            }
            if (! Schema::hasColumn('payroll_runs', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('paid_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            foreach (['settled_at', 'paid_amount', 'payment_status'] as $column) {
                if (Schema::hasColumn('payroll_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('payroll_bank_exports');
        Schema::dropIfExists('payroll_settlements');
    }
};
