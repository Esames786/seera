@extends('layouts.admin')

@section('title', 'Add Designation')
@section('breadcrumb', 'Master Setup / Designations / Add Designation')

@section('content')
    <x-admin.page-header title="Add Designation" description="Create an employee job title"/>

    @include('admin.master.designations._form')
@endsection
