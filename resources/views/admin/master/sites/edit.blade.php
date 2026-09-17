@extends('layouts.admin')

@section('title', 'Edit Location')
@section('breadcrumb', 'Master Setup / Locations / Edit Location')

@section('content')
    <x-admin.page-header :title="'Edit Location: '.$site->name" description="Update location information and geo-fence settings">
        <a class="btn outline" href="{{ route('admin.master.sites.show', $site) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.sites._form', ['site' => $site])
@endsection
