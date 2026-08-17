@extends('layouts.admin')

@section('title', 'Add Posting Rule')
@section('breadcrumb', 'Accounting / Automatic Posting Rules / Add Posting Rule')

@section('content')
    <x-admin.page-header title="Add Automatic Posting Rule" description="Define how a module event turns into a journal entry"/>

    @include('admin.accounting.posting-rules._form')
@endsection
