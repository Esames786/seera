<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing module (client change request NR-16): a manager creates a lead,
 * marketing staff take it up, visit the prospect and record the outcome and
 * follow-up. Visits feed the manager's visit report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_code')->unique();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_title')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('city')->nullable();
            $table->string('location')->nullable();
            $table->string('source')->nullable();
            $table->string('requirement')->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->string('status')->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('next_follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'assigned_to']);
        });

        Schema::create('marketing_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_lead_id')->constrained('marketing_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visit_date');
            $table->time('visit_time')->nullable();
            $table->string('location')->nullable();
            $table->string('person_met')->nullable();
            $table->string('person_title')->nullable();
            $table->string('outcome')->default('follow_up');
            $table->text('remarks')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->string('next_action')->nullable();
            $table->timestamps();

            $table->index(['visit_date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_visits');
        Schema::dropIfExists('marketing_leads');
    }
};
