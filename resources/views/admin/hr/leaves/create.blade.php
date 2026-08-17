@extends('layouts.admin')

@section('title', 'Add Leave')
@section('breadcrumb', 'HR &amp; Payroll / Leaves / Add Leave')

@section('content')
    <x-admin.page-header title="Add Leave Request" description="Create an employee leave request for approval"/>

    @include('admin.hr.leaves._form')
@endsection
