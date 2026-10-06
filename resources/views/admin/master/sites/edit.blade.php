@extends('layouts.admin')
@section('title', __('workspace.manage').' — '.$site->name)
@section('content')
@include('admin.master.sites._workspace', ['manage'=>true])
@endsection
