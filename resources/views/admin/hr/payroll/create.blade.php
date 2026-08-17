@extends('layouts.admin')

@section('title', 'Create Payroll Run')
@section('breadcrumb', 'HR &amp; Payroll / Payroll / Create Payroll Run')

@section('content')
    <x-admin.page-header title="Create Payroll Run" description="Set the payroll month, period and scope before processing"/>

    @include('admin.hr.payroll._form')
@endsection
