@extends('layouts.admin')

@section('title', 'Edit Customer')
@section('breadcrumb', 'Master Setup / Customers / Edit Customer')

@section('content')
    <x-admin.page-header :title="'Edit Customer: '.$customer->name" description="Update customer information">
        <a class="btn outline" href="{{ route('admin.master.customers.show', $customer) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.customers._form', ['customer' => $customer])
@endsection
