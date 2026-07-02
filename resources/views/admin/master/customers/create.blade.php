@extends('layouts.admin')

@section('title', 'Add Customer')
@section('breadcrumb', 'Master Setup / Customers / Add Customer')

@section('content')
    <x-admin.page-header title="Add Customer" description="Create a customer for contracts, receivables, and ZATCA e-invoicing"/>

    @include('admin.master.customers._form')
@endsection
