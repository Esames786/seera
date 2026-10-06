@extends('layouts.admin')
@section('title', __('workspace.manage').' — '.$user->name)
@section('content')
@include('admin.users._workspace', ['manage'=>true])
@endsection
