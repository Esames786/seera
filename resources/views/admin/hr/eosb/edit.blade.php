@extends('layouts.admin')

@section('title', 'Edit EOSB Record')
@section('breadcrumb', 'HR &amp; Payroll / End of Service / Edit Record')

@section('content')
    <x-admin.page-header :title="'Edit EOSB: '.$record->employee->name" :description="'Termination date '.$record->termination_date->toDateString()">
        <a class="btn outline" href="{{ route('admin.hr.eosb.show', $record) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.hr.eosb._form', ['record' => $record])
@endsection
