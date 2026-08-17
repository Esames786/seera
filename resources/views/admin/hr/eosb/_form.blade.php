@php /** @var \App\Models\EndOfServiceRecord|null $record */ $record = $record ?? null; @endphp

<form method="POST" action="{{ $record ? route('admin.hr.eosb.update', $record) : route('admin.hr.eosb.store') }}">
    @csrf
    @if ($record) @method('PUT') @endif

    <x-admin.form-section title="End of Service Record" columns="3">
        <div>
            <label for="employee_id">Employee *</label>
            <select id="employee_id" name="employee_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id', $record?->employee_id) == $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="termination_date">Termination Date *</label><input id="termination_date" name="termination_date" type="date" class="input" value="{{ old('termination_date', $record?->termination_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="service_years">Service Years *</label><input id="service_years" name="service_years" type="number" step="0.01" min="0" class="input" value="{{ old('service_years', $record?->service_years ?? 0) }}" required/></div>
        <div><label for="last_basic_salary">Last Basic Salary *</label><input id="last_basic_salary" name="last_basic_salary" type="number" step="0.01" min="0" class="input" value="{{ old('last_basic_salary', $record?->last_basic_salary ?? 0) }}" required/></div>
        <div><label for="eosb_amount">EOSB Amount *</label><input id="eosb_amount" name="eosb_amount" type="number" step="0.01" min="0" class="input" value="{{ old('eosb_amount', $record?->eosb_amount ?? 0) }}" required/></div>
        <div><label for="leave_salary">Leave Salary *</label><input id="leave_salary" name="leave_salary" type="number" step="0.01" min="0" class="input" value="{{ old('leave_salary', $record?->leave_salary ?? 0) }}" required/></div>
        <div><label for="other_dues">Other Dues *</label><input id="other_dues" name="other_dues" type="number" step="0.01" min="0" class="input" value="{{ old('other_dues', $record?->other_dues ?? 0) }}" required/></div>
        <div><label for="deductions">Deductions *</label><input id="deductions" name="deductions" type="number" step="0.01" min="0" class="input" value="{{ old('deductions', $record?->deductions ?? 0) }}" required/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $record?->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="reason">Reason / Notes</label><textarea id="reason" name="reason" class="textarea">{{ old('reason', $record?->reason) }}</textarea></div>
    </x-admin.form-section>

    <div class="note">
        Final amount = EOSB amount + leave salary + other dues - deductions. Saudi-compliant EOSB calculation rules come in a later phase.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.eosb.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $record ? 'Update EOSB Record' : 'Save EOSB Record' }}</button>
    </div>
</form>
