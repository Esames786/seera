@extends('layouts.admin')

@section('title', 'Add Unit')
@section('breadcrumb', 'Inventory / Units / Add Unit')

@section('content')
    <x-admin.page-header title="Add Unit" description="Create a unit of measure for inventory items"/>

    @include('admin.inventory.units._form')
@endsection
