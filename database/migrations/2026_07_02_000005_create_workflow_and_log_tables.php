<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('module');
            $table->string('trigger_action')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('scope')->default('Assigned Project/Site');
            $table->string('auto_posting')->default('No Auto Posting');
            $table->boolean('notify_requester')->default(true);
            $table->boolean('lock_after_approval')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_no');
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('approver_note')->nullable();
            $table->boolean('is_required')->default(true);
            $table->decimal('amount_limit', 15, 2)->nullable();
            $table->unsignedInteger('sla_hours')->default(24);
            $table->foreignId('escalation_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('can_reject')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('module', 100);
            $table->string('action', 100);
            $table->text('description')->nullable();
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('status')->default('success');
            $table->timestamps();
            $table->index(['module', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
