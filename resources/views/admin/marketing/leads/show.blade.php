@extends('layouts.admin')

@section('title', 'Lead')
@section('breadcrumb', 'Marketing / Leads & Visits / Lead')

@section('content')
    <x-admin.page-header :title="$lead->company_name" :description="$lead->lead_code.' · '.($lead->city ?? 'city not set').' · assigned to '.($lead->assignee?->name ?? 'nobody yet')">
        <a class="btn outline" href="{{ route('admin.marketing.leads.index') }}">Back to Leads</a>
        @if (auth()->user()->hasPermission('Marketing', 'edit'))
            <a class="btn outline" href="{{ route('admin.marketing.leads.edit', $lead) }}">Edit</a>
        @endif
        @if ($canRecordVisit)
            <a class="btn primary" href="#visit-form">Record Visit</a>
        @endif
        @if ($lead->customer)
            <a class="btn outline" href="{{ route('admin.master.customers.show', $lead->customer) }}">Open Customer {{ $lead->customer->code }}</a>
        @elseif (auth()->user()->hasPermission('Marketing', 'edit'))
            <form method="POST" action="{{ route('admin.marketing.leads.convert', $lead) }}" onsubmit="return confirm('Create a customer record from this lead and mark it Won?');">
                @csrf
                <button type="submit" class="btn primary">Convert to Customer</button>
            </form>
        @endif
    </x-admin.page-header>

    @if ($errors->has('visit'))
        <div class="alert flash">{{ $errors->first('visit') }}</div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$lead->statusLabel()" label="Status"/>
        <x-admin.metric-card color="cyan" :value="$lead->visits->count()" label="Visits Recorded"/>
        <x-admin.metric-card :color="$lead->isFollowUpDue() ? 'red' : 'yellow'" :value="$lead->isOpen() && $lead->next_follow_up_date ? $lead->next_follow_up_date->toDateString() : '-'" :label="$lead->isFollowUpDue() ? 'Follow-up Due' : 'Next Follow-up'"/>
        <x-admin.metric-card color="green" :value="$lead->estimated_value ? 'SAR '.number_format($lead->estimated_value, 2) : '-'" label="Estimated Value"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Lead Information" class="detail-table">
            <tbody>
                <tr><th>Lead Code</th><td>{{ $lead->lead_code }}</td></tr>
                <tr><th>Company / Client</th><td>{{ $lead->company_name }}</td></tr>
                <tr><th>Contact</th><td>{{ $lead->contact_name ?? '-' }}@if($lead->contact_title) ({{ $lead->contact_title }})@endif</td></tr>
                <tr><th>Phone / Email</th><td>{{ $lead->contact_phone ?? '-' }} @if($lead->contact_email)· {{ $lead->contact_email }}@endif</td></tr>
                <tr><th>City / Location</th><td>{{ $lead->city ?? '-' }}@if($lead->location) · {{ $lead->location }}@endif</td></tr>
                <tr><th>Source</th><td>{{ $lead->source ?? '-' }}</td></tr>
                <tr><th>Requirement</th><td>{{ $lead->requirement ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$lead->statusLabel()"/></td></tr>
                <tr><th>Assigned To</th><td>{{ $lead->assignee?->name ?? 'Unassigned' }}</td></tr>
                <tr><th>Created By</th><td>{{ $lead->creator?->name ?? '-' }} · {{ $lead->created_at->toDateString() }}</td></tr>
                <tr><th>Customer</th><td>{{ $lead->customer ? $lead->customer->code.' - '.$lead->customer->name : 'Not converted yet' }}</td></tr>
                <tr><th>Notes</th><td>{{ $lead->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        @if ($canRecordVisit)
            <div id="visit-form">
            <x-admin.form-section title="Record Visit">
                <form method="POST" action="{{ route('admin.marketing.leads.visits.store', $lead) }}">
                    @csrf
                    <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div><label for="visit_date">Visit Date *</label><input id="visit_date" name="visit_date" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old('visit_date', now()->toDateString()) }}" required/>@error('visit_date')<div class="field-error">{{ $message }}</div>@enderror</div>
                        <div><label for="visit_time">Visit Time</label><input id="visit_time" name="visit_time" type="time" class="input" value="{{ old('visit_time') }}"/></div>
                        <div style="grid-column:1 / -1"><label for="location">Location</label><input id="location" name="location" class="input" value="{{ old('location', $lead->location) }}"/></div>
                        <div><label for="person_met">Person Met *</label><input id="person_met" name="person_met" class="input" value="{{ old('person_met', $lead->contact_name) }}" required/>@error('person_met')<div class="field-error">{{ $message }}</div>@enderror</div>
                        <div><label for="person_title">Their Title</label><input id="person_title" name="person_title" class="input" value="{{ old('person_title', $lead->contact_title) }}"/></div>
                        <div>
                            <label for="outcome">Outcome *</label>
                            <select id="outcome" name="outcome" class="select" required>
                                @foreach ($outcomes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('outcome', 'follow_up') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="next_follow_up_date">Next Follow-up</label><input id="next_follow_up_date" name="next_follow_up_date" type="date" class="input" value="{{ old('next_follow_up_date') }}"/>@error('next_follow_up_date')<div class="field-error">{{ $message }}</div>@enderror</div>
                        <div style="grid-column:1 / -1"><label for="next_action">Next Action</label><input id="next_action" name="next_action" class="input" value="{{ old('next_action') }}" placeholder="e.g. Send quotation, call back after tender opening"/></div>
                        <div style="grid-column:1 / -1"><label for="remarks">Remarks</label><textarea id="remarks" name="remarks" class="textarea">{{ old('remarks') }}</textarea></div>
                    </div>
                    <div class="small" style="margin:10px 0">"Deal won" closes the lead as Won; "Not interested" closes it as Lost; any other outcome keeps it open with the follow-up date.</div>
                    <button type="submit" class="btn primary block">Save Visit</button>
                </form>
            </x-admin.form-section>
            </div>
        @else
            <x-admin.data-table title="Record Visit">
                <tbody>
                    <tr><td class="table-empty">
                        @if (! $lead->isOpen())
                            This lead is {{ strtolower($lead->statusLabel()) }}; no further visits are recorded. A manager can reopen it from Edit.
                        @else
                            You do not have permission to record visits.
                        @endif
                    </td></tr>
                </tbody>
            </x-admin.data-table>
        @endif
    </div>

    <x-admin.data-table title="Visit History" :subtitle="$lead->visits->count().' visits'">
        <thead>
            <tr><th>Date / Time</th><th>Visited By</th><th>Location</th><th>Person Met</th><th>Outcome</th><th>Next Follow-up</th><th>Remarks</th></tr>
        </thead>
        <tbody>
            @forelse ($lead->visits as $visit)
                <tr>
                    <td>{{ $visit->visit_date->toDateString() }}@if($visit->timeLabel()) {{ $visit->timeLabel() }}@endif</td>
                    <td>{{ $visit->user?->name ?? '-' }}</td>
                    <td>{{ $visit->location ?? '-' }}</td>
                    <td>{{ $visit->person_met ?? '-' }}@if($visit->person_title)<div class="small">{{ $visit->person_title }}</div>@endif</td>
                    <td><x-admin.status-badge :status="$visit->outcomeLabel()"/></td>
                    <td>{{ $visit->next_follow_up_date?->toDateString() ?? '-' }}@if($visit->next_action)<div class="small">{{ $visit->next_action }}</div>@endif</td>
                    <td>{{ $visit->remarks ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="table-empty">No visits recorded yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
