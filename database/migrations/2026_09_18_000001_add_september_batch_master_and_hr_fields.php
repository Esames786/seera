<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * September media batch (NR-01 .. NR-34), master data and HR fields:
 *  - suppliers: city, rating, bank details, allowed payment types, project links (NR-01, NR-04, NR-05)
 *  - customers: rating, office/site contacts, shared notes (NR-06, NR-08, NR-09)
 *  - lookup values for extensible dropdowns such as supplier category and nationality (NR-02)
 *  - employees: leave entitlement (NR-18); employee documents: subtype (NR-11)
 *  - leave requests: supporting attachment (NR-17)
 *  - supplier payments / customer receipts: payment method and purpose (NR-28, NR-29)
 *
 * Additive only. Existing rows keep working with the defaults below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('city')->nullable()->after('category');
            $table->string('rating')->nullable()->after('city');
            $table->string('bank_name')->nullable()->after('email');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('iban')->nullable()->after('bank_account_name');
            $table->string('allowed_payment_types')->default('Both')->after('iban');
        });

        Schema::create('supplier_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'project_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('rating')->nullable()->after('type');
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('location_type')->default('office'); // office | site
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('lookup_values', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('value');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['type', 'value']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('annual_leave_entitlement')->default(21)->after('contract_end_date');
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('document_subtype')->nullable()->after('document_type');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('reason');
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('payment_account_id');
            $table->string('purpose')->default('Bill payment')->after('payment_method');
        });

        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('receipt_account_id');
        });

        // Seed the dropdown values the forms used to hard-code, so nothing disappears.
        $now = now();
        $rows = [];
        foreach (['Materials', 'Fuel', 'Equipment', 'Services', 'Subcontractor'] as $i => $value) {
            $rows[] = ['type' => 'supplier_category', 'value' => $value, 'sort_order' => $i, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (['Saudi', 'Pakistani', 'Indian', 'Bangladeshi', 'Egyptian', 'Filipino', 'Yemeni', 'Sudanese', 'Nepali', 'Sri Lankan', 'Other'] as $i => $value) {
            $rows[] = ['type' => 'nationality', 'value' => $value, 'sort_order' => $i, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('lookup_values')->insert($rows);

        // Keep values already stored on records, even if they are not in the list above.
        foreach (DB::table('suppliers')->whereNotNull('category')->distinct()->pluck('category') as $value) {
            DB::table('lookup_values')->insertOrIgnore(['type' => 'supplier_category', 'value' => $value, 'sort_order' => 99, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (DB::table('employees')->whereNotNull('nationality')->distinct()->pluck('nationality') as $value) {
            DB::table('lookup_values')->insertOrIgnore(['type' => 'nationality', 'value' => $value, 'sort_order' => 99, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('customer_receipts', fn (Blueprint $table) => $table->dropColumn('payment_method'));
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->dropColumn(['payment_method', 'purpose']));
        Schema::table('leave_requests', fn (Blueprint $table) => $table->dropColumn(['attachment_path', 'attachment_name']));
        Schema::table('employee_documents', fn (Blueprint $table) => $table->dropColumn('document_subtype'));
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('annual_leave_entitlement'));
        Schema::dropIfExists('lookup_values');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_contacts');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('rating'));
        Schema::dropIfExists('supplier_projects');
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn(['city', 'rating', 'bank_name', 'bank_account_name', 'iban', 'allowed_payment_types']));
    }
};
