@php
    /** @var \App\Models\GoodsReceipt|null $grn */
    $grn = $grn ?? null;
    $order = $order ?? null;
    $prefill = $order?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'ordered_quantity' => (float) $line->quantity,
        'received_quantity' => $line->outstandingQuantity(),
        'accepted_quantity' => $line->outstandingQuantity(),
        'unit_cost' => (float) $line->unit_price,
    ])->all() ?? [];
    $lineData = old('lines', $grn?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'ordered_quantity' => (float) $line->ordered_quantity,
        'received_quantity' => (float) $line->received_quantity,
        'accepted_quantity' => (float) $line->accepted_quantity,
        'unit_cost' => (float) $line->unit_cost,
    ])->all() ?? $prefill);
    $rows = max(count($lineData), 4);
@endphp

<form method="POST" action="{{ $grn ? route('admin.inventory.goods-receipts.update', $grn) : route('admin.inventory.goods-receipts.store') }}">
    @csrf
    @if ($grn) @method('PUT') @endif

    <x-admin.form-section title="Receipt Information" columns="3">
        <div>
            <label for="supplier_id">Supplier *</label>
            <select id="supplier_id" name="supplier_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id', $grn?->supplier_id ?? $order?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="warehouse_id">Receiving Warehouse *</label>
            <select id="warehouse_id" name="warehouse_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $grn?->warehouse_id ?? $order?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="purchase_order_id">Purchase Order</label>
            <select id="purchase_order_id" name="purchase_order_id" class="select">
                <option value="">Not linked</option>
                @foreach ($openOrders as $openOrder)
                    <option value="{{ $openOrder->id }}" @selected(old('purchase_order_id', $grn?->purchase_order_id ?? $order?->id) == $openOrder->id)>{{ $openOrder->po_number }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="received_date">Received Date *</label><input id="received_date" name="received_date" type="date" class="input" value="{{ old('received_date', $grn?->received_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="delivery_note_number">Delivery Note Number</label><input id="delivery_note_number" name="delivery_note_number" class="input" value="{{ old('delivery_note_number', $grn?->delivery_note_number) }}"/></div>
        <div><label for="invoice_number">Supplier Invoice Number</label><input id="invoice_number" name="invoice_number" class="input" value="{{ old('invoice_number', $grn?->invoice_number) }}"/></div>
        <div><label for="vat_rate">VAT Rate (%) *</label><input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" class="input" value="{{ old('vat_rate', $grn?->vat_rate ?? 15) }}" required/></div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $grn?->notes) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Received Lines">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="min-width:240px">Item *</th>
                        <th style="width:130px">Ordered</th>
                        <th style="width:130px">Received *</th>
                        <th style="width:130px">Accepted</th>
                        <th style="width:150px">Unit Cost</th>
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
                            <td><input name="lines[{{ $i }}][ordered_quantity]" type="number" step="0.001" min="0" class="input" value="{{ $line['ordered_quantity'] ?? '' }}"/></td>
                            <td><input name="lines[{{ $i }}][received_quantity]" type="number" step="0.001" min="0" class="input" value="{{ $line['received_quantity'] ?? '' }}"/></td>
                            <td><input name="lines[{{ $i }}][accepted_quantity]" type="number" step="0.001" min="0" class="input" value="{{ $line['accepted_quantity'] ?? '' }}"/></td>
                            <td><input name="lines[{{ $i }}][unit_cost]" type="number" step="0.0001" min="0" class="input" value="{{ $line['unit_cost'] ?? '' }}"/></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">
            Accepted quantity is what enters stock. Anything received but not accepted is recorded as rejected. Leave accepted blank to accept the full received quantity.
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.goods-receipts.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $grn ? 'Update Goods Receipt' : 'Save Goods Receipt' }}</button>
    </div>
</form>
