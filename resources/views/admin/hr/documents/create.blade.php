@extends('layouts.admin')

@section('title', 'Add Document')
@section('breadcrumb', 'HR &amp; Payroll / Documents / Add Document')

@section('content')
    <x-admin.page-header title="Add Employee Document" description="Record IQAMA, passport, contract and other employee documents"/>

    @include('admin.hr.documents._form')
@endsection
