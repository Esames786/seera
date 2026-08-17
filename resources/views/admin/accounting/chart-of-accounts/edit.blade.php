@extends('layouts.admin')

@section('title', 'Edit Account')
@section('breadcrumb', 'Accounting / Chart of Accounts / Edit Account')

@section('content')
    <x-admin.page-header :title="'Edit Account: '.$account->label()" description="Update account type, parent, opening balance and flags">
        <a class="btn outline" href="{{ route('admin.accounting.chart-of-accounts.show', $account) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.accounting.chart-of-accounts._form', ['account' => $account])
@endsection
