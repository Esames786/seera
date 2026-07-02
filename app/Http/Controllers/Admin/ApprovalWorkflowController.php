<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $workflows = ApprovalWorkflow::with(['department', 'steps.approverRole', 'steps.escalationRole'])
            ->orderBy('name')
            ->get();

        $selectedWorkflow = $request->filled('workflow')
            ? $workflows->firstWhere('id', $request->integer('workflow'))
            : $workflows->first();

        return view('admin.roles.approval-workflows', [
            'workflows' => $workflows,
            'selectedWorkflow' => $selectedWorkflow,
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::orderBy('level')->orderBy('name')->get(),
        ]);
    }
}
