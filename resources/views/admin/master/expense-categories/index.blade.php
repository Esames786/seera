@extends('layouts.admin')

@section('title', 'Expense Categories')
@section('breadcrumb', 'Master Setup / Expense Categories')

@section('content')
    <x-admin.page-header title="Expense Category Setup" description="Used by site staff mobile expense entry and automatic accounting posting">
        <a class="btn primary" href="{{ route('admin.master.expense-categories.create') }}">+ Add Expense Category</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalCategories" label="Total Categories"/>
        <x-admin.metric-card color="green" :value="$mobileCategories" label="Mobile Visible"/>
        <x-admin.metric-card color="yellow" :value="$approvalCategories" label="Approval Required"/>
        <x-admin.metric-card color="cyan" :value="$totalCategories - $approvalCategories" label="Auto Approved"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:240px" type="search" name="search" value="{{ request('search') }}" placeholder="Search category name or code..."/>
        <select class="select" style="width:140px" name="status">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <x-slot:actions>
            <a class="btn primary" href="{{ route('admin.master.expense-categories.create') }}">+ Add Expense Category</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Expense Categories Listing">
        <thead>
            <tr>
                <th>Category Code</th><th>Category Name</th><th>Linked Account</th><th>Approval Required</th>
                <th>Mobile Visible</th><th>VAT Treatment</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->code }}</td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->linked_account ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$category->approval_required ? 'yes' : 'no'"/></td>
                    <td><x-admin.status-badge :status="$category->mobile_visible ? 'yes' : 'no'"/></td>
                    <td>{{ $category->vat_treatment }}</td>
                    <td><x-admin.status-badge :status="$category->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.master.expense-categories.show', $category)"
                            :edit="route('admin.master.expense-categories.edit', $category)"
                            :delete="route('admin.master.expense-categories.destroy', $category)"
                            :name="$category->name"/>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No expense categories found for the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $categories->firstItem() ?? 0 }}-{{ $categories->lastItem() ?? 0 }} of {{ $categories->total() }}</span>
            {{ $categories->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
