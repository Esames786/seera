<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProjectClassification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Project classifications (CR-15). Normally created from the "+ New" dialog on
 * the project form; this small screen is the maintenance list.
 */
class ProjectClassificationController extends Controller
{
    public function index(): View
    {
        return view('admin.master.project-classifications.index', [
            'classifications' => ProjectClassification::withCount('projects')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $classification = ProjectClassification::create($this->validated($request));

        ActivityLog::record($request, 'Projects', 'Created project classification', $classification->name);

        if ($request->wantsJson()) {
            return response()->json(['id' => $classification->id, 'label' => $classification->name], 201);
        }

        return redirect()->route('admin.master.project-classifications.index')
            ->with('status', 'Classification "'.$classification->name.'" created.');
    }

    public function update(Request $request, ProjectClassification $project_classification): RedirectResponse
    {
        $project_classification->update($this->validated($request, $project_classification));

        ActivityLog::record($request, 'Projects', 'Updated project classification', $project_classification->name);

        return redirect()->route('admin.master.project-classifications.index')
            ->with('status', 'Classification "'.$project_classification->name.'" updated.');
    }

    public function destroy(Request $request, ProjectClassification $project_classification): RedirectResponse
    {
        if ($project_classification->projects()->exists()) {
            return back()->withErrors(['classification' => 'Projects still use this classification. Mark it inactive instead.']);
        }

        $name = $project_classification->name;
        $project_classification->delete();

        ActivityLog::record($request, 'Projects', 'Deleted project classification', $name);

        return redirect()->route('admin.master.project-classifications.index')
            ->with('status', 'Classification "'.$name.'" deleted.');
    }

    private function validated(Request $request, ?ProjectClassification $classification = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:project_classifications,name'.($classification ? ','.$classification->id : '')],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ], ['name.unique' => 'That classification already exists; pick it from the list.']);

        $data['status'] = $data['status'] ?? 'active';

        return $data;
    }
}
