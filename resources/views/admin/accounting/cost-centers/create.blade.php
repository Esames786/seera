@extends('layouts.admin')

@section('title', 'Add Cost Center')
@section('breadcrumb', 'Accounting / Cost Centers / Add Cost Center')

@section('content')
    <x-admin.page-header title="Add Cost Center" description="Create a cost center linked to a branch, department, project, site or warehouse"/>

    @include('admin.accounting.cost-centers._form')
@endsection
