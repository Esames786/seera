@php /** @var \App\Models\Department|null $department */ $department = $department ?? null; @endphp

<form method="POST" action="{{ $department ? route('admin.master.departments.update', $department) : route('admin.master.departments.store') }}">
    @csrf
    @if ($department) @method('PUT') @endif

    <x-admin.form-section title="Department Information" columns="3">
        <div><label for="name">Department Name *</label><input id="name" name="name" class="input" value="{{ old('name', $department?->name) }}" required/></div>
        <div><label for="code">Department Code *</label><input id="code" name="code" class="input" value="{{ old('code', $department?->code) }}" placeholder="FIN" required/></div>
        <div>
            <label for="head_user_id">Department Head</label>
            <select id="head_user_id" name="head_user_id" class="select">
                <option value="">Select...</option>
                @foreach ($heads as $head)
                    <option value="{{ $head->id }}" @selected(old('head_user_id', $department?->head_user_id) == $head->id)>{{ $head->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" class="textarea" placeholder="Department purpose and responsibilities...">{{ old('description', $department?->description) }}</textarea></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $department?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $department?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.master.departments.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $department ? 'Update Department' : 'Save Department' }}</button>
    </div>
</form>
