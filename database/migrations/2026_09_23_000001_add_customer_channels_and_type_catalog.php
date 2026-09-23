<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('allowed_payment_types')->default('Both');
        });

        $types = collect(['Company', 'Individual'])
            ->merge(DB::table('customers')->distinct()->pluck('type'))->filter()->unique();
        foreach ($types as $type) {
            DB::table('lookup_values')->insertOrIgnore([
                'type' => 'customer_type', 'value' => $type, 'sort_order' => 50,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('allowed_payment_types'));
        // Retain catalogue entries: customer records may already use custom values.
    }
};
