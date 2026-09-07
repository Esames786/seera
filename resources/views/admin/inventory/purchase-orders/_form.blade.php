@php
    /** @var \App\Models\PurchaseOrder|null $order */
    $order = $order ?? null;
    $sourceRequest = $sourceRequest ?? null;
    $vatRate = old('vat_rate', $order?->vat_rate ?? 15);
    $prefill = $sourceRequest?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'description' => null,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->estimated_unit_cost,
        'discount_percent' => '',
        'vat_rate' => '',
    ])->all() ?? [];
    $lineData = old('lines', $order?->lines->map(fn ($line) => [
        'item_id' => $line->item_id,
        'description' => $line->description,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->unit_price,
        'discount_percent' => (float) $line->discount_percent > 0 ? (float) $line->discount_percent : '',
        'vat_rate' => (float) $line->vat_rate,
    ])->all() ?? $prefill);
    $lineData = array_values($lineData);
    $rows = max(count($lineData), 2);
@endphp

<form method="POST" action="{{ $order ? route('admin.inventory.purchase-orders.update', $order) : route('admin.inventory.purchase-orders.store') }}" enctype="multipart/form-data">
    @csrf
    @if ($order) @method('PUT') @endif

    <x-admin.form-section title="Order Information" columns="3">
        <div>
            <div class="label-row">
                <label for="supplier_id">Supplier *</label>
                <x-admin.quick-create id="qc-supplier" target="supplier_id" :url="route('admin.master.suppliers.store')" title="New Supplier" permission="Suppliers">
                    <div class="full"><label for="qc-sup-name">Supplier Name *</label><input id="qc-sup-name" name="name" class="input" required/></div>
                    <div><label for="qc-sup-code">Code</label><input id="qc-sup-code" name="code" class="input" placeholder="Auto if blank"/></div>
                    <div><label for="qc-sup-vat">VAT Number</label><input id="qc-sup-vat" name="vat_number" class="input"/></div>
                    <div><label for="qc-sup-contact">Contact Person</label><input id="qc-sup-contact" name="contact_person" class="input"/></div>
                    <div><label for="qc-sup-phone">Phone</label><input id="qc-sup-phone" name="phone" class="input" placeholder="+966..."/></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
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
        <div><label for="vat_rate">Default VAT Rate (%) *</label><input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" class="input" value="{{ $vatRate }}" required/></div>
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
                    <option value="{{ $site->id }}" data-parent="{{ $site->project_id }}" @selected(old('site_id', $order?->site_id ?? $sourceRequest?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $order?->notes) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="Order Lines">
        <div class="small" style="margin-bottom:10px">Prices are excluding VAT (SAR). Discount is a percentage of the line. Leave VAT % blank to use the order default.</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="min-width:220px">Item *</th>
                        <th style="min-width:220px">Description</th>
                        <th style="width:110px">Qty *</th>
                        <th style="width:130px">Unit Price</th>
                        <th style="width:100px">Disc %</th>
                        <th style="width:100px">VAT %</th>
                        <th style="width:130px;text-align:right">Line Total</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="po-lines-body">
                    @for ($i = 0; $i < $rows; $i++)
                        @include('admin.inventory.purchase-orders._line-row', ['i' => $i, 'line' => $lineData[$i] ?? [], 'items' => $items, 'vatRate' => $vatRate])
                    @endfor
                </tbody>
            </table>
        </div>
        <template id="po-line-template">
            @include('admin.inventory.purchase-orders._line-row', ['i' => '__INDEX__', 'line' => [], 'items' => $items, 'vatRate' => $vatRate])
        </template>
        <div class="dynamic-rows-actions">
            <button type="button" class="btn outline" id="po-add-line">+ Add Line</button>
            @error('lines')<div class="field-error">{{ $message }}</div>@enderror
        </div>
        <div class="totals-box">
            <div class="tot"><div class="k">Gross</div><div class="v" id="tot-gross">0.00</div></div>
            <div class="tot"><div class="k">Discount</div><div class="v" id="tot-discount">0.00</div></div>
            <div class="tot"><div class="k">Taxable</div><div class="v" id="tot-taxable">0.00</div></div>
            <div class="tot"><div class="k">VAT</div><div class="v" id="tot-vat">0.00</div></div>
            <div class="tot grand"><div class="k">Total (SAR)</div><div class="v" id="tot-total">0.00</div></div>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="Supplier Quotation">
        <label for="quotations">Attach quotation files</label>
        <input id="quotations" name="quotations[]" type="file" class="input" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"/>
        <div class="small" style="margin-top:8px">
            Upload the supplier's quotation as received: a PDF or a photo/scan of the paper copy. PDF, JPG, PNG or WEBP, up to 10 MB each, up to 10 files.
            More files can be added later from the order page.
        </div>
        @error('quotations')<div class="field-error">{{ $message }}</div>@enderror
        @error('quotations.*')<div class="field-error">{{ $message }}</div>@enderror

        @if ($order && $order->attachments->isNotEmpty())
            <br/>
            <ul class="attachment-list">
                @foreach ($order->attachments as $attachment)
                    <li>
                        <span>📎 {{ $attachment->file_name }} <span class="small">({{ $attachment->humanSize() }})</span></span>
                        <a href="{{ route('admin.inventory.purchase-orders.attachments.download', [$order, $attachment]) }}" style="color:var(--blue);font-weight:700">Download</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $order ? 'Update Purchase Order' : 'Save Purchase Order' }}</button>
    </div>
</form>

<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>
<x-admin.dynamic-rows body="po-lines-body" template="po-line-template" add="po-add-line" min="1"/>

@push('scripts')
<script>
    (function () {
        var body = document.getElementById('po-lines-body');
        var headerVat = document.getElementById('vat_rate');
        if (!body || !headerVat) return;

        function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }
        function money(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function num(el) { var v = parseFloat(el ? el.value : ''); return isNaN(v) ? 0 : v; }
        function set(id, value) { var el = document.getElementById(id); if (el) el.textContent = money(value); }

        function recalc() {
            var gross = 0, discount = 0, taxable = 0, vat = 0;
            var defaultVat = num(headerVat);

            body.querySelectorAll('tr[data-row-index]').forEach(function (row) {
                var quantity = num(row.querySelector('[data-field=quantity]'));
                var price = num(row.querySelector('[data-field=unit_price]'));
                var disc = num(row.querySelector('[data-field=discount_percent]'));
                var vatInput = row.querySelector('[data-field=vat_rate]');
                if (vatInput) vatInput.placeholder = headerVat.value;
                var rate = vatInput && vatInput.value !== '' ? num(vatInput) : defaultVat;

                var g = round2(quantity * price);
                var d = round2(g * disc / 100);
                var t = round2(g - d);
                var v = round2(t * rate / 100);

                var cell = row.querySelector('.line-total');
                if (cell) cell.textContent = money(round2(t + v));

                gross += g; discount += d; taxable += t; vat += v;
            });

            set('tot-gross', gross);
            set('tot-discount', discount);
            set('tot-taxable', taxable);
            set('tot-vat', vat);
            set('tot-total', round2(taxable + vat));
        }

        body.addEventListener('input', recalc);
        body.addEventListener('change', recalc);
        body.addEventListener('seera:rows-changed', recalc);
        headerVat.addEventListener('input', recalc);
        recalc();
    })();
</script>
@endpush
