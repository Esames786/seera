@extends('layouts.admin')

@section('title', 'Add Equipment / Vehicle')
@section('breadcrumb', 'Operations / Equipment & Vehicles / Add')

@section('content')
    <x-admin.page-header title="Add Equipment / Vehicle" description="Register the asset. Assignment to a project, fuel, maintenance and documents are recorded on the asset page afterwards; nothing here posts to accounting.">
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.equipment.index')) }}">Back to Equipment</a>
    </x-admin.page-header>
    @include('admin.equipment._form', ['asset' => null])
@endsection
