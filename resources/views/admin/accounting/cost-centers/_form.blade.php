@php /** @var \App\Models\CostCenter|null $costCenter */ $costCenter = $costCenter ?? null; @endphp

<form method="POST" action="{{ $costCenter ? route('admin.accounting.cost-centers.update', $costCenter) : route('admin.accounting.cost-centers.store') }}">
    @csrf
    @if ($costCenter) @method('PUT') @endif

    <x-admin.form-section title="Cost Center Information" columns="3">
        <div><label for="code">Cost Center Code *</label><input id="code" name="code" class="input" value="{{ old('code', $costCenter?->code) }}" placeholder="CC-PRJ-001" required/></div>
        <div><label for="name">Cost Center Name *</label><input id="name" name="name" class="input" value="{{ old('name', $costCenter?->name) }}" placeholder="Riyadh Tower" required/></div>
        <div>
            <label for="type">Type *</label>
            <select id="type" name="type" class="select" required data-cost-center-type>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(old('type', $costCenter?->type ?? 'project') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="linked_id">Linked Record</label>
            <select id="linked_id" name="linked_id" class="select">
                <option value="">Not linked</option>
                @foreach ($linkedOptions as $optionType => $records)
                    <optgroup label="{{ ucfirst($optionType) }}" data-linked-group="{{ $optionType }}">
                        @foreach ($records as $record)
                            <option value="{{ $record->id }}" data-linked-type="{{ $optionType }}" @selected(old('type', $costCenter?->type) === $optionType && old('linked_id', $costCenter?->linked_id) == $record->id)>{{ $record->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div>
            <label for="manager_id">Manager</label>
            <select id="manager_id" name="manager_id" class="select">
                <option value="">No manager</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('manager_id', $costCenter?->manager_id) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $costCenter?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <div class="help-box">
        The linked record ties this cost center back to the Phase 2 master data, so project and site reporting can roll up automatically.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.accounting.cost-centers.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $costCenter ? 'Update Cost Center' : 'Save Cost Center' }}</button>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const typeSelect = document.querySelector('[data-cost-center-type]');
        const linkedSelect = document.getElementById('linked_id');
        if (!typeSelect || !linkedSelect) return;

        function syncGroups() {
            const type = typeSelect.value;
            linkedSelect.querySelectorAll('optgroup').forEach(function (group) {
                const matches = group.dataset.linkedGroup === type;
                group.hidden = !matches;
                group.disabled = !matches;
            });

            const selected = linkedSelect.selectedOptions[0];
            if (selected && selected.dataset.linkedType && selected.dataset.linkedType !== type) {
                linkedSelect.value = '';
            }
        }

        typeSelect.addEventListener('change', syncGroups);
        syncGroups();
    })();
</script>
@endpush
