@php
    /** @var \App\Models\SupplierBill|null $bill */
    $bill = $bill ?? null;
    $lineData = old('lines', $bill?->lines->map(fn ($line) => [
        'description' => $line->description,
        'expense_category_id' => $line->expense_category_id,
        'chart_of_account_id' => $line->chart_of_account_id,
        'quantity' => (float) $line->quantity,
        'unit_price' => (float) $line->unit_price,
        'cost_center_id' => $line->cost_center_id,
    ])->all() ?? []);
    $rows = max(count($lineData), 3);
@endphp

<form method="POST" action="{{ $bill ? route('admin.accounting.accounts-payable.update', $bill) : route('admin.accounting.accounts-payable.store') }}">
    @csrf
    @if ($bill) @method('PUT') @endif

    <x-admin.form-section title="Bill Information" columns="3">
        <div>
            <label for="supplier_id">Supplier *</label>
            <select id="supplier_id" name="supplier_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id', $bill?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="bill_number">Bill Number *</label><input id="bill_number" name="bill_number" class="input" value="{{ old('bill_number', $bill?->bill_number) }}" placeholder="BILL-2026-005" required/></div>
        <div><label for="reference_number">Reference Number</label><input id="reference_number" name="reference_number" class="input" value="{{ old('reference_number', $bill?->reference_number) }}" placeholder="PO-0104"/></div>
        <div><label for="bill_date">Bill Date *</label><input id="bill_date" name="bill_date" type="date" class="input" value="{{ old('bill_date', $bill?->bill_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
        <div><label for="due_date">Due Date</label><input id="due_date" name="due_date" type="date" class="input" value="{{ old('due_date', $bill?->due_date?->toDateString() ?? now()->addDays(30)->toDateString()) }}"/></div>
        <div><label for="vat_rate">VAT Rate (%) *</label><input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" class="input" value="{{ old('vat_rate', $bill?->vat_rate ?? 15) }}" required/></div>
        <div>
            <label for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">No project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $bill?->project_id) == $project->id)>{{ $project->name }}</option>
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
                            <td><input name="lines[{{ $i }}][description]" class="input" value="{{ $line['description'] ?? '' }}"/></td>
                            <td>
                                <select name="lines[{{ $i }}][expense_category_id]" class="select">
                                    <option value="">-</option>
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category->id }}" @selected(($line['expense_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][chart_of_account_id]" class="select">
                                    <option value="">Default material expense</option>
                                    @foreach ($expenseAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(($line['chart_of_account_id'] ?? null) == $account->id)>{{ $account->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.01" min="0" class="input" value="{{ $line['quantity'] ?? 1 }}"/></td>
                            <td><input name="lines[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="input" value="{{ $line['unit_price'] ?? '' }}"/></td>
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
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $bill ? 'Update Bill' : 'Save Bill' }}</button>
    </div>
</form>

<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>
