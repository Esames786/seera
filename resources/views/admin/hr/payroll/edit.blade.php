@extends('layouts.admin')

@section('title', 'Edit Payroll Run')
@section('breadcrumb', 'HR &amp; Payroll / Payroll / Edit Payroll Run')

@section('content')
    <x-admin.page-header :title="'Edit Payroll Run: '.$run->code" :description="$run->periodLabel()">
        <a class="btn outline" href="{{ route('admin.hr.payroll.show', $run) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.hr.payroll._form', ['run' => $run])
@endsection
