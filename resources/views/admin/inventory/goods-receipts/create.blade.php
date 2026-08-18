@extends('layouts.admin')

@section('title', 'Add Goods Receipt')
@section('breadcrumb', 'Inventory / Goods Receipt Notes / Add Goods Receipt')

@section('content')
    <x-admin.page-header title="Add Goods Receipt" description="Record a delivery. Posting it increases warehouse stock and posts to accounting."/>

    @if ($order)
        <div class="help-box">
            Lines are pre-filled from purchase order
            <a href="{{ route('admin.inventory.purchase-orders.show', $order) }}" style="color:var(--blue);font-weight:700">{{ $order->po_number }}</a>
            using the outstanding quantity on each line.
        </div>
    @endif

    @include('admin.inventory.goods-receipts._form')
@endsection
