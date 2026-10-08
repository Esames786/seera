@php /** @var \App\Models\Warehouse|null $warehouse */ $warehouse = $warehouse ?? null; @endphp

<form method="POST" action="{{ $warehouse ? route('admin.master.warehouses.update', $warehouse) : route('admin.master.warehouses.store') }}">
    @csrf
    @if ($warehouse) @method('PUT') @endif

    <x-admin.form-section title="Warehouse Information" columns="3">
        <div><label for="name">Warehouse Name *</label><input id="name" name="name" class="input" value="{{ old('name', $warehouse?->name) }}" required/></div>
        <div><label for="code">Warehouse Code *</label><input id="code" name="code" class="input" value="{{ old('code', $warehouse?->code) }}" placeholder="WH-RYD" required/></div>
        <div>
            <label for="branch_id">Branch *</label>
            <select id="branch_id" name="branch_id" class="select">
                <option value="">Select...</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $warehouse?->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="project_id">Project (optional)</label>
            @if($warehouse)<input type="hidden" name="project_id" value="{{ $warehouse->project_id }}"/>@endif
            <select id="project_id" name="project_id" class="select" @disabled($warehouse)>
                <option value="">Branch level</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $warehouse?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="site_id">Site (optional)</label>
            @if($warehouse)<input type="hidden" name="site_id" value="{{ $warehouse->site_id }}"/>@endif
            <select id="site_id" name="site_id" class="select" @disabled($warehouse)>
                <option value="">None</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" data-parent="{{ $site->project_id }}" @selected(old('site_id', $warehouse?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="incharge_id">Warehouse Incharge</label>
            <select id="incharge_id" name="incharge_id" class="select">
                <option value="">Select...</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('incharge_id', $warehouse?->incharge_id) == $user->id)>{{ $user->name }}</option>
                @endforeach
                @if($warehouse?->incharge && !$users->contains('id',$warehouse->incharge_id))<option value="{{ $warehouse->incharge_id }}" selected>{{ $warehouse->incharge->name }}</option>@endif
            </select>
        </div>
        <div>
            <label for="valuation_method">Inventory Valuation Method *</label>
            <select id="valuation_method" name="valuation_method" class="select" required>
                <option @selected(old('valuation_method', $warehouse?->valuation_method ?? 'FIFO') === 'FIFO')>FIFO</option>
                <option @selected(old('valuation_method', $warehouse?->valuation_method) === 'Average')>Average</option>
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $warehouse?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $warehouse?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="full"><label for="address">Location / Address</label><textarea id="address" name="address" class="textarea" placeholder="Warehouse address...">{{ old('address', $warehouse?->address) }}</textarea></div>
    </x-admin.form-section>

    <p class="note">{{ __('inventory_workspace.ownership_fixed') }} {{ __('inventory_workspace.valuation_help') }}</p>
    <x-admin.form-actions :cancel="route('admin.master.warehouses.index')" :save-new="auth()->user()->hasPermission('Warehouses','create')"/>
</form>

@if(!$warehouse)<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>@endif
