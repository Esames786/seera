@extends('layouts.admin')

@section('title', 'Add Site')
@section('breadcrumb', 'Master Setup / Sites / Add Site')

@section('content')
    <x-admin.page-header title="Add Site" description="Create a construction site with geo-fence settings"/>

    @include('admin.master.sites._form')
@endsection
