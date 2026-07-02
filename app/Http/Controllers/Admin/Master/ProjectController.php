<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::with(['customer', 'branch', 'manager'])
            ->withCount('sites')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.projects.index', [
            'projects' => $projects,
            'branches' => Branch::orderBy('name')->get(),
            'totalProjects' => Project::count(),
            'activeProjects' => Project::where('status', 'active')->count(),
            'totalBudget' => Project::sum('budget'),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.projects.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $project = Project::create($this->validated($request));

        ActivityLog::record($request, 'Projects', 'Created project', $project->name);

        return redirect()->route('admin.master.projects.index')->with('status', 'Project "'.$project->name.'" created successfully.');
    }

    public function show(Project $project): View
    {
        $project->load(['customer', 'branch', 'manager', 'sites.supervisor', 'warehouses']);

        return view('admin.master.projects.show', ['project' => $project]);
    }

    public function edit(Project $project): View
    {
        return view('admin.master.projects.edit', ['project' => $project] + $this->formOptions());
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validated($request, $project));

        ActivityLog::record($request, 'Projects', 'Updated project', $project->name);

        return redirect()->route('admin.master.projects.index')->with('status', 'Project "'.$project->name.'" updated successfully.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        if ($project->sites()->exists() || $project->warehouses()->exists()) {
            return back()->withErrors(['project' => 'This project still has sites or warehouses attached.']);
        }

        $name = $project->name;
        $project->delete();

        ActivityLog::record($request, 'Projects', 'Deleted project', $name);

        return redirect()->route('admin.master.projects.index')->with('status', 'Project "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:projects,code'.($project ? ','.$project->id : '')],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,planning,on hold,completed,inactive'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'managers' => User::orderBy('name')->get(),
        ];
    }
}
