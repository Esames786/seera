@extends('layouts.admin')
@section('title', __('approvals_ui.title'))
@section('breadcrumb', __('approvals_ui.title'))
@section('content')
    <x-admin.page-header :title="__('approvals_ui.title')" :description="__('approvals_ui.description')" />
    <form method="GET" class="card" style="padding:16px">
        <div class="form-grid">
            <div><label for="module">{{ __('approvals_ui.module') }}</label><select id="module" name="module" class="select"><option value="">{{ __('approvals_ui.all_modules') }}</option>@foreach(['Purchase Requests', 'Site Expenses', 'Accounts Payable'] as $module)@if(auth()->user()->hasPermission($module, 'view'))<option value="{{ $module }}" @selected(request('module') === $module)>{{ __(match ($module) { 'Purchase Requests' => 'approvals_ui.purchase_requests', 'Site Expenses' => 'approvals_ui.site_expenses', default => 'approvals_ui.supplier_bills' }) }}</option>@endif @endforeach</select></div>
            <div><label for="status">{{ __('approvals_ui.status') }}</label><select id="status" name="status" class="select"><option value="pending">{{ __('approvals_ui.pending_actionable') }}</option></select></div>
            <div><label for="project">{{ __('approvals_ui.project') }}</label><select id="project" name="project" class="select"><option value="">{{ __('approvals_ui.all_permitted_projects') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>@endforeach</select></div>
            <div><label for="from">{{ __('approvals_ui.submitted_from') }}</label><input class="input" id="from" name="from" type="date" value="{{ request('from') }}"></div>
            <div><label for="to">{{ __('approvals_ui.submitted_to') }}</label><input class="input" id="to" name="to" type="date" value="{{ request('to') }}"></div>
        </div>
        <div class="form-actions"><button class="btn primary">{{ __('approvals_ui.filter') }}</button><a class="btn outline" href="{{ route('admin.my-approvals.index') }}">{{ __('approvals_ui.reset') }}</a></div>
    </form>
    <x-admin.data-table :title="__('approvals_ui.actionable_approvals')">
        <thead><tr><th>{{ __('approvals_ui.document') }}</th><th>{{ __('approvals_ui.module') }}</th><th>{{ __('approvals_ui.requested_by') }}</th><th>{{ __('approvals_ui.project_site') }}</th><th>{{ __('approvals_ui.submitted') }}</th><th>{{ __('approvals_ui.current_step') }}</th><th>{{ __('approvals_ui.status') }}</th><th>{{ __('approvals_ui.actions') }}</th></tr></thead>
        <tbody>
        @forelse($tasks as $task)
            @php $instance = $task['instance']; $step = $task['step']; $document = $task['document']; $url = $task['url']; @endphp
            @if($instance->source_type === 'supplier_bill')<tr><td colspan="8">{{ __('approvals_ui.supplier_bill') }} · {{ $document->supplier?->name }} · SAR {{ $document->total_amount }} · {{ __('approvals_ui.submitted_by') }} {{ $instance->snapshot['submitter_name'] ?? '-' }}</td></tr>@endif
            <tr><td><a href="{{ $url }}">{{ $task['reference'] }}</a></td><td>{{ __(match ($instance->module) { 'Purchase Requests' => 'approvals_ui.purchase_requests', 'Site Expenses' => 'approvals_ui.site_expenses', 'Accounts Payable' => 'approvals_ui.supplier_bills', default => 'approvals_ui.module' }) }}</td><td>{{ $instance->snapshot['requester_name'] }}</td><td>{{ $document->project?->name ?? '-' }} / {{ $document->site?->name ?? '-' }}</td><td>{{ $instance->requested_at->format('Y-m-d H:i') }}</td><td>{{ $step->step_no }} — {{ $step->snapshot['role_name'] ?? '-' }}</td><td>{{ __('approvals_ui.pending') }}</td><td><a class="btn sm outline" href="{{ $url }}">{{ __('approvals_ui.view') }}</a> @if($task['approve'])<a class="btn sm primary" href="{{ $url }}">{{ __('approvals_ui.review_approve') }}</a>@endif @if($task['reject'])<a class="btn sm outline" href="{{ $url }}">{{ __('approvals_ui.review_reject') }}</a>@endif</td></tr>
        @empty
            <tr><td colspan="8" class="table-empty">{{ __('approvals_ui.no_tasks') }}</td></tr>
        @endforelse
        </tbody>
        <x-slot:footer>{{ $tasks->links() }}</x-slot:footer>
    </x-admin.data-table>
@endsection
