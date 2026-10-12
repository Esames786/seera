@php /** @var \App\Models\EquipmentAsset|null $asset */ $asset = $asset ?? null; @endphp

<form method="POST" action="{{ $asset ? route('admin.equipment.update', $asset) : route('admin.equipment.store') }}">
    @csrf
    @if ($asset) @method('PUT') @endif
    <input type="hidden" name="{{ \App\Support\SaveAction::RETURN_FIELD }}" value="{{ old(\App\Support\SaveAction::RETURN_FIELD, \App\Support\SaveAction::returnTo()) }}"/>

    <x-admin.form-section title="Identification" columns="3">
        <div><label for="asset_code">Asset code *</label><input id="asset_code" name="asset_code" class="input" maxlength="40" required value="{{ old('asset_code', $asset?->asset_code) }}" placeholder="EXC-001"/></div>
        <div><label for="name">Name *</label><input id="name" name="name" class="input" maxlength="150" required value="{{ old('name', $asset?->name) }}" placeholder="Excavator CAT 320"/></div>
        <div><label for="asset_type">Type *</label><select id="asset_type" name="asset_type" class="select" required>@foreach ($types as $key => $label)<option value="{{ $key }}" @selected(old('asset_type', $asset?->asset_type ?? 'equipment') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="category">Category</label><input id="category" name="category" class="input" maxlength="80" value="{{ old('category', $asset?->category) }}" placeholder="Excavator / Pickup / Generator"/></div>
        <div><label for="make">Make</label><input id="make" name="make" class="input" maxlength="80" value="{{ old('make', $asset?->make) }}"/></div>
        <div><label for="model">Model</label><input id="model" name="model" class="input" maxlength="80" value="{{ old('model', $asset?->model) }}"/></div>
        <div><label for="year">Year</label><input id="year" name="year" type="number" min="1950" max="{{ now()->year + 1 }}" class="input" value="{{ old('year', $asset?->year) }}"/></div>
        <div><label for="serial_number">Serial number</label><input id="serial_number" name="serial_number" class="input" maxlength="80" value="{{ old('serial_number', $asset?->serial_number) }}"/></div>
        <div><label for="ownership_type">Ownership *</label><select id="ownership_type" name="ownership_type" class="select" required>@foreach ($ownership as $key => $label)<option value="{{ $key }}" @selected(old('ownership_type', $asset?->ownership_type ?? 'owned') === $key)>{{ $label }}</option>@endforeach</select></div>
    </x-admin.form-section>

    <x-admin.form-section title="Vehicle details (vehicles only)" columns="3">
        <div><label for="registration_number">Registration / plate number</label><input id="registration_number" name="registration_number" class="input" maxlength="40" value="{{ old('registration_number', $asset?->registration_number) }}"/></div>
        <div><label for="chassis_number">Chassis / VIN</label><input id="chassis_number" name="chassis_number" class="input" maxlength="60" value="{{ old('chassis_number', $asset?->chassis_number) }}"/></div>
        <div><label for="registration_expiry_date">Registration expiry</label><input id="registration_expiry_date" name="registration_expiry_date" type="date" class="input" value="{{ old('registration_expiry_date', $asset?->registration_expiry_date?->toDateString()) }}"/></div>
        <div><label for="insurance_expiry_date">Insurance expiry</label><input id="insurance_expiry_date" name="insurance_expiry_date" type="date" class="input" value="{{ old('insurance_expiry_date', $asset?->insurance_expiry_date?->toDateString()) }}"/></div>
        <div class="full"><p class="small">Plate and chassis are kept only for vehicles; equipment uses the serial number. Expiry dates give the register warnings (no notification jobs in this phase).</p></div>
    </x-admin.form-section>

    <x-admin.form-section title="Acquisition, meter and status" columns="3">
        <div><label for="acquisition_date">Acquisition date</label><input id="acquisition_date" name="acquisition_date" type="date" class="input" value="{{ old('acquisition_date', $asset?->acquisition_date?->toDateString()) }}" max="{{ today()->toDateString() }}"/></div>
        <div><label for="acquisition_cost">Acquisition cost (SAR)</label><input id="acquisition_cost" name="acquisition_cost" type="number" step="0.01" min="0" class="input" value="{{ old('acquisition_cost', $asset?->acquisition_cost) }}"/></div>
        <div><label for="meter_type">Meter *</label><select id="meter_type" name="meter_type" class="select" required>@foreach ($meterTypes as $key => $label)<option value="{{ $key }}" @selected(old('meter_type', $asset?->meter_type ?? 'odometer') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="current_meter">{{ $asset ? 'Meter reading' : 'Initial meter reading' }}</label><input id="current_meter" name="current_meter" type="number" step="0.1" min="0" class="input" value="{{ old('current_meter', $asset?->current_meter) }}"/></div>
        @if ($asset)
            <div><label for="meter_correction">Meter correction</label><select id="meter_correction" name="meter_correction" class="select"><option value="0">No — reading must not go backwards</option><option value="1" @selected(old('meter_correction') === '1')>Yes — controlled correction (logged)</option></select></div>
            <div><label for="status">Status *</label><select id="status" name="status" class="select" required>@foreach ($statuses as $key => $label)<option value="{{ $key }}" @selected(old('status', $asset->status) === $key)>{{ $label }}</option>@endforeach</select></div>
        @endif
    </x-admin.form-section>

    <x-admin.form-section title="Tracking readiness (no live GPS yet)" columns="3">
        <div><label for="tracker_provider">Telematics provider</label><input id="tracker_provider" name="tracker_provider" class="input" maxlength="80" value="{{ old('tracker_provider', $asset?->tracker_provider) }}" placeholder="e.g. provider name"/></div>
        <div><label for="tracker_device_id">Tracker device ID</label><input id="tracker_device_id" name="tracker_device_id" class="input" maxlength="120" value="{{ old('tracker_device_id', $asset?->tracker_device_id) }}"/></div>
        <div class="full"><p class="small">Stored for the future provider integration only. Live position, routes, speed, idle time and geofence events are NOT YET OPERATIONAL; nothing here produces tracking data.</p></div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea" maxlength="2000">{{ old('notes', $asset?->notes) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-actions :cancel="\App\Support\SaveAction::cancelUrl(route('admin.equipment.index'))" :save-new="! $asset"/>
</form>
