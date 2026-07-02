@extends('layouts.admin')

@section('title', 'Edit Supplier')
@section('breadcrumb', 'Master Setup / Suppliers / Edit Supplier')

@section('content')
    <x-admin.page-header :title="'Edit Supplier: '.$supplier->name" description="Update supplier information">
        <a class="btn outline" href="{{ route('admin.master.suppliers.show', $supplier) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.suppliers._form', ['supplier' => $supplier])
@endsection
