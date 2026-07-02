@php
    /** @var \App\Models\ApprovalWorkflow|null $workflow */
    $workflow = $workflow ?? null;
    $stepRows = old('steps', $workflow?->steps->map(fn ($step) => [
        'step_no' => $step->step_no,
        'approver_role_id' => $step->approver_role_id,
        'approver_user_id' => $step->approver_user_id,
        'is_required' => $step->is_required,
        'amount_limit' => $step->amount_limit,
        'sla_hours' => $step->sla_hours,
        'escalation_role_id' => $step->escalation_role_id,
        'can_reject' => $step->can_reject,
        'can_send_back' => $step->can_send_back,
    ])->all() ?? [['step_no' => 1, 'approver_role_id' => null, 'approver_user_id' => null, 'is_required' => true, 'amount_limit' => null, 'sla_hours' => 24, 'escalation_role_id' => null, 'can_reject' => true, 'can_send_back' => true]]);
@endphp

<form method="POST" action="{{ $workflow ? route('admin.roles.approval-workflows.update', $workflow) : route('admin.roles.approval-workflows.store') }}">
    @csrf
    @if ($workflow) @method('PUT') @endif

    <x-admin.form-section title="Workflow Basic Information" columns="3">
        <div><label for="name">Workflow Name *</label><input id="name" name="name" class="input" value="{{ old('name', $workflow?->name) }}" required/></div>
        <div>
            <label for="module">Module *</label>
            <select id="module" name="module" class="select" required>
                @foreach ($modules as $module)
                    <option @selected(old('module', $workflow?->module) === $module)>{{ $module }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="trigger_action">Trigger Action</label>
            <select id="trigger_action" name="trigger_action" class="select">
                @foreach ($triggers as $trigger)
                    <option @selected(old('trigger_action', $workflow?->trigger_action) === $trigger)>{{ $trigger }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">Select...</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $workflow?->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="scope">Project/Site Scope *</label>
            <select id="scope" name="scope" class="select" required>
                @foreach ($scopes as $scope)
                    <option @selected(old('scope', $workflow?->scope ?? 'Assigned Project/Site') === $scope)>{{ $scope }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $workflow?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $workflow?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
    </x-admin.form-section>

    <div class="table-card">
        <div class="table-title">
            <span>Approval Steps <span class="small">Steps run in order of step number</span></span>
            <button type="button" class="btn sm primary" id="btn-add-step">+ Add Step</button>
        </div>
        <div class="table-wrap">
            <table id="steps-table">
                <thead>
                    <tr>
                        <th style="width:70px">Step</th><th>Approver Role *</th><th>Specific User</th><th>Required</th>
                        <th>Amount Limit (SAR)</th><th>SLA (hours)</th><th>Escalation Role</th><th>Can Reject</th><th>Can Send Back</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stepRows as $i => $step)
                        <tr class="js-step-row">
                            <td><input class="input js-step-no" name="steps[{{ $i }}][step_no]" type="number" min="1" max="20" value="{{ $step['step_no'] }}" required/></td>
                            <td>
                                <select class="select" name="steps[{{ $i }}][approver_role_id]" required>
                                    <option value="">Select role...</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected(($step['approver_role_id'] ?? null) == $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="select" name="steps[{{ $i }}][approver_user_id]">
                                    <option value="">Any assigned user</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(($step['approver_user_id'] ?? null) == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="select" name="steps[{{ $i }}][is_required]">
                                    <option value="1" @selected($step['is_required'] ?? true)>Yes</option>
                                    <option value="0" @selected(!($step['is_required'] ?? true))>No</option>
                                </select>
                            </td>
                            <td><input class="input" name="steps[{{ $i }}][amount_limit]" type="number" step="0.01" min="0" value="{{ $step['amount_limit'] }}" placeholder="No limit"/></td>
                            <td><input class="input" name="steps[{{ $i }}][sla_hours]" type="number" min="1" max="720" value="{{ $step['sla_hours'] ?? 24 }}"/></td>
                            <td>
                                <select class="select" name="steps[{{ $i }}][escalation_role_id]">
                                    <option value="">None</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected(($step['escalation_role_id'] ?? null) == $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="select" name="steps[{{ $i }}][can_reject]">
                                    <option value="1" @selected($step['can_reject'] ?? true)>Yes</option>
                                    <option value="0" @selected(!($step['can_reject'] ?? true))>No</option>
                                </select>
                            </td>
                            <td>
                                <select class="select" name="steps[{{ $i }}][can_send_back]">
                                    <option value="1" @selected($step['can_send_back'] ?? true)>Yes</option>
                                    <option value="0" @selected(!($step['can_send_back'] ?? true))>No</option>
                                </select>
                            </td>
                            <td><button type="button" class="btn sm danger js-remove-step" title="Remove step">&times;</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-admin.form-section title="Final Action After Approval" columns="3">
        <div>
            <label for="auto_posting">Auto Posting *</label>
            <select id="auto_posting" name="auto_posting" class="select" required>
                @foreach ($postings as $posting)
                    <option @selected(old('auto_posting', $workflow?->auto_posting ?? 'No Auto Posting') === $posting)>{{ $posting }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="notify_requester">Notify Requester</label>
            <select id="notify_requester" name="notify_requester" class="select">
                <option value="1" @selected(old('notify_requester', $workflow?->notify_requester ?? true))>Yes</option>
                <option value="0" @selected(!old('notify_requester', $workflow?->notify_requester ?? true))>No</option>
            </select>
        </div>
        <div>
            <label for="lock_after_approval">Lock After Approval</label>
            <select id="lock_after_approval" name="lock_after_approval" class="select">
                <option value="1" @selected(old('lock_after_approval', $workflow?->lock_after_approval ?? true))>Yes</option>
                <option value="0" @selected(!old('lock_after_approval', $workflow?->lock_after_approval ?? true))>No</option>
            </select>
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.roles.approval-workflows.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $workflow ? 'Update Workflow' : 'Save Workflow' }}</button>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const table = document.getElementById('steps-table');
        if (!table) return;
        const tbody = table.querySelector('tbody');

        function renumberNames() {
            tbody.querySelectorAll('.js-step-row').forEach(function (row, index) {
                row.querySelectorAll('select, input').forEach(function (field) {
                    field.name = field.name.replace(/steps\[\d+]/, 'steps[' + index + ']');
                });
            });
        }

        document.getElementById('btn-add-step').addEventListener('click', function () {
            const rows = tbody.querySelectorAll('.js-step-row');
            const clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelector('.js-step-no').value = rows.length + 1;
            clone.querySelectorAll('input[type="number"]').forEach(f => { if (!f.classList.contains('js-step-no') && !f.name.includes('sla_hours')) f.value = ''; });
            tbody.appendChild(clone);
            renumberNames();
        });

        tbody.addEventListener('click', function (event) {
            const button = event.target.closest('.js-remove-step');
            if (!button) return;
            if (tbody.querySelectorAll('.js-step-row').length <= 1) {
                alert('A workflow needs at least one approval step.');
                return;
            }
            button.closest('.js-step-row').remove();
            renumberNames();
        });
    })();
</script>
@endpush
