@extends('layouts.admin')

@section('title', 'Edit Journal Entry')
@section('breadcrumb', 'Accounting / Journal Entries / Edit Journal Entry')

@section('content')
    <x-admin.page-header :title="'Edit Journal Entry: '.$entry->journal_number" description="Only draft and approved journals can be edited">
        <a class="btn outline" href="{{ route('admin.accounting.journal-entries.show', $entry) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.accounting.journal-entries._form', ['entry' => $entry])
@endsection
