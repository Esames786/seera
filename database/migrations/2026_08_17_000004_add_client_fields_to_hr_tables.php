<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Sponsorship vs freelancer drives Saudi labour treatment.
            $table->string('employee_classification')->default('Sponsorship')->after('contract_type');

            // Documents tracked directly on the profile alongside IQAMA/passport.
            $table->string('insurance_number')->nullable()->after('passport_expiry_date');
            $table->date('insurance_expiry_date')->nullable()->after('insurance_number');
            $table->string('driving_license_number')->nullable()->after('insurance_expiry_date');
            $table->date('driving_license_expiry_date')->nullable()->after('driving_license_number');

            // Default allowances, used to pre-fill the salary structure.
            $table->decimal('housing_allowance', 12, 2)->default(0)->after('basic_salary');
            $table->decimal('transport_allowance', 12, 2)->default(0)->after('housing_allowance');
            $table->decimal('food_allowance', 12, 2)->default(0)->after('transport_allowance');
            $table->decimal('fuel_allowance', 12, 2)->default(0)->after('food_allowance');
            $table->decimal('other_allowance', 12, 2)->default(0)->after('fuel_allowance');
        });

        Schema::table('salary_structures', function (Blueprint $table) {
            $table->decimal('fuel_allowance', 12, 2)->default(0)->after('food_allowance');
        });

        Schema::table('end_of_service_records', function (Blueprint $table) {
            // Saudi gratuity inputs: how service ended drives the entitlement.
            $table->string('termination_reason')->default('termination')->after('termination_date');
            $table->decimal('gratuity_before_adjustment', 12, 2)->default(0)->after('last_basic_salary');
            $table->decimal('entitlement_percentage', 5, 2)->default(100)->after('gratuity_before_adjustment');
            $table->boolean('manual_override')->default(false)->after('eosb_amount');
        });
    }

    public function down(): void
    {
        Schema::table('end_of_service_records', function (Blueprint $table) {
            $table->dropColumn(['termination_reason', 'gratuity_before_adjustment', 'entitlement_percentage', 'manual_override']);
        });

        Schema::table('salary_structures', function (Blueprint $table) {
            $table->dropColumn('fuel_allowance');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'employee_classification', 'insurance_number', 'insurance_expiry_date',
                'driving_license_number', 'driving_license_expiry_date',
                'housing_allowance', 'transport_allowance', 'food_allowance',
                'fuel_allowance', 'other_allowance',
            ]);
        });
    }
};
