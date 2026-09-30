<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_instances', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->string('module', 100);
            $table->unsignedInteger('attempt');
            $table->foreignId('approval_workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            // Snapshot IDs intentionally survive user/role deletion; display names
            // and complete configuration/document data live in the JSON snapshot.
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'attempt'], 'approval_source_attempt_unique');
            $table->index(['source_type', 'status']);
        });
        Schema::create('approval_instance_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_instance_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('step_no');
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->boolean('is_required');
            $table->boolean('can_reject');
            $table->json('eligible_user_ids');
            $table->json('snapshot');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['approval_instance_id', 'step_no'], 'approval_step_order_unique');
        });
        Schema::table('purchase_requests', function (Blueprint $table) {
            // Existing records stay legacy. The HTTP store path sets runtime.
            $table->string('approval_mode', 20)->default('legacy');
        });
        DB::table('permissions')->insertOrIgnore(['module' => 'Approval History', 'module_group' => 'Back Office', 'action' => 'view', 'created_at' => now(), 'updated_at' => now()]);
        $historyId = DB::table('permissions')->where('module', 'Approval History')->where('action', 'view')->value('id');
        $viewId = DB::table('permissions')->where('module', 'Purchase Requests')->where('action', 'view')->value('id');
        foreach (DB::table('role_permissions')->where('permission_id', $viewId)->pluck('role_id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $historyId]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_requests', fn (Blueprint $table) => $table->dropColumn('approval_mode'));
        Schema::dropIfExists('approval_instance_steps');
        Schema::dropIfExists('approval_instances');
    }
};
