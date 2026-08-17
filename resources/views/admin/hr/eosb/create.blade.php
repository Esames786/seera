@extends('layouts.admin')

@section('title', 'Add EOSB Record')
@section('breadcrumb', 'HR &amp; Payroll / End of Service / Add Record')

@section('content')
    <x-admin.page-header title="Add End of Service Record" description="Create a draft EOSB settlement with manual amounts"/>

    @include('admin.hr.eosb._form')
@endsection
