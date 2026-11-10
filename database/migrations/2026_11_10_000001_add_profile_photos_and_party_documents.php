<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'employees'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('profile_photo_path')->nullable());
        }
        // Separate foreign keys keep ownership explicit and allow the guarded reset to retain
        // each party's documents only when that party's entry group is retained.
        foreach (['customer', 'supplier'] as $kind) {
            Schema::create($kind.'_documents', function (Blueprint $table) use ($kind) {
                $table->id();
                $table->foreignId($kind.'_id')->constrained()->restrictOnDelete();
                $table->string('document_type', 80);
                $table->string('document_number', 100)->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('original_filename');
                $table->string('path');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
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
