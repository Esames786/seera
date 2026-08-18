@extends('layouts.admin')

@section('title', 'Item Categories')
@section('breadcrumb', 'Inventory / Item Categories')

@section('content')
    <x-admin.page-header title="Item Categories" description="Material grouping with the inventory and expense accounts each category posts to">
        <a class="btn primary" href="{{ route('admin.inventory.categories.create') }}">+ Add Category</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalCategories" label="Total Categories"/>
        <x-admin.metric-card color="green" :value="$activeCategories" label="Active Categories"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Category code or name..."/>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            @foreach (['active', 'inactive'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.inventory.categories.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Categories Listing">
        <thead>
            <tr><th>Code</th><th>Name</th><th>Parent</th><th>Inventory Account</th><th>Expense Account</th><th>Items</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->code }}</td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->parent?->name ?? '-' }}</td>
                    <td>{{ $category->inventoryAccount?->label() ?? '-' }}</td>
                    <td>{{ $category->expenseAccount?->label() ?? '-' }}</td>
                    <td>{{ $category->items_count }}</td>
                    <td><x-admin.status-badge :status="$category->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :edit="route('admin.inventory.categories.edit', $category)"
                            :delete="route('admin.inventory.categories.destroy', $category)"
                            :name="$category->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No categories match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $categories->firstItem() ?? 0 }}-{{ $categories->lastItem() ?? 0 }} of {{ $categories->total() }}</span>
            {{ $categories->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
