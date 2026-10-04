@extends('layouts.admin')
@section('title', $expense->exists ? 'Edit Site Expense' : 'Add Site Expense')
@section('breadcrumb', 'Site Expenses / Entry')
@push('styles')
<style>
@media(max-width:640px){
    .topbar{height:auto;min-height:64px;flex-wrap:wrap;gap:10px;padding:12px 20px}
    .topbar-right{flex-wrap:wrap;gap:10px;max-width:100%}
    .content{min-width:0}
}
</style>
@endpush
@section('content')
<x-admin.page-header :title="$expense->exists ? 'Edit '.$expense->expense_number : 'Add Site Expense'" description="Record an expense and attach its receipt. Save a draft or send it for approval from this screen." />
<form method="POST" enctype="multipart/form-data" action="{{ $expense->exists ? route('admin.site-expenses.update', $expense) : route('admin.site-expenses.store') }}" data-se-form>
    @csrf @if($expense->exists) @method('PUT') @endif
    @if($expense->status === 'rejected')
        <div class="alert">Rejected: {{ $expense->rejection_reason }}. Saving corrections keeps the rejection history; Submit starts a new approval attempt.</div>
        <input type="hidden" name="previous_instance_id" value="{{ $expense->approvals()->latest('attempt')->value('id') }}">
    @endif
    <x-admin.form-section title="Expense details" columns="2">
        <div><label for="expense_date">Date *</label><input id="expense_date" type="date" name="expense_date" class="input" required value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d') ?? now()->toDateString()) }}">@error('expense_date')<p role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="project_id">Project *</label><select id="project_id" name="project_id" class="select" required><option value="">Select project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id', $expense->project_id ?? request('project_id') ?? auth()->user()->project_id) == $project->id)>{{ $project->name }}</option>@endforeach</select>@error('project_id')<p role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="site_id">Site *</label><select id="site_id" name="site_id" class="select" required><option value="">Select site</option>@foreach($sites as $site)<option value="{{ $site->id }}" data-project="{{ $site->project_id }}" @selected(old('site_id', $expense->site_id ?? auth()->user()->site_id) == $site->id)>{{ $site->name }}</option>@endforeach</select>@error('site_id')<p role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="expense_category_id">Category *</label><select id="expense_category_id" name="expense_category_id" class="select" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-rate="{{ $category->vat_treatment === 'VAT 15%' ? 15 : 0 }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select>@error('expense_category_id')<p role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="taxable_amount">Amount before VAT (SAR) *</label><input class="input" id="taxable_amount" name="taxable_amount" type="number" min="0.01" step="0.01" inputmode="decimal" required value="{{ old('taxable_amount', $expense->taxable_amount) }}">@error('taxable_amount')<p role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="vat_applicable">VAT applicable *</label><select class="select" name="vat_applicable" id="vat_applicable"><option value="0" @selected(!old('vat_applicable', (float)$expense->vat_rate > 0))>No</option><option value="1" @selected(old('vat_applicable', (float)$expense->vat_rate > 0))>Yes — use category rate</option></select>
        @if(auth()->user()->hasPermission('Site Expenses', 'post'))<label for="vat_rate">Finance VAT rate override (%)</label><input class="input" id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" value="{{ old('vat_rate', $expense->exists ? $expense->vat_rate : '') }}" placeholder="Category default">@error('vat_rate')<p role="alert">{{ $message }}</p>@enderror @endif
        <p data-se-total aria-live="polite">VAT and total are calculated on save.</p></div>
        <div><label for="payment_type">Payment type *</label><select id="payment_type" name="payment_type" class="select" required>@foreach(\App\Models\SiteExpense::PAYMENT_TYPES as $type)<option @selected(old('payment_type', $expense->payment_type ?? 'Cash') === $type)>{{ $type }}</option>@endforeach</select>@error('payment_type')<p role="alert">{{ $message }}</p>@enderror</div>
        <div data-payment-account><label for="payment_account_id">Paid from</label><select id="payment_account_id" name="payment_account_id" class="select"><option value="">Select payment account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" data-channel="{{ $account->account_code === '1110' ? 'Cash' : 'Bank' }}" @selected(old('payment_account_id', $expense->payment_account_id) == $account->id)>{{ $account->account_name }}</option>@endforeach</select>@error('payment_account_id')<p role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="supplier_id">Supplier (required for Supplier Credit)</label><select id="supplier_id" name="supplier_id" class="select"><option value="">No supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $expense->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>@error('supplier_id')<p role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="reference_number">Invoice / receipt reference</label><input class="input" id="reference_number" name="reference_number" value="{{ old('reference_number', $expense->reference_number) }}" maxlength="191"></div>
        <div class="full"><label for="receipt">Receipt / invoice photo or PDF</label><input class="input" id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,application/pdf"><small>Private upload. JPG, PNG or PDF, up to 10 MB. On a phone, choose the camera or a saved file. Existing receipts remain attached.</small>@error('receipt')<p role="alert">{{ $message }}</p>@enderror</div>
        <div class="full"><label for="description">Short description *</label><textarea id="description" name="description" class="textarea" required maxlength="4000">{{ old('description', $expense->description) }}</textarea>@error('description')<p role="alert">{{ $message }}</p>@enderror</div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea" maxlength="4000">{{ old('notes', $expense->notes) }}</textarea></div>
        @if($workflows->count() > 1)<div><label for="workflow_id">Approval workflow</label><select name="workflow_id" id="workflow_id" class="select"><option value="">Select workflow</option>@foreach($workflows as $workflow)<option value="{{ $workflow->id }}" @selected(old('workflow_id') == $workflow->id)>{{ $workflow->name }}</option>@endforeach</select></div>@endif
    </x-admin.form-section>
    <div class="alert">Employee-paid expenses remain payable until Finance reimburses them. Supplier Credit creates one draft Supplier Bill after expense approval. Finance must review and submit that bill for its own required approvals before bill accounting and payment.</div>
    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.site-expenses.index') }}">Back to expenses</a>
        <button type="submit" data-save-default class="btn outline" name="_save_action" value="stay">Save draft &amp; stay</button>
        <button type="submit" class="btn outline" name="_save_action" value="new">Save &amp; new</button>
        <button class="btn outline" name="_save_action" value="close">Save &amp; close</button>
        <button class="btn primary" name="_intent" value="submit">{{ $expense->status === 'rejected' ? 'Resubmit for approval' : 'Submit for approval' }}</button>
    </div>
