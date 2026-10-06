@extends('layouts.admin')
@section('title', __('workspace.'.$panel))
@section('content')
<a class="btn outline" href="{{ route('admin.master.sites.show',$site).'#'.$panel }}">{{ __('workspace.back') }}</a>
@include('admin.master.sites._panel')
@endsection
