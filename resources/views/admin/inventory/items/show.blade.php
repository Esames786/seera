@extends('layouts.admin')
@section('title', $item->name)
@section('content')
@include('admin.inventory.workspace._workspace', ['manage' => false])
@endsection
