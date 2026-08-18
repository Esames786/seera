@php
    /** @var \App\Models\StockTransfer|null $transfer */
    $transfer = $transfer ?? null;
    $lineData = old('lines', $transfer?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'quantity' => (float) $line->quantity,
    ])->all() ?? []);
    $rows = max(count($lineData), 4);
@endphp

<form method="POST" action="{{ $transfer ? route('admin.inventory.stock-transfers.update', $transfer) : route('admin.inventory.stock-transfers.store') }}">
    @csrf
    @if ($transfer) @method('PUT') @endif

    <x-admin.form-section title="Transfer Information" columns="3">
        <div><label for="transfer_date">Transfer Date *</label><input id="transfer_date" name="transfer_date" type="date" class="input" value="{{ old('transfer_date', $transfer?->transfer_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div>
            <label for="from_warehouse_id">From Warehouse *</label>
            <select id="from_warehouse_id" name="from_warehouse_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('from_warehouse_id', $transfer?->from_warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="to_warehouse_id">To Warehouse *</label>
            <select id="to_warehouse_id" name="to_warehouse_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('to_warehouse_id', $transfer?->to_warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $transfer?->notes) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Transferred Items">
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
        <div class="small" style="margin-top:10px">Unit cost is taken from the source warehouse average cost at dispatch, so it is not entered here.</div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.stock-transfers.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $transfer ? 'Update Stock Transfer' : 'Save Stock Transfer' }}</button>
    </div>
</form>
