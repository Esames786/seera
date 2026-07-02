@php /** @var \App\Models\Branch|null $branch */ $branch = $branch ?? null; @endphp

<form method="POST" action="{{ $branch ? route('admin.master.branches.update', $branch) : route('admin.master.branches.store') }}">
    @csrf
    @if ($branch) @method('PUT') @endif

    <x-admin.form-section title="Branch Information" columns="3">
        <div><label for="name">Branch Name *</label><input id="name" name="name" class="input" value="{{ old('name', $branch?->name) }}" required/></div>
        <div><label for="code">Branch Code *</label><input id="code" name="code" class="input" value="{{ old('code', $branch?->code) }}" placeholder="BR-RYD" required/></div>
        <div><label for="city">City *</label><input id="city" name="city" class="input" value="{{ old('city', $branch?->city) }}" placeholder="Riyadh"/></div>
        <div>
            <label for="manager_id">Branch Manager</label>
            <select id="manager_id" name="manager_id" class="select">
                <option value="">Select...</option>
                @foreach ($managers as $manager)
                    <option value="{{ $manager->id }}" @selected(old('manager_id', $branch?->manager_id) == $manager->id)>{{ $manager->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="phone">Phone</label><input id="phone" name="phone" class="input" value="{{ old('phone', $branch?->phone) }}" placeholder="+966..."/></div>
        <div><label for="email">Email</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $branch?->email) }}"/></div>
        <div class="full"><label for="address">Address</label><textarea id="address" name="address" class="textarea">{{ old('address', $branch?->address) }}</textarea></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $branch?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $branch?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.master.branches.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $branch ? 'Update Branch' : 'Save Branch' }}</button>
    </div>
</form>
