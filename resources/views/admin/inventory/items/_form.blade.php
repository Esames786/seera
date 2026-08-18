@php /** @var \App\Models\Item|null $item */ $item = $item ?? null; @endphp

<form method="POST" action="{{ $item ? route('admin.inventory.items.update', $item) : route('admin.inventory.items.store') }}">
    @csrf
    @if ($item) @method('PUT') @endif

    <x-admin.form-section title="A. Item Information" columns="3">
        <div><label for="item_code">Item Code *</label><input id="item_code" name="item_code" class="input" value="{{ old('item_code', $item?->item_code) }}" placeholder="ITM-0041" required/></div>
        <div><label for="name">Item Name *</label><input id="name" name="name" class="input" value="{{ old('name', $item?->name) }}" required/></div>
        <div>
            <label for="item_category_id">Category</label>
            <select id="item_category_id" name="item_category_id" class="select">
                <option value="">Select...</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('item_category_id', $item?->item_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="unit_id">Unit *</label>
            <select id="unit_id" name="unit_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected(old('unit_id', $item?->unit_id) == $unit->id)>{{ $unit->code }} - {{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="valuation_method">Valuation Method *</label>
            <select id="valuation_method" name="valuation_method" class="select" required>
                @foreach ($valuationMethods as $method)
                    <option value="{{ $method }}" @selected(old('valuation_method', $item?->valuation_method ?? 'average') === $method)>{{ strtoupper($method) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $item?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" class="textarea">{{ old('description', $item?->description) }}</textarea></div>
    </x-admin.form-section>

    <x-admin.form-section title="B. Stock Control" columns="3">
        <div><label for="reorder_level">Reorder Level *</label><input id="reorder_level" name="reorder_level" type="number" step="0.001" min="0" class="input" value="{{ old('reorder_level', $item?->reorder_level ?? 0) }}" required/></div>
        <div><label for="minimum_stock">Minimum Stock *</label><input id="minimum_stock" name="minimum_stock" type="number" step="0.001" min="0" class="input" value="{{ old('minimum_stock', $item?->minimum_stock ?? 0) }}" required/></div>
        <div><label for="maximum_stock">Maximum Stock *</label><input id="maximum_stock" name="maximum_stock" type="number" step="0.001" min="0" class="input" value="{{ old('maximum_stock', $item?->maximum_stock ?? 0) }}" required/></div>
        <div>
            <label for="preferred_supplier_id">Preferred Supplier</label>
            <select id="preferred_supplier_id" name="preferred_supplier_id" class="select">
                <option value="">No preferred supplier</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('preferred_supplier_id', $item?->preferred_supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="C. Accounting" columns="3">
        <div>
            <label for="inventory_account_id">Linked Inventory Account</label>
            <select id="inventory_account_id" name="inventory_account_id" class="select">
                <option value="">Default inventory asset</option>
                @foreach ($inventoryAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('inventory_account_id', $item?->inventory_account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="expense_account_id">Linked Expense Account</label>
            <select id="expense_account_id" name="expense_account_id" class="select">
                <option value="">Default material expense</option>
                @foreach ($expenseAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('expense_account_id', $item?->expense_account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="vat_applicable">VAT Applicable</label>
            <select id="vat_applicable" name="vat_applicable" class="select">
                <option value="1" @selected(old('vat_applicable', $item?->vat_applicable ?? true))>Yes</option>
                <option value="0" @selected(! old('vat_applicable', $item?->vat_applicable ?? true))>No</option>
            </select>
        </div>
    </x-admin.form-section>

    <div class="help-box">
        Average cost is maintained automatically by goods receipts and stock issues. FIFO is stored as a valuation option but batch costing is not built in this phase.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.items.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $item ? 'Update Item' : 'Save Item' }}</button>
    </div>
</form>
