@extends('layouts.admin')

@section('title', 'Customer Details')
@section('breadcrumb', 'Master Setup / Customers / Customer Details')

@section('content')
    @php $ratingColor = match ($customer->rating) { 'Green' => 'green', 'Amber' => 'yellow', 'Red' => 'red', default => 'cyan' }; @endphp
    <x-admin.page-header :title="$customer->name" description="Read-only customer view. Nothing on this page changes data.">
        @if (auth()->user()->hasPermission('Customers', 'edit'))
            <a class="btn primary" href="{{ route('admin.master.customers.edit', $customer) }}">Edit / Manage</a>
        @endif
        <a class="btn outline" href="{{ route('admin.master.customers.index') }}">Back to Customers</a>
    </x-admin.page-header>

    @include('admin.master.customers._workspace-header', ['customer' => $customer, 'summary' => $summary])

    <nav class="tabs workspace-nav" aria-label="Customer sections">
        <a class="tab" href="#profile">Profile</a>
        @foreach ($panels as $key => $data)
            <a class="tab" href="#{{ $key }}">{{ __($data['title']) }}</a>
        @endforeach
    </nav>

    <x-admin.data-table title="Customer Information" class="detail-table" id="profile">
        <tbody>
            <tr><th>Customer Name</th><td>{{ $customer->name }}</td></tr>
            <tr><th>Customer Code</th><td>{{ $customer->code }}</td></tr>
            <tr><th>Type</th><td>{{ $customer->type }}</td></tr>
            <tr><th>Rating</th><td>@if($customer->rating)<span class="badge {{ $ratingColor }}">{{ $customer->rating }}</span>@else - @endif</td></tr>
            <tr><th>{{ __('ui.payment_types') }}</th><td>{{ __('ui.'.strtolower($customer->allowed_payment_types ?? 'Both')) }}</td></tr>
            <tr><th>VAT Number</th><td>{{ $customer->vat_number ?? '-' }}</td></tr>
            <tr><th>CR Number</th><td>{{ $customer->cr_number ?? '-' }}</td></tr>
            <tr><th>Contact Person</th><td>{{ $customer->contact_person ?? '-' }}</td></tr>
            <tr><th>Phone</th><td>{{ $customer->phone ?? '-' }}</td></tr>
            <tr><th>Email</th><td>{{ $customer->email ?? '-' }}</td></tr>
            <tr><th>Credit Limit</th><td>SAR {{ number_format($customer->credit_limit, 2) }}</td></tr>
            <tr><th>Linked Receivable Account</th><td>{{ $customer->linked_account ?? '-' }}</td></tr>
            <tr><th>Opening Receivable (master data)</th><td>SAR {{ number_format($customer->opening_receivable, 2) }}</td></tr>
            <tr><th>Billing Address</th><td>{{ $customer->billing_address ?? '-' }}</td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$customer->status"/></td></tr>
        </tbody>
    </x-admin.data-table>

    @foreach ($panels as $key => $data)
        @include('admin.master.customers._workspace-panel', $data)
    @endforeach
@endsection
