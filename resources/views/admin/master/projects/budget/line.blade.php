@extends('layouts.admin')

@section('title', 'Edit Budget Line')
@section('breadcrumb', 'Master Setup / Projects / Budget / BOQ / Edit Line')

@section('content')
    @php $categories = \App\Models\ProjectBudgetLine::CATEGORIES; @endphp
    <x-admin.page-header :title="'Edit line '.$line->cost_code.' — '.$budget->reference" :description="$project->code.' / '.$project->name.' · draft v'.$budget->version.'. Amount is calculated on the server (quantity × rate, or the lump sum).'">
        <a class="btn outline" href="{{ $returnTo }}">Back to Budget Lines</a>
    </x-admin.page-header>

    @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.master.projects.budget.lines.update', [$project, $budget->id, $line->id]) }}">
        @csrf @method('PUT')
        <x-admin.form-section title="Budget Line" columns="3">
            <div><label for="cost_code">Cost code *</label><input id="cost_code" name="cost_code" class="input" maxlength="32" required value="{{ old('cost_code', $line->cost_code) }}"/></div>
            <div><label for="category">Category *</label><select id="category" name="category" class="select" required>@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category', $line->category) === $key)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="site_id">Site</label><select id="site_id" name="site_id" class="select"><option value="">Whole project</option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected((string) old('site_id', $line->site_id) === (string) $site->id)>{{ $site->name }}</option>@endforeach</select></div>
            <div class="full"><label for="description">Description *</label><input id="description" name="description" class="input" maxlength="200" required value="{{ old('description', $line->description) }}"/></div>
            <div><label for="item_id">Item (materials)</label><select id="item_id" name="item_id" class="select"><option value="">None</option>@foreach ($items as $item)<option value="{{ $item->id }}" @selected((string) old('item_id', $line->item_id) === (string) $item->id)>{{ $item->item_code }} - {{ $item->name }}</option>@endforeach</select></div>
            <div><label for="unit_id">Unit</label><select id="unit_id" name="unit_id" class="select"><option value="">From item / none</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('unit_id', $line->unit_id) === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</select></div>
            <div><label for="chart_of_account_id">Cost account</label><select id="chart_of_account_id" name="chart_of_account_id" class="select"><option value="">Not mapped — planning only</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('chart_of_account_id', $line->chart_of_account_id) === (string) $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select></div>
            <div><label for="quantity">Quantity</label><input id="quantity" name="quantity" type="number" step="0.001" min="0" class="input" value="{{ old('quantity', $line->quantity) }}"/></div>
            <div><label for="rate">Rate (SAR)</label><input id="rate" name="rate" type="number" step="0.0001" min="0" class="input" value="{{ old('rate', $line->rate) }}"/></div>
            <div><label for="amount">Lump sum (SAR)</label><input id="amount" name="amount" type="number" step="0.01" min="0" class="input" value="{{ old('amount', $line->isLumpSum() ? $line->amount : '') }}" placeholder="when no qty × rate"/></div>
            <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea" maxlength="1000">{{ old('notes', $line->notes) }}</textarea></div>
        </x-admin.form-section>
        <div class="form-actions">
            <a class="btn outline" href="{{ $returnTo }}">Cancel</a>
            <button type="submit" class="btn primary">Save Line</button>
        </div>
    </form>
@endsection
