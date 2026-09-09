<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client follow-up of 8 September 2026 (CR-15, CR-17, CR-18):
 *  - projects get a user-managed Classification list;
 *  - suppliers get a user-managed Payment Terms list with a number of days;
 *  - the supplier's Linked Payable Account becomes a real Chart of Accounts link.
 *
 * Additive. The four current payment-term choices are inserted so existing
 * suppliers keep their meaning, and existing text values are mapped where the
 * mapping is unambiguous. Legacy text columns stay in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('days')->default(0);
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_classification_id')->nullable()->after('customer_id')
                ->constrained('project_classifications')->nullOnDelete();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('payment_term_id')->nullable()->after('payment_terms')
                ->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('linked_account_id')->nullable()->after('linked_account')
                ->constrained('chart_of_accounts')->nullOnDelete();
        });

        $now = now();
        DB::table('payment_terms')->insert([
            ['name' => 'Cash', 'days' => 0, 'description' => 'Paid on delivery', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '15 Days', 'days' => 15, 'description' => null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '30 Days', 'days' => 30, 'description' => null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '60 Days', 'days' => 60, 'description' => null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach (DB::table('payment_terms')->get() as $term) {
            DB::table('suppliers')->where('payment_terms', $term->name)->update(['payment_term_id' => $term->id]);
        }

        // Every supplier so far has posted to the payables control account (2100).
        $payable = DB::table('chart_of_accounts')->where('account_code', '2100')->first();
        if ($payable) {
            DB::table('suppliers')->whereNull('linked_account_id')->update(['linked_account_id' => $payable->id]);
        }
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_term_id');
            $table->dropConstrainedForeignId('linked_account_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_classification_id');
        });

        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('project_classifications');
    }
};
