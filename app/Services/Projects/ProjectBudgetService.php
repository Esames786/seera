<?php

namespace App\Services\Projects;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetLine;
use App\Models\Site;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project BOQ / Detailed Budget, Phase 1. Draft versions are edited line by
 * line; "Set as Current" freezes the version and supersedes the previous
 * current one; a revision copies the current lines into a new draft. The
 * project row is the mutex for version changes, so two finalizations can
 * never leave two current budgets. Nothing here touches accounting or stock.
 */
class ProjectBudgetService
{
    public const COST_CODE_PATTERN = '/^[A-Z0-9][A-Z0-9\-\.\/]{0,31}$/';

    /** @param array{title?: ?string, notes?: ?string} $data */
    public function createDraft(Project $project, array $data, int $actorId): ProjectBudget
    {
        return DB::transaction(function () use ($project, $data, $actorId) {
            $this->lockProject($project);
            if (ProjectBudget::where('project_id', $project->id)->where('status', ProjectBudget::STATUS_DRAFT)->exists()) {
                $this->invalid('budget', 'A draft version already exists for this project. Continue on that draft or discard it first.');
            }
            if (ProjectBudget::where('project_id', $project->id)->where('is_current', true)->exists()) {
                $this->invalid('budget', 'This project already has a current budget. Create a revision of it instead of a new baseline.');
            }
            $version = (int) ProjectBudget::where('project_id', $project->id)->max('version') + 1;
            $budget = ProjectBudget::create([
                'project_id' => $project->id,
                'version' => $version,
                'reference' => $this->reference($project, $version),
                'title' => trim((string) ($data['title'] ?? '')) !== '' ? trim($data['title']) : 'Detailed budget v'.$version,
                'status' => ProjectBudget::STATUS_DRAFT,
                'is_current' => false,
                'created_by' => $actorId,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->log($project, $actorId, 'Created project budget', $budget->reference.' (draft v'.$version.')');

            return $budget;
        }, 3);
    }

    /** Current budget → new draft version carrying every line. The current version stays untouched. */
    public function revise(ProjectBudget $budget, int $actorId, ?string $title = null): ProjectBudget
    {
        return DB::transaction(function () use ($budget, $actorId, $title) {
            $project = $this->lockProject($budget->project);
            $source = ProjectBudget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            if (! $source->isCurrent()) {
                $this->invalid('budget', 'Only the current budget can be revised. Open the current version and choose Create Revision.');
            }
            if (ProjectBudget::where('project_id', $project->id)->where('status', ProjectBudget::STATUS_DRAFT)->exists()) {
                $this->invalid('budget', 'A draft version already exists for this project. Finish or delete it before creating another revision.');
            }
            $version = (int) ProjectBudget::where('project_id', $project->id)->max('version') + 1;
            $draft = ProjectBudget::create([
                'project_id' => $project->id,
                'version' => $version,
                'reference' => $this->reference($project, $version),
                'title' => trim((string) $title) !== '' ? trim($title) : 'Revision of '.$source->reference,
                'status' => ProjectBudget::STATUS_DRAFT,
                'is_current' => false,
                'based_on_budget_id' => $source->id,
                'created_by' => $actorId,
            ]);
            foreach ($source->lines as $line) {
                $draft->lines()->create($line->only(['sort_order', 'cost_code', 'category', 'description', 'site_id', 'item_id', 'unit_id', 'quantity', 'rate', 'amount', 'chart_of_account_id', 'notes']));
            }
            $this->recalculate($draft);
            $this->log($project, $actorId, 'Created budget revision', $draft->reference.' (draft v'.$version.' from '.$source->reference.')');

            return $draft;
        }, 3);
    }

    /** Set a draft as the current budget; the previous current version becomes superseded. Idempotent. */
    public function finalize(int $budgetId, int $actorId): ProjectBudget
    {
        return DB::transaction(function () use ($budgetId, $actorId) {
            $budget = ProjectBudget::whereKey($budgetId)->firstOrFail();
            $project = $this->lockProject($budget->project);
            $budget = ProjectBudget::whereKey($budgetId)->lockForUpdate()->firstOrFail();
            if ($budget->isCurrent()) {
                return $budget; // same request repeated
            }
            if (! $budget->isDraft()) {
                $this->invalid('budget', 'A superseded version cannot be made current again. Create a revision of the current budget instead.');
            }
            $lines = $budget->lines()->get();
            if ($lines->isEmpty()) {
                $this->invalid('budget', 'Add at least one budget line before setting this version as current.');
            }
            foreach ($lines as $line) {
                $this->validateLine($budget, $line->toArray(), $line->id, true);
            }
            $this->recalculate($budget);
            $previous = ProjectBudget::where('project_id', $project->id)->where('is_current', true)->whereKeyNot($budget->id)->lockForUpdate()->get();
            foreach ($previous as $old) {
                $old->update(['status' => ProjectBudget::STATUS_SUPERSEDED, 'is_current' => false, 'superseded_at' => now()]);
            }
            $budget->update(['status' => ProjectBudget::STATUS_CURRENT, 'is_current' => true, 'finalized_by' => $actorId, 'finalized_at' => now()]);
            $this->log($project, $actorId, 'Set current project budget', $budget->reference.' v'.$budget->version.' · SAR '.number_format((float) $budget->total_amount, 2)
                .($previous->isNotEmpty() ? ' (supersedes '.$previous->pluck('reference')->implode(', ').')' : ''));

            return $budget->fresh();
        }, 3);
    }

    /** Discard a draft: it stays in the version history (read-only) so numbers are never reused; current and superseded versions cannot be removed. */
    public function discardDraft(ProjectBudget $budget, int $actorId): void
    {
        DB::transaction(function () use ($budget, $actorId) {
            $project = $this->lockProject($budget->project);
            $budget = ProjectBudget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
            if (! $budget->isDraft()) {
                $this->invalid('budget', 'Only a draft version can be discarded; current and superseded versions are history.');
            }
            $budget->update(['status' => ProjectBudget::STATUS_DISCARDED, 'is_current' => false]);
            $this->log($project, $actorId, 'Discarded draft project budget', $budget->reference.' ('.$budget->line_count.' lines kept for history)');
        }, 3);
    }

    // ------------------------------------------------------------------ lines

    /** @param array<string, mixed> $data */
    public function addLine(ProjectBudget $budget, array $data, int $actorId): ProjectBudgetLine
    {
        return DB::transaction(function () use ($budget, $data, $actorId) {
            $budget = $this->lockDraft($budget);
            $values = $this->validateLine($budget, $data);
            $values['sort_order'] = (int) $budget->lines()->max('sort_order') + 1;
            $line = $budget->lines()->create($values);
            $this->recalculate($budget);
            $this->log($budget->project, $actorId, 'Added budget line', $budget->reference.' · '.$line->cost_code.' '.$line->description.' · SAR '.number_format((float) $line->amount, 2));

            return $line;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateLine(ProjectBudgetLine $line, array $data, int $actorId): ProjectBudgetLine
    {
        return DB::transaction(function () use ($line, $data, $actorId) {
            $budget = $this->lockDraft($line->budget);
            $values = $this->validateLine($budget, $data, $line->id);
            $line->update($values);
            $this->recalculate($budget);
            $this->log($budget->project, $actorId, 'Edited budget line', $budget->reference.' · '.$line->cost_code.' '.$line->description.' · SAR '.number_format((float) $line->amount, 2));

            return $line->fresh();
        }, 3);
    }

    public function copyLine(ProjectBudgetLine $line, int $actorId): ProjectBudgetLine
    {
        return DB::transaction(function () use ($line, $actorId) {
            $budget = $this->lockDraft($line->budget);
            $copy = $budget->lines()->create($line->only(['cost_code', 'category', 'description', 'site_id', 'item_id', 'unit_id', 'quantity', 'rate', 'amount', 'chart_of_account_id', 'notes'])
                + ['sort_order' => (int) $budget->lines()->max('sort_order') + 1]);
            $this->recalculate($budget);
            $this->log($budget->project, $actorId, 'Added budget line', $budget->reference.' · '.$copy->cost_code.' '.$copy->description.' (copied) · SAR '.number_format((float) $copy->amount, 2));

            return $copy;
        }, 3);
    }

    public function deleteLine(ProjectBudgetLine $line, int $actorId): void
    {
        DB::transaction(function () use ($line, $actorId) {
            $budget = $this->lockDraft($line->budget);
            $label = $line->cost_code.' '.$line->description;
            $line->delete();
            $this->recalculate($budget);
            $this->log($budget->project, $actorId, 'Removed budget line', $budget->reference.' · '.$label);
        }, 3);
    }

    /**
     * Normalise and validate a line against the budget's project. Every
     * relation is checked server-side: a site must belong to the project, an
     * item must be an active item, an account must be an active expense
     * account mapped to one cost code only within the version, and the amount
     * is quantity × rate or an explicit lump sum — never the submitted total.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateLine(ProjectBudget $budget, array $data, ?int $ignoreLineId = null, bool $finalizing = false): array
    {
        $errors = [];
        $costCode = strtoupper(trim((string) ($data['cost_code'] ?? '')));
        if (! preg_match(self::COST_CODE_PATTERN, $costCode)) {
            $errors['cost_code'] = 'Cost code: letters, digits, "-", "." or "/" only, up to 32 characters (e.g. MAT-001).';
        }
        $category = (string) ($data['category'] ?? '');
        if (! array_key_exists($category, ProjectBudgetLine::CATEGORIES)) {
            $errors['category'] = 'Choose a budget category.';
        }
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 200) {
            $errors['description'] = 'Describe the line (up to 200 characters).';
        }
        $siteId = $this->optionalId($data['site_id'] ?? null);
        if ($siteId !== null && ! Site::where('project_id', $budget->project_id)->whereKey($siteId)->exists()) {
            $errors['site_id'] = 'Choose a site that belongs to this project and your scope.';
        }
        $itemId = $this->optionalId($data['item_id'] ?? null);
        $item = $itemId !== null ? Item::whereKey($itemId)->where('status', 'active')->first() : null;
        if ($itemId !== null && ! $item) {
            $errors['item_id'] = 'Choose an active item from the Item master.';
        }
        $unitId = $this->optionalId($data['unit_id'] ?? null) ?? $item?->unit_id;
        if ($unitId !== null && ! Unit::whereKey($unitId)->exists()) {
            $errors['unit_id'] = 'Choose a valid unit.';
        }
        $quantity = $this->optionalNumber($data['quantity'] ?? null);
        $rate = $this->optionalNumber($data['rate'] ?? null);
        if ($quantity !== null && $quantity < 0) {
            $errors['quantity'] = 'Quantity cannot be negative.';
        }
        if ($rate !== null && $rate < 0) {
            $errors['rate'] = 'Rate cannot be negative.';
        }
        if ($quantity !== null && $rate !== null) {
            $amount = round($quantity * $rate, 2);
        } else {
            $lump = $this->optionalNumber($data['amount'] ?? null);
            if ($lump === null || $lump < 0) {
                $errors['amount'] = 'Enter quantity and rate, or a lump-sum amount for the line.';
            }
            $amount = round((float) $lump, 2);
            $quantity = $rate = null;   // a lump sum carries no partial quantity / rate
        }
        $accountId = $this->optionalId($data['chart_of_account_id'] ?? null);
        if ($accountId !== null) {
            $account = ChartOfAccount::whereKey($accountId)->first();
            if (! $account || $account->status !== 'active' || $account->account_type !== 'expense') {
                $errors['chart_of_account_id'] = 'Map an active expense (cost) account, or leave the account empty for a planning-only line.';
            } else {
                $other = ProjectBudgetLine::where('project_budget_id', $budget->id)->where('chart_of_account_id', $accountId)
                    ->where(fn ($q) => $q->where('cost_code', '!=', $costCode)->orWhere('category', '!=', $category))
                    ->when($ignoreLineId, fn ($q) => $q->whereKeyNot($ignoreLineId))->first();
                if ($other) {
                    $errors['chart_of_account_id'] = 'Account '.$account->label().' is already mapped to cost code '.$other->cost_code.' ('.$other->category.') in this version. One account belongs to one category/cost-code group, so posted cost is never counted twice.';
                }
            }
        }
        if ($errors !== []) {
            if ($finalizing) {
                $this->invalid('budget', 'Line '.$costCode.' '.$description.' is not valid: '.implode(' ', $errors).' Correct the draft before setting it as current.');
            }
            throw ValidationException::withMessages($errors);
        }

        return [
            'cost_code' => $costCode,
            'category' => $category,
            'description' => $description,
            'site_id' => $siteId,
            'item_id' => $itemId,
            'unit_id' => $unitId,
            'quantity' => $quantity,
            'rate' => $rate,
            'amount' => $amount,
            'chart_of_account_id' => $accountId,
            'notes' => trim((string) ($data['notes'] ?? '')) !== '' ? mb_substr(trim($data['notes']), 0, 1000) : null,
        ];
    }

    public function recalculate(ProjectBudget $budget): void
    {
        $totals = $budget->lines()->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as line_total')->first();
        $budget->update(['total_amount' => round((float) $totals->total, 2), 'line_count' => (int) $totals->line_total]);
    }

    // ------------------------------------------------------------------ helpers

    private function lockProject(Project $project): Project
    {
        return Project::withoutGlobalScopes()->whereKey($project->id)->lockForUpdate()->firstOrFail();
    }

    private function lockDraft(ProjectBudget $budget): ProjectBudget
    {
        $locked = ProjectBudget::whereKey($budget->id)->lockForUpdate()->firstOrFail();
        if (! $locked->isDraft()) {
            $this->invalid('budget', 'This budget version is '.$locked->statusLabel().' and cannot be changed. Create a revision of the current budget to plan changes.');
        }

        return $locked;
    }

    private function reference(Project $project, int $version): string
    {
        return mb_substr($project->code ?: 'PRJ-'.$project->id, 0, 32).'-B'.str_pad((string) $version, 2, '0', STR_PAD_LEFT);
    }

    private function optionalId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function optionalNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : -1.0;
    }

    private function log(Project $project, int $actorId, string $action, string $detail): void
    {
        ActivityLog::create(['user_id' => $actorId, 'user_name' => User::find($actorId)?->name ?? 'System', 'module' => 'Projects',
            'action' => $action, 'description' => '[Project #'.$project->id.'] '.$project->code.' · '.$detail, 'status' => 'success']);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
