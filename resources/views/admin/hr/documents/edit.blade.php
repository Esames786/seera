@extends('layouts.admin')

@section('title', 'Edit Document')
@section('breadcrumb', 'HR &amp; Payroll / Documents / Edit Document')

@section('content')
    <x-admin.page-header :title="'Edit Document: '.$document->document_type" :description="$document->employee->name"/>

    @include('admin.hr.documents._form', ['document' => $document])
@endsection
