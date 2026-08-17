@extends('layouts.admin')

@section('title', 'Edit Posting Rule')
@section('breadcrumb', 'Accounting / Automatic Posting Rules / Edit Posting Rule')

@section('content')
    <x-admin.page-header :title="'Edit Rule: '.$rule->source_module" :description="$rule->trigger_event">
        <a class="btn outline" href="{{ route('admin.accounting.posting-rules.show', $rule) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.accounting.posting-rules._form', ['rule' => $rule])
@endsection
