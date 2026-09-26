@extends('layouts.admin')

@section('title', 'Supplier Workspace')
@section('breadcrumb', 'Master Setup / Suppliers / Supplier Workspace')

@section('content')
    @php $closeUrl = \App\Support\SaveAction::cancelUrl(route('admin.master.suppliers.index')); @endphp
    <x-admin.page-header :title="'Supplier Workspace: '.$supplier->name" description="Profile and related records of this supplier in one place. Each section saves independently; approvals and payments stay on their own documents.">
        <a class="btn outline" href="{{ route('admin.master.suppliers.show', $supplier) }}">View (read-only)</a>
        <a class="btn outline" href="{{ $closeUrl }}">Back to Suppliers</a>
    </x-admin.page-header>

    @include('admin.master.suppliers._workspace-header', ['supplier' => $supplier, 'summary' => $summary])

    <div data-workspace>
        <nav class="tabs workspace-nav" data-workspace-nav aria-label="Supplier sections" hidden>
            <a class="tab" href="#profile" data-workspace-section="profile">Profile</a>
            @foreach ($panels as $key => $definition)
                <a class="tab" href="#{{ $key }}" data-workspace-related="{{ $key }}" data-related-url="{{ route('admin.master.suppliers.workspace.panel', [$supplier, $key]) }}">{{ __($definition->title) }}</a>
            @endforeach
        </nav>

        <div data-workspace-form>
            @include('admin.master.suppliers._form', ['supplier' => $supplier, 'workspace' => true])
        </div>

        <x-admin.workspace-host :close-url="$closeUrl"/>
    </div>
@endsection
