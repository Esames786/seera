<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F04: goods receipts accrue to "Goods Received Not Invoiced" (GRNI) and the
 * matched supplier bill clears that accrual, so a received purchase carries
 * one payable and one input VAT event. Additive and production-safe: the
 * GRNI account is created only when missing, nothing existing is rewritten,
 * and no posted journal is touched.
 */
return new class extends Migration
{
    public const GRNI_CODE = '2150';

    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')->nullable()->after('item_id')
                ->constrained('purchase_order_lines')->nullOnDelete();
            $table->decimal('invoiced_quantity', 15, 3)->default(0)->after('rejected_quantity');
        });

        Schema::create('supplier_bill_grn_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignId('supplier_bill_line_id')->constrained('supplier_bill_lines')->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained('goods_receipt_lines')->restrictOnDelete();
            $table->decimal('matched_quantity', 15, 3);
            $table->decimal('matched_taxable_amount', 15, 2);
            // Set when the bill is approved (the GRN line's invoiced quantity is consumed), cleared on reopen.
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_bill_line_id', 'goods_receipt_line_id'], 'bill_line_grn_line_unique');
            $table->index(['goods_receipt_line_id', 'committed_at']);
        });

        $this->ensureGrniAccount();
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bill_grn_matches');

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_line_id');
            $table->dropColumn('invoiced_quantity');
        });

        // The GRNI account is left in place: it may already carry postings.
    }

    /** Idempotent master data: add 2150 under Liabilities when the chart does not have it. */
    private function ensureGrniAccount(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        if (DB::table('chart_of_accounts')->where('account_code', self::GRNI_CODE)->exists()) {
            return;
        }

        $parentId = DB::table('chart_of_accounts')->where('account_code', '2000')->value('id');
        $now = now();

        $accountId = DB::table('chart_of_accounts')->insertGetId([
            'account_code' => self::GRNI_CODE,
            'account_name' => 'Goods Received Not Invoiced',
            'account_type' => 'liability',
            'parent_id' => $parentId,
            'opening_balance' => 0,
            'normal_balance' => 'credit',
            'vat_applicable' => false,
            'cost_center_required' => false,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // The "Inventory Purchase" posting rule now describes the GRNI accrual.
        if (Schema::hasTable('automatic_posting_rules')) {
            DB::table('automatic_posting_rules')
                ->where('source_module', 'Inventory')
                ->where('trigger_event', 'Inventory Purchase')
                ->update([
                    'credit_account_id' => $accountId,
                    'notes' => 'Inventory asset against Goods Received Not Invoiced when a goods receipt is posted. The matched supplier bill clears GRNI and records input VAT against accounts payable.',
                    'updated_at' => $now,
                ]);
        }
    }
};
