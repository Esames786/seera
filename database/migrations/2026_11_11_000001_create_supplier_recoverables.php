<?php

use App\Support\FeedbackMigrationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        FeedbackMigrationSchema::assertTransactionalTables(['suppliers', 'projects', 'sites', 'users', 'chart_of_accounts', 'journal_entries', 'supplier_bills']);
        Schema::create('supplier_advances', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->string('purpose', 30);
            $table->decimal('amount', 15, 2);
            $table->foreignId('asset_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('reference', 100);
            $table->text('reason');
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_journal_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('supplier_advance_movements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('supplier_advance_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->foreignId('supplier_bill_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
            $table->date('movement_date');
            $table->decimal('amount', 15, 2);
            $table->string('reference', 100);
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_journal_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_advance_movements');
        Schema::dropIfExists('supplier_advances');
    }
};
