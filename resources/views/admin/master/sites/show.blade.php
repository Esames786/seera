@extends('layouts.admin')
@section('title', __('workspace.view').' — '.$site->name)
@section('content')
@include('admin.master.sites._workspace', ['manage'=>false])
@endsection
