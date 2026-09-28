<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectClassification;
use App\Models\Role;
use App\Models\User;
use App\Support\SaveAction;
use App\Support\Workspace\ProjectWorkspacePanels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::with(['customer', 'branch', 'manager', 'classification'])
            ->withCount('sites')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('classification'), fn ($q) => $q->where('project_classification_id', $request->integer('classification')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.projects.index', [
            'projects' => $projects,
            'branches' => Branch::orderBy('name')->get(),
            'classifications' => ProjectClassification::orderBy('name')->get(),
            'totalProjects' => Project::count(),
            'activeProjects' => Project::where('status', 'active')->count(),
            'totalBudget' => Project::sum('budget'),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.projects.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $project = Project::create($this->validated($request));

        ActivityLog::record($request, 'Projects', 'Created project', '[Project #'.$project->id.'] '.$project->code.' / '.$project->name);

        if ($request->wantsJson()) {
            return response()->json(['id' => $project->id, 'label' => $project->name], 201);
        }

        return $this->saved($request, $project);
    }

    public function show(Project $project): View
    {
        return view('admin.master.projects.show', $this->workspace($project));
    }

    public function edit(Project $project): View
    {
        return view('admin.master.projects.edit', $this->workspace($project) + $this->formOptions());
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validated($request, $project));

        ActivityLog::record($request, 'Projects', 'Updated project', '[Project #'.$project->id.'] '.$project->code.' / '.$project->name);

        return $this->saved($request, $project);
    }

    private function workspace(Project $project): array
    {
        $project->load(['customer', 'branch', 'manager', 'classification']);

        return ['project' => $project, 'panels' => ProjectWorkspacePanels::visibleDefinitions(request()->user()),
            'summary' => ProjectWorkspacePanels::summary($project, request()->user())];
    }

    private function saved(Request $request, Project $project): RedirectResponse
    {
        $origin = SaveAction::returnTo($request);

        return SaveAction::redirect($request, [
            'stay' => route('admin.master.projects.edit', [$project, 'return_to' => $origin]),
            'close' => route('admin.master.projects.index'),
            'new' => route('admin.master.projects.create'),
        ])->with('status', 'Project saved successfully.');
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
            'project_classification_id' => ['nullable', 'exists:project_classifications,id'],
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
            'classifications' => ProjectClassification::active()->orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'managers' => User::orderBy('name')->get(),
            // For the inline "new project manager account" dialog.
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::where('status', 'active')->orderBy('level')->orderBy('name')->get(),
        ];
    }
}
