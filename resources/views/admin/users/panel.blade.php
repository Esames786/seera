@extends('layouts.admin')
@section('title', __('workspace.'.$panel))
@section('content')
<a class="btn outline" href="{{ route($manage ? 'admin.users.edit' : 'admin.users.show',$user).'#'.$panel }}">{{ __('workspace.back') }}</a>
@include('admin.users._panel')
@endsection
