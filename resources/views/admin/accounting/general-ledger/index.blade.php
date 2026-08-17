@extends('layouts.admin')

@section('title', 'General Ledger')
@section('breadcrumb', 'Accounting / General Ledger')

@section('content')
    <x-admin.page-header title="General Ledger" description="Account-wise transaction history from posted journal entries">
        <a class="btn outline" href="{{ route('admin.accounting.reports.trial-balance') }}">Trial Balance</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($totalDebit, 2)" label="Total Debit"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($totalCredit, 2)" label="Total Credit"/>
        <x-admin.metric-card color="cyan" :value="$account ? 'SAR '.number_format($openingBalance, 2) : '-'" label="Opening Balance"/>
        <x-admin.metric-card color="green" :value="$lines->total()" label="Ledger Lines"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:150px" type="date" name="from" value="{{ request('from') }}"/>
        <input class="input" style="width:150px" type="date" name="to" value="{{ request('to') }}"/>
        <select class="select" style="width:230px" name="account">
            <option value="">All Accounts</option>
            @foreach ($accounts as $option)
                <option value="{{ $option->id }}" @selected(request('account') == $option->id)>{{ $option->label() }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="cost_center">
            <option value="">All Cost Centers</option>
            @foreach ($costCenters as $costCenter)
                <option value="{{ $costCenter->id }}" @selected(request('cost_center') == $costCenter->id)>{{ $costCenter->code }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="site">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected(request('site') == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="source">
            <option value="">All Sources</option>
            @foreach ($sourceModules as $module)
                <option value="{{ $module }}" @selected(request('source') === $module)>{{ $module }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="posted_only">
            <option value="1" @selected($postedOnly)>Posted only</option>
            <option value="0" @selected(! $postedOnly)>Include drafts</option>
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.general-ledger') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    @unless ($account)
        <div class="help-box">
            Select a single account to see a running balance column. Without an account filter the ledger lists every posted line in date order.
        </div>
    @endunless

    <x-admin.data-table :title="$account ? 'Ledger: '.$account->label() : 'General Ledger'">
        <thead>
            <tr>
                <th>Date</th><th>Voucher</th><th>Account</th><th>Description</th>
                <th>Source</th><th>Cost Center</th><th>Debit</th><th>Credit</th>
                @if ($account)<th>Balance</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line->journalEntry->journal_date->toDateString() }}</td>
                    <td><a href="{{ route('admin.accounting.journal-entries.show', $line->journalEntry) }}" style="color:var(--blue);font-weight:700">{{ $line->journalEntry->journal_number }}</a></td>
                    <td>{{ $line->account->label() }}</td>
                    <td>{{ $line->description ?? $line->journalEntry->description ?? '-' }}</td>
                    <td>{{ $line->journalEntry->source_module }}</td>
                    <td>{{ $line->costCenter?->code ?? '-' }}</td>
                    <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                    <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                    @if ($account)<td><strong>{{ number_format($line->running_balance, 2) }}</strong></td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $account ? 9 : 8 }}" class="table-empty">No ledger lines match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $lines->firstItem() ?? 0 }}-{{ $lines->lastItem() ?? 0 }} of {{ $lines->total() }}</span>
            {{ $lines->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
