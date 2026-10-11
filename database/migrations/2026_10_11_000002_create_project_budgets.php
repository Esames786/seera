<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project BOQ / Detailed Budget Engine, Phase 1. Planning data only: a budget
 * version per project with its lines. Nothing here posts to the ledger, moves
 * stock or touches the existing scalar `projects.budget` (the master budget).
 * Additive and re-runnable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_budgets')) {
            Schema::create('project_budgets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('reference', 40);
                $table->string('title', 150);
                $table->string('status', 16)->default('draft');
                $table->boolean('is_current')->default(false);
                $table->foreignId('based_on_budget_id')->nullable()->constrained('project_budgets')->nullOnDelete();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->unsignedInteger('line_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamp('superseded_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['project_id', 'version'], 'project_budgets_project_version_unique');
                $table->index(['project_id', 'is_current'], 'project_budgets_project_current_idx');
            });
        }

        if (! Schema::hasTable('project_budget_lines')) {
            Schema::create('project_budget_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_budget_id')->constrained('project_budgets')->restrictOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('cost_code', 32);
                $table->string('category', 32);
                $table->string('description', 200);
                $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
                $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
                $table->decimal('quantity', 15, 3)->nullable();
                $table->decimal('rate', 15, 4)->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['project_budget_id', 'cost_code'], 'project_budget_lines_budget_code_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_lines');
        Schema::dropIfExists('project_budgets');
    }
};
