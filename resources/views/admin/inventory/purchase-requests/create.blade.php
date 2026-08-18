@extends('layouts.admin')

@section('title', 'Add Purchase Request')
@section('breadcrumb', 'Inventory / Purchase Requests / Add Purchase Request')

@section('content')
    <x-admin.page-header title="Add Purchase Request" description="Raise a material request for a project, site or warehouse"/>

    @include('admin.inventory.purchase-requests._form')
@endsection
