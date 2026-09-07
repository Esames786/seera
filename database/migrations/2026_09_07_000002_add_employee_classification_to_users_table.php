<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client change request CR-04: the Sponsorship / Freelancer classification
 * the client expects on the user setup screen. It is kept in step with the
 * linked employee record so there is one answer for a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_classification')->nullable()->after('contract_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employee_classification');
        });
    }
};
