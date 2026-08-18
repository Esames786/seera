@extends('layouts.admin')

@section('title', 'Add Category')
@section('breadcrumb', 'Inventory / Item Categories / Add Category')

@section('content')
    <x-admin.page-header title="Add Item Category" description="Create a material category and link its accounting treatment"/>

    @include('admin.inventory.categories._form')
@endsection
