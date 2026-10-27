@extends('layouts.admin')

@section('title', __('Edit Employee'))
@section('breadcrumb', __('HR & Payroll / Employees / Edit Employee'))

@section('content')
    <x-admin.page-header :title="__('Employee Workspace').': '.$employee->name" description="Save employee details and related records here. Each section has its own save.">
        <a class="btn outline" href="{{ route('admin.hr.employees.index') }}">{{ __('Back to Employees') }}</a>
    </x-admin.page-header>

    <x-admin.linked-identity :link="$linkedUser" :title="__('ui.linked_user')"/>
    @include('admin.hr.employees._form', ['employee' => $employee])
@endsection
