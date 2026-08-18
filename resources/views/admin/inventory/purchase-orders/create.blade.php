@extends('layouts.admin')

@section('title', 'Add Purchase Order')
@section('breadcrumb', 'Inventory / Purchase Orders / Add Purchase Order')

@section('content')
    <x-admin.page-header title="Add Purchase Order" description="Raise a supplier order, optionally from an approved purchase request"/>

    @if ($sourceRequest)
        <div class="help-box">
            Lines are pre-filled from purchase request
            <a href="{{ route('admin.inventory.purchase-requests.show', $sourceRequest) }}" style="color:var(--blue);font-weight:700">{{ $sourceRequest->pr_number }}</a>.
            Approving this order will mark the request as converted.
        </div>
    @endif

    @include('admin.inventory.purchase-orders._form')
@endsection
