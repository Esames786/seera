<?php

use App\Support\FeedbackMigrationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        FeedbackMigrationSchema::assertTransactionalTables(['users', 'permissions'], ['user_permission_overrides']);
        if (! Schema::hasTable('user_permission_overrides')) {
            Schema::create('user_permission_overrides', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('user_id');
                $table->foreignId('permission_id');
                $table->string('effect', 5);
                $table->string('reason', 1000);
                $table->foreignId('changed_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'permission_id'], 'user_permission_override_unique');
            });
        }
        FeedbackMigrationSchema::requireColumns('user_permission_overrides', [
            'id', 'user_id', 'permission_id', 'effect', 'reason', 'changed_by', 'created_at', 'updated_at',
        ]);
        if (! Schema::hasIndex('user_permission_overrides', ['user_id', 'permission_id'], 'unique')) {
            Schema::table('user_permission_overrides', fn (Blueprint $table) => $table->unique(['user_id', 'permission_id'], 'user_permission_override_unique'));
        }
        FeedbackMigrationSchema::ensureForeignKey('user_permission_overrides', 'user_id', 'users', 'cascade');
        FeedbackMigrationSchema::ensureForeignKey('user_permission_overrides', 'permission_id', 'permissions', 'cascade');
        FeedbackMigrationSchema::ensureForeignKey('user_permission_overrides', 'changed_by', 'users', 'set null');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
