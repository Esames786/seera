@extends('layouts.admin')

@section('title', 'Edit Department')
@section('breadcrumb', 'Master Setup / Departments / Edit Department')

@section('content')
    <x-admin.page-header :title="'Edit Department: '.$department->name" description="Update department information">
        <a class="btn outline" href="{{ route('admin.master.departments.show', $department) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.departments._form', ['department' => $department])
@endsection
