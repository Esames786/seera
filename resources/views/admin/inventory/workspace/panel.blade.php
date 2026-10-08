@extends('layouts.admin')
@section('title', __('inventory_workspace.'.$panel))
@section('content')
<a class="btn outline" href="{{ route(\App\Support\Workspace\InventoryWorkspace::prefix($parent).'.show',$parent).'#'.$panel }}">{{ __('workspace.back') }}</a>
@include('admin.inventory.workspace._panel')
@endsection
