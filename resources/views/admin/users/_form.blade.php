@php /** @var \App\Models\User|null $user */ $user = $user ?? null; @endphp

<form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if ($user) @method('PUT') @endif

    <div class="split">
        <div>
            <div class="card profile-card" style="margin-bottom:16px">
                <div class="avatar lg" style="margin:auto">{{ $user?->initials() ?? '+' }}</div>
                <h3>{{ $user?->name ?? 'New User' }}</h3>
                <div class="small">{{ $user?->primaryRole()?->name ?? 'Role not assigned yet' }}</div>
                <div style="height:12px"></div>
                <button class="btn outline" type="button">Upload Profile Photo</button>
            </div>

            <x-admin.form-section title="Role & Quick Capabilities">
                <div class="label-row">
                    <label for="role_id">Primary Role</label>
                    <x-admin.quick-create id="qc-role" target="role_id" :url="route('admin.roles.store')" title="New Role" permission="Roles" submit="Create Role" :wide="true">
                        <div>
                            <label for="qc-role-department">Department</label>
                            <select id="qc-role-department" name="department_id" class="select" data-prefill-from="department_id">
                                <option value="">Select...</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="qc-role-type">Role Type</label>
                            <select id="qc-role-type" name="role_type" class="select">
                                <option value="">Custom name...</option>
                                @foreach ($roleTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="qc-role-name">Role Name *</label><input id="qc-role-name" name="name" class="input" required/></div>
                        <div><label for="qc-role-code">Role Code</label><input id="qc-role-code" name="code" class="input" placeholder="Generated from the name"/></div>
                        <div>
                            <label for="qc-role-scope">Access Scope *</label>
                            <select id="qc-role-scope" name="access_scope" class="select" required>
                                @foreach (['Company Level', 'Branch Level', 'Project Level', 'Site Level', 'Warehouse Level', 'All Company'] as $scope)
                                    <option>{{ $scope }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="qc-role-level">Role Level *</label>
                            <select id="qc-role-level" name="level" class="select" required>
                                @foreach (range(1, 6) as $level)
                                    <option value="{{ $level }}" @selected($level === 3)>Level {{ $level }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="qc-role-copy">Start with permissions of</label>
                            <select id="qc-role-copy" name="copy_permissions_from" class="select">
                                <option value="">No permissions yet</option>
                                @foreach ($roles as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="qc-role-mobile">Mobile App Access</label>
                            <select id="qc-role-mobile" name="mobile_app_access" class="select">
                                <option value="0">Not Allowed</option>
                                <option value="1">Allowed</option>
                            </select>
                        </div>
                        <input type="hidden" name="status" value="active"/>
                        <input type="hidden" name="can_approve_child_requests" value="1"/>
                        <div class="full help-box">Fine-tune the new role's permissions afterwards on the Permission Matrix. Copying from an existing role gives it a sensible starting point.</div>
                    </x-admin.quick-create>
                </div>
                <select id="role_id" name="role_id" class="select" required>
                    <option value="">Select role...</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id', $user?->primaryRole()?->id) == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('role_id')<div class="field-error">{{ $message }}</div>@enderror
                <div style="height:12px"></div>
                <div class="check-line"><input class="checkbox" type="checkbox" name="mobile_access" value="1" @checked(old('mobile_access', $user?->mobile_access))/> Mobile App Access</div>
                <div class="check-line"><input class="checkbox" type="checkbox" name="two_factor_enabled" value="1" @checked(old('two_factor_enabled', $user?->two_factor_enabled))/> Two Factor Authentication</div>
                <div class="check-line"><input class="checkbox" type="checkbox" name="temporary_access" value="1" @checked(old('temporary_access', $user?->temporary_access))/> Temporary Access</div>
            </x-admin.form-section>
        </div>

        <div>
            <x-admin.form-section title="Profile Identity" columns="2">
                <div><label for="name">Full Name *</label><input id="name" name="name" class="input" value="{{ old('name', $user?->name) }}" required/></div>
                <div><label for="employee_id">Employee ID</label><input id="employee_id" name="employee_id" class="input" value="{{ old('employee_id', $user?->employee_id) }}" placeholder="EMP-000"/></div>
                <div><label for="email">Email Address *</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $user?->email) }}" required/></div>
                <div><label for="phone">Phone Number</label><input id="phone" name="phone" class="input" value="{{ old('phone', $user?->phone) }}" placeholder="+966 5X XXX XXXX"/></div>
                <div>
                    <label for="language">Language</label>
                    <select id="language" name="language" class="select">
                        @foreach (['English', 'Arabic'] as $language)
                            <option @selected(old('language', $user?->language ?? 'English') === $language)>{{ $language }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="username">Username</label><input id="username" name="username" class="input" value="{{ old('username', $user?->username) }}"/></div>
            </x-admin.form-section>

            <x-admin.form-section title="Employment Information" columns="3">
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
                            <option value="{{ $department->id }}" @selected(old('department_id', $user?->department_id) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div class="label-row">
                        <label for="designation_id">Designation</label>
                        <x-admin.quick-create id="qc-designation" target="designation_id" :url="route('admin.master.designations.store')" title="New Designation" permission="Designations">
                            <div class="full"><label for="qc-desig-name">Designation Name *</label><input id="qc-desig-name" name="name" class="input" required/></div>
                            <div>
                                <label for="qc-desig-department">Department *</label>
                                <select id="qc-desig-department" name="department_id" class="select" data-prefill-from="department_id" required>
                                    <option value="">Select...</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="qc-desig-grade">Grade / Level</label>
                                <select id="qc-desig-grade" name="grade" class="select">
                                    <option value="">Select...</option>
                                    @foreach (['L1', 'L2', 'L3', 'L4', 'L5'] as $grade)
                                        <option>{{ $grade }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="hidden" name="status" value="active"/>
                        </x-admin.quick-create>
                    </div>
                    <select id="designation_id" name="designation_id" class="select">
                        <option value="">Select...</option>
                        @foreach ($designations as $designation)
                            <option value="{{ $designation->id }}" data-parent="{{ $designation->department_id }}" @selected(old("designation_id", $user?->designation_id) == $designation->id)>{{ $designation->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="employee_classification">Employee Classification</label>
                    <select id="employee_classification" name="employee_classification" class="select">
                        <option value="">Not set</option>
                        @foreach ($classifications as $classification)
                            <option value="{{ $classification }}" @selected(old('employee_classification', $user?->employee_classification ?? $user?->employee?->employee_classification) === $classification)>{{ $classification }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="joining_date">Joining Date</label><input id="joining_date" name="joining_date" type="date" class="input" value="{{ old('joining_date', $user?->joining_date?->format('Y-m-d')) }}"/></div>
                <div>
                    <label for="contract_type">Contract Type</label>
                    <select id="contract_type" name="contract_type" class="select">
                        @foreach (['Full Time', 'Contract', 'Temporary'] as $type)
                            <option @selected(old('contract_type', $user?->contract_type ?? 'Full Time') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label for="iqama_number">Iqama Number</label><input id="iqama_number" name="iqama_number" class="input" value="{{ old('iqama_number', $user?->iqama_number) }}" placeholder="Enter Iqama number"/></div>
                <div><label for="iqama_expiry_date">Iqama Expiry Date</label><input id="iqama_expiry_date" name="iqama_expiry_date" type="date" class="input" value="{{ old('iqama_expiry_date', $user?->iqama_expiry_date?->format('Y-m-d')) }}"/></div>
            </x-admin.form-section>

            <x-admin.form-section title="Access & Security" columns="3">
                <div>
                    <label for="password">{{ $user ? 'New Password (leave blank to keep)' : 'Password' }}</label>
                    <input id="password" name="password" type="password" class="input" placeholder="{{ $user ? '••••••••' : 'Blank = '.\App\Http\Controllers\Admin\UserController::DEFAULT_PASSWORD.', must change at first login' }}"/>
                </div>
                <div>
                    <label for="status">Account Status *</label>
                    <select id="status" name="status" class="select" required>
                        @foreach (['active', 'inactive', 'locked', 'pending'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $user?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div></div>
                <div><label for="access_start_date">Access Start Date</label><input id="access_start_date" name="access_start_date" type="date" class="input" value="{{ old('access_start_date', $user?->access_start_date?->format('Y-m-d')) }}"/></div>
                <div><label for="access_end_date">Access End Date</label><input id="access_end_date" name="access_end_date" type="date" class="input" value="{{ old('access_end_date', $user?->access_end_date?->format('Y-m-d')) }}"/></div>
            </x-admin.form-section>

            <x-admin.form-section title="Project / Site / Warehouse Scope" columns="3">
                <div>
                    <div class="label-row">
                        <label for="branch_id">Assigned Branch</label>
                        <x-admin.quick-create id="qc-branch" target="branch_id" :url="route('admin.master.branches.store')" title="New Branch" permission="Branches">
                            <div><label for="qc-branch-name">Branch Name *</label><input id="qc-branch-name" name="name" class="input" required/></div>
                            <div><label for="qc-branch-code">Code</label><input id="qc-branch-code" name="code" class="input" placeholder="Auto if blank"/></div>
                            <div><label for="qc-branch-city">City</label><input id="qc-branch-city" name="city" class="input" placeholder="Riyadh"/></div>
                            <div><label for="qc-branch-phone">Phone</label><input id="qc-branch-phone" name="phone" class="input" placeholder="+966..."/></div>
                            <input type="hidden" name="status" value="active"/>
                        </x-admin.quick-create>
                    </div>
                    <select id="branch_id" name="branch_id" class="select">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $user?->branch_id) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="project_id">Assigned Project</label>
                    <select id="project_id" name="project_id" class="select">
                        <option value="">All Projects</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $user?->project_id) == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="site_id">Assigned Site</label>
                    <select id="site_id" name="site_id" class="select">
                        <option value="">All Sites</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" data-parent="{{ $site->project_id }}" @selected(old("site_id", $user?->site_id) == $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="warehouse_id">Assigned Warehouse</label>
                    <select id="warehouse_id" name="warehouse_id" class="select">
                        <option value="">All Warehouses</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $user?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
            </x-admin.form-section>

            <div class="form-actions">
                <a class="btn outline" href="{{ route('admin.users.index') }}">Cancel</a>
                <button type="submit" class="btn primary">{{ $user ? 'Update User' : 'Save User' }}</button>
            </div>
        </div>
    </div>
</form>

<x-admin.dependent-select parent="department_id" child="designation_id" placeholder="designations"/>
<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>

@push('scripts')
<script>
    // Department + role type suggest a consistent role name/code inside the "New Role" dialog.
    (function () {
        var type = document.getElementById('qc-role-type');
        var department = document.getElementById('qc-role-department');
        var name = document.getElementById('qc-role-name');
        var code = document.getElementById('qc-role-code');
        if (!type || !name || !code) return;

        var nameTouched = false;
        var codeTouched = false;

        function toCode(value) {
            return value.toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }

        function suggest() {
            if (!type.value) return;
            var departmentName = department && department.value ? department.options[department.selectedIndex].textContent.trim() : '';
            if (!nameTouched) name.value = (departmentName ? departmentName + ' ' : '') + type.value;
            if (!codeTouched) code.value = toCode(name.value);
        }

        type.addEventListener('change', function () { nameTouched = false; suggest(); });
        if (department) department.addEventListener('change', suggest);
        name.addEventListener('input', function () { nameTouched = name.value !== ''; if (!codeTouched) code.value = toCode(name.value); });
        code.addEventListener('input', function () { codeTouched = code.value !== ''; });
    })();
</script>
@endpush
