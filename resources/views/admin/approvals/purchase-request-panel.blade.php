<section class="card" id="approvals" style="padding:20px">
    <h2>Approval status</h2>
    @if($approvalInstance)
        <p>Attempt {{ $approvalInstance->attempt }}: <strong>{{ ucfirst($approvalInstance->status) }}</strong>.
            Requested by {{ $approvalInstance->snapshot['requester_name'] }};
            submitted {{ $approvalInstance->requested_at->format('Y-m-d H:i') }}.
            @if($approvalStep) Current step: {{ $approvalStep->step_no }} — {{ $approvalStep->snapshot['role_name'] ?? '-' }}. @endif
        </p>
        <p class="small">Steps run sequentially. All required steps must approve. Saving or submitting does not approve this request or post accounting entries.</p>
    @elseif($pr->approval_mode === 'legacy')
        <p>Legacy document — no runtime approval history was created. Historical approval fields remain unchanged.</p>
        @if(in_array($pr->status, ['draft','pending'], true))<p>Its existing approval action remains available. An authorized requester/editor can explicitly enrol it below; enrolment replaces the legacy action for this document.</p>@endif
    @else
        <p>Not submitted for approval. Choose a configured workflow below. Saving a pending request alone does not create approval tasks.</p>
    @endif

    @if($canSubmitApproval && (!$approvalInstance || $approvalInstance->status === 'rejected'))
        <form method="POST" action="{{ route('admin.inventory.purchase-requests.submit-approval', $pr) }}">
            @csrf
            @if($approvalInstance)<input type="hidden" name="previous_instance_id" value="{{ $approvalInstance->id }}">@endif
            <label for="approval_workflow_id">Approval workflow *</label>
            <select class="select" name="approval_workflow_id" id="approval_workflow_id" required>
                <option value="">Choose workflow...</option>
                @foreach($approvalWorkflows as $workflow)<option value="{{ $workflow->id }}">{{ $workflow->name }}</option>@endforeach
            </select>
            <p class="small">A workflow must match this module, requester department and document scope, have resolvable required approvers, blank amount limits and no automatic posting. Unsupported configuration is rejected with an explanation; no step is silently skipped.</p>
            @if($approvalInstance)<p>Correct this request first if necessary. Resubmit creates a new attempt; the rejected history is retained.</p>@endif
            <div class="form-actions"><button class="btn primary" type="submit">{{ $approvalInstance ? 'Resubmit for approval' : 'Submit for approval' }}</button></div>
        </form>
    @endif

    @if($runtimeCanApprove)
        <form method="POST" action="{{ route('admin.inventory.purchase-requests.approve', $pr) }}">
            @csrf
            <input type="hidden" name="instance_id" value="{{ $approvalInstance->id }}"><input type="hidden" name="step_id" value="{{ $approvalStep->id }}">
            <label for="approval_comment">Approval comment (optional)</label><textarea class="textarea" id="approval_comment" name="comment" maxlength="1000"></textarea>
            <div class="form-actions"><button class="btn primary" type="submit">Approve step {{ $approvalStep->step_no }}</button></div>
        </form>
    @endif
    @if($runtimeCanReject)
        <form method="POST" action="{{ route('admin.inventory.purchase-requests.reject', $pr) }}">
            @csrf
            <input type="hidden" name="instance_id" value="{{ $approvalInstance->id }}"><input type="hidden" name="step_id" value="{{ $approvalStep->id }}">
            <label for="runtime_rejection_reason">Rejection reason *</label><textarea class="textarea" id="runtime_rejection_reason" name="rejection_reason" maxlength="1000" required></textarea>
            <div class="form-actions"><button class="btn danger" type="submit">Reject request</button></div>
        </form>
    @endif
    @if($canViewApprovalHistory)
        <h3>Approval history</h3>
        @forelse($approvalHistory as $attempt)
            <h4>Attempt {{ $attempt->attempt }} — {{ ucfirst($attempt->status) }} — {{ $attempt->snapshot['workflow']['name'] }}</h4>
            <p class="small">Submitted by {{ $attempt->snapshot['submitter_name'] }} at {{ $attempt->requested_at->format('Y-m-d H:i') }}. Configuration and eligible users were snapshotted at submission; current permissions and scope are checked again for every decision.</p>
            <div style="overflow-x:auto"><table class="table">
                <thead><tr><th>Step</th><th>Required</th><th>Approver role / user</th><th>Decision</th><th>Decided by</th><th>When</th><th>Comment</th></tr></thead>
                <tbody>@foreach($attempt->steps as $step)<tr>
                    <td>{{ $step->step_no }}</td><td>{{ $step->is_required ? 'Yes' : 'No (informational)' }}</td>
                    <td>{{ $step->snapshot['role_name'] ?? '-' }} / {{ $step->snapshot['user_name'] ?? 'Any eligible assigned user' }}</td>
                    <td>{{ ucfirst($step->status) }}@if($attempt->status === 'rejected' && $step->status === 'pending') (stopped)@elseif($approvalStep?->id === $step->id) (current)@endif</td>
                    <td>{{ $step->decided_by_name ?? '-' }}</td><td>{{ $step->decided_at?->format('Y-m-d H:i') ?? '-' }}</td><td>{{ $step->comment ?? '-' }}</td>
                </tr>@endforeach</tbody>
            </table></div>
        @empty
            <p>No runtime history.</p>
        @endforelse
    @elseif($approvalInstance)
        <p>Detailed history requires the Approval History / View permission.</p>
    @endif
</section>
