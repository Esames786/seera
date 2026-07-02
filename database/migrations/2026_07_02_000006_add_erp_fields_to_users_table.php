<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->unique()->after('name');
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('phone')->nullable()->after('username');
            $table->string('language')->default('English')->after('phone');
            $table->foreignId('department_id')->nullable()->after('language')->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('designations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('designation_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->after('branch_id')->constrained('projects')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->after('project_id')->constrained('sites')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('site_id')->constrained('warehouses')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->string('contract_type')->default('Full Time');
            $table->string('iqama_number')->nullable();
            $table->date('iqama_expiry_date')->nullable();
            $table->boolean('mobile_access')->default(false);
            $table->boolean('two_factor_enabled')->default(false);
            $table->boolean('temporary_access')->default(false);
            $table->date('access_start_date')->nullable();
            $table->date('access_end_date')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('status')->default('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn([
                'employee_id', 'username', 'phone', 'language', 'joining_date',
                'contract_type', 'iqama_number', 'iqama_expiry_date', 'mobile_access',
                'two_factor_enabled', 'temporary_access', 'access_start_date',
                'access_end_date', 'last_login_at', 'status',
            ]);
        });
    }
};
