@extends('layouts.admin')

@section('title', 'Edit Overtime')
@section('breadcrumb', 'HR &amp; Payroll / Overtime / Edit Overtime')

@section('content')
    <x-admin.page-header :title="'Edit Overtime: '.$record->employee->name" :description="$record->overtime_date->toDateString()"/>

    @include('admin.hr.overtime._form', ['record' => $record])
@endsection
