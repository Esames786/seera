@extends('layouts.admin')

@section('title', 'Edit Overtime')
@section('breadcrumb', 'HR & Payroll / Overtime / Edit Overtime')

@section('content')
    <x-admin.page-header :title="'Edit Overtime: '.$record->employee->name" :description="$record->overtime_date->toDateString()">
        @if($returnTo ?? null)<a class="btn outline" href="{{ $returnTo }}">{{ __('workspace.inv_back_origin') }}</a>@endif
    </x-admin.page-header>

    @include('admin.hr.overtime._form', ['record' => $record])
@endsection
