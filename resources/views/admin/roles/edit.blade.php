@extends('layouts.admin')

@section('title', 'Edit Role')
@section('breadcrumb', 'Administration / Roles / Edit Role')

@section('content')
    <x-admin.page-header :title="'Edit Role: '.$role->name" description="Update role information, hierarchy parent, access scope, and permissions">
        <a class="btn outline" href="{{ route('admin.roles.show', $role) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.roles._form', ['role' => $role])
@endsection
