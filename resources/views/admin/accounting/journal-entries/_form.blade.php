@php
    /** @var \App\Models\JournalEntry|null $entry */
    $entry = $entry ?? null;
    $lineData = old('lines', $entry?->lines->map(fn ($line) => [
        'chart_of_account_id' => $line->chart_of_account_id,
        'description' => $line->description,
        'debit' => (float) $line->debit ?: '',
        'credit' => (float) $line->credit ?: '',
        'cost_center_id' => $line->cost_center_id,
        'project_id' => $line->project_id,
        'site_id' => $line->site_id,
    ])->all() ?? []);
@endphp

<form method="POST" action="{{ $entry ? route('admin.accounting.journal-entries.update', $entry) : route('admin.accounting.journal-entries.store') }}" data-journal-form>
    @csrf
    @if ($entry) @method('PUT') @endif

    <x-admin.form-section title="Journal Header" columns="3">
        <div><label for="journal_date">Journal Date *</label><input id="journal_date" name="journal_date" type="date" class="input" value="{{ old('journal_date', $entry?->journal_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="reference_number">Reference Number</label><input id="reference_number" name="reference_number" class="input" value="{{ old('reference_number', $entry?->reference_number) }}"/></div>
        <div>
            <label for="source_module">Source Module *</label>
            <select id="source_module" name="source_module" class="select" required>
                @foreach ($sourceModules as $module)
                    <option value="{{ $module }}" @selected(old('source_module', $entry?->source_module ?? 'Manual') === $module)>{{ $module }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cost_center_id">Cost Center</label>
            <select id="cost_center_id" name="cost_center_id" class="select">
                <option value="">No cost center</option>
                @foreach ($costCenters as $costCenter)
                    <option value="{{ $costCenter->id }}" @selected(old('cost_center_id', $entry?->cost_center_id) == $costCenter->id)>{{ $costCenter->code }} - {{ $costCenter->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['draft', 'approved', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $entry?->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" class="textarea">{{ old('description', $entry?->description) }}</textarea></div>
    </x-admin.form-section>

    @include('admin.accounting.journal-entries._lines', ['lineData' => $lineData])

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.accounting.journal-entries.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $entry ? 'Update Journal Entry' : 'Save Journal Entry' }}</button>
    </div>
</form>
