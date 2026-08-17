@extends('layouts.admin')

@section('title', 'Edit Attendance')
@section('breadcrumb', 'HR &amp; Payroll / Attendance / Edit Attendance')

@section('content')
    <x-admin.page-header
        :title="'Edit Attendance: '.$record->employee->name"
        :description="$record->attendance_date->toDateString()"/>

    @include('admin.hr.attendance._form', ['record' => $record])
@endsection
