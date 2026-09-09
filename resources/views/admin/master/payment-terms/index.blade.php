@extends('layouts.admin')

@section('title', 'Payment Terms')
@section('breadcrumb', 'Master Setup / Suppliers / Payment Terms')

@section('content')
    <x-admin.page-header title="Payment Terms" description="Agreed credit periods for suppliers. Pick one on the supplier form or add it there with + New.">
        <a class="btn outline" href="{{ route('admin.master.suppliers.index') }}">Back to Suppliers</a>
    </x-admin.page-header>

    <div class="help-box">
        The number of days is counted from the bill date. When a supplier bill is saved without a due date, the due date is set from the supplier's payment term.
    </div>

    <div class="split">
        <x-admin.form-section title="Add Payment Term">
            <form method="POST" action="{{ route('admin.master.payment-terms.store') }}">
                @csrf
                <label for="name">Name *</label>
                <input id="name" name="name" class="input" value="{{ old('name') }}" placeholder="e.g. 45 Days, End of month" required/>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
                <div style="height:10px"></div>
                <label for="days">Days *</label>
                <input id="days" name="days" type="number" min="0" max="365" class="input" value="{{ old('days', 0) }}" required/>
                @error('days')<div class="field-error">{{ $message }}</div>@enderror
                <div style="height:10px"></div>
                <label for="description">Description</label>
                <input id="description" name="description" class="input" value="{{ old('description') }}"/>
                <div style="height:14px"></div>
                <button type="submit" class="btn primary block">Add Payment Term</button>
            </form>
        </x-admin.form-section>

        <x-admin.data-table title="Payment Terms" :subtitle="$terms->count().' defined'">
            <thead>
                <tr><th>Name</th><th style="width:110px">Days</th><th>Description</th><th>Suppliers</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($terms as $term)
                    <tr>
                        <form method="POST" action="{{ route('admin.master.payment-terms.update', $term) }}" id="term-{{ $term->id }}">
                            @csrf
                            @method('PUT')
                        </form>
                        <td><input name="name" form="term-{{ $term->id }}" class="input" value="{{ $term->name }}" required/></td>
                        <td><input name="days" form="term-{{ $term->id }}" type="number" min="0" max="365" class="input" value="{{ $term->days }}" required/></td>
                        <td><input name="description" form="term-{{ $term->id }}" class="input" value="{{ $term->description }}"/></td>
                        <td>{{ $term->suppliers_count }}</td>
                        <td>
                            <select name="status" form="term-{{ $term->id }}" class="select" style="width:120px">
                                <option value="active" @selected($term->status === 'active')>Active</option>
                                <option value="inactive" @selected($term->status === 'inactive')>Inactive</option>
                            </select>
                        </td>
                        <td>
                            <div class="actions">
                                <button type="submit" form="term-{{ $term->id }}" class="btn sm primary">Save</button>
                                @if ($term->suppliers_count === 0)
                                    <button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.master.payment-terms.destroy', $term) }}" data-delete-name="{{ $term->name }}">Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty">No payment terms defined.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    </div>
@endsection
