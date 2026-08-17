@extends('layouts.admin')

@section('title', 'Add Account')
@section('breadcrumb', 'Accounting / Chart of Accounts / Add Account')

@section('content')
    <x-admin.page-header title="Add Account" description="Create a ledger account under the chart of accounts"/>

    @include('admin.accounting.chart-of-accounts._form')
@endsection
