@extends('layouts.admin')

@section('title', 'Add Overtime')
@section('breadcrumb', 'HR &amp; Payroll / Overtime / Add Overtime')

@section('content')
    <x-admin.page-header title="Add Overtime" description="Record an overtime claim for approval"/>

    @include('admin.hr.overtime._form')
@endsection
