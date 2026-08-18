@extends('layouts.admin')

@section('title', 'Edit Stock Transfer')
@section('breadcrumb', 'Inventory / Stock Transfers / Edit Stock Transfer')

@section('content')
    <x-admin.page-header :title="'Edit Stock Transfer: '.$transfer->transfer_number" description="Only a draft transfer can be edited">
        <a class="btn outline" href="{{ route('admin.inventory.stock-transfers.show', $transfer) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.inventory.stock-transfers._form', ['transfer' => $transfer])
@endsection
