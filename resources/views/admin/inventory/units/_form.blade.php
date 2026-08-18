@php /** @var \App\Models\Unit|null $unit */ $unit = $unit ?? null; @endphp

<form method="POST" action="{{ $unit ? route('admin.inventory.units.update', $unit) : route('admin.inventory.units.store') }}">
    @csrf
    @if ($unit) @method('PUT') @endif

    <x-admin.form-section title="Unit Information" columns="3">
        <div><label for="code">Unit Code *</label><input id="code" name="code" class="input" value="{{ old('code', $unit?->code) }}" placeholder="BAG" required/></div>
        <div><label for="name">Unit Name *</label><input id="name" name="name" class="input" value="{{ old('name', $unit?->name) }}" placeholder="Bag" required/></div>
        <div>
            <label for="allows_decimal">Allows Decimal</label>
            <select id="allows_decimal" name="allows_decimal" class="select">
                <option value="1" @selected(old('allows_decimal', $unit?->allows_decimal ?? true))>Yes</option>
                <option value="0" @selected(! old('allows_decimal', $unit?->allows_decimal ?? true))>No</option>
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $unit?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.units.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $unit ? 'Update Unit' : 'Save Unit' }}</button>
    </div>
</form>
