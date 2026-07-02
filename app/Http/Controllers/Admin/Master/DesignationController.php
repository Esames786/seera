<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function index(Request $request): View
    {
        $designations = Designation::with(['department', 'defaultRole'])
            ->withCount('users')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.designations.index', [
            'designations' => $designations,
            'departments' => Department::orderBy('name')->get(),
            'totalDesignations' => Designation::count(),
            'activeDesignations' => Designation::where('status', 'active')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.designations.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $designation = Designation::create($this->validated($request));

        ActivityLog::record($request, 'Designations', 'Created designation', $designation->name);

        return redirect()->route('admin.master.designations.index')->with('status', 'Designation "'.$designation->name.'" created successfully.');
    }

    public function show(Designation $designation): View
    {
        $designation->load(['department', 'defaultRole', 'users.branch']);

        return view('admin.master.designations.show', ['designation' => $designation]);
    }

    public function edit(Designation $designation): View
    {
        return view('admin.master.designations.edit', ['designation' => $designation] + $this->formOptions());
    }

    public function update(Request $request, Designation $designation): RedirectResponse
    {
        $designation->update($this->validated($request));

        ActivityLog::record($request, 'Designations', 'Updated designation', $designation->name);

        return redirect()->route('admin.master.designations.index')->with('status', 'Designation "'.$designation->name.'" updated successfully.');
    }

    public function destroy(Request $request, Designation $designation): RedirectResponse
    {
        if ($designation->users()->exists()) {
            return back()->withErrors(['designation' => 'This designation still has employees attached.']);
        }

        $name = $designation->name;
        $designation->delete();

        ActivityLog::record($request, 'Designations', 'Deleted designation', $name);

        return redirect()->route('admin.master.designations.index')->with('status', 'Designation "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'grade' => ['nullable', 'string', 'max:20'],
            'default_role_id' => ['nullable', 'exists:roles,id'],
            'mobile_access_default' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function formOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::orderBy('level')->orderBy('name')->get(),
        ];
    }
}
