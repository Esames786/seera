<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table) {
            $table->foreignId('approver_user_id')->nullable()->after('approver_role_id')->constrained('users')->nullOnDelete();
            $table->boolean('can_send_back')->default(true)->after('can_reject');
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_user_id');
            $table->dropColumn('can_send_back');
        });
    }
};
