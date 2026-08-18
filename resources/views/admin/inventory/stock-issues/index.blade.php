@extends('layouts.admin')

@section('title', 'Stock Issues')
@section('breadcrumb', 'Inventory / Stock Issues')

@section('content')
    <x-admin.page-header title="Stock Issues" description="Material issued from a warehouse to a project or site. Posting reduces stock and charges the project.">
        <a class="btn primary" href="{{ route('admin.inventory.stock-issues.create') }}">+ Add Stock Issue</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftCount" label="Draft Issues"/>
        <x-admin.metric-card color="green" :value="$postedCount" label="Posted Issues"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($issuedValue, 2)" label="Issued Value"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($issuedThisMonth, 2)" label="Issued This Month"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Issue number or purpose..."/>
        <select class="select" style="width:180px" name="warehouse">
            <option value="">All Warehouses</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(request('warehouse') == $warehouse->id)>{{ $warehouse->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:170px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.stock-issues.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Stock Issues Listing">
        <thead>
            <tr><th>Issue Number</th><th>Date</th><th>Warehouse</th><th>Project / Site</th><th>Requested By</th><th>Lines</th><th>Total Cost</th><th>Accounting</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($issues as $issue)
                <tr>
                    <td><a href="{{ route('admin.inventory.stock-issues.show', $issue) }}" style="color:var(--blue);font-weight:700">{{ $issue->issue_number }}</a></td>
                    <td>{{ $issue->issue_date->toDateString() }}</td>
                    <td>{{ $issue->warehouse->name }}</td>
                    <td>{{ $issue->project?->name ?? '-' }}@if($issue->site) / {{ $issue->site->name }}@endif</td>
                    <td>{{ $issue->requester?->name ?? '-' }}</td>
                    <td>{{ $issue->lines_count }}</td>
                    <td><strong>SAR {{ number_format($issue->total_cost, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$issue->accounting_posted ? 'posted' : 'pending'"/></td>
                    <td><x-admin.status-badge :status="$issue->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.inventory.stock-issues.show', $issue)"
                            :edit="$issue->isEditable() ? route('admin.inventory.stock-issues.edit', $issue) : null"
                            :delete="$issue->isEditable() ? route('admin.inventory.stock-issues.destroy', $issue) : null"
                            :name="$issue->issue_number">
                            @if ($issue->status === 'draft')
                                <form method="POST" action="{{ route('admin.inventory.stock-issues.post', $issue) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Post</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No stock issues match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $issues->firstItem() ?? 0 }}-{{ $issues->lastItem() ?? 0 }} of {{ $issues->total() }}</span>
            {{ $issues->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        Posting an issue debits project material expense and credits the inventory asset at average cost. Issuing more than the available quantity is rejected.
    </div>
@endsection
