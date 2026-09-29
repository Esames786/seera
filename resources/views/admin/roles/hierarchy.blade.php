@extends('layouts.admin')

@section('title', 'Role Hierarchy')
@section('breadcrumb', 'Administration / Role Hierarchy')

@section('content')
    <x-admin.page-header title="Role Hierarchy" description="Primary hierarchy and additional reporting relationships"/>

    <div class="help-box">
        {{ __('ui.reporting_help') }} {{ __('ui.reporting_pending') }}
    </div>

    <div class="split">
        <div class="tree">
            <h3 style="margin-top:0">Role Tree</h3>
            <ul>
                @foreach ($rootRoles as $rootRole)
                    @include('admin.roles._tree-node', ['node' => $rootRole])
                @endforeach
            </ul>
        </div>

        <div>
            @if ($selectedRole)
                <x-admin.form-section title="Selected Role Details" columns="2">
                    <div><label>Role Name</label><input class="input" value="{{ $selectedRole->name }}" readonly/></div>
                    <div><label>{{ __('ui.reporting_parents') }}</label>@include('admin.roles._reporting-parents', ['role' => $selectedRole])</div>
                    <div><label>Department</label><input class="input" value="{{ $selectedRole->department?->name ?? '-' }}" readonly/></div>
                    <div><label>Role Level</label><input class="input" value="{{ $selectedRole->level }}" readonly/></div>
                    <div><label>Can Approve Child Requests?</label><input class="input" value="{{ $selectedRole->can_approve_child_requests ? 'Yes' : 'No' }}" readonly/></div>
                    <div><label>Status</label><input class="input" value="{{ ucfirst($selectedRole->status) }}" readonly/></div>
                </x-admin.form-section>
            @endif

            <x-admin.data-table title="Hierarchy Rules">
                <thead>
                    <tr><th>Rule</th><th>Value</th></tr>
                </thead>
                <tbody>
                    <tr><td>{{ __('ui.primary_parent') }}</td><td>Existing primary hierarchy; module permissions and access scopes still apply.</td></tr>
                    <tr><td>{{ __('ui.additional_parents') }}</td><td>{{ __('ui.reporting_help') }}</td></tr>
                    <tr><td>Approval routing</td><td>{{ __('ui.reporting_pending') }}</td></tr>
                </tbody>
            </x-admin.data-table>

            <div class="form-actions">
                <a class="btn outline" href="{{ route('admin.roles.create') }}">Add Child Role</a>
                @if ($selectedRole)
                    <a class="btn primary" href="{{ route('admin.roles.edit', $selectedRole) }}">Edit Selected Role</a>
                @endif
            </div>
        </div>
    </div>
@endsection
