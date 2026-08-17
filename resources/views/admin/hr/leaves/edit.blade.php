@extends('layouts.admin')

@section('title', 'Edit Leave')
@section('breadcrumb', 'HR &amp; Payroll / Leaves / Edit Leave')

@section('content')
    <x-admin.page-header :title="'Edit Leave: '.$leave->employee->name" description="Update leave dates, reason and status">
        <a class="btn outline" href="{{ route('admin.hr.leaves.show', $leave) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.hr.leaves._form', ['leave' => $leave])
@endsection
