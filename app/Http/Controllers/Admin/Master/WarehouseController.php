<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $warehouses = Warehouse::with(['branch', 'project', 'site', 'incharge'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.warehouses.index', [
            'warehouses' => $warehouses,
            'branches' => Branch::orderBy('name')->get(),
            'totalWarehouses' => Warehouse::count(),
            'activeWarehouses' => Warehouse::where('status', 'active')->count(),
            'siteWarehouses' => Warehouse::whereNotNull('site_id')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.warehouses.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $warehouse = Warehouse::create($this->validated($request));

        ActivityLog::record($request, 'Warehouses', 'Created warehouse', $warehouse->name);

        return redirect()->route('admin.master.warehouses.index')->with('status', 'Warehouse "'.$warehouse->name.'" created successfully.');
    }

    public function show(Warehouse $warehouse): View
    {
        $warehouse->load(['branch', 'project', 'site', 'incharge']);

        return view('admin.master.warehouses.show', ['warehouse' => $warehouse]);
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('admin.master.warehouses.edit', ['warehouse' => $warehouse] + $this->formOptions());
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validated($request, $warehouse));

        ActivityLog::record($request, 'Warehouses', 'Updated warehouse', $warehouse->name);

        return redirect()->route('admin.master.warehouses.index')->with('status', 'Warehouse "'.$warehouse->name.'" updated successfully.');
    }

    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $name = $warehouse->name;
        $warehouse->delete();

        ActivityLog::record($request, 'Warehouses', 'Deleted warehouse', $name);

        return redirect()->route('admin.master.warehouses.index')->with('status', 'Warehouse "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:warehouses,code'.($warehouse ? ','.$warehouse->id : '')],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'incharge_id' => ['nullable', 'exists:users,id'],
            'valuation_method' => ['required', 'in:FIFO,Average'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ];
    }
}
