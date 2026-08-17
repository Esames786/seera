@extends('layouts.admin')

@section('title', 'Edit Employee')
@section('breadcrumb', 'HR &amp; Payroll / Employees / Edit Employee')

@section('content')
    <x-admin.page-header :title="'Edit Employee: '.$employee->name" description="Update employment, document, payroll and access details">
        <a class="btn outline" href="{{ route('admin.hr.employees.show', $employee) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.hr.employees._form', ['employee' => $employee])
@endsection
