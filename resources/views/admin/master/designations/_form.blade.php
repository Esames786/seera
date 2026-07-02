@php /** @var \App\Models\Designation|null $designation */ $designation = $designation ?? null; @endphp

<form method="POST" action="{{ $designation ? route('admin.master.designations.update', $designation) : route('admin.master.designations.store') }}">
    @csrf
    @if ($designation) @method('PUT') @endif

    <x-admin.form-section title="Designation Information" columns="3">
        <div><label for="name">Designation Name *</label><input id="name" name="name" class="input" value="{{ old('name', $designation?->name) }}" required/></div>
        <div>
            <label for="department_id">Department *</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">Select...</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $designation?->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="grade">Grade / Level</label>
            <select id="grade" name="grade" class="select">
                <option value="">Select...</option>
                @foreach (['L1', 'L2', 'L3', 'L4', 'L5'] as $grade)
                    <option @selected(old('grade', $designation?->grade) === $grade)>{{ $grade }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="default_role_id">Default Role</label>
            <select id="default_role_id" name="default_role_id" class="select">
                <option value="">None</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('default_role_id', $designation?->default_role_id) == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="mobile_access_default">Mobile App Access Default</label>
            <select id="mobile_access_default" name="mobile_access_default" class="select">
                <option value="1" @selected(old('mobile_access_default', $designation?->mobile_access_default))>Yes</option>
                <option value="0" @selected(!old('mobile_access_default', $designation?->mobile_access_default ?? false))>No</option>
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $designation?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $designation?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" class="textarea" placeholder="Responsibilities and access notes...">{{ old('description', $designation?->description) }}</textarea></div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.master.designations.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $designation ? 'Update Designation' : 'Save Designation' }}</button>
    </div>
</form>
