<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CostCenterController extends Controller
{
    public function index(Request $request): View
    {
        $costCenters = CostCenter::with('manager')
            ->withCount('journalLines')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.accounting.cost-centers.index', [
            'costCenters' => $costCenters,
            'totalCostCenters' => CostCenter::count(),
            'projectCostCenters' => CostCenter::where('type', 'project')->count(),
            'siteCostCenters' => CostCenter::where('type', 'site')->count(),
            'activeCostCenters' => CostCenter::where('status', 'active')->count(),
            'types' => CostCenter::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.accounting.cost-centers.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $costCenter = CostCenter::create($this->validated($request));

        ActivityLog::record($request, 'Accounting', 'Created cost center', $costCenter->code.' - '.$costCenter->name);

        return redirect()->route('admin.accounting.cost-centers.index')
            ->with('status', 'Cost center "'.$costCenter->name.'" created successfully.');
    }

    public function show(CostCenter $cost_center): View
    {
        $cost_center->load('manager');

        return view('admin.accounting.cost-centers.show', [
            'costCenter' => $cost_center,
            'linkedRecord' => $cost_center->linkedRecord(),
            'lines' => $cost_center->journalLines()
                ->with(['account', 'journalEntry'])
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
                ->latest('id')
                ->limit(15)
                ->get(),
            'totalDebit' => round((float) $cost_center->journalLines()->sum('debit'), 2),
            'totalCredit' => round((float) $cost_center->journalLines()->sum('credit'), 2),
        ]);
    }

    public function edit(CostCenter $cost_center): View
    {
        return view('admin.accounting.cost-centers.edit', ['costCenter' => $cost_center] + $this->formOptions());
    }

    public function update(Request $request, CostCenter $cost_center): RedirectResponse
    {
        $cost_center->update($this->validated($request, $cost_center));

        ActivityLog::record($request, 'Accounting', 'Updated cost center', $cost_center->code.' - '.$cost_center->name);

        return redirect()->route('admin.accounting.cost-centers.index')
            ->with('status', 'Cost center "'.$cost_center->name.'" updated successfully.');
    }

    public function destroy(Request $request, CostCenter $cost_center): RedirectResponse
    {
        $label = $cost_center->code.' - '.$cost_center->name;

        if ($cost_center->journalLines()->exists()) {
            $cost_center->update(['status' => 'inactive']);

            ActivityLog::record($request, 'Accounting', 'Deactivated cost center', $label);

            return redirect()->route('admin.accounting.cost-centers.index')
                ->with('status', 'Cost center "'.$cost_center->name.'" has journal lines, so it was deactivated instead of deleted.');
        }

        $cost_center->delete();

        ActivityLog::record($request, 'Accounting', 'Deleted cost center', $label);

        return redirect()->route('admin.accounting.cost-centers.index')
            ->with('status', 'Cost center "'.$cost_center->name.'" deleted successfully.');
    }

    private function validated(Request $request, ?CostCenter $costCenter = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:cost_centers,code'.($costCenter ? ','.$costCenter->id : '')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:branch,department,project,site,warehouse'],
            'linked_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'types' => CostCenter::TYPES,
            'users' => User::orderBy('name')->get(),
            'linkedOptions' => [
                'branch' => Branch::orderBy('name')->get(['id', 'name']),
                'department' => Department::orderBy('name')->get(['id', 'name']),
                'project' => Project::orderBy('name')->get(['id', 'name']),
                'site' => Site::orderBy('name')->get(['id', 'name']),
                'warehouse' => Warehouse::orderBy('name')->get(['id', 'name']),
            ],
        ];
    }
}
