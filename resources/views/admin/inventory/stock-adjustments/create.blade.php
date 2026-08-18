@extends('layouts.admin')

@section('title', 'Add Stock Adjustment')
@section('breadcrumb', 'Inventory / Stock Adjustments / Add Stock Adjustment')

@section('content')
    <x-admin.page-header title="Add Stock Adjustment" description="Record a counted quantity against the system balance"/>

    @include('admin.inventory.stock-adjustments._form')
@endsection
