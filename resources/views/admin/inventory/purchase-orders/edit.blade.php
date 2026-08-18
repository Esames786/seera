@extends('layouts.admin')

@section('title', 'Edit Purchase Order')
@section('breadcrumb', 'Inventory / Purchase Orders / Edit Purchase Order')

@section('content')
    <x-admin.page-header :title="'Edit Purchase Order: '.$order->po_number" description="Only a draft purchase order can be edited">
        <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.show', $order) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.inventory.purchase-orders._form', ['order' => $order])
@endsection
