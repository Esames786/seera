@extends('layouts.admin')
@section('title', __('workspace.view').' — '.$user->name)
@section('content')
@include('admin.users._workspace', ['manage'=>false])
@endsection
