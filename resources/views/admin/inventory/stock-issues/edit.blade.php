@extends('layouts.admin')

@section('title', 'Edit Stock Issue')
@section('breadcrumb', 'Inventory / Stock Issues / Edit Stock Issue')

@section('content')
    <x-admin.page-header :title="'Edit Stock Issue: '.$issue->issue_number" description="Only a draft stock issue can be edited">
        <a class="btn outline" href="{{ route('admin.inventory.stock-issues.show', $issue) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.inventory.stock-issues._form', ['issue' => $issue])
@endsection
