@extends('layouts.admin')

@section('title', 'Create Role')
@section('breadcrumb', 'Administration / Roles / Create Role')

@section('content')
    <x-admin.page-header title="Create Role" description="Define role information, hierarchy parent, access scope, and permissions"/>

    @include('admin.roles._form')
@endsection
