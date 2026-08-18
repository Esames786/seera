@extends('layouts.admin')

@section('title', 'Edit Stock Adjustment')
@section('breadcrumb', 'Inventory / Stock Adjustments / Edit Stock Adjustment')

@section('content')
    <x-admin.page-header :title="'Edit Adjustment: '.$adjustment->adjustment_number" description="A posted adjustment is read-only">
        <a class="btn outline" href="{{ route('admin.inventory.stock-adjustments.show', $adjustment) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.inventory.stock-adjustments._form', ['adjustment' => $adjustment])
@endsection
