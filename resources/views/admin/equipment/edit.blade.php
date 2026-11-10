@extends('layouts.admin')

@section('title', __('Edit Equipment / Vehicle'))
@section('breadcrumb', __('Operations / Equipment & Vehicles / Edit'))

@section('content')
    <x-admin.page-header :title="'Edit: '.$asset->asset_code.' — '.$asset->name" :description="__('feedback.equipment_edit_help')">
        <a class="btn outline" href="{{ route('admin.equipment.show', $asset) }}">{{ __('View Equipment') }}</a>
        <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.equipment.index')) }}">{{ __('Back to Equipment') }}</a>
    </x-admin.page-header>
    @include('admin.equipment._form', ['asset' => $asset])
@endsection
