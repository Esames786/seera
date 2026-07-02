@extends('layouts.admin')

@section('title', 'Create Project')
@section('breadcrumb', 'Master Setup / Projects / Create Project')

@section('content')
    <x-admin.page-header title="Create Project" description="Set up a project before budgeting, attendance and site assignment"/>

    @include('admin.master.projects._form')
@endsection
