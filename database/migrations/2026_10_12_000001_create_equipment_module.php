<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment & Vehicles, Phase 1: asset master, project / site assignment
 * history, fuel logs, maintenance records and private documents. Additive and
 * re-runnable. Accounting stays in the existing journal / supplier bill
 * tables; the records here only link to those documents. The Equipment
 * permission rows are completed idempotently (no production seeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('equipment_assets')) {
            Schema::create('equipment_assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code', 40);
                $table->string('name', 150);
                $table->string('asset_type', 16)->default('equipment');
                $table->string('category', 80)->nullable();
                $table->string('make', 80)->nullable();
                $table->string('model', 80)->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('registration_number', 40)->nullable();
                $table->string('chassis_number', 60)->nullable();
                $table->string('serial_number', 80)->nullable();
                $table->string('ownership_type', 16)->default('owned');
                $table->date('acquisition_date')->nullable();
                $table->decimal('acquisition_cost', 15, 2)->nullable();
                $table->string('status', 24)->default('available');
                $table->foreignId('current_project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('current_site_id')->nullable()->constrained('sites')->nullOnDelete();
                $table->foreignId('current_operator_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->unsignedBigInteger('current_assignment_id')->nullable();
                $table->string('meter_type', 16)->default('odometer');
                $table->decimal('current_meter', 12, 1)->default(0);
                $table->date('registration_expiry_date')->nullable();
                $table->date('insurance_expiry_date')->nullable();
                $table->string('tracker_provider', 80)->nullable();
                $table->string('tracker_device_id', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique('asset_code', 'equipment_assets_code_unique');
                $table->index(['current_project_id', 'status'], 'equipment_assets_project_status_idx');
                $table->index('current_site_id', 'equipment_assets_site_idx');
            });
        }

        if (! Schema::hasTable('equipment_assignments')) {
            Schema::create('equipment_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equipment_asset_id')->constrained('equipment_assets')->restrictOnDelete();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
                $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
                $table->date('assigned_from');
                $table->date('assigned_until')->nullable();
                $table->decimal('start_meter', 12, 1)->nullable();
                $table->decimal('end_meter', 12, 1)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['equipment_asset_id', 'is_active'], 'eq_assign_asset_active_idx');
                $table->index(['project_id', 'is_active'], 'eq_assign_project_active_idx');
            });
        }

        if (! Schema::hasTable('equipment_documents')) {
            Schema::create('equipment_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equipment_asset_id')->constrained('equipment_assets')->restrictOnDelete();
                $table->string('document_type', 30)->default('other');
                $table->string('document_number', 80)->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('original_filename');
                $table->string('path');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['equipment_asset_id', 'document_type'], 'eq_docs_asset_type_idx');
            });
        }

        foreach (['equipment_fuel_logs' => 'fuel_number', 'equipment_maintenance_records' => 'maintenance_number'] as $name => $numberColumn) {
            if (Schema::hasTable($name)) {
                continue;
            }
            Schema::create($name, function (Blueprint $table) use ($name, $numberColumn) {
                $table->id();
                $table->string($numberColumn, 40);
                $table->foreignId('equipment_asset_id')->constrained('equipment_assets')->restrictOnDelete();
                $table->foreignId('equipment_assignment_id')->nullable()->constrained('equipment_assignments')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
                $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
                $table->date($name === 'equipment_fuel_logs' ? 'fuel_date' : 'service_date');
                $table->decimal('meter_reading', 12, 1)->nullable();
                if ($name === 'equipment_fuel_logs') {
                    $table->decimal('quantity', 12, 3);
                    $table->string('unit', 16)->default('litre');
                    $table->decimal('rate', 12, 4);
                } else {
                    $table->string('maintenance_type', 24)->default('repair');
                    $table->text('description');
                    $table->date('next_service_date')->nullable();
                    $table->decimal('next_service_meter', 12, 1)->nullable();
                    $table->string('status', 16)->default('open');
                    $table->timestamp('completed_at')->nullable();
                }
                $table->decimal('taxable_amount', 15, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
                $table->string('reference_number', 100)->nullable();
                $table->string('payment_type', 24)->default('Cash');
                $table->foreignId('payment_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
                $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
                $table->string('accounting_status', 16)->default('not_posted');
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->restrictOnDelete();
                $table->text('posting_error')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('document_id')->nullable()->constrained('equipment_documents')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $prefix = $name === 'equipment_fuel_logs' ? 'eq_fuel' : 'eq_maint';
                $table->unique($numberColumn, $prefix.'_number_unique');
                $table->index(['equipment_asset_id', $name === 'equipment_fuel_logs' ? 'fuel_date' : 'service_date'], $prefix.'_asset_date_idx');
                $table->index(['project_id', 'accounting_status'], $prefix.'_project_acc_idx');
            });
        }

        // Complete the Equipment permission rows for every catalogue action (idempotent; no role is granted anything).
        $now = now();
        foreach (\App\Models\Permission::ACTIONS as $action) {
            $exists = DB::table('permissions')->where('module', 'Equipment')->where('action', $action)->exists();
            if (! $exists) {
                DB::table('permissions')->insert(['module' => 'Equipment', 'action' => $action, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_records');
        Schema::dropIfExists('equipment_fuel_logs');
        Schema::dropIfExists('equipment_documents');
        Schema::dropIfExists('equipment_assignments');
        Schema::dropIfExists('equipment_assets');
    }
};
