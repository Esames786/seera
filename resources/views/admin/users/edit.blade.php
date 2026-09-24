@extends('layouts.admin')

@section('title', 'Edit User')
@section('breadcrumb', 'Administration / Users / Edit User')

@section('content')
    <x-admin.page-header :title="'Edit User: '.$user->name" description="Update ERP user, employment info, role and scope">
        @if(auth()->user()->hasPermission('Users', 'view'))<a class="btn outline" href="{{ route('admin.users.show', $user) }}">{{ __('View') }}</a>@endif
    </x-admin.page-header>

    <x-admin.linked-identity :link="$linkedEmployee" :title="__('ui.linked_employee')"/>
    @include('admin.users._form', ['user' => $user])
@endsection
