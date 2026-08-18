@extends('layouts.admin')

@section('title', 'Edit Item')
@section('breadcrumb', 'Inventory / Materials / Edit Item')

@section('content')
    <x-admin.page-header :title="'Edit Item: '.$item->name" description="Update stock control and accounting settings">
        <a class="btn outline" href="{{ route('admin.inventory.items.show', $item) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.inventory.items._form', ['item' => $item])
@endsection
