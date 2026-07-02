@extends('layouts.admin')

@section('title', 'Assign Users to Role')
@section('breadcrumb', 'Administration / Roles / Assign Users')

@section('content')
    <x-admin.page-header title="Assign Users to Role" description="Assign or remove users from a role with project/site filters and temporary access"/>

    <form method="GET" class="toolbar">
        <div class="toolbar-left">
            <select class="select" style="width:240px" name="role" onchange="this.form.submit()">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($selectedRole && $selectedRole->id === $role->id)>Selected Role: {{ $role->name }}</option>
                @endforeach
            </select>
            <select class="select" style="width:180px" name="department">
                <option value="">All Departments</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="toolbar-right">
            <button type="submit" class="btn outline">Apply</button>
        </div>
    </form>

    <div class="dual-list">
        <div class="user-list">
            <h3>Available Users <span class="badge green">{{ $availableUsers->count() }} Total</span></h3>
            @forelse ($availableUsers as $user)
                <div class="user-row">
                    <div class="avatar">{{ $user->initials() }}</div>
                    <div>
                        <b>{{ $user->name }}</b>
                        <div class="small">{{ $user->designation?->name ?? 'No designation' }} • {{ $user->site?->name ?? $user->project?->name ?? 'Unassigned' }}</div>
                    </div>
                </div>
            @empty
                <p class="small">All users already hold this role.</p>
            @endforelse
        </div>

        <div class="center-actions">
            <button class="btn primary" type="button" title="Assign selected">&rarr;</button>
            <button class="btn outline" type="button" title="Remove selected">&larr;</button>
            <button class="btn sm" type="button">Add All</button>
            <button class="btn sm danger" type="button">Remove All</button>
        </div>

        <div class="user-list">
            <h3>Assigned to Role <span class="badge yellow">{{ $assignedUsers->count() }} Members</span></h3>
            @forelse ($assignedUsers as $user)
                <div class="user-row">
                    <div class="avatar">{{ $user->initials() }}</div>
                    <div>
                        <b>{{ $user->name }}</b>
                        <div class="small">{{ $user->designation?->name ?? $selectedRole?->name }} • {{ $user->site?->name ?? $user->project?->name ?? 'Unassigned' }}</div>
                    </div>
                </div>
            @empty
                <p class="small">No users assigned to this role yet.</p>
            @endforelse
        </div>
    </div>

    <div style="height:16px"></div>

    <x-admin.form-section title="Temporary Access Option" columns="4">
        <div>
            <label>Temporary Access</label>
            <select class="select"><option>No</option><option>Yes</option></select>
        </div>
        <div><label>Access Start Date</label><input class="input" type="date"/></div>
        <div><label>Access End Date</label><input class="input" type="date"/></div>
        <div>
            <label>Approval Required?</label>
            <select class="select"><option>Yes</option><option>No</option></select>
        </div>
        <div class="full"><label>Reason</label><textarea class="textarea" placeholder="Example: temporary project coverage for Riyadh Tower"></textarea></div>
    </x-admin.form-section>

    <div class="note">
        Drag-and-drop assignment will be wired to a save endpoint in the next iteration. Role membership can currently be changed from the user edit screen.
    </div>
@endsection
