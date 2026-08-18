<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Site;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = PurchaseRequest::with(['requester', 'project', 'warehouse'])
            ->withCount('lines')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('pr_number', 'like', "%{$search}%")->orWhere('reason', 'like', "%{$search}%"));
            })
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('request_date')->orderByDesc('id')
            ->paginate(10)->withQueryString();

        return view('admin.inventory.purchase-requests.index', [
            'requests' => $requests,
            'pendingCount' => PurchaseRequest::where('status', 'pending')->count(),
            'approvedCount' => PurchaseRequest::where('status', 'approved')->count(),
            'rejectedCount' => PurchaseRequest::where('status', 'rejected')->count(),
            'estimatedValue' => round((float) PurchaseRequest::whereIn('status', ['pending', 'approved'])->sum('estimated_total'), 2),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.inventory.purchase-requests.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        $pr = DB::transaction(function () use ($data, $lines, $request) {
            $pr = PurchaseRequest::create($data + [
                'pr_number' => PurchaseRequest::nextNumber((int) date('Y', strtotime($data['request_date']))),
                'requested_by' => $request->user()->id,
            ]);
            $pr->lines()->createMany($lines);

            return $pr;
        });

        ActivityLog::record($request, 'Inventory', 'Created purchase request', $pr->pr_number);

        return redirect()->route('admin.inventory.purchase-requests.show', $pr)
            ->with('status', 'Purchase request "'.$pr->pr_number.'" created successfully.');
    }

    public function show(PurchaseRequest $purchase_request): View
    {
        $purchase_request->load(['lines.item', 'lines.unit', 'requester', 'approver', 'project', 'site', 'warehouse', 'purchaseOrders.supplier']);

        return view('admin.inventory.purchase-requests.show', ['pr' => $purchase_request]);
    }

    public function edit(PurchaseRequest $purchase_request): View
    {
        if (! $purchase_request->isEditable()) {
            abort(403, 'An approved or rejected purchase request can no longer be edited.');
        }

        $purchase_request->load('lines');

        return view('admin.inventory.purchase-requests.edit', ['pr' => $purchase_request] + $this->formOptions());
    }

    public function update(Request $request, PurchaseRequest $purchase_request): RedirectResponse
    {
        if (! $purchase_request->isEditable()) {
            return back()->withErrors(['pr' => 'An approved or rejected purchase request can no longer be edited.']);
        }

        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($purchase_request, $data, $lines) {
            $purchase_request->update($data);
            $purchase_request->lines()->delete();
            $purchase_request->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Inventory', 'Updated purchase request', $purchase_request->pr_number);

        return redirect()->route('admin.inventory.purchase-requests.show', $purchase_request)
            ->with('status', 'Purchase request updated successfully.');
    }

    public function destroy(Request $request, PurchaseRequest $purchase_request): RedirectResponse
    {
        if (! $purchase_request->isEditable()) {
            return back()->withErrors(['pr' => 'An approved or rejected purchase request cannot be deleted.']);
        }

        $number = $purchase_request->pr_number;
        $purchase_request->delete();

        ActivityLog::record($request, 'Inventory', 'Deleted purchase request', $number);

        return redirect()->route('admin.inventory.purchase-requests.index')
            ->with('status', 'Purchase request "'.$number.'" deleted successfully.');
    }

    public function approve(Request $request, PurchaseRequest $purchase_request): RedirectResponse
    {
        if (! in_array($purchase_request->status, ['draft', 'pending'], true)) {
            return back()->withErrors(['pr' => 'Only a draft or pending purchase request can be approved.']);
        }

        $purchase_request->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        ActivityLog::record($request, 'Inventory', 'Approved purchase request', $purchase_request->pr_number);

        return back()->with('status', 'Purchase request "'.$purchase_request->pr_number.'" approved and ready to convert into a purchase order.');
    }

    public function reject(Request $request, PurchaseRequest $purchase_request): RedirectResponse
    {
        if (! in_array($purchase_request->status, ['draft', 'pending'], true)) {
            return back()->withErrors(['pr' => 'Only a draft or pending purchase request can be rejected.']);
        }

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        $purchase_request->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        ActivityLog::record($request, 'Inventory', 'Rejected purchase request', $purchase_request->pr_number);

        return back()->with('status', 'Purchase request "'.$purchase_request->pr_number.'" rejected.');
    }

    /**
     * @return array{0: array, 1: array} Header data and normalized line rows.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'reason' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,pending'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_id' => ['nullable', 'exists:units,id'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.budget_line' => ['nullable', 'string', 'max:100'],
        ], ['lines.required' => 'Add at least one requested item.']);

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['item_id'] ?? null) && (float) ($line['quantity'] ?? 0) > 0)
            ->map(function ($line) {
                $quantity = (float) $line['quantity'];
                $cost = (float) ($line['estimated_unit_cost'] ?? 0);

                return [
                    'item_id' => $line['item_id'],
                    'description' => $line['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_id' => $line['unit_id'] ?? null,
                    'estimated_unit_cost' => $cost,
                    'estimated_total' => round($quantity * $cost, 2),
                    'budget_line' => $line['budget_line'] ?? null,
                ];
            })->values()->all();

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line with an item and a quantity.']);
        }

        unset($data['lines']);
        $data['estimated_total'] = round(array_sum(array_column($lines, 'estimated_total')), 2);

        return [$data, $lines];
    }

    private function formOptions(): array
    {
        return [
            'items' => Item::where('status', 'active')->orderBy('item_code')->get(),
            'units' => Unit::where('status', 'active')->orderBy('code')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'priorities' => PurchaseRequest::PRIORITIES,
            'statuses' => PurchaseRequest::STATUSES,
        ];
    }
}
