<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $workflows = ApprovalWorkflow::with(['department', 'steps.approverRole', 'steps.escalationRole'])
            ->withCount('steps')
            ->orderBy('name')
            ->get();

        $selectedWorkflow = $request->filled('workflow')
            ? $workflows->firstWhere('id', $request->integer('workflow'))
            : $workflows->first();

        return view('admin.roles.approval-workflows', [
            'workflows' => $workflows,
            'selectedWorkflow' => $selectedWorkflow,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.approval-workflows-create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $steps] = $this->validated($request);

        $workflow = DB::transaction(function () use ($data, $steps) {
            $workflow = ApprovalWorkflow::create($data);
            $workflow->steps()->createMany($steps);

            return $workflow;
        });

        ActivityLog::record($request, 'Workflows', 'Created approval workflow', $workflow->name.' ('.count($steps).' steps)');

        return redirect()
            ->route('admin.roles.approval-workflows.index', ['workflow' => $workflow->id])
            ->with('status', 'Workflow "'.$workflow->name.'" created successfully.');
    }

    public function edit(ApprovalWorkflow $approval_workflow): View
    {
        $approval_workflow->load('steps');

        return view('admin.roles.approval-workflows-edit', ['workflow' => $approval_workflow] + $this->formOptions());
    }

    public function update(Request $request, ApprovalWorkflow $approval_workflow): RedirectResponse
    {
        [$data, $steps] = $this->validated($request);

        DB::transaction(function () use ($approval_workflow, $data, $steps) {
            $approval_workflow->update($data);
            $approval_workflow->steps()->delete();
            $approval_workflow->steps()->createMany($steps);
        });

        ActivityLog::record($request, 'Workflows', 'Updated approval workflow', $approval_workflow->name.' ('.count($steps).' steps)');

        return redirect()
            ->route('admin.roles.approval-workflows.index', ['workflow' => $approval_workflow->id])
            ->with('status', 'Workflow "'.$approval_workflow->name.'" updated successfully.');
    }

    public function destroy(Request $request, ApprovalWorkflow $approval_workflow): RedirectResponse
    {
        $name = $approval_workflow->name;
        $approval_workflow->delete();

        ActivityLog::record($request, 'Workflows', 'Deleted approval workflow', $name);

        return redirect()
            ->route('admin.roles.approval-workflows.index')
            ->with('status', 'Workflow "'.$name.'" deleted successfully.');
    }

    /**
     * @return array{0: array, 1: array} Workflow attributes and normalized step rows.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'module' => ['required', 'string', 'max:100'],
            'trigger_action' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'scope' => ['required', 'string', 'max:100'],
            'auto_posting' => ['required', 'string', 'max:100'],
            'notify_requester' => ['nullable', 'boolean'],
            'lock_after_approval' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_no' => ['required', 'integer', 'min:1', 'max:20'],
            'steps.*.approver_role_id' => ['required', 'exists:roles,id'],
            'steps.*.approver_user_id' => ['nullable', 'exists:users,id'],
            'steps.*.is_required' => ['nullable', 'boolean'],
            'steps.*.amount_limit' => ['nullable', 'numeric', 'min:0'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'steps.*.escalation_role_id' => ['nullable', 'exists:roles,id'],
            'steps.*.can_reject' => ['nullable', 'boolean'],
            'steps.*.can_send_back' => ['nullable', 'boolean'],
        ], [
            'steps.required' => 'Add at least one approval step.',
        ]);

        $steps = collect($data['steps'])
            ->sortBy('step_no')
            ->values()
            ->map(fn (array $step, int $index) => [
                'step_no' => $index + 1,
                'approver_role_id' => $step['approver_role_id'],
                'approver_user_id' => $step['approver_user_id'] ?? null,
                'is_required' => (bool) ($step['is_required'] ?? true),
                'amount_limit' => $step['amount_limit'] ?? null,
                'sla_hours' => $step['sla_hours'] ?? 24,
                'escalation_role_id' => $step['escalation_role_id'] ?? null,
                'can_reject' => (bool) ($step['can_reject'] ?? true),
                'can_send_back' => (bool) ($step['can_send_back'] ?? true),
            ])
            ->all();

        unset($data['steps']);
        $data['notify_requester'] = $request->boolean('notify_requester');
        $data['lock_after_approval'] = $request->boolean('lock_after_approval');

        return [$data, $steps];
    }

    private function formOptions(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::orderBy('level')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'modules' => ['Site Expenses', 'Purchase Request', 'Payroll', 'Leave Request', 'Inventory Transfer', 'Equipment Maintenance'],
            'triggers' => ['Expense Submitted', 'Request Created', 'Payroll Generated', 'Transfer Requested', 'Maintenance Reported'],
            'scopes' => ['Assigned Project/Site', 'All Projects', 'All Company', 'Branch Level'],
            'postings' => ['No Auto Posting', 'Create Accounting Entry'],
        ];
    }
}
