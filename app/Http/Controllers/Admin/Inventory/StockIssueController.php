<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Project;
use App\Models\Site;
use App\Models\StockIssue;
use App\Models\Warehouse;
use App\Services\Accounting\PostingService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockIssueController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
        private readonly PostingService $posting
    ) {
    }

    public function index(Request $request): View
    {
        $issues = StockIssue::with(['warehouse', 'project', 'site', 'requester'])
            ->withCount('lines')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('issue_number', 'like', "%{$search}%")->orWhere('purpose', 'like', "%{$search}%"));
            })
            ->when($request->filled('warehouse'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('admin.inventory.stock-issues.index', [
            'issues' => $issues,
            'draftCount' => StockIssue::where('status', 'draft')->count(),
            'postedCount' => StockIssue::where('status', 'posted')->count(),
            'issuedValue' => round((float) StockIssue::where('status', 'posted')->sum('total_cost'), 2),
            'issuedThisMonth' => round((float) StockIssue::where('status', 'posted')->whereDate('issue_date', '>=', now()->startOfMonth())->sum('total_cost'), 2),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.inventory.stock-issues.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        $issue = DB::transaction(function () use ($data, $lines, $request) {
            $issue = StockIssue::create($data + [
                'issue_number' => StockIssue::nextNumber((int) date('Y', strtotime($data['issue_date']))),
                'requested_by' => $request->user()->id,
            ]);
            $issue->lines()->createMany($lines);

            return $issue;
        });

        ActivityLog::record($request, 'Inventory', 'Created stock issue', $issue->issue_number);

        return redirect()->route('admin.inventory.stock-issues.show', $issue)
            ->with('status', 'Stock issue "'.$issue->issue_number.'" saved. Post it to reduce warehouse stock.');
    }

    public function show(StockIssue $stock_issue): View
    {
        $stock_issue->load(['lines.item.unit', 'warehouse', 'project', 'site', 'requester', 'approver', 'journalEntry.lines.account']);

        return view('admin.inventory.stock-issues.show', ['issue' => $stock_issue]);
    }

    public function edit(StockIssue $stock_issue): View
    {
        if (! $stock_issue->isEditable()) {
            abort(403, 'A posted stock issue is read-only.');
        }

        $stock_issue->load('lines');

        return view('admin.inventory.stock-issues.edit', ['issue' => $stock_issue] + $this->formOptions());
    }

    public function update(Request $request, StockIssue $stock_issue): RedirectResponse
    {
        if (! $stock_issue->isEditable()) {
            return back()->withErrors(['issue' => 'A posted stock issue is read-only.']);
        }

        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($stock_issue, $data, $lines) {
            $stock_issue->update($data);
            $stock_issue->lines()->delete();
            $stock_issue->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Inventory', 'Updated stock issue', $stock_issue->issue_number);

        return redirect()->route('admin.inventory.stock-issues.show', $stock_issue)
            ->with('status', 'Stock issue updated successfully.');
    }

    public function destroy(Request $request, StockIssue $stock_issue): RedirectResponse
    {
        if (! $stock_issue->isEditable()) {
            return back()->withErrors(['issue' => 'A posted stock issue cannot be deleted.']);
        }

        $number = $stock_issue->issue_number;
        $stock_issue->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted stock issue', $number);

        return redirect()->route('admin.inventory.stock-issues.index')
            ->with('status', 'Stock issue "'.$number.'" deleted successfully.');
    }

    /**
     * Posting an issue decreases stock at average cost and charges the project.
     * A shortfall on any line aborts the whole document.
     */
    public function post(Request $request, StockIssue $stock_issue): RedirectResponse
    {
        if ($stock_issue->status !== 'draft') {
            return back()->withErrors(['issue' => 'This stock issue is already posted.']);
        }

        $stock_issue->load('lines.item');

        if ($stock_issue->lines->isEmpty()) {
            return back()->withErrors(['issue' => 'Add at least one line before posting.']);
        }

        try {
            DB::transaction(function () use ($stock_issue, $request) {
                $total = 0.0;

                foreach ($stock_issue->lines as $line) {
                    if (! $line->item) {
                        continue;
                    }

                    $entry = $this->stock->issue($line->item, $stock_issue->warehouse_id, (float) $line->quantity, [
                        'movement_type' => 'issue',
                        'reference_type' => StockIssue::class,
                        'reference_id' => $stock_issue->id,
                        'reference_number' => $stock_issue->issue_number,
                        'movement_date' => $stock_issue->issue_date->toDateString(),
                        'project_id' => $stock_issue->project_id,
                        'site_id' => $stock_issue->site_id,
                        'created_by' => $request->user()->id,
                    ]);

                    $line->update([
                        'unit_cost' => $entry->unit_cost,
                        'total_cost' => round((float) $line->quantity * (float) $entry->unit_cost, 2),
                    ]);

                    $total += (float) $line->total_cost;
                }

                $stock_issue->update([
                    'total_cost' => round($total, 2),
                    'status' => 'posted',
                    'approved_by' => $request->user()->id,
                ]);

                $entry = $this->posting->postStockIssue($stock_issue->fresh('lines.item'), $request->user()->id);

                $stock_issue->update([
                    'accounting_posted' => (bool) $entry,
                    'journal_entry_id' => $entry?->id,
                ]);
            });
        } catch (InsufficientStockException $exception) {
            return back()->withErrors(['issue' => $exception->getMessage()]);
        }

        ActivityLog::record($request, 'Inventory', 'Posted stock issue', $stock_issue->issue_number);

        return redirect()->route('admin.inventory.stock-issues.show', $stock_issue)
            ->with('status', 'Stock issue posted. Warehouse stock has been reduced and the project charged.');
    }

    /**
     * @return array{0: array, 1: array} Header data and normalized line rows.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'issue_date' => ['required', 'date'],
            'purpose' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ], ['lines.required' => 'Add at least one issued item.']);

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['item_id'] ?? null) && (float) ($line['quantity'] ?? 0) > 0)
            ->map(fn ($line) => [
                'item_id' => $line['item_id'],
                'quantity' => (float) $line['quantity'],
                'unit_cost' => 0,
                'total_cost' => 0,
            ])->values()->all();

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line with an item and a quantity.']);
        }

        unset($data['lines']);
        $data['status'] = 'draft';

        return [$data, $lines];
    }

    private function formOptions(): array
    {
        return [
            'warehouses' => Warehouse::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(),
            'statuses' => StockIssue::STATUSES,
        ];
    }
}
