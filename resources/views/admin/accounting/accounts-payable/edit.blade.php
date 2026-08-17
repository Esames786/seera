@extends('layouts.admin')

@section('title', 'Edit Supplier Bill')
@section('breadcrumb', 'Accounting / Accounts Payable / Edit Supplier Bill')

@section('content')
    <x-admin.page-header :title="'Edit Bill: '.$bill->bill_number" description="Only a draft bill can be edited">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.show', $bill) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.accounting.accounts-payable._form', ['bill' => $bill])
@endsection
