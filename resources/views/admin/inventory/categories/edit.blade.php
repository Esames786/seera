@extends('layouts.admin')

@section('title', 'Edit Category')
@section('breadcrumb', 'Inventory / Item Categories / Edit Category')

@section('content')
    <x-admin.page-header :title="'Edit Category: '.$category->name" description="Update the parent category and linked accounts"/>

    @include('admin.inventory.categories._form', ['category' => $category])
@endsection
