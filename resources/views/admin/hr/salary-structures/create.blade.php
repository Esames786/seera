@extends('layouts.admin')

@section('title', 'Add Salary Structure')
@section('breadcrumb', 'HR &amp; Payroll / Salary Structures / Add')

@section('content')
    <x-admin.page-header title="Add Salary Structure" description="Define basic salary, allowances, deductions and additional items"/>

    @include('admin.hr.salary-structures._form')
@endsection
