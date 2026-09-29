<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_reporting_parents', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('parent_role_id')->constrained('roles')->cascadeOnDelete();
            $table->unique(['role_id', 'parent_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_reporting_parents');
    }
};
