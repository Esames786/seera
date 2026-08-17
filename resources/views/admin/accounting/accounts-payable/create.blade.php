@extends('layouts.admin')

@section('title', 'Add Supplier Bill')
@section('breadcrumb', 'Accounting / Accounts Payable / Add Supplier Bill')

@section('content')
    <x-admin.page-header title="Add Supplier Bill" description="Record a supplier bill. Approving it posts the expense, input VAT and payable."/>

    @include('admin.accounting.accounts-payable._form')
@endsection
