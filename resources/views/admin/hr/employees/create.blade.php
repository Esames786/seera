@extends('layouts.admin')

@section('title', 'Add Employee')
@section('breadcrumb', 'HR &amp; Payroll / Employees / Add Employee')

@section('content')
    <x-admin.page-header title="Add Employee" description="Create an HR employee profile with employment, document, payroll and access details"/>

    @include('admin.hr.employees._form')
@endsection
