<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\ProjectBudgetLine;
use App\Models\Site;
use App\Models\Unit;
use App\Services\Projects\ProjectBudgetReport;
use App\Services\Projects\ProjectBudgetService;
use App\Support\Workspace\DocumentActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Project Budget / BOQ workspace (Phase 1). The route project is authoritative:
 * every budget, line and site is resolved through it, so a forged id from
 * another project is simply not found. Reads never write; writes are explicit
 * business actions on a draft version.
 */
class ProjectBudgetController extends Controller
{
    public function __construct(private readonly ProjectBudgetService $service, private readonly ProjectBudgetReport $report) {}

    public function show(Request $request, Project $project): View
    {
        $request->validate([
            'version' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'], 'site' => ['nullable', 'integer'],
            'category' => ['nullable', Rule::in(array_keys(ProjectBudgetLine::CATEGORIES))], 'cost_code' => ['nullable', 'string', 'max:32'], 'project_id' => ['prohibited'],
        ]);
        $user = $request->user();
        $project->load(['customer', 'manager', 'currentBudget']);
        $versions = $project->budgets()->with(['creator', 'finalizer', 'basedOn'])->get();
        $budget = $request->filled('version')
            ? $versions->firstWhere('version', $request->integer('version'))
            : ($versions->firstWhere('status', ProjectBudget::STATUS_DRAFT) ?? $versions->firstWhere('is_current', true) ?? $versions->firstWhere('status', ProjectBudget::STATUS_SUPERSEDED));
        abort_if($request->filled('version') && ! $budget, 404);
        $siteId = $request->integer('site') ?: null;
        abort_if($siteId && ! Site::where('project_id', $project->id)->whereKey($siteId)->exists(), 404);
        $costCode = strtoupper(trim((string) $request->input('cost_code', '')));

        $lines = $budget
            ? $budget->lines()->with(['site', 'item.unit', 'unit', 'account'])
                ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
                ->when($costCode !== '', fn ($q) => $q->where('cost_code', 'like', $costCode.'%'))
                ->paginate(25)->withQueryString()->fragment('lines')
            : null;
        $canFinance = $user->hasPermission('Financial Reports', 'view');
        $report = $budget ? $this->report->build($project, $budget, $siteId) : null;

        return view('admin.master.projects.budget.show', [
            'project' => $project,
            'budget' => $budget,
            'versions' => $versions,
            'lines' => $lines,
            'report' => $report,
            'siteId' => $siteId,
            'costCodeFilter' => $costCode,
            'sites' => Site::where('project_id', $project->id)->orderBy('name')->get(),
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(['id', 'item_code', 'name', 'unit_id']),
            'units' => Unit::orderBy('name')->get(['id', 'code', 'name']),
            'accounts' => ChartOfAccount::where('account_type', 'expense')->where('status', 'active')->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'canFinance' => $canFinance,
            'canEdit' => $user->hasPermission('Projects', 'edit'),
            'canFinalize' => $user->hasPermission('Projects', 'approve'),
            'canDelete' => $user->hasPermission('Projects', 'delete'),
            'canExport' => $canFinance && $user->hasPermission('Projects', 'export'),
            // Budget events carry the project token and the version reference; the latest 15 the viewer may see.
            'activity' => DocumentActivity::latest($user, ['[Project #'.$project->id.'] '.$project->code.' ·'], ['Projects'], 15),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000']]);
        if ($project->budgets()->where('status', ProjectBudget::STATUS_DRAFT)->exists()) {
            return back()->withErrors(['budget' => 'A draft version already exists for this project. Continue on that draft or discard it first.']);
        }
        if ($project->budgets()->where('is_current', true)->exists()) {
            return back()->withErrors(['budget' => 'This project already has a current budget. Create a revision of it instead of a new baseline.']);
        }
        $budget = $this->service->createDraft($project, $data, $request->user()->id);

        return redirect()->to($this->budgetUrl($project, $budget, 'lines'))->with('status', 'Draft budget '.$budget->reference.' created. Add BOQ lines, then Set as Current.');
    }

    public function storeLine(Request $request, Project $project, int $budget): RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        try {
            $line = $this->service->addLine($version, $this->lineInput($request), $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->to($this->budgetUrl($project, $version, 'lines'))->with('status', 'Line '.$line->cost_code.' added (SAR '.number_format((float) $line->amount, 2).').');
    }

    public function editLine(Request $request, Project $project, int $budget, int $line): View|RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        $row = $version->lines()->findOrFail($line);
        if (! $version->isDraft()) {
            return redirect()->to($this->budgetUrl($project, $version, 'lines'))->withErrors(['budget' => 'This version is '.$version->statusLabel().'; its lines are read-only. Create a revision to plan changes.']);
        }

        return view('admin.master.projects.budget.line', [
            'project' => $project, 'budget' => $version, 'line' => $row,
            'sites' => Site::where('project_id', $project->id)->orderBy('name')->get(),
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(['id', 'item_code', 'name', 'unit_id']),
            'units' => Unit::orderBy('name')->get(['id', 'code', 'name']),
            'accounts' => ChartOfAccount::where('account_type', 'expense')->where('status', 'active')->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'returnTo' => $this->budgetUrl($project, $version, 'lines'),
        ]);
    }

    public function updateLine(Request $request, Project $project, int $budget, int $line): RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        $row = $version->lines()->findOrFail($line);
        try {
            $row = $this->service->updateLine($row, $this->lineInput($request), $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->to($this->budgetUrl($project, $version, 'lines'))->with('status', 'Line '.$row->cost_code.' updated (SAR '.number_format((float) $row->amount, 2).').');
    }

    public function copyLine(Request $request, Project $project, int $budget, int $line): RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        $row = $version->lines()->findOrFail($line);
        try {
            $copy = $this->service->copyLine($row, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->to($this->budgetUrl($project, $version, 'lines'))->with('status', 'Line '.$copy->cost_code.' copied; edit the copy as needed.');
    }

    public function destroyLine(Request $request, Project $project, int $budget, int $line): RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        $row = $version->lines()->findOrFail($line);
        try {
            $this->service->deleteLine($row, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->to($this->budgetUrl($project, $version, 'lines'))->with('status', 'Line removed from the draft.');
    }

    /** Set as Current: freezes the draft and supersedes the previous current version. */
    public function finalize(Request $request, Project $project, int $budget): RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        try {
            $version = $this->service->finalize($version->id, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->to($this->budgetUrl($project, $version, 'summary'))->with('status', $version->reference.' is now the current budget (SAR '.number_format((float) $version->total_amount, 2).'). No accounting entry was created.');
    }

    public function revise(Request $request, Project $project, int $budget): RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:150']]);
        try {
            $draft = $this->service->revise($version, $request->user()->id, $data['title'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->to($this->budgetUrl($project, $draft, 'lines'))->with('status', 'Revision '.$draft->reference.' created as a draft with '.$draft->line_count.' lines; '.$version->reference.' stays current until the draft is set as current.');
    }

    public function destroy(Request $request, Project $project, int $budget): RedirectResponse
    {
        $version = $project->budgets()->findOrFail($budget);
        try {
            $this->service->discardDraft($version, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('admin.master.projects.budget.show', $project)->with('status', 'Draft '.$version->reference.' discarded; it stays in the version history as read-only. Current and superseded versions are kept.');
    }

    /** Budget vs Actual as CSV (Projects export + Financial Reports view; exact figures of the screen). */
    public function export(Request $request, Project $project, int $budget): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('Financial Reports', 'view'), 403, 'Budget vs Actual export needs Financial Reports view.');
        $version = $project->budgets()->findOrFail($budget);
        $request->validate(['site' => ['nullable', 'integer']]);
        $siteId = $request->integer('site') ?: null;
        abort_if($siteId && ! Site::where('project_id', $project->id)->whereKey($siteId)->exists(), 404);
        $rows = $this->report->csvRows($this->report->build($project, $version, $siteId));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'budget-vs-actual-'.$version->reference.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /** Shape-only validation; business validation (ownership, accounts, amounts) lives in the service. */
    private function lineInput(Request $request): array
    {
        return $request->validate([
            'cost_code' => ['required', 'string', 'max:32'],
            'category' => ['required', 'string', 'max:32'],
            'description' => ['required', 'string', 'max:200'],
            'site_id' => ['nullable', 'integer'],
            'item_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric'],
            'rate' => ['nullable', 'numeric'],
            'amount' => ['nullable', 'numeric'],
            'chart_of_account_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'project_id' => ['prohibited'],
        ]);
    }

    private function budgetUrl(Project $project, ProjectBudget $budget, string $section): string
    {
        return route('admin.master.projects.budget.show', [$project, 'version' => $budget->version]).'#'.$section;
    }
}
