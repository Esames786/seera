<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('grade')->nullable();
            $table->foreignId('default_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('mobile_access_default')->default(false);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('Company');
            $table->string('vat_number')->nullable();
            $table->string('cr_number')->nullable();
            $table->decimal('opening_receivable', 15, 2)->default(0);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('linked_account')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('cr_number')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('linked_account')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('linked_account')->nullable();
            $table->boolean('approval_required')->default(true);
            $table->boolean('mobile_visible')->default(true);
            $table->string('payment_type')->default('Both');
            $table->boolean('invoice_photo_required')->default(true);
            $table->string('vat_treatment')->default('VAT 15%');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('designations');
    }
};
