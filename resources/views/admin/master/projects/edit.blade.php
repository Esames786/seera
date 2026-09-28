@extends('layouts.admin')
@section('title', 'Project Workspace')
@section('breadcrumb', 'Master Setup / Projects / Edit Manage')
@section('content')
    <x-admin.page-header :title="'Project Workspace: '.$project->name" description="Save the profile here. Related records remain independent; no Save All or automatic business actions.">
        @if(auth()->user()->hasPermission('Projects', 'view'))<a class="btn outline" href="{{ route('admin.master.projects.show', $project) }}">View (read-only)</a>@endif
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.master.projects.index')) }}">Back to Projects</a>
    </x-admin.page-header>
    @include('admin.master.projects._workspace', ['manage' => true])
@endsection
