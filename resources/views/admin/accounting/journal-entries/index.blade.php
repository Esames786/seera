@extends('layouts.admin')

@section('title', 'Journal Entries')
@section('breadcrumb', 'Accounting / Journal Entries')

@section('content')
    <x-admin.page-header title="Journal Entries" description="Manual and automatic double-entry transactions. Only a balanced journal can be posted.">
        <a class="btn primary" href="{{ route('admin.accounting.journal-entries.create') }}">+ Add Journal Entry</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$draftCount" label="Draft Journals"/>
        <x-admin.metric-card color="green" :value="$postedCount" label="Posted Journals"/>
        <x-admin.metric-card color="red" :value="$cancelledCount" label="Cancelled Journals"/>
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($postedTotal, 2)" label="Posted Value"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:200px" type="search" name="search" value="{{ request('search') }}" placeholder="Journal no, reference..."/>
        <input class="input" style="width:150px" type="date" name="from" value="{{ request('from') }}"/>
        <input class="input" style="width:150px" type="date" name="to" value="{{ request('to') }}"/>
        <select class="select" style="width:170px" name="source">
            <option value="">All Sources</option>
            @foreach ($sourceModules as $module)
                <option value="{{ $module }}" @selected(request('source') === $module)>{{ $module }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.journal-entries.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Journal Entries Listing">
        <thead>
            <tr><th>Journal No</th><th>Date</th><th>Reference</th><th>Source</th><th>Description</th><th>Lines</th><th>Debit</th><th>Credit</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td><a href="{{ route('admin.accounting.journal-entries.show', $entry) }}" style="color:var(--blue);font-weight:700">{{ $entry->journal_number }}</a></td>
                    <td>{{ $entry->journal_date->toDateString() }}</td>
                    <td>{{ $entry->reference_number ?? '-' }}</td>
                    <td>{{ $entry->source_module }}</td>
                    <td>{{ $entry->description ? Str::limit($entry->description, 32) : '-' }}</td>
                    <td>{{ $entry->lines_count }}</td>
                    <td>SAR {{ number_format($entry->total_debit, 2) }}</td>
                    <td>SAR {{ number_format($entry->total_credit, 2) }}</td>
                    <td><x-admin.status-badge :status="$entry->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.accounting.journal-entries.show', $entry)"
                            :edit="$entry->isEditable() ? route('admin.accounting.journal-entries.edit', $entry) : null"
                            :delete="$entry->status !== 'posted' ? route('admin.accounting.journal-entries.destroy', $entry) : null"
                            :name="$entry->journal_number">
                            @if ($entry->status !== 'posted' && $entry->status !== 'cancelled')
                                <form method="POST" action="{{ route('admin.accounting.journal-entries.post', $entry) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Post</button>
                                </form>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No journal entries match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }}</span>
            {{ $entries->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
