@extends('layouts.admin')

@section('title', __($module).' — '.__('ui.coming_soon_title'))
@section('breadcrumb', __($module))

@section('content')
    <x-admin.coming-soon :module="$module"/>
@endsection
