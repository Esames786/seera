@extends('layouts.admin')

@section('title', 'Expense Category Details')
@section('breadcrumb', 'Master Setup / Expense Categories / Category Details')

@section('content')
    <x-admin.page-header :title="$category->name" description="Expense category overview">
        <a class="btn primary" href="{{ route('admin.master.expense-categories.edit', $category) }}">Edit Category</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="strtoupper($category->code)" label="Category Code"/>
        <x-admin.metric-card color="green" :value="$category->mobile_visible ? 'Yes' : 'No'" label="Mobile Visible"/>
        <x-admin.metric-card color="yellow" :value="$category->approval_required ? 'Yes' : 'No'" label="Approval Required"/>
        <x-admin.metric-card color="cyan" :value="$category->vat_treatment" label="VAT Treatment"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Category Information" class="detail-table">
            <tbody>
                <tr><th>Category Name</th><td>{{ $category->name }}</td></tr>
                <tr><th>Category Code</th><td>{{ $category->code }}</td></tr>
                <tr><th>Linked Chart of Account</th><td>{{ $category->linked_account ?? '-' }}</td></tr>
                <tr><th>Allowed Payment Type</th><td>{{ $category->payment_type }}</td></tr>
                <tr><th>Invoice Photo Required</th><td>{{ $category->invoice_photo_required ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Description</th><td>{{ $category->description ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$category->status"/></td></tr>
            </tbody>
        </x-admin.data-table>

        <div class="table-card">
            <div class="table-title">Recent Expenses</div>
            <div class="table-empty">
                Expense entries under this category will appear here when the Site Expenses module ships in a later phase.
            </div>
        </div>
    </div>
@endsection
