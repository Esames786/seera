<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expense_categories', 'chart_of_account_id')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('site_expenses')) {
            Schema::create('site_expenses', function (Blueprint $table) {
                $table->id();
                $table->string('expense_number', 40)->unique('se_number_unique');
                $table->date('expense_date');
                $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('project_id')->constrained()->restrictOnDelete();
                $table->foreignId('site_id')->constrained()->restrictOnDelete();
                $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('payment_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
                $table->string('payment_type', 40);
                $table->text('description');
                $table->decimal('taxable_amount', 15, 2);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2);
                $table->string('reference_number')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 40)->default('draft')->index('se_status_idx');
                $table->boolean('accounting_posted')->default(false);
                $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('supplier_bill_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('settlement_journal_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->foreignId('settlement_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('reversal_journal_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->timestamp('settled_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('reversal_reason')->nullable();
                $table->text('posting_error')->nullable();
                $table->text('rejection_reason')->nullable();
                foreach (['submitted_at', 'approved_at', 'rejected_at', 'posted_at'] as $column) {
                    $table->timestamp($column)->nullable();
                }
                $table->timestamps();
                $table->index(['project_id', 'site_id', 'expense_date'], 'se_scope_date_idx');
            });
        }
        if (! Schema::hasTable('site_expense_receipts')) {
            Schema::create('site_expense_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_expense_id')->constrained()->restrictOnDelete();
                $table->string('original_filename');
                $table->string('path');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size');
                $table->string('document_type', 30)->default('receipt');
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('supplier_bills', 'site_expense_id')) {
            Schema::table('supplier_bills', function (Blueprint $table) {
                $table->foreignId('site_expense_id')->nullable()->constrained()->restrictOnDelete();
                $table->unique('site_expense_id', 'sb_site_expense_unique');
            });
        }
        // A shared liability, not one GL account per employee. Never overwrite a
        // production account or infer that an existing salary liability is suitable.
        DB::table('chart_of_accounts')->insertOrIgnore([
            'account_code' => '2310', 'account_name' => 'Employee Expense Reimbursement Payable',
            'account_type' => 'liability', 'normal_balance' => 'credit',
            'opening_balance' => 0, 'vat_applicable' => false, 'cost_center_required' => false,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['view', 'create', 'edit', 'delete', 'approve', 'reject', 'post', 'process', 'retry', 'mobile'] as $action) {
            DB::table('permissions')->insertOrIgnore([
                'module' => 'Site Expenses', 'module_group' => 'Operations', 'action' => $action,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Financial history must not be dropped by a routine code rollback.
        throw new RuntimeException('Site Expense migration is additive and forward-only. Restore a verified database backup for schema rollback.');
    }
};
