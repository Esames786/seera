@php /** @var \App\Models\Role|null $role */ $role = $role ?? null; @endphp

<form method="POST" action="{{ $role ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
    @csrf
    @if ($role) @method('PUT') @endif

    <x-admin.form-section title="Basic Role Information" columns="3">
        <div><label for="name">Role Name *</label><input id="name" name="name" class="input" value="{{ old('name', $role?->name) }}" required/></div>
        <div><label for="code">Role Code *</label><input id="code" name="code" class="input" value="{{ old('code', $role?->code) }}" placeholder="SITE_SUPERVISOR" required/></div>
        <div>
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">Select...</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $role?->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="parent_id">Parent Role</label>
            <select id="parent_id" name="parent_id" class="select">
                <option value="">None (top level)</option>
                @foreach ($parentRoles as $parentRole)
                    @continue($role && $parentRole->id === $role->id)
                    <option value="{{ $parentRole->id }}" @selected(old('parent_id', $role?->parent_id) == $parentRole->id)>{{ $parentRole->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="level">Role Level *</label>
            <select id="level" name="level" class="select" required>
                @foreach (range(1, 6) as $level)
                    <option value="{{ $level }}" @selected(old('level', $role?->level ?? 1) == $level)>Level {{ $level }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $role?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $role?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
        <div>
            <label for="default_dashboard">Default Dashboard</label>
            <select id="default_dashboard" name="default_dashboard" class="select">
                @foreach (['Admin Dashboard', 'Finance Dashboard', 'HR Dashboard', 'Project Dashboard', 'Site Dashboard', 'Inventory Dashboard'] as $dashboard)
                    <option @selected(old('default_dashboard', $role?->default_dashboard) === $dashboard)>{{ $dashboard }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" class="textarea">{{ old('description', $role?->description) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Access Scope" columns="3">
        <div>
            <label for="access_scope">Access Scope *</label>
            <select id="access_scope" name="access_scope" class="select" required>
                @foreach (['All Company', 'Company Level', 'Branch Level', 'Project Level', 'Site Level', 'Warehouse Level'] as $scope)
                    <option @selected(old('access_scope', $role?->access_scope ?? 'Company Level') === $scope)>{{ $scope }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="mobile_app_access">Mobile App Access</label>
            <select id="mobile_app_access" name="mobile_app_access" class="select">
                <option value="0" @selected(!old('mobile_app_access', $role?->mobile_app_access))>Not Allowed</option>
                <option value="1" @selected(old('mobile_app_access', $role?->mobile_app_access))>Allowed</option>
            </select>
        </div>
        <div>
            <label for="can_approve_child_requests">Can Approve Child Requests?</label>
            <select id="can_approve_child_requests" name="can_approve_child_requests" class="select">
                <option value="1" @selected(old('can_approve_child_requests', $role?->can_approve_child_requests ?? true))>Yes</option>
                <option value="0" @selected(!old('can_approve_child_requests', $role?->can_approve_child_requests ?? true))>No</option>
            </select>
        </div>
    </x-admin.form-section>

    @php $granted = collect(old('permissions', $role?->permissions->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id); @endphp

    <x-admin.data-table title="Permission Matrix" class="permission-table">
        <thead>
            <tr>
                <th>Module</th>
                @foreach (['view', 'create', 'edit', 'delete', 'approve', 'export', 'mobile'] as $action)
                    <th>{{ ucfirst($action) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($permissionModules as $module => $permissions)
                <tr>
                    <td>{{ $module }}</td>
                    @foreach (['view', 'create', 'edit', 'delete', 'approve', 'export', 'mobile'] as $action)
                        @php $permission = $permissions->firstWhere('action', $action); @endphp
                        <td>
                            @if ($permission)
                                <input class="checkbox" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($granted->contains($permission->id))/>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </x-admin.data-table>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.roles.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $role ? 'Update Role' : 'Save Role' }}</button>
    </div>
</form>
