@extends('layouts.admin')

@section('title', 'Add Expense Category')
@section('breadcrumb', 'Master Setup / Expense Categories / Add Category')

@section('content')
    <x-admin.page-header title="Add Expense Category" description="Create an expense category for mobile entry and accounting posting"/>

    @include('admin.master.expense-categories._form')
@endsection
