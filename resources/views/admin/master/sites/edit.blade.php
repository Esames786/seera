@extends('layouts.admin')

@section('title', 'Edit Site')
@section('breadcrumb', 'Master Setup / Sites / Edit Site')

@section('content')
    <x-admin.page-header :title="'Edit Site: '.$site->name" description="Update site information and geo-fence settings">
        <a class="btn outline" href="{{ route('admin.master.sites.show', $site) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.sites._form', ['site' => $site])
@endsection
