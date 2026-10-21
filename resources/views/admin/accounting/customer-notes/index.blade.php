@extends('layouts.admin')

@section('title', __('customer_notes.title'))
@section('breadcrumb', 'Accounting / Accounts Receivable / '.__('customer_notes.title'))

@section('content')
    @php($money = fn ($v) => 'SAR '.number_format((float) $v, 2))
    <x-admin.page-header :title="__('customer_notes.title')" :description="__('customer_notes.register_help')">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.index') }}">Accounts Receivable</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="yellow" :value="$money($creditedTotal)" :label="__('customer_notes.credited').' (posted)'"/>
        <x-admin.metric-card color="blue" :value="$money($debitedTotal)" :label="__('customer_notes.debited').' (posted)'"/>
        <x-admin.metric-card color="purple" :value="$money($unappliedCredit)" :label="__('customer_notes.unapplied')"/>
        <x-admin.metric-card color="cyan" :value="$draftCount" label="Draft notes"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:240px" type="search" name="search" value="{{ request('search') }}" placeholder="Note, invoice number or customer..."/>
        <select class="select" style="width:160px" name="type">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ __('customer_notes.type_'.$type) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="status">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <select class="select" style="width:180px" name="customer">
            <option value="">All Customers</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected(request('customer') == $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.notes.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table :title="__('customer_notes.title')">
        <thead>
            <tr>
                <th>Note</th><th>Type</th><th>Customer</th><th>{{ __('customer_notes.original_invoice') }}</th><th>Date</th>
                <th>Taxable</th><th>VAT</th><th>Total</th><th>Status</th><th>ZATCA</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($notes as $note)
                <tr>
                    <td><a href="{{ route('admin.accounting.accounts-receivable.notes.show', $note) }}" style="color:var(--blue);font-weight:700">{{ $note->note_number }}</a></td>
                    <td>{{ $note->typeLabel() }}</td>
                    <td>{{ $note->customer?->name }}</td>
                    <td>@if ($note->invoice)<a href="{{ route('admin.accounting.accounts-receivable.show', $note->invoice) }}">{{ $note->invoice->invoice_number }}</a>@else - @endif</td>
                    <td>{{ $note->note_date->toDateString() }}</td>
                    <td>{{ number_format($note->taxable_amount, 2) }}</td>
                    <td>{{ number_format($note->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($note->total_amount, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$note->status"/></td>
                    <td>@if ($note->zatcaRecord)<span class="badge {{ $note->zatcaRecord->statusColor() }}">{{ $note->zatcaRecord->statusLabel() }}</span>@else <span class="small">-</span>@endif</td>
                    <td><a class="btn sm primary" href="{{ route('admin.accounting.accounts-receivable.notes.show', $note) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">{{ __('customer_notes.no_notes') }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $notes->firstItem() ?? 0 }}-{{ $notes->lastItem() ?? 0 }} of {{ $notes->total() }}</span>
            {{ $notes->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
