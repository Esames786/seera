@extends('layouts.admin')
@section('title', 'Project View')
@section('breadcrumb', 'Master Setup / Projects / View')
@section('content')
    <x-admin.page-header :title="'Project: '.$project->name" description="Read-only project context. Open Edit / Manage to update the profile.">
        @if(auth()->user()->hasPermission('Projects', 'edit'))
            <a class="btn primary" href="{{ route('admin.master.projects.edit', $project) }}">Edit / Manage</a>
        @endif
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.master.projects.index')) }}">Back to Projects</a>
    </x-admin.page-header>
    @include('admin.master.projects._workspace', ['manage' => false])
@endsection
