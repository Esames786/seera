@php /** @var \App\Models\Project|null $project */ $project = $project ?? null; @endphp

<form method="POST" action="{{ $project ? route('admin.master.projects.update', $project) : route('admin.master.projects.store') }}">
    @csrf
    @if ($project) @method('PUT') @endif

    <x-admin.form-section title="Project Information" columns="3">
        <div><label for="name">Project Name *</label><input id="name" name="name" class="input" value="{{ old('name', $project?->name) }}" required/></div>
        <div><label for="code">Project Code *</label><input id="code" name="code" class="input" value="{{ old('code', $project?->code) }}" placeholder="PRJ-001" required/></div>
        <div>
            <label for="customer_id">Client Name *</label>
            <select id="customer_id" name="customer_id" class="select">
                <option value="">Select...</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(old('customer_id', $project?->customer_id) == $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="branch_id">Branch *</label>
            <select id="branch_id" name="branch_id" class="select">
                <option value="">Select...</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $project?->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="manager_id">Project Manager *</label>
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
