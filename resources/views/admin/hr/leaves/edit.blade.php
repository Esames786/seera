@extends('layouts.admin')

@section('title', 'Edit Leave')
@section('breadcrumb', 'HR & Payroll / Leaves / Edit Leave')

@section('content')
    <x-admin.page-header :title="'Edit Leave: '.$leave->employee->name" description="Update leave dates, reason and status">
        <a class="btn outline" href="{{ route('admin.hr.leaves.show', ['leave_request' => $leave, 'return_to' => $returnTo ?? null]) }}">View Details</a>
        @if($returnTo ?? null)<a class="btn outline" href="{{ $returnTo }}">{{ __('workspace.inv_back_origin') }}</a>@endif
    </x-admin.page-header>

    @include('admin.hr.leaves._form', ['leave' => $leave])
@endsection
