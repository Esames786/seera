@extends('layouts.admin')

@section('title', 'Permission Matrix')
@section('breadcrumb', 'Administration / Permission Matrix')

@section('content')
    <x-admin.page-header title="Permission Matrix" description="Role-wise module permissions. Tick All to grant a whole row, then untick any exception."/>

    <div class="help-box">
        Select a role, adjust module permissions, and save. Changes apply instantly to every user assigned to the role.
        The module list is narrowed to the role's department by default; modules that are not shown keep their current permissions.
    </div>

    <form method="GET" class="toolbar">
        <div class="toolbar-left">
            <select class="select" style="width:220px" name="role" onchange="this.form.group.value='';this.form.submit()">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($selectedRole && $selectedRole->id === $role->id)>Role: {{ $role->name }}</option>
                @endforeach
            </select>
            <select class="select" style="width:220px" name="group" onchange="this.form.submit()">
                <option value="all" @selected($selectedGroup === 'all')>All modules</option>
                @foreach ($groupOptions as $code => $label)
                    <option value="{{ $code }}" @selected($selectedGroup === $code)>Modules for: {{ $label }}</option>
                @endforeach
            </select>
            <input class="input" style="width:200px" type="search" name="search" value="{{ request('search') }}" placeholder="Search module..."/>
        </div>
        <div class="toolbar-right">
            <button type="submit" class="btn outline">Apply</button>
        </div>
    </form>

    @if ($selectedRole)
        <form method="POST" action="{{ route('admin.roles.permission-matrix.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="role_id" value="{{ $selectedRole->id }}"/>
            <input type="hidden" name="search" value="{{ request('search') }}"/>
            <input type="hidden" name="group" value="{{ $selectedGroup }}"/>

            <div class="table-card">
                <div class="table-title">
                    <span>Module Permission Matrix <span class="small">Role: {{ $selectedRole->name }}</span></span>
                    <button type="submit" class="btn sm primary">Save Permissions</button>
                </div>
                <div class="matrix-tools">
                    <div class="left">
                        <span class="matrix-note">
                            Showing {{ $permissionsByModule->count() }} of {{ $totalModules }} modules
                            @if ($selectedGroup !== 'all') for {{ $groupOptions[$selectedGroup] ?? $selectedGroup }}. Hidden modules keep their current permissions. @endif
                        </span>
                    </div>
                    <div class="right">
                        <button type="button" class="btn sm outline" data-matrix-select="permission-matrix-table" data-matrix-value="1">Select all visible</button>
                        <button type="button" class="btn sm outline" data-matrix-select="permission-matrix-table" data-matrix-value="0">Clear visible</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="permission-table" id="permission-matrix-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th class="all-col">All</th>
                                @foreach ($actions as $action)
                                    <th>{{ $action === 'mobile' ? 'Mobile Access' : ucfirst($action) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($permissionsByModule as $module => $permissions)
                                <tr data-module="{{ $module }}">
                                    <td>{{ $module }}</td>
                                    <td class="all-col"><input class="checkbox js-row-all" type="checkbox" aria-label="All actions for {{ $module }}"/></td>
                                    @foreach ($actions as $action)
                                        @php $permission = $permissions->firstWhere('action', $action); @endphp
                                        <td>
                                            @if ($permission)
                                                <input type="hidden" name="visible_permission_ids[]" value="{{ $permission->id }}"/>
                                                <input class="checkbox" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $grantedIds))/>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($actions) + 2 }}" class="table-empty">No modules match your search.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-actions">
                <a class="btn outline" href="{{ route('admin.roles.permission-matrix', ['role' => $selectedRole->id, 'group' => $selectedGroup]) }}">Reset</a>
                <button type="submit" class="btn primary">Save Permissions</button>
            </div>
        </form>

        <x-admin.permission-matrix-tools table="permission-matrix-table"/>
    @endif
@endsection
