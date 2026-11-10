<?php

use App\Support\FeedbackMigrationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional. Check every parent before adding any columns.
        FeedbackMigrationSchema::assertTransactionalTables(
            ['users', 'employees', 'customers', 'suppliers', 'permissions'],
            ['customer_documents', 'supplier_documents', 'user_permission_overrides']
        );
        foreach (['users', 'employees'] as $name) {
            if (! Schema::hasColumn($name, 'profile_photo_path')) {
                Schema::table($name, fn (Blueprint $table) => $table->string('profile_photo_path')->nullable());
            }
        }
        // Separate foreign keys keep ownership explicit and allow the guarded reset to retain
        // each party's documents only when that party's entry group is retained.
        foreach (['customer', 'supplier'] as $kind) {
            if (! Schema::hasTable($kind.'_documents')) {
                Schema::create($kind.'_documents', function (Blueprint $table) use ($kind) {
                    $table->engine = 'InnoDB';
                    $table->id();
                    $table->foreignId($kind.'_id');
                    $table->string('document_type', 80);
                    $table->string('document_number', 100)->nullable();
                    $table->date('issue_date')->nullable();
                    $table->date('expiry_date')->nullable();
                    $table->string('original_filename');
                    $table->string('path');
                    $table->string('mime_type', 100);
                    $table->unsignedBigInteger('size');
                    $table->foreignId('uploaded_by')->nullable();
                    $table->timestamps();
                });
            }
            FeedbackMigrationSchema::requireColumns($kind.'_documents', [
                'id', $kind.'_id', 'document_type', 'document_number', 'issue_date', 'expiry_date',
                'original_filename', 'path', 'mime_type', 'size', 'uploaded_by', 'created_at', 'updated_at',
            ]);
            FeedbackMigrationSchema::ensureForeignKey($kind.'_documents', $kind.'_id', $kind.'s', 'restrict');
            FeedbackMigrationSchema::ensureForeignKey($kind.'_documents', 'uploaded_by', 'users', 'set null');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');
        Schema::dropIfExists('customer_documents');
        foreach (['users', 'employees'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('profile_photo_path'));
        }
    }
};
