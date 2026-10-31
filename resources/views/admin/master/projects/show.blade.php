@extends('layouts.admin')
@section('title', __('Project View'))
@section('breadcrumb', __('Master Setup / Projects / View'))
@section('content')
    <x-admin.page-header :title="__('Project:').' '.$project->name" :description="__('Read-only project context. Open Edit / Manage to update the profile.')">
        @if(auth()->user()->hasPermission('Projects', 'edit'))
            <a class="btn primary" href="{{ route('admin.master.projects.edit', $project) }}">{{ __('Edit / Manage') }}</a>
        @endif
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.master.projects.index')) }}">{{ __('Back to Projects') }}</a>
    </x-admin.page-header>
    @include('admin.master.projects._workspace', ['manage' => false])
@endsection
