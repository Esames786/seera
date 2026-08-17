@extends('layouts.admin')

@section('title', 'Posting Rule')
@section('breadcrumb', 'Accounting / Automatic Posting Rules / Posting Rule')

@section('content')
    <x-admin.page-header :title="$rule->source_module.' — '.$rule->trigger_event" description="Automatic posting rule">
        <a class="btn primary" href="{{ route('admin.accounting.posting-rules.edit', $rule) }}">Edit Rule</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$rule->debitAccount?->account_code ?? 'Document'" label="Debit Account"/>
        <x-admin.metric-card color="cyan" :value="$rule->creditAccount?->account_code ?? 'Document'" label="Credit Account"/>
        <x-admin.metric-card :color="$rule->auto_post ? 'green' : 'yellow'" :value="$rule->auto_post ? 'Auto post' : 'Draft first'" label="Posting Behaviour"/>
        <x-admin.metric-card color="yellow" :value="$rule->approval_required ? 'Yes' : 'No'" label="Approval Required"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Rule Information" class="detail-table">
            <tbody>
                <tr><th>Source Module</th><td>{{ $rule->source_module }}</td></tr>
                <tr><th>Trigger Event</th><td>{{ $rule->trigger_event }}</td></tr>
                <tr><th>Debit Account</th><td>{{ $rule->debitAccount?->label() ?? 'Resolved from the document' }}</td></tr>
                <tr><th>Credit Account</th><td>{{ $rule->creditAccount?->label() ?? 'Resolved from the document' }}</td></tr>
                <tr><th>Cost Center Rule</th><td>{{ $rule->cost_center_rule }}</td></tr>
                <tr><th>Auto Post</th><td>{{ $rule->auto_post ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Approval Required</th><td>{{ $rule->approval_required ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$rule->status"/></td></tr>
                <tr><th>Notes</th><td>{{ $rule->notes ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="Resulting Journal Shape">
                <thead>
                    <tr><th>Side</th><th>Account</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="badge blue">Debit</span></td><td>{{ $rule->debitAccount?->label() ?? 'Resolved from the document lines' }}</td></tr>
                    <tr><td><span class="badge purple">Credit</span></td><td>{{ $rule->creditAccount?->label() ?? 'Resolved from the document lines' }}</td></tr>
                    <tr><td>Cost Center</td><td>{{ $rule->cost_center_rule }}</td></tr>
                </tbody>
            </x-admin.data-table>

            <div class="note">
                {{ $rule->auto_post
                    ? 'When this event fires, the journal entry is created and posted to the general ledger immediately.'
                    : 'When this event fires, the journal entry is created as a draft and waits for a finance manager to post it.' }}
            </div>
        </div>
    </div>
@endsection
