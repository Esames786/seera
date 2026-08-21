@php /** @var \App\Models\EndOfServiceRecord|null $record */ $record = $record ?? null; @endphp

<form method="POST" action="{{ $record ? route('admin.hr.eosb.update', $record) : route('admin.hr.eosb.store') }}">
    @csrf
    @if ($record) @method('PUT') @endif

    <x-admin.form-section title="A. Service Details" columns="3">
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
        <div>
            <label for="termination_reason">Reason For Leaving *</label>
            <select id="termination_reason" name="termination_reason" class="select" required>
                @foreach ($reasons as $value => $label)
                    <option value="{{ $value }}" @selected(old('termination_reason', $record?->termination_reason ?? 'termination') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="service_years">Service Years *</label><input id="service_years" name="service_years" type="number" step="0.01" min="0" class="input" value="{{ old('service_years', $record?->service_years ?? 0) }}" required/></div>
        <div><label for="last_basic_salary">Final Wage (SAR) *</label><input id="last_basic_salary" name="last_basic_salary" type="number" step="0.01" min="0" class="input" value="{{ old('last_basic_salary', $record?->last_basic_salary ?? 0) }}" required/><span class="small">Use the employee's last wage, including regular wage components required by policy and law.</span></div>
        <div><label>Status</label><input class="input" value="Draft (approval is a separate action)" disabled/></div>
    </x-admin.form-section>

    <x-admin.form-section title="B. Gratuity" columns="3">
        <div>
            <label for="manual_override">Gratuity Calculation</label>
            <select id="manual_override" name="manual_override" class="select">
                <option value="0" @selected(! old('manual_override', $record?->manual_override ?? false))>Automatic (Saudi rules)</option>
                <option value="1" @selected(old('manual_override', $record?->manual_override ?? false))>Manual override</option>
            </select>
        </div>
        <div><label for="eosb_amount">Gratuity Amount (SAR)</label><input id="eosb_amount" name="eosb_amount" type="number" step="0.01" min="0" class="input" value="{{ old('eosb_amount', $record?->eosb_amount ?? 0) }}"/></div>
        <div class="full">
            <div class="help-box">
                On automatic, the gratuity is calculated on save: half a month's salary for each of the first five years,
                a full month's wage for each year after that, on the final wage. Resignation then scales the
                award by length of service; termination, contract completion and Article 87 exceptions pay in full.
                The amount entered here is only used when you pick manual override.
            </div>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="C. Other Dues and Deductions" columns="3">
        <div><label for="leave_salary">Leave Salary *</label><input id="leave_salary" name="leave_salary" type="number" step="0.01" min="0" class="input" value="{{ old('leave_salary', $record?->leave_salary ?? 0) }}" required/></div>
        <div><label for="other_dues">Other Dues *</label><input id="other_dues" name="other_dues" type="number" step="0.01" min="0" class="input" value="{{ old('other_dues', $record?->other_dues ?? 0) }}" required/></div>
        <div><label for="deductions">Deductions *</label><input id="deductions" name="deductions" type="number" step="0.01" min="0" class="input" value="{{ old('deductions', $record?->deductions ?? 0) }}" required/></div>
        <div class="full"><label for="reason">Notes</label><textarea id="reason" name="reason" class="textarea">{{ old('reason', $record?->reason) }}</textarea></div>
    </x-admin.form-section>

    <div class="note">
        Final amount = gratuity + leave salary + other dues - deductions.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.eosb.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $record ? 'Update EOSB Record' : 'Save EOSB Record' }}</button>
    </div>
</form>
