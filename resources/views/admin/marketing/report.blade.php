@extends('layouts.admin')

@section('title', 'Marketing Visit Report')
@section('breadcrumb', 'Marketing / Visit Report')

@section('content')
    <x-admin.page-header title="Visit Report" description="Every recorded visit with client, location, time, person met, outcome, follow-up and remarks">
        <a class="btn outline" href="{{ route('admin.marketing.leads.index') }}">Leads &amp; Visits</a>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <select class="select" style="width:170px" name="preset" data-report-preset title="Quick date range">
            @foreach ($presets as $key => $label)
                <option value="{{ $key }}" @selected($period->preset === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="input" style="width:150px" type="date" name="from" value="{{ $period->from?->toDateString() }}" title="From"/>
        <input class="input" style="width:150px" type="date" name="to" value="{{ $period->to?->toDateString() }}" title="To"/>
        <select class="select" style="width:180px" name="user">
            <option value="">All Staff</option>
            @foreach ($staff as $member)
                <option value="{{ $member->id }}" @selected(request('user') == $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:180px" name="outcome">
            <option value="">All Outcomes</option>
            @foreach ($outcomes as $key => $label)
                <option value="{{ $key }}" @selected(request('outcome') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.marketing.report') }}">Reset</a>
            <button type="submit" name="export" value="csv" class="btn outline">Export Excel (CSV)</button>
            <button type="button" class="btn outline" onclick="window.print()">Export PDF</button>
        </x-slot:actions>
    </x-admin.filter-bar>

    <div class="small" style="margin:-6px 0 14px 2px">Showing: <strong>{{ $period->label() }}</strong></div>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$visits->count()" label="Visits"/>
        <x-admin.metric-card color="cyan" :value="$leadsVisited" label="Clients Visited"/>
        <x-admin.metric-card color="yellow" :value="$followUps" label="Follow-ups Scheduled"/>
        <x-admin.metric-card color="green" :value="$won" label="Deals Won"/>
    </div>

    <x-admin.data-table title="Visits" :subtitle="$openLeads.' open leads overall'">
        <thead>
            <tr><th>Date / Time</th><th>Client</th><th>Location</th><th>Person Met</th><th>Visited By</th><th>Outcome</th><th>Next Follow-up</th><th>Remarks</th></tr>
        </thead>
        <tbody>
            @forelse ($visits as $visit)
                <tr>
                    <td>{{ $visit->visit_date->toDateString() }}@if($visit->timeLabel()) {{ $visit->timeLabel() }}@endif</td>
                    <td>
                        <a href="{{ route('admin.marketing.leads.show', $visit->lead) }}" style="color:var(--blue);font-weight:700">{{ $visit->lead->company_name }}</a>
                        <div class="small">{{ $visit->lead->lead_code }}@if($visit->lead->customer) · Customer {{ $visit->lead->customer->code }}@endif</div>
                    </td>
                    <td>{{ $visit->location ?? '-' }}</td>
                    <td>{{ $visit->person_met ?? '-' }}@if($visit->person_title)<div class="small">{{ $visit->person_title }}</div>@endif</td>
                    <td>{{ $visit->user?->name ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$visit->outcomeLabel()"/></td>
                    <td>{{ $visit->next_follow_up_date?->toDateString() ?? '-' }}@if($visit->next_action)<div class="small">{{ $visit->next_action }}</div>@endif</td>
                    <td>{{ $visit->remarks ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No visits in the selected range.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <script>
        (function () {
            document.querySelectorAll('[data-report-preset]').forEach(function (select) {
                var form = select.form;
                if (!form) return;
                var dates = form.querySelectorAll('input[type="date"]');
                select.addEventListener('change', function () {
                    if (select.value !== 'custom') {
                        dates.forEach(function (input) { input.value = ''; });
                        form.submit();
                    }
                });
                dates.forEach(function (input) {
                    input.addEventListener('change', function () { select.value = 'custom'; });
                });
            });
        })();
    </script>
@endsection
