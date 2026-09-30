@extends('layouts.admin')
@section('title', 'My Approvals')
@section('breadcrumb', 'My Approvals')
@section('content')
    <x-admin.page-header title="My Approvals" description="Only your currently actionable Purchase Request approvals. Open the document and review its items before deciding." />
    <form method="GET" class="card" style="padding:16px">
        <div class="form-grid">
            <div><label for="module">Module</label><select id="module" name="module" class="select"><option value="">All integrated modules</option><option @selected(request('module') === 'Purchase Requests')>Purchase Requests</option></select></div>
            <div><label for="status">Status</label><select id="status" name="status" class="select"><option value="pending">Pending (actionable only)</option></select></div>
            <div><label for="project">Project</label><select id="project" name="project" class="select"><option value="">All permitted projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>@endforeach</select></div>
            <div><label for="from">Submitted from</label><input class="input" id="from" name="from" type="date" value="{{ request('from') }}"></div>
            <div><label for="to">Submitted to</label><input class="input" id="to" name="to" type="date" value="{{ request('to') }}"></div>
        </div>
        <div class="form-actions"><button class="btn primary">Filter</button><a class="btn outline" href="{{ route('admin.my-approvals.index') }}">Reset</a></div>
    </form>
    <x-admin.data-table title="Actionable approvals">
        <thead><tr><th>Document</th><th>Module</th><th>Requested by</th><th>Project / Site</th><th>Submitted</th><th>Current step</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($tasks as $task)
            @php $instance = $task['instance']; $step = $task['step']; $document = $task['document']; $url = route('admin.inventory.purchase-requests.show', $document).'#approvals'; @endphp
            <tr><td><a href="{{ $url }}">{{ $document->pr_number }}</a></td><td>{{ $instance->module }}</td><td>{{ $instance->snapshot['requester_name'] }}</td><td>{{ $document->project?->name ?? '-' }} / {{ $document->site?->name ?? '-' }}</td><td>{{ $instance->requested_at->format('Y-m-d H:i') }}</td><td>{{ $step->step_no }} — {{ $step->snapshot['role_name'] ?? '-' }}</td><td>Pending</td><td><a class="btn sm outline" href="{{ $url }}">View</a> @if($task['approve'])<a class="btn sm primary" href="{{ $url }}">Review &amp; approve</a>@endif @if($task['reject'])<a class="btn sm outline" href="{{ $url }}">Review &amp; reject</a>@endif</td></tr>
        @empty
            <tr><td colspan="8" class="table-empty">No actionable approvals for your account and filters.</td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $tasks->links() }}</x-slot:footer>
    </x-admin.data-table>
@endsection
