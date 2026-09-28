@extends('layouts.admin')
@section('title', $title)
@section('breadcrumb', 'Master Setup / Projects / '.$title)
@section('content')
    <x-admin.page-header :title="$project->code.' / '.$project->name">
        <a class="btn outline" href="{{ $returnTo }}">Back to Project</a>
    </x-admin.page-header>
    @include('admin.master.projects._panel')
@endsection
