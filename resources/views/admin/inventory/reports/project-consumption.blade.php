@extends('layouts.admin')

@section('title', 'Project Material Consumption')
@section('breadcrumb', 'Inventory / Reports / Project Consumption')

@section('content')
    <x-admin.page-header title="Project Material Consumption" description="Material issued to each project, taken from posted stock issues">
        <a class="btn outline" href="{{ route('admin.inventory.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <input class="input" style="width:150px" type="date" name="from" value="{{ request('from') }}"/>
        <input class="input" style="width:150px" type="date" name="to" value="{{ request('to') }}"/>
        <select class="select" style="width:190px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.reports.project-consumption') }}">Reset</a>
            <button type="button" class="btn outline" onclick="window.print()">Export PDF</button>
        </x-slot:actions>
    </x-admin.filter-bar>

    <div class="card-grid">
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($totalValue, 2)" label="Total Consumption"/>
        <x-admin.metric-card color="blue" :value="$rows->count()" label="Projects With Consumption"/>
    </div>

    <x-admin.data-table title="Consumption By Project">
        <thead>
            <tr><th>Project</th><th>Quantity Issued</th><th>Consumption Value</th><th>Share</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->project_name }}</td>
                    <td>{{ rtrim(rtrim(number_format($row->total_quantity, 3), '0'), '.') }}</td>
                    <td><strong>SAR {{ number_format($row->total_value, 2) }}</strong></td>
                    <td>{{ $totalValue > 0 ? number_format((float) $row->total_value / $totalValue * 100, 1) : '0.0' }}%</td>
                </tr>
            @empty
                <tr><td colspan="4" class="table-empty">No material has been issued to a project in this period.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span><strong>Total</strong></span>
            <span><strong>SAR {{ number_format($totalValue, 2) }}</strong></span>
        </x-slot:footer>
    </x-admin.data-table>
@endsection
