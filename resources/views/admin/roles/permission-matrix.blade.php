@extends('layouts.admin')

@section('title', 'Permission Matrix')
@section('breadcrumb', 'Administration / Permission Matrix')

@section('content')
    <x-admin.page-header title="Permission Matrix" description="Role-wise module permissions with View/Create/Edit/Delete/Approve/Export/Mobile"/>

    <div class="help-box">
        Select a role, adjust module permissions, and save. Changes apply instantly to every user assigned to the role.
    </div>

    <form method="GET" class="toolbar">
        <div class="toolbar-left">
            <select class="select" style="width:220px" name="role" onchange="this.form.submit()">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($selectedRole && $selectedRole->id === $role->id)>Role: {{ $role->name }}</option>
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

            <x-admin.data-table title="Module Permission Matrix" :subtitle="'Role: '.$selectedRole->name" class="permission-table">
                <x-slot:headerActions>
                    <button type="submit" class="btn sm primary">Save Permissions</button>
                </x-slot:headerActions>
                <thead>
                    <tr>
                        <th>Module</th>
                        @foreach ($actions as $action)
                            <th>{{ $action === 'mobile' ? 'Mobile Access' : ucfirst($action) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissionsByModule as $module => $permissions)
                        <tr>
                            <td>{{ $module }}</td>
                            @foreach ($actions as $action)
                                @php $permission = $permissions->firstWhere('action', $action); @endphp
                                <td>
                                    @if ($permission)
                                        <input class="checkbox" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $grantedIds))/>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No modules match your search.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>

            <div class="form-actions">
                <a class="btn outline" href="{{ route('admin.roles.permission-matrix', ['role' => $selectedRole->id]) }}">Reset</a>
                <button type="submit" class="btn primary">Save Permissions</button>
            </div>
        </form>
    @endif
@endsection
