@extends('layouts.admin')

@section('title', 'Add Shift')
@section('breadcrumb', 'HR &amp; Payroll / Shifts / Add Shift')

@section('content')
    <x-admin.page-header title="Add Shift" description="Create a work shift with timing and overtime rules"/>

    @include('admin.hr.shifts._form')
@endsection
