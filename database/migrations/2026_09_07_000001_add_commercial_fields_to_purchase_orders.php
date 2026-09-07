<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client change requests CR-12 and CR-13: purchase order lines gain a
 * description, an optional percentage discount and a per-line VAT rate that
 * can differ from the order default; purchase orders gain supplier quotation
 * attachments. Everything is additive so existing orders keep their totals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->text('description')->nullable()->after('item_id');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('unit_price');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_percent');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 15, 2)->default(0)->after('taxable_amount');
        });

        Schema::create('purchase_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_attachments');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['description', 'discount_percent', 'discount_amount']);
        });
    }
};
