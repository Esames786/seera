@php /** @var \App\Models\Project|null $project */ $project = $project ?? null; @endphp

<form method="POST" action="{{ $project ? route('admin.master.projects.update', $project) : route('admin.master.projects.store') }}">
    @csrf
    @if ($project) @method('PUT') @endif

    <x-admin.form-section title="Project Information" columns="3">
        <div><label for="name">Project Name *</label><input id="name" name="name" class="input" value="{{ old('name', $project?->name) }}" required/></div>
        <div><label for="code">Project Code *</label><input id="code" name="code" class="input" value="{{ old('code', $project?->code) }}" placeholder="PRJ-001" required/></div>
        <div>
            <div class="label-row">
                <label for="customer_id">Client Name *</label>
                <x-admin.quick-create id="qc-customer" target="customer_id" :url="route('admin.master.customers.store')" title="New Client" permission="Customers" submit="Create Client">
                    <div class="full"><label for="qc-cust-name">Client Name *</label><input id="qc-cust-name" name="name" class="input" required/></div>
                    <div>
                        <label for="qc-cust-type">Type *</label>
                        <select id="qc-cust-type" name="type" class="select" required>
                            <option>Company</option>
                            <option>Individual</option>
                        </select>
                    </div>
                    <div><label for="qc-cust-code">Code</label><input id="qc-cust-code" name="code" class="input" placeholder="Auto if blank"/></div>
                    <div><label for="qc-cust-vat">VAT Number</label><input id="qc-cust-vat" name="vat_number" class="input"/></div>
                    <div><label for="qc-cust-phone">Phone</label><input id="qc-cust-phone" name="phone" class="input" placeholder="+966..."/></div>
                    <div><label for="qc-cust-contact">Contact Person</label><input id="qc-cust-contact" name="contact_person" class="input"/></div>
                    <div><label for="qc-cust-email">Email</label><input id="qc-cust-email" name="email" type="email" class="input"/></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="customer_id" name="customer_id" class="select">
                <option value="">Select...</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $project?->customer_id) == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="label-row">
                <label for="project_classification_id">Classification</label>
                <x-admin.quick-create id="qc-classification" target="project_classification_id" :url="route('admin.master.project-classifications.store')" :edit-url="route('admin.master.project-classifications.update', '__ID__')" title="Project Classification" permission="Projects" submit="Save Classification">
                    <div class="full"><label for="qc-cls-name">Classification Name *</label><input id="qc-cls-name" name="name" class="input" placeholder="e.g. Infrastructure, Residential, Maintenance" data-edit-from="label" required/></div>
                    <div class="full"><label for="qc-cls-description">Description</label><input id="qc-cls-description" name="description" class="input"/></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="project_classification_id" name="project_classification_id" class="select">
                <option value="">Not classified</option>
                @foreach ($classifications as $classification)
                    <option value="{{ $classification->id }}" @selected(old('project_classification_id', $project?->project_classification_id) == $classification->id)>{{ $classification->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="label-row">
                <label for="branch_id">Branch *</label>
                <x-admin.quick-create id="qc-branch" target="branch_id" :url="route('admin.master.branches.store')" title="New Branch" permission="Branches">
                    <div><label for="qc-branch-name">Branch Name *</label><input id="qc-branch-name" name="name" class="input" required/></div>
                    <div><label for="qc-branch-code">Code</label><input id="qc-branch-code" name="code" class="input" placeholder="Auto if blank"/></div>
                    <div><label for="qc-branch-city">City</label><input id="qc-branch-city" name="city" class="input" placeholder="Riyadh"/></div>
                    <div><label for="qc-branch-phone">Phone</label><input id="qc-branch-phone" name="phone" class="input" placeholder="+966..."/></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="branch_id" name="branch_id" class="select">
                <option value="">Select...</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $project?->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="label-row">
                <label for="manager_id">Project Manager *</label>
                <x-admin.quick-create id="qc-manager" target="manager_id" :url="route('admin.users.store')" title="New Project Manager Account" permission="Users" submit="Create Account" :wide="true">
                    <div><label for="qc-mgr-name">Full Name *</label><input id="qc-mgr-name" name="name" class="input" required/></div>
                    <div><label for="qc-mgr-email">Email *</label><input id="qc-mgr-email" name="email" type="email" class="input" required/></div>
                    <div><label for="qc-mgr-phone">Phone</label><input id="qc-mgr-phone" name="phone" class="input" placeholder="+966..."/></div>
                    <div>
                        <label for="qc-mgr-department">Department</label>
                        <select id="qc-mgr-department" name="department_id" class="select">
                            <option value="">Select...</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full">
                        <label for="qc-mgr-role">Role *</label>
                        <select id="qc-mgr-role" name="role_id" class="select" required>
                            <option value="">Select role...</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected($role->code === 'PROJECT_MANAGER')>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="status" value="active"/>
                    <div class="full help-box">The account is created with the temporary password <strong>{{ \App\Http\Controllers\Admin\UserController::DEFAULT_PASSWORD }}</strong> and must set its own password at first sign-in.</div>
                </x-admin.quick-create>
            </div>
            <select id="manager_id" name="manager_id" class="select">
                <option value="">Select...</option>
                @foreach ($managers as $manager)
                    <option value="{{ $manager->id }}" @selected(old('manager_id', $project?->manager_id) == $manager->id)>{{ $manager->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'planning', 'on hold', 'completed', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $project?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="start_date">Start Date *</label><input id="start_date" name="start_date" type="date" class="input" value="{{ old('start_date', $project?->start_date?->format('Y-m-d')) }}"/></div>
        <div><label for="end_date">End Date *</label><input id="end_date" name="end_date" type="date" class="input" value="{{ old('end_date', $project?->end_date?->format('Y-m-d')) }}"/></div>
        <div><label for="budget">Budget Amount (SAR) *</label><input id="budget" name="budget" type="number" step="0.01" min="0" class="input" value="{{ old('budget', $project?->budget) }}"/></div>
        <div class="full"><label for="location">Project Location</label><textarea id="location" name="location" class="textarea" placeholder="City, district, street, exact construction location...">{{ old('location', $project?->location) }}</textarea></div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" class="textarea" placeholder="Project scope, commercial notes, client requirements...">{{ old('description', $project?->description) }}</textarea></div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.master.projects.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $project ? 'Update Project' : 'Save Project' }}</button>
    </div>
</form>
