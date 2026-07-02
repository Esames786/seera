@extends('layouts.admin')

@section('title', $module.' - Coming Soon')
@section('breadcrumb', $module)

@section('content')
    <x-admin.coming-soon :module="$module"/>
@endsection
