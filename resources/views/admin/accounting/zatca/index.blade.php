@extends('layouts.admin')

@section('title', __('zatca.title'))
@section('breadcrumb', 'Accounting / '.__('zatca.title'))

@section('content')
    <x-admin.page-header :title="__('zatca.title')" :description="__('zatca.env_'.$environment).' · '.($unit ? $unit->name.' — '.$unit->statusLabel() : __('zatca.no_unit'))">
        @if ($canConfigure)
            <a class="btn outline" href="{{ route('admin.accounting.zatca.settings.index') }}">{{ __('zatca.settings_title') }}</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="green" :value="(int) (($counts['cleared'] ?? 0) + ($counts['reported'] ?? 0))" :label="__('zatca.status_cleared').' / '.__('zatca.status_reported')"/>
        <x-admin.metric-card color="yellow" :value="(int) (($counts['signed'] ?? 0) + ($counts['validated'] ?? 0) + ($counts['submission_pending'] ?? 0))" :label="__('zatca.status_signed').' / '.__('zatca.status_validated')"/>
        <x-admin.metric-card color="red" :value="(int) (($counts['rejected'] ?? 0) + ($counts['failed'] ?? 0) + ($counts['validation_failed'] ?? 0))" :label="__('zatca.status_rejected').' / '.__('zatca.status_failed')"/>
        <x-admin.metric-card color="blue" :value="(int) ($counts['outcome_unknown'] ?? 0)" :label="__('zatca.status_outcome_unknown')"/>
    </div>

    <div class="note">{{ __('zatca.sdk_note') }}</div>
    <br/>

    <x-admin.filter-bar>
        <input class="input" style="width:260px" type="search" name="search" value="{{ request('search') }}" placeholder="UUID or invoice number..."/>
        <select class="select" style="width:230px" name="status">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('zatca.status_'.$status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.zatca.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table :title="__('zatca.record_title')">
        <thead>
            <tr>
                <th>Invoice</th><th>{{ __('zatca.invoice_type') }}</th><th>Customer</th><th>Issue date</th>
                <th>{{ __('zatca.environment') }}</th><th>{{ __('zatca.icv') }}</th><th>Status</th><th>{{ __('zatca.zatca_reference') }}</th><th>{{ __('zatca.attempts') }}</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>
                        @if ($record->customerInvoice)
                            <a href="{{ route('admin.accounting.accounts-receivable.show', $record->customerInvoice) }}" style="color:var(--blue);font-weight:700">{{ $record->customerInvoice->invoice_number }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td class="small">{{ $record->invoice_type ? __('zatca.type_'.$record->invoice_type) : '-' }}</td>
                    <td>{{ $record->customerInvoice?->customer?->name ?? '-' }}</td>
                    <td>{{ $record->customerInvoice?->invoice_date?->toDateString() ?? '-' }}</td>
                    <td class="small">{{ $record->environment ? __('zatca.env_'.$record->environment) : '-' }}</td>
                    <td>{{ $record->icv ?? '-' }}</td>
                    <td><span class="badge {{ $record->statusColor() }}">{{ $record->statusLabel() }}</span>@if ($record->accepted_with_warnings) <span class="small">({{ __('zatca.warnings') }})</span>@endif</td>
                    <td class="small">{{ $record->zatca_status ?? '-' }}</td>
                    <td>{{ $record->submission_attempts }}</td>
                    <td><a class="btn sm primary" href="{{ route('admin.accounting.zatca.show', $record) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">{{ __('zatca.no_record') }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>
            {{ $records->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
