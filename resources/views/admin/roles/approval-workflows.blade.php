@extends('layouts.admin')

@section('title', 'Approval Workflows')
@section('breadcrumb', 'Administration / Approval Workflows')

@section('content')
    <x-admin.page-header title="Approval Workflows" description="Create approval flows for ERP transactions">
        <a class="btn primary" href="{{ route('admin.roles.approval-workflows.create') }}">+ New Workflow</a>
    </x-admin.page-header>

    <div class="help-box">
        Approval workflows drive site expenses, purchase requests, leave, payroll, inventory transfers, and equipment maintenance approvals.
    </div>

    <x-admin.data-table title="Workflows Listing">
        <thead>
            <tr>
                <th>Workflow Name</th><th>Module</th><th>Trigger</th><th>Department</th>
                <th>Scope</th><th>Steps</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($workflows as $workflow)
                <tr>
                    <td>{{ $workflow->name }}</td>
                    <td>{{ $workflow->module }}</td>
                    <td>{{ $workflow->trigger_action ?? '-' }}</td>
                    <td>{{ $workflow->department?->name ?? '-' }}</td>
                    <td>{{ $workflow->scope }}</td>
                    <td>{{ $workflow->steps_count }}</td>
                    <td><x-admin.status-badge :status="$workflow->status"/></td>
                    <td>
                        <div class="actions">
                            <a class="btn sm primary" href="{{ route('admin.roles.approval-workflows.index', ['workflow' => $workflow->id]) }}">Preview</a>
                            <a class="btn sm" href="{{ route('admin.roles.approval-workflows.edit', $workflow) }}">Edit</a>
                            <button type="button" class="btn sm danger js-delete"
                                    data-delete-url="{{ route('admin.roles.approval-workflows.destroy', $workflow) }}"
                                    data-delete-name="{{ $workflow->name }}">Delete</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No approval workflows yet. Create the first one.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    @if ($selectedWorkflow)
        <x-admin.data-table :title="'Steps: '.$selectedWorkflow->name">
            <x-slot:headerActions>
                <a class="btn sm primary" href="{{ route('admin.roles.approval-workflows.edit', $selectedWorkflow) }}">Edit Workflow</a>
            </x-slot:headerActions>
            <thead>
                <tr>
                    <th>Step No</th><th>Approver Role</th><th>Specific User</th><th>Required?</th>
                    <th>Amount Limit</th><th>SLA</th><th>Escalation Role</th><th>Can Reject</th><th>Can Send Back</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($selectedWorkflow->steps as $step)
                    <tr>
                        <td>{{ $step->step_no }}</td>
                        <td>{{ $step->approverRole?->name ?? 'Any' }}</td>
                        <td>{{ $step->approverUser?->name ?? 'Any assigned user' }}</td>
                        <td><span class="badge {{ $step->is_required ? 'green' : 'gray' }}">{{ $step->is_required ? 'Required' : 'Optional' }}</span></td>
                        <td>{{ $step->amount_limit ? 'Up to '.number_format($step->amount_limit).' SAR' : 'Final approval' }}</td>
                        <td>{{ $step->sla_hours }} hours</td>
                        <td>{{ $step->escalationRole?->name ?? '-' }}</td>
                        <td>{{ $step->can_reject ? 'Yes' : 'No' }}</td>
                        <td>{{ $step->can_send_back ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin.data-table>

        <div class="table-card">
            <div class="table-title">Workflow Preview</div>
            <div style="padding:12px 16px">
                <div class="workflow">
                    <div class="workflow-step">Requester<br><span class="small">{{ $selectedWorkflow->trigger_action ?? 'Submitted' }}</span></div>
                    <div class="arrow">&rarr;</div>
                    @foreach ($selectedWorkflow->steps as $step)
                        <div class="workflow-step">{{ $step->approverRole?->name ?? 'Any' }}<br><span class="small">{{ $loop->last ? 'Final Approval' : 'Step '.$step->step_no }}</span></div>
                        <div class="arrow">&rarr;</div>
                    @endforeach
                    <div class="workflow-step">{{ $selectedWorkflow->auto_posting === 'Create Accounting Entry' ? 'Accounting Entry' : 'Completed' }}<br><span class="small">{{ $selectedWorkflow->auto_posting === 'Create Accounting Entry' ? 'Auto Post' : 'Done' }}</span></div>
                </div>
            </div>
        </div>
    @endif
@endsection
