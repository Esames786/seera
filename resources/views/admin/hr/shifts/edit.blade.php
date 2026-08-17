@extends('layouts.admin')

@section('title', 'Edit Shift')
@section('breadcrumb', 'HR &amp; Payroll / Shifts / Edit Shift')

@section('content')
    <x-admin.page-header :title="'Edit Shift: '.$shift->name" description="Update shift timing and overtime rules"/>

    @include('admin.hr.shifts._form', ['shift' => $shift])
@endsection
