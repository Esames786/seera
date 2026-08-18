@php /** @var \App\Models\StockAdjustment|null $adjustment */ $adjustment = $adjustment ?? null; @endphp

<form method="POST" action="{{ $adjustment ? route('admin.inventory.stock-adjustments.update', $adjustment) : route('admin.inventory.stock-adjustments.store') }}">
    @csrf
    @if ($adjustment) @method('PUT') @endif

    <x-admin.form-section title="Adjustment Information" columns="3">
        <div>
            <label for="warehouse_id">Warehouse *</label>
            <select id="warehouse_id" name="warehouse_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $adjustment?->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="item_id">Item *</label>
            <select id="item_id" name="item_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" @selected(old('item_id', $adjustment?->item_id) == $item->id)>{{ $item->label() }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="adjustment_date">Adjustment Date *</label><input id="adjustment_date" name="adjustment_date" type="date" class="input" value="{{ old('adjustment_date', $adjustment?->adjustment_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="adjusted_quantity">Counted Quantity *</label><input id="adjusted_quantity" name="adjusted_quantity" type="number" step="0.001" min="0" class="input" value="{{ old('adjusted_quantity', $adjustment?->adjusted_quantity ?? 0) }}" required/></div>
        <div class="full"><label for="reason">Reason</label><textarea id="reason" name="reason" class="textarea" placeholder="Physical count variance, damage, wastage...">{{ old('reason', $adjustment?->reason) }}</textarea></div>
    </x-admin.form-section>

    <div class="help-box">
        Enter the quantity you actually counted. The current system quantity, the difference and the adjustment value are calculated from the warehouse stock record when you save.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.stock-adjustments.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $adjustment ? 'Update Adjustment' : 'Save Adjustment' }}</button>
    </div>
</form>
