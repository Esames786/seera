@extends('layouts.admin')

@section('title', 'Add Item')
@section('breadcrumb', 'Inventory / Materials / Add Item')

@section('content')
    <x-admin.page-header title="Add Item" description="Create a material with stock control and accounting settings"/>

    @include('admin.inventory.items._form')
@endsection
