<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GPS attendance runtime, Phase 1 (online mobile web): structured location
 * evidence for check-in and check-out. Additive and re-runnable; existing
 * manual rows keep their values (all new columns are nullable). Check-in and
 * check-out evidence are stored separately so a check-out never overwrites
 * the check-in capture.
 */
return new class extends Migration
{
    private const COLUMNS = ['check_in', 'check_out'];

    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (self::COLUMNS as $prefix) {
                if (! Schema::hasColumn('attendance_records', $prefix.'_latitude')) {
                    $table->decimal($prefix.'_latitude', 10, 7)->nullable()->after('remarks');
                }
                if (! Schema::hasColumn('attendance_records', $prefix.'_longitude')) {
                    $table->decimal($prefix.'_longitude', 10, 7)->nullable()->after($prefix.'_latitude');
                }
                if (! Schema::hasColumn('attendance_records', $prefix.'_accuracy_meters')) {
                    $table->decimal($prefix.'_accuracy_meters', 9, 2)->nullable()->after($prefix.'_longitude');
                }
                if (! Schema::hasColumn('attendance_records', $prefix.'_distance_meters')) {
                    $table->decimal($prefix.'_distance_meters', 10, 2)->nullable()->after($prefix.'_accuracy_meters');
                }
                if (! Schema::hasColumn('attendance_records', $prefix.'_geofence_status')) {
                    $table->string($prefix.'_geofence_status', 32)->nullable()->after($prefix.'_distance_meters');
                }
                if (! Schema::hasColumn('attendance_records', $prefix.'_recorded_at')) {
                    $table->dateTime($prefix.'_recorded_at')->nullable()->after($prefix.'_geofence_status');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            foreach (self::COLUMNS as $prefix) {
                foreach (['latitude', 'longitude', 'accuracy_meters', 'distance_meters', 'geofence_status', 'recorded_at'] as $suffix) {
                    if (Schema::hasColumn('attendance_records', $prefix.'_'.$suffix)) {
                        $table->dropColumn($prefix.'_'.$suffix);
                    }
                }
            }
        });
    }
};
