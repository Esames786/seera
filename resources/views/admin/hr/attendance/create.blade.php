@extends('layouts.admin')

@section('title', 'Add Attendance')
@section('breadcrumb', 'HR &amp; Payroll / Attendance / Manual Attendance')

@section('content')
    <x-admin.page-header title="Manual Attendance" description="Record a check-in/check-out entry for an employee"/>

    @include('admin.hr.attendance._form')
@endsection
