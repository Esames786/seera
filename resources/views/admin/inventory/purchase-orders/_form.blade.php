@php
    /** @var \App\Models\PurchaseOrder|null $order */
    $order = $order ?? null;
    $sourceRequest = $sourceRequest ?? null;
    $prefill = $sourceRequest?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->estimated_unit_cost,
    ])->all() ?? [];
    $lineData = old('lines', $order?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->unit_price,
    ])->all() ?? $prefill);
    $rows = max(count($lineData), 4);
@endphp

<form method="POST" action="{{ $order ? route('admin.inventory.purchase-orders.update', $order) : route('admin.inventory.purchase-orders.store') }}">
    @csrf
    @if ($order) @method('PUT') @endif

    <x-admin.form-section title="Order Information" columns="3">
        <div>
            <label for="supplier_id">Supplier *</label>
            <select id="supplier_id" name="supplier_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id', $order?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="po_date">PO Date *</label><input id="po_date" name="po_date" type="date" class="input" value="{{ old('po_date', $order?->po_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="expected_delivery_date">Expected Delivery</label><input id="expected_delivery_date" name="expected_delivery_date" type="date" class="input" value="{{ old('expected_delivery_date', $order?->expected_delivery_date?->toDateString()) }}"/></div>
        <div>
            <label for="purchase_request_id">Source Purchase Request</label>
            <select id="purchase_request_id" name="purchase_request_id" class="select">
                <option value="">Not linked</option>
                @foreach ($approvedRequests as $requestOption)
                    <option value="{{ $requestOption->id }}" @selected(old('purchase_request_id', $order?->purchase_request_id ?? $sourceRequest?->id) == $requestOption->id)>{{ $requestOption->pr_number }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="warehouse_id">Deliver To Warehouse *</label>
            <select id="warehouse_id" name="warehouse_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $order?->warehouse_id ?? $sourceRequest?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="vat_rate">VAT Rate (%) *</label><input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" class="input" value="{{ old('vat_rate', $order?->vat_rate ?? 15) }}" required/></div>
        <div>
            <label for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">No project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $order?->project_id ?? $sourceRequest?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="site_id">Site</label>
            <select id="site_id" name="site_id" class="select">
                <option value="">No site</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected(old('site_id', $order?->site_id ?? $sourceRequest?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $order?->notes) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Order Lines">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th style="min-width:260px">Item *</th><th style="width:140px">Quantity *</th><th style="width:160px">Unit Price</th></tr>
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
                            <td><input name="lines[{{ $i }}][unit_price]" type="number" step="0.0001" min="0" class="input" value="{{ $line['unit_price'] ?? '' }}"/></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">Taxable, VAT and total are calculated from quantity, unit price and the order VAT rate.</div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $order ? 'Update Purchase Order' : 'Save Purchase Order' }}</button>
    </div>
</form>
