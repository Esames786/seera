@extends('layouts.admin')

@section('title', 'Add Customer Invoice')
@section('breadcrumb', 'Accounting / Accounts Receivable / Add Customer Invoice')

@section('content')
    <x-admin.page-header title="Add Customer Invoice" description="Record a customer invoice. Approving it posts receivable, revenue and output VAT, and creates the ZATCA record."/>

    @include('admin.accounting.accounts-receivable._form')
@endsection
