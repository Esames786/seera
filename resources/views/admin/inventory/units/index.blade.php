@extends('layouts.admin')

@section('title', 'Units')
@section('breadcrumb', 'Inventory / Units')

@section('content')
    <x-admin.page-header title="Units of Measure" description="Units used by items, purchase requests and stock documents">
        <a class="btn primary" href="{{ route('admin.inventory.units.create') }}">+ Add Unit</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalUnits" label="Total Units"/>
        <x-admin.metric-card color="green" :value="$activeUnits" label="Active Units"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Unit code or name..."/>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.units.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Units Listing">
        <thead>
            <tr><th>Code</th><th>Name</th><th>Allows Decimal</th><th>Items</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($units as $unit)
                <tr>
                    <td>{{ $unit->code }}</td>
                    <td>{{ $unit->name }}</td>
                    <td><x-admin.status-badge :status="$unit->allows_decimal ? 'yes' : 'no'"/></td>
                    <td>{{ $unit->items_count }}</td>
                    <td><x-admin.status-badge :status="$unit->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :edit="route('admin.inventory.units.edit', $unit)"
                            :delete="route('admin.inventory.units.destroy', $unit)"
                            :name="$unit->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No units match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $units->firstItem() ?? 0 }}-{{ $units->lastItem() ?? 0 }} of {{ $units->total() }}</span>
            {{ $units->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
