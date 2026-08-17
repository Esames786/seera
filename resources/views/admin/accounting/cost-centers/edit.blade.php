@extends('layouts.admin')

@section('title', 'Edit Cost Center')
@section('breadcrumb', 'Accounting / Cost Centers / Edit Cost Center')

@section('content')
    <x-admin.page-header :title="'Edit Cost Center: '.$costCenter->name" description="Update the type, linked record and manager">
        <a class="btn outline" href="{{ route('admin.accounting.cost-centers.show', $costCenter) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.accounting.cost-centers._form', ['costCenter' => $costCenter])
@endsection
