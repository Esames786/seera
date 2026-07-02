@extends('layouts.admin')

@section('title', 'Approval Workflows')
@section('breadcrumb', 'Administration / Approval Workflows')

@section('content')
    <x-admin.page-header title="Approval Workflow Builder" description="Create approval flows for ERP transactions"/>

    <div class="help-box">
        Approval workflows drive site expenses, purchase requests, leave, payroll, inventory transfers, and equipment maintenance approvals.
    </div>

    <form method="GET" class="toolbar">
        <div class="toolbar-left">
            <select class="select" style="width:260px" name="workflow" onchange="this.form.submit()">
                @foreach ($workflows as $workflow)
                    <option value="{{ $workflow->id }}" @selected($selectedWorkflow && $selectedWorkflow->id === $workflow->id)>Workflow: {{ $workflow->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="toolbar-right">
            <button type="button" class="btn primary">+ New Workflow</button>
        </div>
    </form>

    @if ($selectedWorkflow)
        <x-admin.form-section title="Workflow Basic Information" columns="3">
            <div><label>Workflow Name</label><input class="input" value="{{ $selectedWorkflow->name }}" readonly/></div>
            <div><label>Module</label><input class="input" value="{{ $selectedWorkflow->module }}" readonly/></div>
            <div><label>Trigger Action</label><input class="input" value="{{ $selectedWorkflow->trigger_action }}" readonly/></div>
            <div><label>Department</label><input class="input" value="{{ $selectedWorkflow->department?->name ?? '-' }}" readonly/></div>
            <div><label>Project/Site Scope</label><input class="input" value="{{ $selectedWorkflow->scope }}" readonly/></div>
            <div><label>Status</label><input class="input" value="{{ ucfirst($selectedWorkflow->status) }}" readonly/></div>
        </x-admin.form-section>

        <x-admin.data-table title="Approval Steps">
            <x-slot:headerActions>
                <button type="button" class="btn sm primary">+ Add Step</button>
            </x-slot:headerActions>
            <thead>
                <tr>
                    <th>Step No</th><th>Approver Role</th><th>Approver User</th><th>Required?</th>
                    <th>Amount Limit</th><th>SLA</th><th>Escalation Role</th><th>Can Reject</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($selectedWorkflow->steps as $step)
                    <tr>
                        <td>{{ $step->step_no }}</td>
                        <td>{{ $step->approverRole?->name ?? 'Any' }}</td>
                        <td>{{ $step->approver_note ?? '-' }}</td>
                        <td><span class="badge {{ $step->is_required ? 'green' : 'gray' }}">{{ $step->is_required ? 'Required' : 'Optional' }}</span></td>
                        <td>{{ $step->amount_limit ? 'Up to '.number_format($step->amount_limit).' SAR' : 'Final approval' }}</td>
                        <td>{{ $step->sla_hours }} hours</td>
                        <td>{{ $step->escalationRole?->name ?? '-' }}</td>
                        <td>{{ $step->can_reject ? 'Yes' : 'No' }}</td>
                        <td><button type="button" class="btn sm">Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="table-empty">No steps defined for this workflow yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>

        <x-admin.form-section title="Final Action After Approval" columns="3">
            <div><label>Auto Posting</label><input class="input" value="{{ $selectedWorkflow->auto_posting }}" readonly/></div>
            <div><label>Notify Requester</label><input class="input" value="{{ $selectedWorkflow->notify_requester ? 'Yes' : 'No' }}" readonly/></div>
            <div><label>Lock After Approval</label><input class="input" value="{{ $selectedWorkflow->lock_after_approval ? 'Yes' : 'No' }}" readonly/></div>
        </x-admin.form-section>

        <div class="table-card">
            <div class="table-title">Workflow Preview</div>
            <div style="padding:12px 16px">
                <div class="workflow">
                    <div class="workflow-step">Requester<br><span class="small">{{ $selectedWorkflow->trigger_action }}</span></div>
                    <div class="arrow">&rarr;</div>
                    @foreach ($selectedWorkflow->steps as $step)
                        <div class="workflow-step">{{ $step->approverRole?->name ?? 'Any' }}<br><span class="small">{{ $loop->last ? 'Final Approval' : 'Step '.$step->step_no }}</span></div>
                        <div class="arrow">&rarr;</div>
                    @endforeach
                    <div class="workflow-step">{{ $selectedWorkflow->auto_posting === 'Create Accounting Entry' ? 'Accounting Entry' : 'Completed' }}<br><span class="small">{{ $selectedWorkflow->auto_posting === 'Create Accounting Entry' ? 'Auto Post' : 'Done' }}</span></div>
                </div>
            </div>
        </div>
    @else
        <div class="table-empty">No approval workflows defined yet.</div>
    @endif
@endsection
