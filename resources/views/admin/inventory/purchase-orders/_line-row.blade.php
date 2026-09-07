{{--
    One purchase order line. $i is the row index (or the literal __INDEX__ inside
    the <template> used to add rows), $line the current values, $items the
    catalogue, $vatRate the order default.
--}}
<tr data-row-index="{{ $i }}">
    <td>
        <select name="lines[{{ $i }}][item_id]" class="select" data-field="item_id">
            <option value="">Select item...</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected(($line['item_id'] ?? null) == $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
    </td>
    <td><textarea name="lines[{{ $i }}][description]" class="textarea" rows="1" style="min-height:38px" placeholder="Specification, brand, operator, rope length..." data-field="description">{{ $line['description'] ?? '' }}</textarea></td>
    <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.001" min="0" class="input" value="{{ $line['quantity'] ?? '' }}" data-field="quantity"/></td>
    <td><input name="lines[{{ $i }}][unit_price]" type="number" step="0.0001" min="0" class="input" value="{{ $line['unit_price'] ?? '' }}" data-field="unit_price"/></td>
    <td><input name="lines[{{ $i }}][discount_percent]" type="number" step="0.01" min="0" max="100" class="input" value="{{ $line['discount_percent'] ?? '' }}" placeholder="0" data-field="discount_percent"/></td>
    <td><input name="lines[{{ $i }}][vat_rate]" type="number" step="0.01" min="0" max="100" class="input" value="{{ $line['vat_rate'] ?? '' }}" placeholder="{{ $vatRate }}" data-field="vat_rate"/></td>
    <td class="line-total">0.00</td>
    <td><button type="button" class="row-remove" title="Remove line" aria-label="Remove line">&times;</button></td>
</tr>
