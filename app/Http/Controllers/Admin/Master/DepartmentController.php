<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::with('head')
            ->withCount('users')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.master.departments.index', [
            'departments' => $departments,
            'totalDepartments' => Department::count(),
            'activeDepartments' => Department::where('status', 'active')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.departments.create', ['heads' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $department = Department::create($this->validated($request));

        ActivityLog::record($request, 'Departments', 'Created department', $department->name);

        return redirect()->route('admin.master.departments.index')->with('status', 'Department "'.$department->name.'" created successfully.');
    }

    public function show(Department $department): View
    {
        $department->load(['head', 'designations.defaultRole', 'users.designation', 'roles']);

        return view('admin.master.departments.show', ['department' => $department]);
    }

    public function edit(Department $department): View
    {
        return view('admin.master.departments.edit', [
            'department' => $department,
            'heads' => User::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department));

        ActivityLog::record($request, 'Departments', 'Updated department', $department->name);

        return redirect()->route('admin.master.departments.index')->with('status', 'Department "'.$department->name.'" updated successfully.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        if ($department->users()->exists() || $department->designations()->exists() || $department->roles()->exists()) {
            return back()->withErrors(['department' => 'This department still has users, designations, or roles attached.']);
        }

        $name = $department->name;
        $department->delete();

        ActivityLog::record($request, 'Departments', 'Deleted department', $name);

        return redirect()->route('admin.master.departments.index')->with('status', 'Department "'.$name.'" deleted successfully.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'.($department ? ','.$department->id : '')],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
