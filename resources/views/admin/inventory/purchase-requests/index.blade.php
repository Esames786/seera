@extends('layouts.admin')

@section('title', 'Purchase Requests')
@section('breadcrumb', 'Inventory / Purchase Requests')

@section('content')
    <x-admin.page-header title="Purchase Requests" description="Site and project material requests awaiting approval and conversion into purchase orders">
        <a class="btn primary" href="{{ route('admin.inventory.purchase-requests.create') }}">+ Add Purchase Request</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$pendingCount" label="Pending Approval"/>
        <x-admin.metric-card color="green" :value="$approvedCount" label="Approved"/>
        <x-admin.metric-card color="red" :value="$rejectedCount" label="Rejected"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($estimatedValue, 2)" label="Estimated Value"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="PR number or reason..."/>
        <select class="select" style="width:170px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="priority">
            <option value="">All Priorities</option>
            @foreach ($priorities as $priority)
                <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.purchase-requests.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Purchase Requests Listing">
        <thead>
            <tr><th>PR Number</th><th>Request Date</th><th>Requested By</th><th>Project / Site</th><th>Warehouse</th><th>Required</th><th>Lines</th><th>Estimated</th><th>Priority</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($requests as $pr)
                <tr>
                    <td><a href="{{ route('admin.inventory.purchase-requests.show', $pr) }}" style="color:var(--blue);font-weight:700">{{ $pr->pr_number }}</a></td>
                    <td>{{ $pr->request_date->toDateString() }}</td>
                    <td>{{ $pr->requester?->name ?? '-' }}</td>
                    <td>{{ $pr->project?->name ?? '-' }}</td>
                    <td>{{ $pr->warehouse?->name ?? '-' }}</td>
                    <td>{{ $pr->required_date?->toDateString() ?? '-' }}</td>
                    <td>{{ $pr->lines_count }}</td>
                    <td>SAR {{ number_format($pr->estimated_total, 2) }}</td>
                    <td><x-admin.status-badge :status="$pr->priority === 'urgent' ? 'absent' : ($pr->priority === 'high' ? 'late' : 'info')"/></td>
                    <td><x-admin.status-badge :status="$pr->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.inventory.purchase-requests.show', $pr)"
                            :edit="$pr->isEditable() ? route('admin.inventory.purchase-requests.edit', $pr) : null"
                            :delete="$pr->isEditable() ? route('admin.inventory.purchase-requests.destroy', $pr) : null"
                            :name="$pr->pr_number">
                            @if (in_array($pr->status, ['draft', 'pending']))
                                <form method="POST" action="{{ route('admin.inventory.purchase-requests.approve', $pr) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @elseif ($pr->status === 'approved')
                                <a class="btn sm warning" href="{{ route('admin.inventory.purchase-orders.create', ['purchase_request' => $pr->id]) }}">Create PO</a>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No purchase requests match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $requests->firstItem() ?? 0 }}-{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }}</span>
            {{ $requests->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
