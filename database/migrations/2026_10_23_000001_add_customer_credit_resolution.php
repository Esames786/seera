<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer credit resolution (Phase 1): what happens to the unapplied credit
 * of a posted customer credit note. Additive, re-runnable, data preserving:
 *  - customer_credit_allocations: credit applied to another open invoice of
 *    the same customer (AR sub-ledger only, no journal); reversed rows stay.
 *  - customer_credit_refunds: credit refunded from a bank / cash account with
 *    its own journal (Dr Accounts Receivable, Cr bank / cash) and an optional
 *    reversal journal; reversed rows stay.
 * Explicit short constraint names (MySQL limits identifiers to 64 characters).
 * No seeder. No permission rows: Accounts Receivable process / approve are reused.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_credit_allocations')) {
            Schema::create('customer_credit_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_adjustment_note_id')->constrained('customer_adjustment_notes', indexName: 'cca_note_fk')->restrictOnDelete();
                $table->foreignId('customer_invoice_id')->constrained('customer_invoices', indexName: 'cca_invoice_fk')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('customers', indexName: 'cca_customer_fk')->restrictOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('allocation_date');
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 64)->nullable()->unique('cca_idempotency_uq');
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'cca_created_by_fk')->nullOnDelete();
                $table->dateTime('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users', indexName: 'cca_reversed_by_fk')->nullOnDelete();
                $table->string('reversal_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['customer_adjustment_note_id', 'reversed_at'], 'cca_note_active_idx');
                $table->index(['customer_invoice_id', 'reversed_at'], 'cca_invoice_active_idx');
                $table->index('customer_id', 'cca_customer_idx');
            });
        }

        if (! Schema::hasTable('customer_credit_refunds')) {
            Schema::create('customer_credit_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_adjustment_note_id')->constrained('customer_adjustment_notes', indexName: 'ccr_note_fk')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('customers', indexName: 'ccr_customer_fk')->restrictOnDelete();
                $table->date('payment_date');
                $table->foreignId('payment_account_id')->constrained('chart_of_accounts', indexName: 'ccr_account_fk')->restrictOnDelete();
                $table->string('payment_method', 32)->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 64)->nullable()->unique('ccr_idempotency_uq');
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries', indexName: 'ccr_journal_fk')->restrictOnDelete();
                $table->foreignId('reversal_journal_id')->nullable()->constrained('journal_entries', indexName: 'ccr_reversal_journal_fk')->restrictOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'ccr_created_by_fk')->nullOnDelete();
                $table->dateTime('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users', indexName: 'ccr_reversed_by_fk')->nullOnDelete();
                $table->string('reversal_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['customer_adjustment_note_id', 'reversed_at'], 'ccr_note_active_idx');
                $table->index('customer_id', 'ccr_customer_idx');
            });
        }
    }

    public function down(): void
    {
        // Additive release: resolution rows are financial history and stay in place.
    }
};
