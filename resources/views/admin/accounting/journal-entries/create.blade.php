@extends('layouts.admin')

@section('title', 'Add Journal Entry')
@section('breadcrumb', 'Accounting / Journal Entries / Add Journal Entry')

@section('content')
    <x-admin.page-header title="Add Journal Entry" description="Record a balanced double-entry transaction"/>

    @include('admin.accounting.journal-entries._form')
@endsection
