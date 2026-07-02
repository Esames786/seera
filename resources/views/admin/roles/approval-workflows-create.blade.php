@extends('layouts.admin')

@section('title', 'Create Approval Workflow')
@section('breadcrumb', 'Administration / Approval Workflows / Create')

@section('content')
    <x-admin.page-header title="Create Approval Workflow" description="Define the approval route, steps, limits, and escalation for a transaction type"/>

    @include('admin.roles._workflow-form')
@endsection
