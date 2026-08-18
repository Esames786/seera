@extends('layouts.admin')

@section('title', 'Edit Purchase Request')
@section('breadcrumb', 'Inventory / Purchase Requests / Edit Purchase Request')

@section('content')
    <x-admin.page-header :title="'Edit Purchase Request: '.$pr->pr_number" description="Only a draft or pending request can be edited">
        <a class="btn outline" href="{{ route('admin.inventory.purchase-requests.show', $pr) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.inventory.purchase-requests._form', ['pr' => $pr])
@endsection
