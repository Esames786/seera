@php /** @var \App\Models\ChartOfAccount|null $account */ $account = $account ?? null; @endphp

<form method="POST" action="{{ $account ? route('admin.accounting.chart-of-accounts.update', $account) : route('admin.accounting.chart-of-accounts.store') }}">
    @csrf
    @if ($account) @method('PUT') @endif

    <x-admin.form-section title="Account Information" columns="3">
        <div><label for="account_code">Account Code *</label><input id="account_code" name="account_code" class="input" value="{{ old('account_code', $account?->account_code) }}" placeholder="5200" required/></div>
        <div><label for="account_name">Account Name *</label><input id="account_name" name="account_name" class="input" value="{{ old('account_name', $account?->account_name) }}" placeholder="Material Expense" required/></div>
        <div>
            <label for="account_type">Account Type *</label>
            <select id="account_type" name="account_type" class="select" required>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(old('account_type', $account?->account_type) === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="parent_id">Parent Account</label>
            <select id="parent_id" name="parent_id" class="select">
                <option value="">No parent (top level)</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old('parent_id', $account?->parent_id) == $parent->id)>{{ $parent->label() }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="opening_balance">Opening Balance (SAR) *</label><input id="opening_balance" name="opening_balance" type="number" step="0.01" class="input" value="{{ old('opening_balance', $account?->opening_balance ?? 0) }}" required/></div>
        <div>
            <label for="normal_balance">Normal Balance *</label>
            <select id="normal_balance" name="normal_balance" class="select" required>
                @foreach (['debit', 'credit'] as $normal)
                    <option value="{{ $normal }}" @selected(old('normal_balance', $account?->normal_balance ?? 'debit') === $normal)>{{ ucfirst($normal) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="vat_applicable">VAT Applicable</label>
            <select id="vat_applicable" name="vat_applicable" class="select">
                <option value="0" @selected(! old('vat_applicable', $account?->vat_applicable ?? false))>No</option>
                <option value="1" @selected(old('vat_applicable', $account?->vat_applicable ?? false))>Yes</option>
            </select>
        </div>
        <div>
            <label for="cost_center_required">Cost Center Required</label>
            <select id="cost_center_required" name="cost_center_required" class="select">
                <option value="0" @selected(! old('cost_center_required', $account?->cost_center_required ?? false))>No</option>
                <option value="1" @selected(old('cost_center_required', $account?->cost_center_required ?? false))>Yes</option>
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $account?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <div class="help-box">
        Assets and expenses normally carry a debit balance. Liabilities, equity and revenue normally carry a credit balance.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.accounting.chart-of-accounts.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $account ? 'Update Account' : 'Save Account' }}</button>
    </div>
</form>
