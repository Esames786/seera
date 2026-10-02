@php /** @var \App\Models\ExpenseCategory|null $category */ $category = $category ?? null; @endphp

<form method="POST" action="{{ $category ? route('admin.master.expense-categories.update', $category) : route('admin.master.expense-categories.store') }}">
    @csrf
    @if ($category) @method('PUT') @endif

    <x-admin.form-section title="Expense Category Information" columns="3">
        <div><label for="name">Category Name *</label><input id="name" name="name" class="input" value="{{ old('name', $category?->name) }}" required/></div>
        <div><label for="code">Category Code *</label><input id="code" name="code" class="input" value="{{ old('code', $category?->code) }}" placeholder="EXP-FUEL" required/></div>
        <div><label for="chart_of_account_id">Accounting expense account</label><select id="chart_of_account_id" name="chart_of_account_id" class="select"><option value="">Not configured</option>@foreach(\App\Models\ChartOfAccount::where('status', 'active')->where('account_type', 'expense')->orderBy('account_code')->get() as $account)<option value="{{ $account->id }}" @selected(old('chart_of_account_id', $category?->chart_of_account_id) == $account->id)>{{ $account->label() }}</option>@endforeach</select><small>Required for Site Expense posting. Legacy account text is not used as a mapping.</small></div>
        <div>
            <label for="approval_required">Approval Required</label>
            <select id="approval_required" name="approval_required" class="select">
                <option value="1" @selected(old('approval_required', $category?->approval_required ?? true))>Yes</option>
                <option value="0" @selected(!old('approval_required', $category?->approval_required ?? true))>No</option>
            </select>
        </div>
        <div>
            <label for="mobile_visible">Mobile App Visible</label>
            <select id="mobile_visible" name="mobile_visible" class="select">
                <option value="1" @selected(old('mobile_visible', $category?->mobile_visible ?? true))>Yes</option>
                <option value="0" @selected(!old('mobile_visible', $category?->mobile_visible ?? true))>No</option>
            </select>
        </div>
        <div>
            <label for="payment_type">Allowed Payment Type</label>
            <select id="payment_type" name="payment_type" class="select">
                @foreach (['Cash', 'Bank', 'Both'] as $type)
                    <option @selected(old('payment_type', $category?->payment_type ?? 'Both') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="invoice_photo_required">Invoice Photo Required</label>
            <select id="invoice_photo_required" name="invoice_photo_required" class="select">
                <option value="1" @selected(old('invoice_photo_required', $category?->invoice_photo_required ?? true))>Yes</option>
                <option value="0" @selected(!old('invoice_photo_required', $category?->invoice_photo_required ?? true))>No</option>
            </select>
        </div>
        <div>
            <label for="vat_treatment">Default VAT Treatment</label>
            <select id="vat_treatment" name="vat_treatment" class="select">
                <option @selected(old('vat_treatment', $category?->vat_treatment ?? 'VAT 15%') === 'VAT 15%')>VAT 15%</option>
                <option @selected(old('vat_treatment', $category?->vat_treatment) === 'Non-VAT')>Non-VAT</option>
            </select>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                <option value="active" @selected(old('status', $category?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $category?->status) === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" class="textarea" placeholder="Used for daily fuel expense entries from mobile app...">{{ old('description', $category?->description) }}</textarea></div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.master.expense-categories.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $category ? 'Update Category' : 'Save Category' }}</button>
    </div>
</form>
