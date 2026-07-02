@extends('layouts.admin')

@section('title', 'Edit Designation')
@section('breadcrumb', 'Master Setup / Designations / Edit Designation')

@section('content')
    <x-admin.page-header :title="'Edit Designation: '.$designation->name" description="Update designation information">
        <a class="btn outline" href="{{ route('admin.master.designations.show', $designation) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.designations._form', ['designation' => $designation])
@endsection
