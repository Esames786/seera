@extends('layouts.admin')

@section('title', 'Edit Warehouse')
@section('breadcrumb', 'Master Setup / Warehouses / Edit Warehouse')

@section('content')
    <x-admin.page-header :title="'Edit Warehouse: '.$warehouse->name" description="Update warehouse information">
        <a class="btn outline" href="{{ route('admin.master.warehouses.show', $warehouse) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.warehouses._form', ['warehouse' => $warehouse])
@endsection
