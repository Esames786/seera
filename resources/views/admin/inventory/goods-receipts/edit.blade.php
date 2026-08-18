@extends('layouts.admin')

@section('title', 'Edit Goods Receipt')
@section('breadcrumb', 'Inventory / Goods Receipt Notes / Edit Goods Receipt')

@section('content')
    <x-admin.page-header :title="'Edit Goods Receipt: '.$grn->grn_number" description="Only a draft goods receipt can be edited">
        <a class="btn outline" href="{{ route('admin.inventory.goods-receipts.show', $grn) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.inventory.goods-receipts._form', ['grn' => $grn])
@endsection
