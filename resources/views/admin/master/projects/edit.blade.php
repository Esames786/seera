@extends('layouts.admin')

@section('title', 'Edit Project')
@section('breadcrumb', 'Master Setup / Projects / Edit Project')

@section('content')
    <x-admin.page-header :title="'Edit Project: '.$project->name" description="Update project information">
        <a class="btn outline" href="{{ route('admin.master.projects.show', $project) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.projects._form', ['project' => $project])
@endsection
