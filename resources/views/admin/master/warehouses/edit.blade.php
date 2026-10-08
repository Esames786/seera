@extends('layouts.admin')
@section('title', $warehouse->name)
@section('content')
@include('admin.inventory.workspace._workspace', ['manage' => true])
@endsection
