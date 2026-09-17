@extends('layouts.admin')

@section('title', 'Marketing Leads')
@section('breadcrumb', 'Marketing / Leads & Visits')

@section('content')
    <x-admin.page-header title="Leads &amp; Visits" description="Prospects the marketing team is working on: create a lead, assign it, record each visit and its follow-up">
        <a class="btn outline" href="{{ route('admin.marketing.report') }}">Visit Report</a>
        @if (auth()->user()->hasPermission('Marketing', 'create'))
            <a class="btn primary" href="{{ route('admin.marketing.leads.create') }}">+ New Lead</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$openCount" label="Open Leads"/>
        <x-admin.metric-card :color="$dueCount > 0 ? 'red' : 'green'" :value="$dueCount" label="Follow-ups Due"/>
        <x-admin.metric-card color="cyan" :value="$visitsThisMonth" label="Visits This Month"/>
        <x-admin.metric-card color="green" :value="$wonCount" label="Won"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" name="search" value="{{ request('search') }}" placeholder="Search company, contact, city, code"/>
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($isManager)
            <select class="select" style="width:180px" name="assigned_to">
                <option value="">All Staff</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}" @selected(request('assigned_to') == $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
        @endif
        <label class="small" style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="due" value="1" @checked(request('due'))/> Follow-up due</label>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.marketing.leads.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Leads" :subtitle="$leads->total().' found'">
        <thead>
            <tr><th>Code</th><th>Company</th><th>Contact</th><th>City</th><th>Assigned To</th><th>Status</th><th>Next Follow-up</th><th>Visits</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td><a href="{{ route('admin.marketing.leads.show', $lead) }}" style="color:var(--blue);font-weight:700">{{ $lead->lead_code }}</a></td>
                    <td>
                        <strong>{{ $lead->company_name }}</strong>
                        @if ($lead->customer)<div class="small">Customer {{ $lead->customer->code }}</div>@endif
                    </td>
                    <td>{{ $lead->contact_name ?? '-' }}@if($lead->contact_phone)<div class="small">{{ $lead->contact_phone }}</div>@endif</td>
                    <td>{{ $lead->city ?? '-' }}</td>
                    <td>@if ($lead->assignee){{ $lead->assignee->name }}@else<span class="small">Unassigned</span>@endif</td>
                    <td><x-admin.status-badge :status="$lead->statusLabel()"/></td>
                    <td>
                        @if ($lead->next_follow_up_date && $lead->isOpen())
                            {{ $lead->next_follow_up_date->toDateString() }}
                            @if ($lead->isFollowUpDue())<span class="badge red">Due</span>@endif
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $lead->visits_count }}</td>
                    <td>
                        <div class="actions">
                            <a class="btn sm primary" href="{{ route('admin.marketing.leads.show', $lead) }}">View</a>
                            @if (auth()->user()->hasPermission('Marketing', 'edit'))
                                <a class="btn sm" href="{{ route('admin.marketing.leads.edit', $lead) }}">Edit</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No leads yet. Create the first one with "+ New Lead".</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $leads->firstItem() ?? 0 }}-{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }}</span>
            {{ $leads->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="help-box">
        Flow: a manager creates the lead and assigns it → the staff member visits and records who they met and the outcome → the follow-up date keeps the lead in view → the lead is won (converted to a customer) or lost. Managers see every lead; staff see the leads assigned to them.
    </div>
@endsection
