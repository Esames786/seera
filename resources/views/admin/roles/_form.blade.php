@php /** @var \App\Models\Role|null $role */ $role = $role ?? null; @endphp

<form method="POST" action="{{ $role ? route('admin.roles.update', $role) : route('admin.roles.store') }}" id="role-form">
    @csrf
    @if ($role) @method('PUT') @endif

    <x-admin.form-section title="Basic Role Information" columns="3">
        <div>
            <div class="label-row">
                <label for="department_id">Department</label>
                <x-admin.quick-create id="qc-department" target="department_id" :url="route('admin.master.departments.store')" title="New Department" permission="Departments">
                    <div><label for="qc-dept-name">Department Name *</label><input id="qc-dept-name" name="name" class="input" required/></div>
                    <div><label for="qc-dept-code">Code</label><input id="qc-dept-code" name="code" class="input" placeholder="Auto if blank"/></div>
                    <div class="full"><label for="qc-dept-description">Description</label><textarea id="qc-dept-description" name="description" class="textarea" rows="2"></textarea></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="department_id" name="department_id" class="select">
                <option value="">Select...</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $role?->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        @unless ($role)
            <div>
                <label for="role_type">Role Type</label>
                <select id="role_type" name="role_type" class="select">
                    <option value="">Custom name...</option>
                    @foreach ($roleTypes as $type)
                        <option value="{{ $type }}" @selected(old('role_type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <div class="small" style="margin-top:4px">Department + type fills a consistent name and code.</div>
            </div>
        @endunless
        <div><label for="name">Role Name *</label><input id="name" name="name" class="input" value="{{ old('name', $role?->name) }}" required/></div>
        <div>
            <label for="code">Role Code {{ $role ? '' : '(auto)' }}</label>
            @if ($role)
                <input id="code" class="input" value="{{ $role->code }}" readonly disabled/>
                <div class="small" style="margin-top:4px">Codes identify a role across the system and do not change.</div>
            @else
                <input id="code" name="code" class="input" value="{{ old('code') }}" placeholder="Generated from the name"/>
                @error('code')<div class="field-error">{{ $message }}</div>@enderror
            @endif
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

    <div class="table-card">
        <div class="table-title">
            <span>Permission Matrix <span class="small">Tick "All" to grant a whole row, then untick any exception.</span></span>
        </div>
        <div class="matrix-tools">
            <div class="left">
                <label class="check-line" style="font-weight:700"><input class="checkbox" type="checkbox" id="show_all_modules"/> Show all modules</label>
                <span class="matrix-note" id="matrix-note">Choose a department to see only its relevant modules.</span>
            </div>
            <div class="right">
                <button type="button" class="btn sm outline" data-matrix-select="role-permission-table" data-matrix-value="1">Select all visible</button>
                <button type="button" class="btn sm outline" data-matrix-select="role-permission-table" data-matrix-value="0">Clear visible</button>
            </div>
        </div>
        <div class="table-wrap matrix-wrap">
            <table class="permission-table" id="role-permission-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th class="all-col">All</th>
                        @foreach ($formActions as $action)
                            <th>{{ \App\Models\Permission::label($action) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissionModules as $module => $permissions)
                        <tr data-module="{{ $module }}">
                            <td>{{ $module }}</td>
                            <td class="all-col"><input class="checkbox js-row-all" type="checkbox" aria-label="All actions for {{ $module }}"/></td>
                            @foreach ($formActions as $action)
                                @php $permission = $permissions->firstWhere('action', $action); @endphp
                                <td>
                                    @if ($permission)
                                        <input type="hidden" name="visible_permission_ids[]" value="{{ $permission->id }}"/>
                                        <input class="checkbox" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($granted->contains($permission->id))/>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span class="small">
                Every supported action is listed here, the same set as the
                <a href="{{ route('admin.roles.permission-matrix', $role ? ['role' => $role->id] : []) }}" style="color:var(--blue);font-weight:700">full Permission Matrix</a>.
                Modules hidden by the department filter keep their current permissions when this form is saved.
            </span>
        </div>
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.roles.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $role ? 'Update Role' : 'Save Role' }}</button>
    </div>
</form>

<x-admin.permission-matrix-tools table="role-permission-table" department-select="department_id" show-all-toggle="show_all_modules" :groups="$permissionGroups" note="matrix-note"/>

@unless ($role)
    @push('scripts')
    <script>
        (function () {
            var type = document.getElementById('role_type');
            var department = document.getElementById('department_id');
            var name = document.getElementById('name');
            var code = document.getElementById('code');
            if (!type || !name || !code) return;

            var nameTouched = name.value !== '';
            var codeTouched = code.value !== '';

            function toCode(value) {
                return value.toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            }

            function suggest() {
                if (!type.value) return;
                var departmentName = department && department.value
                    ? department.options[department.selectedIndex].textContent.trim()
                    : '';
                if (!nameTouched) name.value = (departmentName ? departmentName + ' ' : '') + type.value;
                if (!codeTouched) code.value = toCode(name.value);
            }

            type.addEventListener('change', function () { nameTouched = false; suggest(); });
            if (department) department.addEventListener('change', suggest);
            name.addEventListener('input', function () {
                nameTouched = name.value !== '';
                if (!codeTouched) code.value = toCode(name.value);
            });
            code.addEventListener('input', function () { codeTouched = code.value !== ''; });
        })();
    </script>
    @endpush
@endunless
