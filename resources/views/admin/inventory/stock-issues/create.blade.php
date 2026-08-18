@extends('layouts.admin')

@section('title', 'Add Stock Issue')
@section('breadcrumb', 'Inventory / Stock Issues / Add Stock Issue')

@section('content')
    <x-admin.page-header title="Add Stock Issue" description="Issue material from a warehouse to a project or site"/>

    @include('admin.inventory.stock-issues._form')
@endsection
