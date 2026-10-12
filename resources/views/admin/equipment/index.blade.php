@extends('layouts.admin')

@section('title', 'Equipment & Vehicles')
@section('breadcrumb', 'Operations / Equipment & Vehicles')

@section('content')
    @php $user = auth()->user(); @endphp
    <x-admin.page-header title="Equipment & Vehicles" description="Asset register with current project / site assignment, operator, meter and document expiry. Live GPS tracking is not connected to a telematics provider.">
        @if ($user->hasPermission('Equipment', 'create'))
            <a class="btn primary" href="{{ route('admin.equipment.create') }}">+ Add Equipment / Vehicle</a>
        @endif
        @if ($user->hasPermission('Equipment', 'export'))
            <a class="btn outline" href="{{ route('admin.equipment.export', request()->query()) }}">Export CSV</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="(int) $counts->total" label="Assets in scope"/>
        <x-admin.metric-card color="green" :value="(int) $counts->assigned" label="Assigned to projects"/>
        <x-admin.metric-card color="yellow" :value="(int) $counts->maintenance" label="Under maintenance"/>
        <x-admin.metric-card color="gray" :value="(int) $counts->available" label="Available"/>
        <x-admin.metric-card color="red" :value="$expiringSoon" label="Registration / insurance expiring (60 days)"/>
        <x-admin.metric-card color="cyan" :value="$dueService" label="Service due (30 days)"/>
    </div>

    <x-admin.data-table title="Equipment Register" :subtitle="$assets->total().' assets · your access scope'">
        <x-slot:headerActions>
            <form method="GET" action="{{ route('admin.equipment.index') }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <input name="search" class="input" style="width:160px" placeholder="Code, name, plate, serial" value="{{ request('search') }}"/>
                <select name="type" class="select" style="width:auto"><option value="">All types</option>@foreach (\App\Models\EquipmentAsset::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select>
                <select name="status" class="select" style="width:auto"><option value="">All statuses</option>@foreach (\App\Models\EquipmentAsset::STATUSES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
                <select name="project" class="select" style="width:auto"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) request('project') === (string) $project->id)>{{ $project->code }} {{ $project->name }}</option>@endforeach</select>
                <button type="submit" class="btn sm outline">Filter</button>
            </form>
        </x-slot:headerActions>
        <thead>
            <tr><th>Asset</th><th>Type / Category</th><th>Make / Model</th><th>Registration / Serial</th><th>Status</th><th>Project / Site</th><th>Operator</th><th>Meter</th><th>Expiry</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($assets as $asset)
                @php $reg = \App\Models\EquipmentAsset::expiryState($asset->registration_expiry_date); $ins = \App\Models\EquipmentAsset::expiryState($asset->insurance_expiry_date); @endphp
                <tr>
                    <td><a href="{{ route('admin.equipment.show', $asset) }}" style="font-weight:700;color:var(--blue)">{{ $asset->asset_code }}</a><div class="small">{{ $asset->name }}</div></td>
                    <td>{{ $asset->typeLabel() }}{{ $asset->category ? ' · '.$asset->category : '' }}</td>
                    <td>{{ trim(($asset->make ?? '').' '.($asset->model ?? '')) ?: '-' }}{{ $asset->year ? ' ('.$asset->year.')' : '' }}</td>
                    <td class="small">{{ $asset->isVehicle() ? ($asset->registration_number ?? '-') : ($asset->serial_number ?? '-') }}</td>
                    <td><x-admin.status-badge :status="$asset->status"/></td>
                    <td>{{ $asset->project?->code ?? 'Not assigned' }}{{ $asset->site ? ' / '.$asset->site->name : '' }}</td>
                    <td>{{ $asset->operator?->name ?? '-' }}</td>
                    <td>{{ $asset->meterLabel() }}</td>
                    <td class="small">
                        @if ($asset->isVehicle())Reg: <span class="badge {{ $reg === 'expired' ? 'red' : ($reg === 'expiring' ? 'yellow' : 'gray') }}">{{ $asset->registration_expiry_date?->toDateString() ?? 'unknown' }}</span><br/>@endif
                        Ins: <span class="badge {{ $ins === 'expired' ? 'red' : ($ins === 'expiring' ? 'yellow' : 'gray') }}">{{ $asset->insurance_expiry_date?->toDateString() ?? 'unknown' }}</span>
                    </td>
                    <td><div class="actions"><a class="btn sm outline" href="{{ route('admin.equipment.show', $asset) }}">View</a>@if ($user->hasPermission('Equipment', 'edit'))<a class="btn sm outline" href="{{ route('admin.equipment.edit', $asset) }}">Edit</a>@endif</div></td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No equipment or vehicles in your scope yet.{{ $user->hasPermission('Equipment', 'create') ? ' Use + Add Equipment / Vehicle to register the first asset.' : '' }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $assets->firstItem() ?? 0 }}-{{ $assets->lastItem() ?? 0 }} of {{ $assets->total() }}</span>
            @if ($assets->hasPages()){{ $assets->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>
@endsection
