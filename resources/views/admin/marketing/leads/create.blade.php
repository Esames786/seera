@extends('layouts.admin')

@section('title', 'New Lead')
@section('breadcrumb', 'Marketing / Leads & Visits / New Lead')

@section('content')
    <x-admin.page-header title="New Lead" description="Register a prospect and hand it to the staff member who will visit">
        <a class="btn outline" href="{{ route('admin.marketing.leads.index') }}">Back to Leads</a>
    </x-admin.page-header>

    @include('admin.marketing.leads._form')
@endsection
