<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One compact page for the three organisation masters (branches, departments,
 * designations). The client asked for these not to sit as separate menu
 * entries; day-to-day they are created inline from the user and employee
 * forms, and this page remains the maintenance route for the full lists.
 */
class OrganizationStructureController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $sections = collect([
            [
                'module' => 'Branches',
                'title' => 'Branches',
                'icon' => '🏬',
                'description' => 'Company locations that projects, users and warehouses belong to.',
                'count' => Branch::count(),
                'recent' => Branch::latest('id')->limit(6)->get(['name', 'code', 'status'])->map(fn ($b) => ['name' => $b->name, 'meta' => $b->code, 'status' => $b->status]),
                'index' => route('admin.master.branches.index'),
                'create' => route('admin.master.branches.create'),
            ],
            [
                'module' => 'Departments',
                'title' => 'Departments',
                'icon' => '🗂️',
                'description' => 'Functional areas that roles and designations are grouped under.',
                'count' => Department::count(),
                'recent' => Department::withCount('users')->latest('id')->limit(6)->get()->map(fn ($d) => ['name' => $d->name, 'meta' => $d->users_count.' users', 'status' => $d->status]),
                'index' => route('admin.master.departments.index'),
                'create' => route('admin.master.departments.create'),
            ],
            [
                'module' => 'Designations',
                'title' => 'Designations',
                'icon' => '🪪',
                'description' => 'Job titles, each attached to a department for the dependent dropdowns.',
                'count' => Designation::count(),
                'recent' => Designation::with('department')->latest('id')->limit(6)->get()->map(fn ($d) => ['name' => $d->name, 'meta' => $d->department?->name ?? '-', 'status' => $d->status]),
                'index' => route('admin.master.designations.index'),
                'create' => route('admin.master.designations.create'),
            ],
        ])->filter(fn (array $section) => $user->hasPermission($section['module'], 'view'))
            ->map(function (array $section) use ($user) {
                $section['canCreate'] = $user->hasPermission($section['module'], 'create');

                return $section;
            })
            ->values();

        return view('admin.master.organization.index', ['sections' => $sections]);
    }
}
