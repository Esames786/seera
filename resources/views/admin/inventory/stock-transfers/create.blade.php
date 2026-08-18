@extends('layouts.admin')

@section('title', 'Add Stock Transfer')
@section('breadcrumb', 'Inventory / Stock Transfers / Add Stock Transfer')

@section('content')
    <x-admin.page-header title="Add Stock Transfer" description="Move material from one warehouse to another"/>

    @include('admin.inventory.stock-transfers._form')
@endsection
