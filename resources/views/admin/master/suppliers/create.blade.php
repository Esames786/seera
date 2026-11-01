@extends('layouts.admin')

@section('title', __('Add Supplier'))
@section('breadcrumb', __('Master Setup / Suppliers / Add Supplier'))

@section('content')
    <x-admin.page-header :title="__('Add Supplier')" :description="__('Create a supplier for purchasing, payables, and VAT records')"/>

    @include('admin.master.suppliers._form')
@endsection
