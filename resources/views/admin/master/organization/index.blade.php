@extends('layouts.admin')

@section('title', 'Organization Structure')
@section('breadcrumb', 'Master Setup / Organization Structure')

@section('content')
    <x-admin.page-header title="Organization Structure" description="Branches, departments and designations in one place"/>

    <div class="help-box">
        These lists are normally maintained while setting up a user or employee: every Department, Designation and Branch
        dropdown has a <strong>+ New</strong> button that creates the record without leaving the form. Use this page to review
        or edit the full lists.
    </div>

    <div class="hub-grid">
        @forelse ($sections as $section)
            <div class="hub-card">
                <h3>{{ $section['icon'] }} {{ $section['title'] }}</h3>
                <div class="small">{{ $section['description'] }}</div>
                <div class="count">{{ $section['count'] }}</div>
                <ul>
                    @forelse ($section['recent'] as $row)
                        <li>
                            <span>{{ $row['name'] }} <span class="small">{{ $row['meta'] }}</span></span>
                            <x-admin.status-badge :status="$row['status']"/>
                        </li>
                    @empty
                        <li><span class="small">Nothing created yet.</span></li>
                    @endforelse
                </ul>
                <div class="hub-actions">
                    <a class="btn sm outline" href="{{ $section['index'] }}">Open full list</a>
                    @if ($section['canCreate'])
                        <a class="btn sm primary" href="{{ $section['create'] }}">+ Add</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="hub-card"><span class="small">You do not have access to any organization master.</span></div>
        @endforelse
    </div>
@endsection
