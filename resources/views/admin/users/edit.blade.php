@extends('layouts.admin')

@section('title', 'Edit User')
@section('breadcrumb', 'Administration / Users / Edit User')

@section('content')
    <x-admin.page-header :title="'Edit User: '.$user->name" description="Update ERP user, employment info, role and scope">
        <a class="btn outline" href="{{ route('admin.users.show', $user) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.users._form', ['user' => $user])
@endsection
