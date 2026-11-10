@extends('layouts.admin')

@section('title', __('Add Equipment / Vehicle'))
@section('breadcrumb', __('Operations / Equipment & Vehicles / Add'))

@section('content')
    <x-admin.page-header :title="__('Add Equipment / Vehicle')" :description="__('feedback.equipment_create_help')">
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.equipment.index')) }}">{{ __('Back to Equipment') }}</a>
    </x-admin.page-header>
    @include('admin.equipment._form', ['asset' => null])
@endsection
