@php /** @var \App\Models\PayrollRun|null $run */ $run = $run ?? null; @endphp

<form method="POST" action="{{ $run ? route('admin.hr.payroll.update', $run) : route('admin.hr.payroll.store') }}">
    @csrf
    @if ($run) @method('PUT') @endif

    <x-admin.form-section title="Payroll Run" columns="3">
        <div>
            <label for="payroll_month">Payroll Month *</label>
            <select id="payroll_month" name="payroll_month" class="select" required>
                @foreach ($months as $number => $label)
                    <option value="{{ $number }}" @selected(old('payroll_month', $run?->payroll_month ?? now()->month) == $number)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="payroll_year">Payroll Year *</label>
            <select id="payroll_year" name="payroll_year" class="select" required>
                @foreach ($years as $year)
                    <option value="{{ $year }}" @selected(old('payroll_year', $run?->payroll_year ?? now()->year) == $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="period_start">Period Start</label><input id="period_start" name="period_start" type="date" class="input" value="{{ old('period_start', $run?->period_start?->toDateString()) }}" placeholder="Auto"/></div>
        <div><label for="period_end">Period End</label><input id="period_end" name="period_end" type="date" class="input" value="{{ old('period_end', $run?->period_end?->toDateString()) }}" placeholder="Auto"/></div>
        <div>
            <label for="branch_id">Branch</label>
            <select id="branch_id" name="branch_id" class="select">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $run?->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">All Projects</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $run?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $run?->notes) }}</textarea></div>
    </x-admin.form-section>

    <div class="help-box">
        Leave the period blank to use the full calendar month. Processing generates one payroll item per active employee inside the selected branch/project scope.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.payroll.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $run ? 'Update Payroll Run' : 'Create Payroll Run' }}</button>
    </div>
</form>
