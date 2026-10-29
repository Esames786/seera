@extends('layouts.admin')

@section('title', __('Project Reports'))
@section('breadcrumb', __('Reports / Project Reports'))

@section('content')
    <x-admin.page-header :title="__('Project Reports')" :description="__('Read-only project portfolio from permitted project records. Financial cost and margin remain in the posted-ledger report.')"/>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$projectCount" :label="__('Projects in scope')"/>
        <x-admin.metric-card color="green" :value="$activeCount" :label="__('Active projects in scope')"/>
    </div>

    <x-admin.filter-bar :action="route('admin.reports.projects.index')">
        <input class="input" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="{{ __('Project code or name...') }}"/>
        <select class="select" name="status">
            <option value="">{{ __('All Status') }}</option>
            @foreach (['active', 'planning', 'on hold', 'completed', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucfirst($status)) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.reports.projects.index') }}">{{ __('Reset') }}</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table :title="__('Project portfolio')">
        <thead>
            <tr>
                <th>{{ __('Project Code') }}</th>
                <th>{{ __('Project Name') }}</th>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Project Manager') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Start') }}</th>
                <th>{{ __('End') }}</th>
                @if ($canViewSites)<th>{{ __('Sites') }}</th>@endif
                <th>{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projects as $project)
                <tr>
                    <td>{{ $project->code }}</td>
                    <td>{{ $project->name }}</td>
                    <td>{{ $project->customer?->name ?? '-' }}</td>
                    <td>{{ $project->manager?->name ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$project->status"/></td>
                    <td>{{ $project->start_date?->toDateString() ?? '-' }}</td>
                    <td>{{ $project->end_date?->toDateString() ?? '-' }}</td>
                    @if ($canViewSites)<td>{{ $project->sites_count }}</td>@endif
                    <td><a class="btn sm outline" href="{{ route('admin.master.projects.show', $project) }}">{{ __('View') }}</a></td>
                </tr>
            @empty
                <tr><td class="table-empty" colspan="{{ $canViewSites ? 9 : 8 }}">{{ __('No projects match your access scope and selected filters.') }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            {{ $projects->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    @if ($canViewFinancialReports)
        <div class="help-box">
            {{ __('Financial cost, revenue and margin are calculated from posted journals in the existing Project Cost Report, not from this operational listing.') }}
            <a class="btn sm primary" href="{{ route('admin.accounting.reports.project-cost-report') }}">{{ __('Open Project Cost Report') }}</a>
        </div>
    @else
        <div class="help-box">{{ __('Financial figures are hidden because you do not have Financial Reports permission.') }}</div>
    @endif
@endsection
