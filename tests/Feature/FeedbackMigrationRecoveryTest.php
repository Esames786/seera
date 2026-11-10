<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class FeedbackMigrationRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No RefreshDatabase: deliberately exercise the pre-migration/partial schema.
        foreach (['users', 'employees', 'customers', 'suppliers', 'permissions'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('name')->nullable();
            });
            DB::table($table)->insert(['id' => 1, 'name' => 'Retained training record']);
        }
    }

    private function runFeedbackMigrations(): void
    {
        foreach (['2026_11_10_000001_add_profile_photos_and_party_documents', '2026_11_10_000002_create_user_permission_overrides'] as $name) {
            (require database_path('migrations/'.$name.'.php'))->up();
        }
    }

    private function partialCustomerDocuments(int $customer = 1): void
    {
        // Reproduces CREATE TABLE succeeding before the first ALTER FOREIGN KEY fails.
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('original_filename');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
        DB::table('customer_documents')->insert(['id' => 7, 'customer_id' => $customer, 'document_type' => 'cr', 'original_filename' => 'training.pdf', 'path' => 'private/retained.pdf', 'mime_type' => 'application/pdf', 'size' => 50, 'uploaded_by' => 1]);
    }

    public function test_fresh_install_has_all_seven_foreign_keys_and_unique_permissions(): void
    {
        $this->runFeedbackMigrations();
        $this->assertCount(2, Schema::getForeignKeys('customer_documents'));
        $this->assertCount(2, Schema::getForeignKeys('supplier_documents'));
        $this->assertCount(3, Schema::getForeignKeys('user_permission_overrides'));
        $this->assertTrue(Schema::hasIndex('user_permission_overrides', ['user_id', 'permission_id'], 'unique'));
        $this->assertTrue(Schema::hasColumn('users', 'profile_photo_path'));
        $this->assertTrue(Schema::hasColumn('employees', 'profile_photo_path'));
    }

    public function test_partial_install_preserves_photos_documents_and_parent_rows(): void
    {
        foreach (['users', 'employees'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('profile_photo_path')->nullable());
            DB::table($table)->where('id', 1)->update(['profile_photo_path' => 'private/existing-'.$table.'.jpg']);
        }
        $this->partialCustomerDocuments();
        $this->runFeedbackMigrations();
        $this->assertDatabaseHas('customer_documents', ['id' => 7, 'path' => 'private/retained.pdf']);
        foreach (['users', 'employees'] as $table) {
            $this->assertDatabaseHas($table, ['id' => 1, 'profile_photo_path' => 'private/existing-'.$table.'.jpg']);
        }
        foreach (['users', 'employees', 'customers', 'suppliers', 'permissions'] as $table) {
            $this->assertDatabaseHas($table, ['id' => 1, 'name' => 'Retained training record']);
        }
        $this->assertCount(2, Schema::getForeignKeys('customer_documents'));
    }

    public function test_repeating_completed_ddl_preserves_existing_permission_exceptions(): void
    {
        $this->runFeedbackMigrations();
        DB::table('user_permission_overrides')->insert(['id' => 4, 'user_id' => 1, 'permission_id' => 1, 'effect' => 'deny', 'reason' => 'Retain reviewed exception', 'changed_by' => 1]);
        $this->runFeedbackMigrations();
        $this->assertDatabaseHas('user_permission_overrides', ['id' => 4, 'effect' => 'deny', 'reason' => 'Retain reviewed exception']);
        $this->assertCount(3, Schema::getForeignKeys('user_permission_overrides'));
    }

    public function test_orphaned_partial_records_are_refused_without_deleting_them(): void
    {
        $this->partialCustomerDocuments(999);
        try {
            $this->runFeedbackMigrations();
            $this->fail('Expected a refusal for orphan references.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('orphan references', $exception->getMessage());
        }
        $this->assertDatabaseHas('customer_documents', ['id' => 7, 'customer_id' => 999]);
        $this->assertCount(0, Schema::getForeignKeys('customer_documents'));
    }

    public function test_wrong_existing_constraint_is_not_silently_replaced(): void
    {
        $this->partialCustomerDocuments();
        Schema::table('customer_documents', fn (Blueprint $table) => $table->foreign('customer_id')->references('id')->on('suppliers')->restrictOnDelete());
        try {
            $this->runFeedbackMigrations();
            $this->fail('Expected an unexpected-constraint refusal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unexpected foreign key', $exception->getMessage());
        }
        $this->assertSame('suppliers', Schema::getForeignKeys('customer_documents')[0]['foreign_table']);
        $this->assertDatabaseHas('customer_documents', ['id' => 7]);
    }

    public function test_unknown_existing_table_shape_is_not_dropped_or_marked_complete(): void
    {
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->string('unknown_field');
        });
        DB::table('customer_documents')->insert(['id' => 3, 'unknown_field' => 'keep']);
        try {
            $this->runFeedbackMigrations();
            $this->fail('Expected a schema-shape refusal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('missing columns', $exception->getMessage());
        }
        $this->assertDatabaseHas('customer_documents', ['id' => 3, 'unknown_field' => 'keep']);
    }
}
