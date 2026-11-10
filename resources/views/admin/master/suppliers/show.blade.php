@extends('layouts.admin')

@section('title', __('Supplier Details'))
@section('breadcrumb', __('Master Setup / Suppliers / Supplier Details'))

@section('content')
    <x-admin.page-header :title="$supplier->name"  :description="__('Read-only supplier view. Nothing on this page changes data.')">
        @if (auth()->user()->hasPermission('Suppliers', 'edit'))
            <a class="btn primary" href="{{ route('admin.master.suppliers.edit', $supplier) }}">{{ __('Edit / Manage') }}</a>
        @endif
        <a class="btn outline" href="{{ route('admin.master.suppliers.index') }}">{{ __('Back to Suppliers') }}</a>
    </x-admin.page-header>

    @include('admin.master.suppliers._workspace-header', ['supplier' => $supplier, 'summary' => $summary])

    <nav class="tabs workspace-nav" aria-label="{{ __('Supplier sections') }}">
        <a class="tab" href="#profile">{{ __('Profile') }}</a>
        @foreach ($panels as $key => $data)
            <a class="tab" href="#{{ $key }}">{{ __($data['title']) }}</a>
        @endforeach
    </nav>

    <x-admin.data-table :title="__('Supplier Information')" class="detail-table" id="profile">
        <tbody>
            <tr><th>{{ __('Supplier Name') }}</th><td>{{ $supplier->name }}</td></tr>
            <tr><th>{{ __('Supplier Code') }}</th><td>{{ $supplier->code }}</td></tr>
            <tr><th>{{ __('Category') }}</th><td>{{ $supplier->category ?? '-' }}</td></tr>
            <tr><th>{{ __('City / Location') }}</th><td>{{ $supplier->city ?? '-' }}</td></tr>
            <tr><th>{{ __('Rating') }}</th><td>@if($supplier->rating)<span class="badge {{ match ($supplier->rating) { 'Green' => 'green', 'Amber' => 'yellow', default => 'red' } }}">{{ $supplier->rating }}</span>@else - @endif</td></tr>
            <tr><th>{{ __('VAT Number') }}</th><td>{{ $supplier->vat_number ?? '-' }}</td></tr>
            <tr><th>{{ __('CR Number') }}</th><td>{{ $supplier->cr_number ?? '-' }}</td></tr>
            <tr><th>{{ __('Contact Person') }}</th><td>{{ $supplier->contact_person ?? '-' }}</td></tr>
            <tr><th>{{ __('Phone') }}</th><td>{{ $supplier->phone ?? '-' }}</td></tr>
            <tr><th>{{ __('Email') }}</th><td>{{ $supplier->email ?? '-' }}</td></tr>
            <tr><th>{{ __('Payment Terms') }}</th><td>{{ $supplier->paymentTerm?->label() ?? $supplier->payment_terms ?? '-' }}</td></tr>
            <tr><th>{{ __('Accepted Payment Types') }}</th><td>{{ __($supplier->allowed_payment_types ?? 'Both') }}</td></tr>
            <tr><th>{{ __('Bank') }}</th><td>{{ $supplier->bank_name ?? '-' }}@if($supplier->bank_account_name) · {{ $supplier->bank_account_name }}@endif</td></tr>
            <tr><th>{{ __('IBAN') }}</th><td>{{ $supplier->iban ?? '-' }}</td></tr>
            <tr><th>{{ __('Linked Payable Account') }}</th><td>{{ $supplier->linkedAccount?->label() ?? $supplier->linked_account ?? '-' }}</td></tr>
            <tr><th>{{ __('Opening Balance (master data)') }}</th><td>SAR {{ number_format($supplier->opening_balance, 2) }}</td></tr>
            <tr><th>{{ __('Address') }}</th><td>{{ $supplier->address ?? '-' }}</td></tr>
            <tr><th>{{ __('Status') }}</th><td><x-admin.status-badge :status="$supplier->status"/></td></tr>
        </tbody>
    </x-admin.data-table>

    <x-admin.record-documents :owner="$supplier" kind="supplier" :readonly="true"/>
    @foreach ($panels as $key => $data)
        @include('admin.master.suppliers._workspace-panel', $data)
    @endforeach
@endsection
