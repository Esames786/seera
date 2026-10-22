@extends('layouts.admin')

@section('title', __('customer_notes.credits_title'))
@section('breadcrumb', 'Accounting / Accounts Receivable / '.__('customer_notes.credits_title'))

@section('content')
    @php($money = fn ($v) => 'SAR '.number_format((float) $v, 2))
    <x-admin.page-header :title="__('customer_notes.credits_title')" :description="__('customer_notes.credits_help')">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.notes.index') }}">{{ __('customer_notes.title') }}</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="purple" :value="$money($totals['available'])" :label="__('customer_notes.available')"/>
        <x-admin.metric-card color="blue" :value="$money($totals['applied'])" :label="__('customer_notes.applied_to_invoices')"/>
        <x-admin.metric-card color="green" :value="$money($totals['refunded'])" :label="__('customer_notes.refunded')"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:240px" type="search" name="search" value="{{ request('search') }}" placeholder="Credit note or customer..."/>
        <select class="select" style="width:180px" name="customer">
            <option value="">All Customers</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected(request('customer') == $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="status">
            <option value="">All statuses</option>
            @foreach (['available', 'partially_used', 'fully_used'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('customer_notes.credit_status_'.$status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.credits.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table :title="__('customer_notes.credits_title')">
        <thead>
            <tr>
                <th>Customer</th><th>{{ __('customer_notes.type_credit') }}</th><th>{{ __('customer_notes.original_invoice') }}</th><th>Date</th>
                <th>{{ __('customer_notes.credit_amount') }}</th><th>{{ __('customer_notes.applied_to_invoices') }}</th><th>{{ __('customer_notes.refunded') }}</th><th>{{ __('customer_notes.available') }}</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php($note = $row['note'])
                <tr>
                    <td>{{ $note->customer?->name }}</td>
                    <td><a href="{{ route('admin.accounting.accounts-receivable.notes.show', $note) }}" style="color:var(--blue);font-weight:700">{{ $note->note_number }}</a></td>
                    <td>{{ $note->invoice?->invoice_number ?? '-' }}</td>
                    <td>{{ $note->note_date->toDateString() }}</td>
                    <td>{{ number_format($row['credit'], 2) }}</td>
                    <td>{{ number_format($row['applied'], 2) }}</td>
                    <td>{{ number_format($row['refunded'], 2) }}</td>
                    <td><strong>{{ number_format($row['available'], 2) }}</strong></td>
                    <td><span class="badge {{ $row['status'] === 'fully_used' ? 'gray' : ($row['status'] === 'partially_used' ? 'yellow' : 'purple') }}">{{ __('customer_notes.credit_status_'.$row['status']) }}</span></td>
                    <td><a class="btn sm primary" href="{{ route('admin.accounting.accounts-receivable.notes.show', $note) }}#resolution">View</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">{{ __('customer_notes.no_notes') }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $notes->firstItem() ?? 0 }}-{{ $notes->lastItem() ?? 0 }} of {{ $notes->total() }}</span>
            {{ $notes->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
