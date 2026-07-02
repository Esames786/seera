@extends('layouts.admin')

@section('title', 'Add User')
@section('breadcrumb', 'Administration / Users / Add User')

@section('content')
    <x-admin.page-header title="Add User" description="Create an ERP user with employment info, role and scope"/>

    @include('admin.users._form')
@endsection
