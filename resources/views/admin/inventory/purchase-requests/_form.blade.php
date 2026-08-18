@php
    /** @var \App\Models\PurchaseRequest|null $pr */
    $pr = $pr ?? null;
    $lineData = old('lines', $pr?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'description' => $line->description,
        'quantity' => (float) $line->quantity,
        'unit_id' => $line->unit_id,
        'estimated_unit_cost' => (float) $line->estimated_unit_cost,
        'budget_line' => $line->budget_line,
    ])->all() ?? []);
    $rows = max(count($lineData), 4);
@endphp

<form method="POST" action="{{ $pr ? route('admin.inventory.purchase-requests.update', $pr) : route('admin.inventory.purchase-requests.store') }}">
    @csrf
    @if ($pr) @method('PUT') @endif

    <x-admin.form-section title="Request Information" columns="3">
        <div><label for="request_date">Request Date *</label><input id="request_date" name="request_date" type="date" class="input" value="{{ old('request_date', $pr?->request_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="required_date">Required Date</label><input id="required_date" name="required_date" type="date" class="input" value="{{ old('required_date', $pr?->required_date?->toDateString()) }}"/></div>
        <div>
            <label for="priority">Priority *</label>
            <select id="priority" name="priority" class="select" required>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $pr?->priority ?? 'normal') === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">No project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $pr?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="site_id">Site</label>
            <select id="site_id" name="site_id" class="select">
                <option value="">No site</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected(old('site_id', $pr?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="warehouse_id">Deliver To Warehouse</label>
            <select id="warehouse_id" name="warehouse_id" class="select">
                <option value="">Select...</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $pr?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['draft', 'pending'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $pr?->status ?? 'pending') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="reason">Reason</label><textarea id="reason" name="reason" class="textarea">{{ old('reason', $pr?->reason) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Requested Items">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="min-width:220px">Item *</th>
                        <th style="min-width:170px">Description</th>
                        <th style="width:120px">Quantity *</th>
                        <th style="min-width:130px">Unit</th>
                        <th style="width:150px">Est. Unit Cost</th>
                        <th style="min-width:140px">Budget Line</th>
                    </tr>
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
                            <td><input name="lines[{{ $i }}][description]" class="input" value="{{ $line['description'] ?? '' }}"/></td>
                            <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.001" min="0" class="input" value="{{ $line['quantity'] ?? '' }}"/></td>
                            <td>
                                <select name="lines[{{ $i }}][unit_id]" class="select">
                                    <option value="">-</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected(($line['unit_id'] ?? null) == $unit->id)>{{ $unit->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $i }}][estimated_unit_cost]" type="number" step="0.0001" min="0" class="input" value="{{ $line['estimated_unit_cost'] ?? '' }}"/></td>
                            <td><input name="lines[{{ $i }}][budget_line]" class="input" value="{{ $line['budget_line'] ?? '' }}"/></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">Rows without an item or quantity are ignored. Estimated total is calculated on save.</div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.purchase-requests.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $pr ? 'Update Purchase Request' : 'Save Purchase Request' }}</button>
    </div>
</form>
