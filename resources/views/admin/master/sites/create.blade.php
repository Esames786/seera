@extends('layouts.admin')

@section('title', 'Add Location')
@section('breadcrumb', 'Master Setup / Locations / Add Location')

@section('content')
    <x-admin.page-header title="Add Location" description="Create an office or site location with its geo-fence"/>

    @include('admin.master.sites._form')
@endsection
