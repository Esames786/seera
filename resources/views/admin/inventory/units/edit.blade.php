@extends('layouts.admin')

@section('title', 'Edit Unit')
@section('breadcrumb', 'Inventory / Units / Edit Unit')

@section('content')
    <x-admin.page-header :title="'Edit Unit: '.$unit->name" description="Update the unit code, name and decimal behaviour"/>

    @include('admin.inventory.units._form', ['unit' => $unit])
@endsection
