@php
    /** @var \App\Models\SupplierBill|null $bill */
    $bill = $bill ?? null;
    $grnPrefill = $grnPrefill ?? [];
    $sourceReceipt = $sourceReceipt ?? null;
    $lineData = old('lines', $bill?->lines->map(fn ($line) => [
        'description' => $line->description,
        'expense_category_id' => $line->expense_category_id,
        'chart_of_account_id' => $line->chart_of_account_id,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->unit_price,
        'cost_center_id' => $line->cost_center_id,
        'goods_receipt_line_id' => $line->grnMatch?->goods_receipt_line_id,
        'matched_quantity' => $line->grnMatch ? (float) $line->grnMatch->matched_quantity : null,
    ])->all() ?? $grnPrefill);
    $rows = max(count($lineData), 3);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp

@if ($sourceReceipt)
    <div class="alert info flash">Billing goods receipt <strong>{{ $sourceReceipt->grn_number }}</strong>: its uninvoiced lines are filled in below. Adjust the invoiced quantity or price to match the supplier's invoice; any price difference is posted as a variance.</div>
@endif

<form method="POST" action="{{ $bill ? route('admin.accounting.accounts-payable.update', $bill) : route('admin.accounting.accounts-payable.store') }}">
    @csrf
    @if ($bill) @method('PUT') @endif

    <x-admin.form-section title="Bill Information" columns="3">
        <div>
            <label for="supplier_id">Supplier *</label>
            <select id="supplier_id" name="supplier_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id', $bill?->supplier_id ?? $sourceReceipt?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="bill_number">Bill Number *</label><input id="bill_number" name="bill_number" class="input" value="{{ old('bill_number', $bill?->bill_number) }}" placeholder="BILL-2026-005" required/></div>
        <div><label for="reference_number">Reference Number</label><input id="reference_number" name="reference_number" class="input" value="{{ old('reference_number', $bill?->reference_number ?? $sourceReceipt?->grn_number) }}" placeholder="PO-0104"/></div>
        <div><label for="bill_date">Bill Date *</label><input id="bill_date" name="bill_date" type="date" class="input" value="{{ old('bill_date', $bill?->bill_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="due_date">Due Date</label><input id="due_date" name="due_date" type="date" class="input" value="{{ old('due_date', $bill?->due_date?->toDateString() ?? now()->addDays(30)->toDateString()) }}"/></div>
        <div><label for="vat_rate">VAT Rate (%) *</label><input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" class="input" value="{{ old('vat_rate', $bill?->vat_rate ?? 15) }}" required/></div>
        <div>
            <label for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">No project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $bill?->project_id ?? $sourceReceipt?->warehouse?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="site_id">Site</label>
            <select id="site_id" name="site_id" class="select">
                <option value="">No site</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" data-parent="{{ $site->project_id }}" @selected(old('site_id', $bill?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="cost_center_id">Cost Center</label>
            <select id="cost_center_id" name="cost_center_id" class="select">
                <option value="">No cost center</option>
                @foreach ($costCenters as $costCenter)
                    <option value="{{ $costCenter->id }}" @selected(old('cost_center_id', $bill?->cost_center_id) == $costCenter->id)>{{ $costCenter->code }} - {{ $costCenter->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $bill?->notes) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Bill Lines">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="min-width:200px">Description *</th>
                        <th style="min-width:240px">Received Goods (GRN)</th>
                        <th style="width:110px">Invoiced Qty</th>
                        <th style="min-width:160px">Expense Category</th>
                        <th style="min-width:200px">Expense Account</th>
                        <th style="width:110px">Qty</th>
                        <th style="width:140px">Unit Price</th>
                        <th style="min-width:150px">Cost Center</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php $line = $lineData[$i] ?? []; @endphp
                        <tr>
                            <td><input name="lines[{{ $i }}][description]" class="input" value="{{ $line['description'] ?? '' }}" data-description/></td>
                            <td>
                                <select name="lines[{{ $i }}][goods_receipt_line_id]" class="select grn-line" title="Choose the posted goods receipt line this bill line invoices. Leave blank for a direct or service charge.">
                                    <option value="">Direct / service line</option>
                                    @foreach ($grnLines as $grnLine)
                                        <option value="{{ $grnLine->id }}"
                                            data-supplier="{{ $grnLine->goodsReceipt->supplier_id }}"
                                            data-remaining="{{ $qty($grnLine->uninvoicedQuantity()) }}"
                                            data-cost="{{ (float) $grnLine->unit_cost }}"
                                            data-label="{{ $grnLine->item?->label() }} ({{ $grnLine->goodsReceipt->grn_number }})"
                                            @selected(($line['goods_receipt_line_id'] ?? null) == $grnLine->id)>{{ $grnLine->goodsReceipt->grn_number }} · {{ $grnLine->item?->label() }} · {{ $qty($grnLine->uninvoicedQuantity()) }} left @ {{ number_format((float) $grnLine->unit_cost, 2) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $i }}][matched_quantity]" type="number" step="0.001" min="0" class="input" value="{{ $line['matched_quantity'] ?? '' }}" data-matched-quantity/></td>
                            <td>
                                <select name="lines[{{ $i }}][expense_category_id]" class="select">
                                    <option value="">-</option>
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category->id }}" @selected(($line['expense_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][chart_of_account_id]" class="select" title="The expense account this line is charged to. Leave on the default for materials; pick Fuel, Maintenance or Equipment Expense for other suppliers.">
                                    <option value="">Default: {{ $defaultExpenseAccount?->label() ?? '5200 - Material Expense' }}</option>
                                    @foreach ($expenseAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(($line['chart_of_account_id'] ?? null) == $account->id)>{{ $account->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.01" min="0" class="input" value="{{ $line['quantity'] ?? 1 }}" data-quantity/></td>
                            <td><input name="lines[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="input" value="{{ $line['unit_price'] ?? '' }}" data-unit-price/></td>
                            <td>
                                <select name="lines[{{ $i }}][cost_center_id]" class="select">
                                    <option value="">-</option>
                                    @foreach ($costCenters as $costCenter)
                                        <option value="{{ $costCenter->id }}" @selected(($line['cost_center_id'] ?? null) == $costCenter->id)>{{ $costCenter->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">
            Taxable amount, VAT and total are calculated from quantity × unit price and the bill VAT rate. Rows without a description or unit price are ignored.
            A line matched to received goods clears the receipt's accrual (Goods Received Not Invoiced) instead of posting an expense; input VAT and the supplier payable are recorded on this bill only.
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $bill ? 'Update Bill' : 'Save Bill' }}</button>
    </div>
</form>

<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>

<script>
    // Received-goods options follow the chosen supplier; picking a line fills the
    // invoiced quantity, quantity, price and description when they are still empty.
    (function () {
        const supplier = document.getElementById('supplier_id');
        const selects = Array.from(document.querySelectorAll('select.grn-line'));
        if (!supplier || selects.length === 0) return;

        const filter = () => {
            selects.forEach(select => {
                Array.from(select.options).forEach(option => {
                    if (!option.value) return;
                    const show = option.dataset.supplier === supplier.value;
                    option.hidden = !show;
                    option.disabled = !show;
                    if (!show && option.selected) select.value = '';
                });
            });
        };

        supplier.addEventListener('change', filter);
        filter();

        selects.forEach(select => select.addEventListener('change', () => {
            const row = select.closest('tr');
            const option = select.selectedOptions[0];
            const matched = row.querySelector('[data-matched-quantity]');
            const quantity = row.querySelector('[data-quantity]');
            const price = row.querySelector('[data-unit-price]');
            const description = row.querySelector('[data-description]');

            if (!option || !option.value) { matched.value = ''; return; }
            if (!matched.value) matched.value = option.dataset.remaining;
            if (!quantity.value || Number(quantity.value) === 1) quantity.value = option.dataset.remaining;
            if (!price.value) price.value = option.dataset.cost;
            if (!description.value) description.value = option.dataset.label;
        }));
    })();
</script>
