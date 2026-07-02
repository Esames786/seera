@extends('layouts.admin')

@section('title', 'Add Branch')
@section('breadcrumb', 'Master Setup / Branches / Add Branch')

@section('content')
    <x-admin.page-header title="Add Branch" description="Create a company branch"/>

    @include('admin.master.branches._form')
@endsection
