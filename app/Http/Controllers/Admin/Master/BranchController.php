<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::with('manager')
            ->withCount('projects')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.branches.index', [
            'branches' => $branches,
            'cities' => Branch::query()->distinct()->orderBy('city')->pluck('city')->filter(),
            'totalBranches' => Branch::count(),
            'activeBranches' => Branch::where('status', 'active')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.branches.create', ['managers' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = Branch::create($this->validated($request));

        ActivityLog::record($request, 'Branches', 'Created branch', $branch->name);

        return redirect()->route('admin.master.branches.index')->with('status', 'Branch "'.$branch->name.'" created successfully.');
    }

    public function show(Branch $branch): View
    {
        $branch->load(['manager', 'projects.customer', 'warehouses']);

        return view('admin.master.branches.show', ['branch' => $branch]);
    }

    public function edit(Branch $branch): View
    {
        return view('admin.master.branches.edit', [
            'branch' => $branch,
            'managers' => User::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validated($request, $branch));

        ActivityLog::record($request, 'Branches', 'Updated branch', $branch->name);

        return redirect()->route('admin.master.branches.index')->with('status', 'Branch "'.$branch->name.'" updated successfully.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        if ($branch->projects()->exists() || $branch->warehouses()->exists()) {
            return back()->withErrors(['branch' => 'This branch still has projects or warehouses attached.']);
        }

        $name = $branch->name;
        $branch->delete();

        ActivityLog::record($request, 'Branches', 'Deleted branch', $name);

        return redirect()->route('admin.master.branches.index')->with('status', 'Branch "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'.($branch ? ','.$branch->id : '')],
            'city' => ['nullable', 'string', 'max:100'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
