@extends('layouts.admin')

@section('title', 'Edit Attendance')
@section('breadcrumb', 'HR & Payroll / Attendance / Edit Attendance')

@section('content')
    <x-admin.page-header
        :title="'Edit Attendance: '.$record->employee->name"
        :description="$record->attendance_date->toDateString()">
        @if($returnTo ?? null)<a class="btn outline" href="{{ $returnTo }}">{{ __('workspace.inv_back_origin') }}</a>@endif
    </x-admin.page-header>

    @include('admin.hr.attendance._form', ['record' => $record])
@endsection
