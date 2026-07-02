@extends('layouts.admin')

@section('title', 'Edit Branch')
@section('breadcrumb', 'Master Setup / Branches / Edit Branch')

@section('content')
    <x-admin.page-header :title="'Edit Branch: '.$branch->name" description="Update branch information">
        <a class="btn outline" href="{{ route('admin.master.branches.show', $branch) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.branches._form', ['branch' => $branch])
@endsection
