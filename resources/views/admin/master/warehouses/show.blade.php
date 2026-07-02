@extends('layouts.admin')

@section('title', 'Warehouse Details')
@section('breadcrumb', 'Master Setup / Warehouses / Warehouse Details')

@section('content')
    <x-admin.page-header :title="$warehouse->name" description="Warehouse overview">
        <a class="btn primary" href="{{ route('admin.master.warehouses.edit', $warehouse) }}">Edit Warehouse</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="strtoupper($warehouse->code)" label="Warehouse Code"/>
        <x-admin.metric-card color="green" :value="$warehouse->valuation_method" label="Valuation Method"/>
        <x-admin.metric-card color="yellow" :value="$warehouse->site_id ? 'Site Level' : ($warehouse->project_id ? 'Project Level' : 'Branch Level')" label="Warehouse Level"/>
        <x-admin.metric-card color="cyan" :value="ucfirst($warehouse->status)" label="Status"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Warehouse Information" class="detail-table">
            <tbody>
                <tr><th>Warehouse Name</th><td>{{ $warehouse->name }}</td></tr>
                <tr><th>Warehouse Code</th><td>{{ $warehouse->code }}</td></tr>
                <tr><th>Branch</th><td>{{ $warehouse->branch?->name ?? '-' }}</td></tr>
                <tr><th>Project</th><td>{{ $warehouse->project?->name ?? 'Branch Level' }}</td></tr>
                <tr><th>Site</th><td>{{ $warehouse->site?->name ?? '-' }}</td></tr>
                <tr><th>Incharge</th><td>{{ $warehouse->incharge?->name ?? '-' }}</td></tr>
                <tr><th>Valuation Method</th><td>{{ $warehouse->valuation_method }}</td></tr>
                <tr><th>Address</th><td>{{ $warehouse->address ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$warehouse->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <div class="table-card">
                <div class="table-title">Stock Summary</div>
                <div class="table-empty">
                    Stock levels, materials, and transfers will appear here when the Inventory module ships in a later phase.
                </div>
            </div>
            <div class="note">
                This warehouse is ready to be used by the Inventory module (materials, stock in/out, transfers) once Phase 5 begins.
            </div>
        </div>
    </div>
@endsection
