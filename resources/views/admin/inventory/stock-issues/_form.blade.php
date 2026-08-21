@php
    /** @var \App\Models\StockIssue|null $issue */
    $issue = $issue ?? null;
    $lineData = old('lines', $issue?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'quantity' => (float) $line->quantity,
    ])->all() ?? []);
    $rows = max(count($lineData), 4);
@endphp

<form method="POST" action="{{ $issue ? route('admin.inventory.stock-issues.update', $issue) : route('admin.inventory.stock-issues.store') }}">
    @csrf
    @if ($issue) @method('PUT') @endif

    <x-admin.form-section title="Issue Information" columns="3">
        <div>
            <label for="warehouse_id">Issue From Warehouse *</label>
            <select id="warehouse_id" name="warehouse_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $issue?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="issue_date">Issue Date *</label><input id="issue_date" name="issue_date" type="date" class="input" value="{{ old('issue_date', $issue?->issue_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div>
            <label for="project_id">Issue To Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">No project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $issue?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="site_id">Issue To Site</label>
            <select id="site_id" name="site_id" class="select">
                <option value="">No site</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" data-parent="{{ $site->project_id }}" @selected(old('site_id', $issue?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="purpose">Purpose</label><textarea id="purpose" name="purpose" class="textarea">{{ old('purpose', $issue?->purpose) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Issued Items">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th style="min-width:280px">Item *</th><th style="width:160px">Quantity *</th></tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php $line = $lineData[$i] ?? []; @endphp
                        <tr>
                            <td>
                                <select name="lines[{{ $i }}][item_id]" class="select">
                                    <option value="">Select item...</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}" @selected(($line['item_id'] ?? null) == $item->id)>{{ $item->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.001" min="0" class="input" value="{{ $line['quantity'] ?? '' }}"/></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">Unit cost is taken from the warehouse average cost when the issue is posted, so it is not entered here.</div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.stock-issues.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $issue ? 'Update Stock Issue' : 'Save Stock Issue' }}</button>
    </div>
</form>

<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>
