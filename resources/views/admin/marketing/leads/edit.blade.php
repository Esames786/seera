@extends('layouts.admin')

@section('title', 'Edit Lead')
@section('breadcrumb', 'Marketing / Leads & Visits / Edit Lead')

@section('content')
    <x-admin.page-header :title="'Edit Lead: '.$lead->lead_code" :description="$lead->company_name">
        <a class="btn outline" href="{{ route('admin.marketing.leads.show', $lead) }}">Back to Lead</a>
    </x-admin.page-header>

    @include('admin.marketing.leads._form')
@endsection
