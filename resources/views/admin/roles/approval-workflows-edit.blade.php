@extends('layouts.admin')

@section('title', 'Edit Approval Workflow')
@section('breadcrumb', 'Administration / Approval Workflows / Edit')

@section('content')
    <x-admin.page-header :title="'Edit Workflow: '.$workflow->name" description="Update workflow information and approval steps">
        <a class="btn outline" href="{{ route('admin.roles.approval-workflows.index', ['workflow' => $workflow->id]) }}">Preview</a>
    </x-admin.page-header>

    @include('admin.roles._workflow-form', ['workflow' => $workflow])
@endsection