</form>
<style>[data-se-form] .btn,[data-se-form] .input,[data-se-form] .select{min-height:44px}@media(max-width:640px){[data-se-form] .form-grid{grid-template-columns:1fr!important}[data-se-form] .form-actions{display:flex;flex-wrap:wrap;gap:10px}[data-se-form] .form-actions .btn{flex:1 1 45%}}</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const f=document.querySelector('[data-se-form]'), project=f.querySelector('#project_id'), site=f.querySelector('#site_id'), payment=f.querySelector('#payment_type'), account=f.querySelector('#payment_account_id');
    function sync(){
        [...site.options].forEach(o=>{if(o.value){o.hidden=o.dataset.project!==project.value;o.disabled=o.hidden;}});
        if(site.selectedOptions[0]?.disabled)site.value='';
        [...account.options].forEach(o=>{if(o.value){o.hidden=o.dataset.channel!==payment.value;o.disabled=o.hidden;}});
        if(account.selectedOptions[0]?.disabled)account.value='';
        f.querySelector('[data-payment-account]').hidden=!['Cash','Bank'].includes(payment.value);
        const category=f.querySelector('#expense_category_id').selectedOptions[0], vat=f.querySelector('#vat_applicable').value==='1', override=f.querySelector('#vat_rate');
        const rate=vat?Number(override?.value || category?.dataset.rate || 0):0, amount=Number(f.querySelector('#taxable_amount').value || 0), tax=Math.round(amount*rate)/100;
        f.querySelector('[data-se-total]').textContent=`VAT ${rate}%: SAR ${tax.toFixed(2)} · Total: SAR ${(amount+tax).toFixed(2)} (server recalculates)`;
    }
    f.addEventListener('input',sync); f.addEventListener('change',sync); sync();
});
</script>
@endsection
