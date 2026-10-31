@extends('layouts.admin')
@section('title', __($title))
@section('breadcrumb', __('Master Setup / Projects /').' '.__($title))
@section('content')
    <x-admin.page-header :title="$project->code.' / '.$project->name">
        <a class="btn outline" href="{{ $returnTo }}">{{ __('Back to Project') }}</a>
    </x-admin.page-header>
    @include('admin.master.projects._panel')
@endsection
