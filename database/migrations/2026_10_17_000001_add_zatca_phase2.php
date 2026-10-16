<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ZATCA e-invoicing Phase 2, sprint 1 (compliant XML, stamp, sandbox clearance
 * and reporting). Additive, re-runnable, data preserving:
 *  - customer_invoices: invoice type / issue time / supply date, immutable
 *    seller and buyer snapshots taken at issuance, and the issuance lock.
 *  - zatca_invoice_records: the explicit state machine, environment, EGS unit,
 *    ICV / PIH / invoice hash chain values, private XML paths, Phase-2 QR,
 *    validation and response details. The legacy `clearance_status` column is
 *    kept untouched as history; `status` is the new authority. Every existing
 *    row becomes `not_generated` (no XML ever existed) or `cancelled`.
 *  - zatca_egs_units: the solution unit (EGS) identity, its encrypted key and
 *    credentials, the invoice counter and the last document hash.
 *  - zatca_submissions: one row per API attempt (sanitised response excerpt).
 *  - customers / company_profiles: national-address and identification fields
 *    required by BR-KSA-09 / BR-KSA-10 / BR-KSA-14.
 * No seeder. No permission rows: the ZATCA Invoicing module already carries the
 * full action catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            foreach ([
                'invoice_type' => fn () => $table->string('invoice_type', 16)->default('standard')->after('invoice_number'),
                'issue_time' => fn () => $table->string('issue_time', 8)->nullable()->after('invoice_date'),
                'supply_date' => fn () => $table->date('supply_date')->nullable()->after('issue_time'),
                'seller_snapshot' => fn () => $table->json('seller_snapshot')->nullable()->after('notes'),
                'buyer_snapshot' => fn () => $table->json('buyer_snapshot')->nullable()->after('seller_snapshot'),
                'zatca_locked_at' => fn () => $table->dateTime('zatca_locked_at')->nullable()->after('buyer_snapshot'),
            ] as $column => $add) {
                if (! Schema::hasColumn('customer_invoices', $column)) {
                    $add();
                }
            }
        });

        if (! Schema::hasTable('zatca_egs_units')) {
            Schema::create('zatca_egs_units', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('environment', 16);
                $table->string('status', 24)->default('new');
                $table->string('egs_serial', 191)->nullable();
                $table->string('vat_number', 20)->nullable();
                $table->string('organization_name', 191)->nullable();
                $table->string('organization_unit', 191)->nullable();
                $table->char('country_code', 2)->default('SA');
                $table->char('invoice_types', 4)->default('1100');
                $table->string('location', 191)->nullable();
                $table->string('industry', 100)->nullable();
                $table->text('private_key_encrypted')->nullable();
                $table->text('public_key_pem')->nullable();
                $table->text('csr')->nullable();
                $table->dateTime('csr_generated_at')->nullable();
                $table->string('compliance_request_id', 64)->nullable();
                $table->text('compliance_token_encrypted')->nullable();
                $table->text('compliance_secret_encrypted')->nullable();
                $table->dateTime('compliance_issued_at')->nullable();
                $table->json('compliance_checks')->nullable();
                $table->text('production_token_encrypted')->nullable();
                $table->text('production_secret_encrypted')->nullable();
                $table->dateTime('production_issued_at')->nullable();
                $table->text('certificate_pem')->nullable();
                $table->dateTime('certificate_expires_at')->nullable();
                $table->unsignedBigInteger('icv_counter')->default(0);
                $table->string('last_invoice_hash', 100)->nullable();
                $table->text('last_error')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['environment', 'name'], 'zatca_egs_env_name_unique');
            });
        }

        Schema::table('zatca_invoice_records', function (Blueprint $table) {
            $columns = [
                'status' => fn () => $table->string('status', 32)->default('not_generated')->after('clearance_status'),
                'environment' => fn () => $table->string('environment', 16)->nullable()->after('status'),
                'zatca_egs_unit_id' => fn () => $table->foreignId('zatca_egs_unit_id')->nullable()->after('environment')->constrained('zatca_egs_units')->nullOnDelete(),
                'invoice_type' => fn () => $table->string('invoice_type', 16)->nullable()->after('zatca_egs_unit_id'),
                'invoice_type_code' => fn () => $table->string('invoice_type_code', 3)->nullable()->after('invoice_type'),
                'transaction_code' => fn () => $table->string('transaction_code', 7)->nullable()->after('invoice_type_code'),
                'icv' => fn () => $table->unsignedBigInteger('icv')->nullable()->after('transaction_code'),
                'previous_hash' => fn () => $table->string('previous_hash', 100)->nullable()->after('icv'),
                'invoice_hash' => fn () => $table->string('invoice_hash', 100)->nullable()->after('previous_hash'),
                'xml_path' => fn () => $table->string('xml_path')->nullable()->after('invoice_hash'),
                'signed_xml_path' => fn () => $table->string('signed_xml_path')->nullable()->after('xml_path'),
                'cleared_xml_path' => fn () => $table->string('cleared_xml_path')->nullable()->after('signed_xml_path'),
                'qr_code' => fn () => $table->text('qr_code')->nullable()->after('cleared_xml_path'),
                'validation_status' => fn () => $table->string('validation_status', 16)->nullable()->after('qr_code'),
                'validation_errors' => fn () => $table->json('validation_errors')->nullable()->after('validation_status'),
                'validation_warnings' => fn () => $table->json('validation_warnings')->nullable()->after('validation_errors'),
                'generated_at' => fn () => $table->dateTime('generated_at')->nullable()->after('validation_warnings'),
                'validated_at' => fn () => $table->dateTime('validated_at')->nullable()->after('generated_at'),
                'signed_at' => fn () => $table->dateTime('signed_at')->nullable()->after('validated_at'),
                'submitted_at' => fn () => $table->dateTime('submitted_at')->nullable()->after('signed_at'),
                'reported_at' => fn () => $table->dateTime('reported_at')->nullable()->after('submitted_at'),
                'outcome_unknown_at' => fn () => $table->dateTime('outcome_unknown_at')->nullable()->after('reported_at'),
                'accepted_with_warnings' => fn () => $table->boolean('accepted_with_warnings')->default(false)->after('outcome_unknown_at'),
                'zatca_status' => fn () => $table->string('zatca_status', 32)->nullable()->after('accepted_with_warnings'),
                'response_warnings' => fn () => $table->json('response_warnings')->nullable()->after('zatca_status'),
                'response_errors' => fn () => $table->json('response_errors')->nullable()->after('response_warnings'),
                'last_error_category' => fn () => $table->string('last_error_category', 32)->nullable()->after('response_errors'),
                'last_error_code' => fn () => $table->string('last_error_code', 60)->nullable()->after('last_error_category'),
                'last_error_message' => fn () => $table->text('last_error_message')->nullable()->after('last_error_code'),
                'submission_attempts' => fn () => $table->unsignedInteger('submission_attempts')->default(0)->after('last_error_message'),
            ];
            foreach ($columns as $column => $add) {
                if (! Schema::hasColumn('zatca_invoice_records', $column)) {
                    $add();
                }
            }
        });
        // Legacy rows: no XML, QR, stamp or ZATCA response ever existed, whatever the local label said.
        DB::table('zatca_invoice_records')->where('status', 'not_generated')->where('clearance_status', 'cancelled')->update(['status' => 'cancelled']);
        DB::table('customer_invoices')->whereIn('zatca_status', ['pending_clearance', 'pending', 'generated', 'cleared', 'failed', 'draft'])->update(['zatca_status' => 'not_generated']);

        if (! Schema::hasTable('zatca_submissions')) {
            Schema::create('zatca_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('zatca_invoice_record_id')->nullable()->constrained('zatca_invoice_records')->cascadeOnDelete();
                $table->foreignId('zatca_egs_unit_id')->nullable()->constrained('zatca_egs_units')->nullOnDelete();
                $table->unsignedInteger('attempt')->default(1);
                $table->string('environment', 16);
                $table->string('endpoint', 32);
                $table->string('invoice_hash', 100)->nullable();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->string('outcome', 32);
                $table->string('zatca_status', 32)->nullable();
                $table->json('warnings')->nullable();
                $table->json('errors')->nullable();
                $table->json('response_excerpt')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['zatca_invoice_record_id', 'attempt'], 'zatca_sub_record_attempt_idx');
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            foreach ([
                'street' => fn () => $table->string('street', 191)->nullable()->after('billing_address'),
                'building_number' => fn () => $table->string('building_number', 10)->nullable()->after('street'),
                'district' => fn () => $table->string('district', 191)->nullable()->after('building_number'),
                'city' => fn () => $table->string('city', 100)->nullable()->after('district'),
                'postal_code' => fn () => $table->string('postal_code', 10)->nullable()->after('city'),
                'country_code' => fn () => $table->char('country_code', 2)->default('SA')->after('postal_code'),
                'buyer_id_scheme' => fn () => $table->string('buyer_id_scheme', 4)->nullable()->after('country_code'),
                'buyer_id_value' => fn () => $table->string('buyer_id_value', 60)->nullable()->after('buyer_id_scheme'),
            ] as $column => $add) {
                if (! Schema::hasColumn('customers', $column)) {
                    $add();
                }
            }
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            foreach ([
                'street' => fn () => $table->string('street', 191)->nullable()->after('address'),
                'building_number' => fn () => $table->string('building_number', 10)->nullable()->after('street'),
                'additional_number' => fn () => $table->string('additional_number', 10)->nullable()->after('building_number'),
                'district' => fn () => $table->string('district', 191)->nullable()->after('additional_number'),
                'postal_code' => fn () => $table->string('postal_code', 10)->nullable()->after('district'),
                'country_code' => fn () => $table->char('country_code', 2)->default('SA')->after('postal_code'),
                'seller_id_scheme' => fn () => $table->string('seller_id_scheme', 4)->default('CRN')->after('country_code'),
            ] as $column => $add) {
                if (! Schema::hasColumn('company_profiles', $column)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            foreach (['street', 'building_number', 'additional_number', 'district', 'postal_code', 'country_code', 'seller_id_scheme'] as $column) {
                if (Schema::hasColumn('company_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('customers', function (Blueprint $table) {
            foreach (['street', 'building_number', 'district', 'city', 'postal_code', 'country_code', 'buyer_id_scheme', 'buyer_id_value'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('zatca_submissions');
        Schema::table('zatca_invoice_records', function (Blueprint $table) {
            if (Schema::hasColumn('zatca_invoice_records', 'zatca_egs_unit_id')) {
                $table->dropConstrainedForeignId('zatca_egs_unit_id');
            }
            foreach (['status', 'environment', 'invoice_type', 'invoice_type_code', 'transaction_code', 'icv', 'previous_hash', 'invoice_hash', 'xml_path', 'signed_xml_path', 'cleared_xml_path',
                'qr_code', 'validation_status', 'validation_errors', 'validation_warnings', 'generated_at', 'validated_at', 'signed_at', 'submitted_at', 'reported_at', 'outcome_unknown_at',
                'accepted_with_warnings', 'zatca_status', 'response_warnings', 'response_errors', 'last_error_category', 'last_error_code', 'last_error_message', 'submission_attempts'] as $column) {
                if (Schema::hasColumn('zatca_invoice_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('zatca_egs_units');
        Schema::table('customer_invoices', function (Blueprint $table) {
            foreach (['invoice_type', 'issue_time', 'supply_date', 'seller_snapshot', 'buyer_snapshot', 'zatca_locked_at'] as $column) {
                if (Schema::hasColumn('customer_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
