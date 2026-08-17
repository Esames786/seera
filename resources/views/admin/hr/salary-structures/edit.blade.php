@extends('layouts.admin')

@section('title', 'Edit Salary Structure')
@section('breadcrumb', 'HR &amp; Payroll / Salary Structures / Edit')

@section('content')
    <x-admin.page-header :title="'Edit Salary Structure: '.$structure->employee->name" description="Update salary components and additional items">
        <a class="btn outline" href="{{ route('admin.hr.salary-structures.show', $structure) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.hr.salary-structures._form', ['structure' => $structure])
@endsection
