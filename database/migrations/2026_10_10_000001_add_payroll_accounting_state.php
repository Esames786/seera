<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll → GL, Phase 1. Additive and re-runnable:
 *  - payroll_runs: accounting state kept apart from the HR status, the link to
 *    the one original journal, the posting error for Finance, and the explicit
 *    reversal journal.
 *  - automatic_posting_rules: an optional deduction liability account, so the
 *    Payroll rule can map deductions explicitly instead of guessing.
 * Nothing is seeded; Finance configures the accounts on the posting rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'accounting_status')) {
                $table->string('accounting_status', 32)->default('not_posted')->after('status');
            }
            if (! Schema::hasColumn('payroll_runs', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')->nullable()->after('accounting_status')->constrained('journal_entries')->restrictOnDelete();
            }
            if (! Schema::hasColumn('payroll_runs', 'posted_at')) {
                $table->dateTime('posted_at')->nullable()->after('journal_entry_id');
            }
            if (! Schema::hasColumn('payroll_runs', 'posting_error')) {
                $table->text('posting_error')->nullable()->after('posted_at');
            }
            if (! Schema::hasColumn('payroll_runs', 'reversal_journal_id')) {
                $table->foreignId('reversal_journal_id')->nullable()->after('posting_error')->constrained('journal_entries')->restrictOnDelete();
            }
            if (! Schema::hasColumn('payroll_runs', 'reversed_at')) {
                $table->dateTime('reversed_at')->nullable()->after('reversal_journal_id');
            }
            if (! Schema::hasColumn('payroll_runs', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('reversed_at');
            }
        });

        Schema::table('automatic_posting_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('automatic_posting_rules', 'deduction_account_id')) {
                $table->foreignId('deduction_account_id')->nullable()->after('credit_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('automatic_posting_rules', function (Blueprint $table) {
            if (Schema::hasColumn('automatic_posting_rules', 'deduction_account_id')) {
                $table->dropConstrainedForeignId('deduction_account_id');
            }
        });
        Schema::table('payroll_runs', function (Blueprint $table) {
            foreach (['reversal_journal_id', 'journal_entry_id'] as $column) {
                if (Schema::hasColumn('payroll_runs', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['reversal_reason', 'reversed_at', 'posting_error', 'posted_at', 'accounting_status'] as $column) {
                if (Schema::hasColumn('payroll_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
