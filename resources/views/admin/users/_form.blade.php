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
                <label for="role_id">Primary Role</label>
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
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" class="select">
                        <option value="">Select...</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $user?->department_id) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="designation_id">Designation</label>
                    <select id="designation_id" name="designation_id" class="select">
                        <option value="">Select...</option>
                        @foreach ($designations as $designation)
                            <option value="{{ $designation->id }}" @selected(old('designation_id', $user?->designation_id) == $designation->id)>{{ $designation->name }}</option>
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
                    <input id="password" name="password" type="password" class="input" placeholder="••••••••" @if(!$user) value="password" @endif/>
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
                    <label for="branch_id">Assigned Branch</label>
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
                            <option value="{{ $site->id }}" @selected(old('site_id', $user?->site_id) == $site->id)>{{ $site->name }}</option>
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
