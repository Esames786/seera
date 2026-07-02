@extends('layouts.admin')

@section('title', 'Add Warehouse')
@section('breadcrumb', 'Master Setup / Warehouses / Add Warehouse')

@section('content')
    <x-admin.page-header title="Add Warehouse" description="Create a branch-level or project/site-level warehouse"/>

    @include('admin.master.warehouses._form')
@endsection
