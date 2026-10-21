<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer credit / debit notes (AR correction documents, ZATCA 381 / 383).
 * Additive, re-runnable, data preserving:
 *  - customer_adjustment_notes: one business note per row, always tied to the
 *    original posted customer invoice; its own number, lines, totals, reason,
 *    frozen seller / buyer snapshots, posting link and ZATCA mirror columns
 *    (same names as on customer_invoices so the e-invoice services treat both
 *    documents alike).
 *  - customer_adjustment_note_lines: the corrected lines, each pointing at the
 *    original invoice line it corrects (a debit note may add a new charge).
 *  - zatca_invoice_records: an optional note foreign key and a document type.
 *    `customer_invoice_id` stays the original invoice for a note record, so
 *    scoping, the register and the customer panels keep working unchanged.
 * No seeder. No permission rows: notes reuse the Accounts Receivable and
 * ZATCA Invoicing action catalogues.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_adjustment_notes')) {
            Schema::create('customer_adjustment_notes', function (Blueprint $table) {
                $table->id();
                $table->string('note_type', 8); // credit | debit
                $table->string('note_number', 100)->unique();
                $table->foreignId('customer_invoice_id')->constrained('customer_invoices')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
                $table->string('invoice_type', 16)->default('standard'); // inherited from the original invoice
                $table->date('note_date');
                $table->string('issue_time', 8)->nullable();
                $table->date('supply_date')->nullable(); // the original supply date (KSA-5 for notes)
                $table->string('reason_code', 32);
                $table->text('reason');
                $table->string('status', 16)->default('draft'); // draft | posted
                $table->decimal('taxable_amount', 15, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('applied_amount', 15, 2)->default(0); // the part that changed the original invoice balance
                $table->decimal('unapplied_amount', 15, 2)->default(0); // credit beyond the open balance: customer credit awaiting Finance
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->json('seller_snapshot')->nullable();
                $table->json('buyer_snapshot')->nullable();
                $table->string('zatca_status', 32)->default('not_generated');
                $table->dateTime('zatca_locked_at')->nullable();
                $table->dateTime('posted_at')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['customer_invoice_id', 'status'], 'can_invoice_status_idx');
                $table->index(['customer_id', 'note_type'], 'can_customer_type_idx');
                $table->index(['project_id', 'note_date'], 'can_project_date_idx');
                $table->index('status', 'can_status_idx');
            });
        }

        if (! Schema::hasTable('customer_adjustment_note_lines')) {
            Schema::create('customer_adjustment_note_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_adjustment_note_id')->constrained('customer_adjustment_notes', indexName: 'canl_note_fk')->cascadeOnDelete(); // explicit short names: MySQL limits identifiers to 64 characters
                $table->foreignId('customer_invoice_line_id')->nullable()->constrained('customer_invoice_lines', indexName: 'canl_invoice_line_fk')->restrictOnDelete();
                $table->string('description');
                $table->decimal('quantity', 15, 2)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('taxable_amount', 15, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->foreignId('revenue_account_id')->nullable()->constrained('chart_of_accounts', indexName: 'canl_revenue_fk')->nullOnDelete();
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers', indexName: 'canl_cost_center_fk')->nullOnDelete();
                $table->timestamps();

                $table->index('customer_invoice_line_id', 'canl_invoice_line_idx');
            });
        }

        Schema::table('zatca_invoice_records', function (Blueprint $table) {
            if (! Schema::hasColumn('zatca_invoice_records', 'customer_adjustment_note_id')) {
                $table->foreignId('customer_adjustment_note_id')->nullable()->after('customer_invoice_id')
                    ->constrained('customer_adjustment_notes', indexName: 'zir_note_fk')->restrictOnDelete();
            }
            if (! Schema::hasColumn('zatca_invoice_records', 'document_type')) {
                $table->string('document_type', 16)->default('invoice')->after('customer_adjustment_note_id'); // invoice | credit_note | debit_note
            }
        });
    }

    public function down(): void
    {
        // Additive release: the note tables and columns stay in place (they hold issued correction documents).
    }
};
