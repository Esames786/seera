@extends('layouts.admin')

@section('title', 'Add Supplier')
@section('breadcrumb', 'Master Setup / Suppliers / Add Supplier')

@section('content')
    <x-admin.page-header title="Add Supplier" description="Create a supplier for purchasing, payables, and VAT records"/>

    @include('admin.master.suppliers._form')
@endsection
