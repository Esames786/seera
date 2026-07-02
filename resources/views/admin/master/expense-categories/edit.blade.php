@extends('layouts.admin')

@section('title', 'Edit Expense Category')
@section('breadcrumb', 'Master Setup / Expense Categories / Edit Category')

@section('content')
    <x-admin.page-header :title="'Edit Category: '.$category->name" description="Update expense category information">
        <a class="btn outline" href="{{ route('admin.master.expense-categories.show', $category) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.master.expense-categories._form', ['category' => $category])
@endsection
