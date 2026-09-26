@extends('layouts.admin')

@section('title', 'Customer Workspace')
@section('breadcrumb', 'Master Setup / Customers / Customer Workspace')

@section('content')
    @php $closeUrl = \App\Support\SaveAction::cancelUrl(route('admin.master.customers.index')); @endphp
    <x-admin.page-header :title="'Customer Workspace: '.$customer->name" description="Profile, contacts, notes and related records of this customer in one place. Each section saves independently; invoice approval and receipts stay on the invoice.">
        <a class="btn outline" href="{{ route('admin.master.customers.show', $customer) }}">View (read-only)</a>
        <a class="btn outline" href="{{ $closeUrl }}">Back to Customers</a>
    </x-admin.page-header>

    @include('admin.master.customers._workspace-header', ['customer' => $customer, 'summary' => $summary])

    <div data-workspace>
        <nav class="tabs workspace-nav" data-workspace-nav aria-label="Customer sections" hidden>
            <a class="tab" href="#profile" data-workspace-section="profile">Profile</a>
            @foreach ($panels as $key => $definition)
                <a class="tab" href="#{{ $key }}" data-workspace-related="{{ $key }}" data-related-url="{{ route('admin.master.customers.workspace.panel', [$customer, $key]) }}">{{ __($definition->title) }}</a>
            @endforeach
        </nav>

        <div data-workspace-form>
            @include('admin.master.customers._form', ['customer' => $customer, 'workspace' => true])
        </div>

        <x-admin.workspace-host :close-url="$closeUrl"/>
    </div>
@endsection
