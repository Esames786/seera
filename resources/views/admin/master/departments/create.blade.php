@extends('layouts.admin')

@section('title', 'Add Department')
@section('breadcrumb', 'Master Setup / Departments / Add Department')

@section('content')
    <x-admin.page-header title="Add Department" description="Create a department"/>

    @include('admin.master.departments._form')
@endsection
