@php /** @var \App\Models\ItemCategory|null $category */ $category = $category ?? null; @endphp

<form method="POST" action="{{ $category ? route('admin.inventory.categories.update', $category) : route('admin.inventory.categories.store') }}">
    @csrf
    @if ($category) @method('PUT') @endif

    <x-admin.form-section title="Category Information" columns="3">
        <div><label for="code">Category Code *</label><input id="code" name="code" class="input" value="{{ old('code', $category?->code) }}" placeholder="CAT-CEM" required/></div>
        <div><label for="name">Category Name *</label><input id="name" name="name" class="input" value="{{ old('name', $category?->name) }}" placeholder="Cement and Concrete" required/></div>
        <div>
            <label for="parent_id">Parent Category</label>
            <select id="parent_id" name="parent_id" class="select">
                <option value="">No parent (top level)</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old('parent_id', $category?->parent_id) == $parent->id)>{{ $parent->code }} - {{ $parent->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="inventory_account_id">Linked Inventory Account</label>
            <select id="inventory_account_id" name="inventory_account_id" class="select">
                <option value="">Default inventory asset</option>
                @foreach ($inventoryAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('inventory_account_id', $category?->inventory_account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="expense_account_id">Linked Expense Account</label>
            <select id="expense_account_id" name="expense_account_id" class="select">
                <option value="">Default material expense</option>
                @foreach ($expenseAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('expense_account_id', $category?->expense_account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $category?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.inventory.categories.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $category ? 'Update Category' : 'Save Category' }}</button>
    </div>
</form>
