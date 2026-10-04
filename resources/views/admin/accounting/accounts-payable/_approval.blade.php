<section class="card" id="approvals" style="padding:20px">
    <h2>Supplier Bill Approval</h2>
    <p><strong>{{ $bill->approvalLabel() }}</strong>. Approval authorizes the bill; accounting posting and payment remain separate actions.</p>
    @if($bill->approval_mode !== 'runtime')<p>Historical/legacy bill: no runtime history has been fabricated. Submit explicitly to use the configured workflow; after submission legacy approval is disabled.</p>@endif
    @if($bill->rejection_reason)<div class="alert">Rejected: {{ $bill->rejection_reason }}</div>@endif
    @if($approvalSubject->canSubmit($bill, $user))
        @if($billWorkflows->isEmpty())
            <p>No supported approval workflow is configured for Supplier Bills. Please contact an administrator.</p>
        @else
            <form method="POST" action="{{ route('admin.accounting.accounts-payable.submit-approval', $bill) }}">
                @csrf
                @if($approvalHistory->isNotEmpty())<input type="hidden" name="previous_instance_id" value="{{ $approvalHistory->first()->id }}">@endif
                <label for="bill-workflow">Approval workflow</label>
                <select class="select" id="bill-workflow" name="workflow_id" required>
                    <option value="">Choose workflow</option>
                    @foreach($billWorkflows as $workflow)<option value="{{ $workflow->id }}" @selected($billWorkflows->count() === 1)>{{ $workflow->name }}</option>@endforeach
                </select>
                <button class="btn primary">{{ $approvalHistory->isEmpty() ? 'Submit for Approval' : 'Resubmit for Approval' }}</button>
            </form>
        @endif
    @endif
    @forelse($approvalHistory as $attempt)
        <h3>Attempt {{ $attempt->attempt }} — {{ ucfirst($attempt->status) }}</h3>
        <p>Submitted by {{ $attempt->snapshot['submitter_name'] ?? '-' }} on {{ $attempt->requested_at?->format('Y-m-d H:i') }}.
        @if($attempt->currentStep())Current step: {{ $attempt->currentStep()->step_no }}.@endif</p>
        @foreach($attempt->steps as $step)
            <div style="padding:12px 0;border-bottom:1px solid #ddd">
                <strong>Step {{ $step->step_no }}: {{ $step->snapshot['role_name'] ?? 'Reviewer' }}</strong>
                @if($step->snapshot['user_name'] ?? null) / {{ $step->snapshot['user_name'] }} @endif
                — {{ ucfirst($step->status) }} ({{ $step->is_required ? 'Required' : 'Optional / skipped' }})
                @if($step->decided_at)<p>{{ $step->decided_by_name }} · {{ $step->decided_at->format('Y-m-d H:i') }}</p>@endif
                @if($step->comment)<p>{{ $step->comment }}</p>@endif
                @if($attempt->status === 'pending' && $attempt->currentStep()?->id === $step->id)
                    @foreach(['approve', 'reject'] as $decision)
                        @if($approvalRuntime->eligible($approvalSubject, $bill, $attempt, $step, $user, $decision))
                            <form method="POST" action="{{ route('admin.accounting.accounts-payable.runtime.'.$decision, $bill) }}">
                                @csrf
                                <input type="hidden" name="instance_id" value="{{ $attempt->id }}"><input type="hidden" name="step_id" value="{{ $step->id }}">
                                <label for="bill-{{ $step->id }}-{{ $decision }}">{{ $decision === 'reject' ? 'Rejection reason (required)' : 'Approval comment' }}</label>
                                <input class="input" id="bill-{{ $step->id }}-{{ $decision }}" name="comment" maxlength="1000" @required($decision === 'reject')>
                                <button class="btn {{ $decision === 'approve' ? 'primary' : 'outline' }}">{{ ucfirst($decision) }}</button>
                            </form>
                        @endif
                    @endforeach
                @endif
            </div>
        @endforeach
    @empty<p>Not submitted to Approval Runtime.</p>@endforelse
    @if($bill->approval_status === 'approved' && $bill->journalEntry?->status !== 'posted')
        <p>All required approvals completed; accounting is still pending. Payment is unavailable until the journal is posted.</p>
        @if($user->hasPermission('Accounts Payable', 'post'))
            @if($bill->posting_error)<div class="alert">{{ $bill->posting_error }}</div>@endif
            @if($user->hasPermission('Accounts Payable', 'retry'))
                <form method="POST" action="{{ route('admin.accounting.accounts-payable.retry', $bill) }}">@csrf<button class="btn primary">Retry Posting</button></form>
            @endif
            @if($bill->journal_entry_id)<p>An existing review-mode journal must be posted in Finance; retry never creates another journal.</p>@endif
        @endif
    @endif
</section>
