@extends('layouts.admin')

@section('title', 'Project Classifications')
@section('breadcrumb', 'Master Setup / Projects / Classifications')

@section('content')
    <x-admin.page-header title="Project Classifications" description="Your own way of grouping projects. Pick one on the project form or add it there with + New.">
        <a class="btn outline" href="{{ route('admin.master.projects.index') }}">Back to Projects</a>
    </x-admin.page-header>

    <div class="split">
        <x-admin.form-section title="Add Classification">
            <form method="POST" action="{{ route('admin.master.project-classifications.store') }}">
                @csrf
                <label for="name">Name *</label>
                <input id="name" name="name" class="input" value="{{ old('name') }}" placeholder="e.g. Infrastructure, Residential, Maintenance" required/>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
                <div style="height:10px"></div>
                <label for="description">Description</label>
                <input id="description" name="description" class="input" value="{{ old('description') }}"/>
                <div style="height:14px"></div>
                <button type="submit" class="btn primary block">Add Classification</button>
            </form>
        </x-admin.form-section>

        <x-admin.data-table title="Classifications" :subtitle="$classifications->count().' defined'">
            <thead>
                <tr><th>Name</th><th>Description</th><th>Projects</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($classifications as $classification)
                    <tr>
                        <form method="POST" action="{{ route('admin.master.project-classifications.update', $classification) }}" id="cls-{{ $classification->id }}">
                            @csrf
                            @method('PUT')
                        </form>
                        <td><input name="name" form="cls-{{ $classification->id }}" class="input" value="{{ $classification->name }}" required/></td>
                        <td><input name="description" form="cls-{{ $classification->id }}" class="input" value="{{ $classification->description }}"/></td>
                        <td>{{ $classification->projects_count }}</td>
                        <td>
                            <select name="status" form="cls-{{ $classification->id }}" class="select" style="width:120px">
                                <option value="active" @selected($classification->status === 'active')>Active</option>
                                <option value="inactive" @selected($classification->status === 'inactive')>Inactive</option>
                            </select>
                        </td>
                        <td>
                            <div class="actions">
                                <button type="submit" form="cls-{{ $classification->id }}" class="btn sm primary">Save</button>
                                @if ($classification->projects_count === 0)
                                    <button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.master.project-classifications.destroy', $classification) }}" data-delete-name="{{ $classification->name }}">Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No classifications yet. Add the first one here or from the project form.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>
@endsection
