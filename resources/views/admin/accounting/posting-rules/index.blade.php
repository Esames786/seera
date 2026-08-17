@extends('layouts.admin')

@section('title', 'Automatic Posting Rules')
@section('breadcrumb', 'Accounting / Automatic Posting Rules')

@section('content')
    <x-admin.page-header title="Automatic Posting Rules" description="How payroll, site expenses, inventory, bills and invoices turn into journal entries">
        <a class="btn primary" href="{{ route('admin.accounting.posting-rules.create') }}">+ Add Posting Rule</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalRules" label="Total Rules"/>
        <x-admin.metric-card color="green" :value="$activeRules" label="Active Rules"/>
        <x-admin.metric-card color="cyan" :value="$autoPostRules" label="Auto Post Enabled"/>
        <x-admin.metric-card color="yellow" :value="$approvalRules" label="Approval Required"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Module or trigger event..."/>
        <select class="select" style="width:180px" name="module">
            <option value="">All Modules</option>
            @foreach ($sourceModules as $module)
                <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.posting-rules.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Posting Rules">
        <thead>
            <tr><th>Source Module</th><th>Trigger Event</th><th>Debit Account</th><th>Credit Account</th><th>Cost Center Rule</th><th>Auto Post</th><th>Approval</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($rules as $rule)
                <tr>
                    <td><a href="{{ route('admin.accounting.posting-rules.show', $rule) }}" style="color:var(--blue);font-weight:700">{{ $rule->source_module }}</a></td>
                    <td>{{ $rule->trigger_event }}</td>
                    <td>{{ $rule->debitAccount?->label() ?? '-' }}</td>
                    <td>{{ $rule->creditAccount?->label() ?? '-' }}</td>
                    <td>{{ $rule->cost_center_rule }}</td>
                    <td><x-admin.status-badge :status="$rule->auto_post ? 'yes' : 'no'"/></td>
                    <td><x-admin.status-badge :status="$rule->approval_required ? 'yes' : 'no'"/></td>
                    <td><x-admin.status-badge :status="$rule->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.accounting.posting-rules.show', $rule)"
                            :edit="route('admin.accounting.posting-rules.edit', $rule)"
                            :delete="route('admin.accounting.posting-rules.destroy', $rule)"
                            :name="$rule->source_module.' - '.$rule->trigger_event"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No posting rules match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $rules->firstItem() ?? 0 }}-{{ $rules->lastItem() ?? 0 }} of {{ $rules->total() }}</span>
            {{ $rules->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <div class="note">
        A rule with auto post enabled sends the generated journal entry straight to the ledger. With auto post off, the entry is created as a draft for a finance manager to review and post.
    </div>
@endsection
