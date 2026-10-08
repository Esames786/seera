<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Project;
use App\Models\Site;
use App\Models\Warehouse;
use App\Support\LinkedIdentityNavigation;
use App\Support\SaveAction;
use App\Support\Workspace\InventoryWorkspace as Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        abort_unless(in_array($request->user()->effectiveAccessScope(), ['company', 'project', 'site'], true), 403);
        $warehouse = Warehouse::create($this->validated($request));

        ActivityLog::record($request, 'Warehouses', 'Created warehouse', '[Warehouse #'.$warehouse->id.'] '.$warehouse->code);

        return $this->saved($request, $warehouse)->with('status', 'Warehouse "'.$warehouse->name.'" created successfully.');
    }

    public function show(Warehouse $warehouse): View
    {
        return view('admin.master.warehouses.show', Workspace::data($warehouse));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('admin.master.warehouses.edit', Workspace::data($warehouse) + $this->formOptions());
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validated($request, $warehouse));

        ActivityLog::record($request, 'Warehouses', 'Updated warehouse', '[Warehouse #'.$warehouse->id.'] '.$warehouse->code);

        return $this->saved($request, $warehouse)->with('status', 'Warehouse "'.$warehouse->name.'" updated successfully.');
    }

    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $name = $warehouse->name;
        $warehouse->update(['status' => 'inactive']);

        ActivityLog::record($request, 'Warehouses', 'Deactivated warehouse', '[Warehouse #'.$warehouse->id.'] '.$warehouse->code);

        return redirect()->route('admin.master.warehouses.index')->with('status', __('inventory_workspace.deactivated'));
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        if ($warehouse) {
            foreach (['project_id', 'site_id'] as $field) {
                if ($request->exists($field) && (string) $request->input($field) !== (string) $warehouse->$field) {
                    throw ValidationException::withMessages([$field => __('inventory_workspace.ownership_fixed')]);
                }
                $request->merge([$field => $warehouse->$field]);
            }
        } else {
            if ($request->filled('project_id')) {
                Project::findOrFail($request->integer('project_id'));
            }
            if ($request->filled('site_id')) {
                Site::whereKey($request->integer('site_id'))->where('project_id', $request->input('project_id'))->firstOrFail();
            }
        }
        if ($request->filled('incharge_id') && (int) $request->input('incharge_id') !== (int) $warehouse?->incharge_id) {
            abort_unless(app(LinkedIdentityNavigation::class)->users($request->user())->whereKey($request->integer('incharge_id'))->exists(), 403);
        }

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
            'users' => app(LinkedIdentityNavigation::class)->users(auth()->user())->orderBy('name')->get(),
        ];
    }

    private function saved(Request $request, Warehouse $warehouse): RedirectResponse
    {
        return SaveAction::redirect($request, ['stay' => route('admin.master.warehouses.edit', [$warehouse, 'return_to' => SaveAction::returnTo($request)]), 'close' => route('admin.master.warehouses.index')]
            + ($request->user()->hasPermission('Warehouses', 'create') ? ['new' => route('admin.master.warehouses.create')] : []));
    }
}
