@extends('layouts.admin')

@section('title', __('Add Customer Invoice'))
@section('breadcrumb', __('Accounting / Accounts Receivable / Add Customer Invoice'))

@section('content')
    <x-admin.page-header :title="__('Add Customer Invoice')" :description="__('Record a customer invoice. Approving it posts receivable, revenue and output VAT, and creates the ZATCA record.')"/>

    @include('admin.accounting.accounts-receivable._form')
@endsection
