@extends('layouts.admin')
@section('title', $expense->expense_number)
@section('breadcrumb', 'Site Expenses / View')
@section('content')
<x-admin.page-header :title="$expense->expense_number" :description="$expense->project?->name.' / '.$expense->site?->name">
    <a class="btn outline" href="{{ route('admin.site-expenses.index') }}">Back to expenses</a>
    @if(app(\App\Services\Approvals\SiteExpenseApprovalSubject::class)->canSubmit($expense, auth()->user()))<a class="btn primary" href="{{ route('admin.site-expenses.edit',$expense) }}">Edit</a>@endif
</x-admin.page-header>
<div class="card" style="padding:20px"><p>{{ $expense->expense_date->format('Y-m-d') }} · {{ $expense->submitter?->name }} · SAR {{ $expense->total_amount }}</p><p>Approval / document: <strong>{{ str_replace('_',' ',$expense->status) }}</strong> · Accounting: <strong>{{ $expense->accounting_posted ? 'Posted' : 'Not posted' }}</strong></p><nav><a href="#details">Details</a> · <a href="#receipts">Receipts</a> · <a href="#approvals">Approval history</a> · <a href="#accounting">Accounting</a> · <a href="#activity">Activity</a></nav></div>
<section class="card" id="details" style="padding:20px"><h2>Expense details</h2><p>{{ $expense->description }}</p><dl><dt>Category</dt><dd>{{ $expense->category?->name }}</dd><dt>Payment type</dt><dd>{{ $expense->payment_type }}</dd><dt>Supplier</dt><dd>{{ $expense->supplier?->name ?? '—' }}</dd><dt>Amount before VAT / VAT / Total</dt><dd>SAR {{ $expense->taxable_amount }} / {{ $expense->vat_amount }} ({{ $expense->vat_rate }}%) / {{ $expense->total_amount }}</dd><dt>Reference</dt><dd>{{ $expense->reference_number ?? '—' }}</dd></dl><p>{{ $expense->notes }}</p></section>
<section class="card" id="receipts" style="padding:20px"><h2>Receipts / invoices</h2>@forelse($expense->receipts as $file)<p>{{ $file->original_filename }} · {{ $file->created_at->format('Y-m-d H:i') }} <a class="btn sm outline" target="_blank" rel="noopener" href="{{ route('admin.site-expenses.receipt-file',[$expense,$file->id]) }}">View</a> <a class="btn sm outline" href="{{ route('admin.site-expenses.receipt-file',[$expense,$file->id,'download'=>1]) }}">Download</a></p>@empty<p>No receipt attached.</p>@endforelse</section>
<section class="card" id="approvals" style="padding:20px"><h2>Approval history</h2>
@if($expense->rejection_reason)<div class="alert">Rejected: {{ $expense->rejection_reason }}</div>@endif
@if($subject->canSubmit($expense,auth()->user()))
<form method="POST" action="{{ route('admin.site-expenses.submit',$expense) }}">@csrf
    @if($expense->status === 'rejected')<input type="hidden" name="previous_instance_id" value="{{ $history->first()?->id }}">@endif
    <label for="workflow_id">Approval workflow</label><select name="workflow_id" id="workflow_id" class="select" required><option value="">Select workflow</option>@foreach($workflows as $workflow)<option value="{{ $workflow->id }}" @selected($workflows->count()===1)>{{ $workflow->name }}</option>@endforeach</select>
    @if($workflows->isEmpty())<p>No approval workflow is configured for Site Expenses. Please contact an administrator.</p>@endif
    <button class="btn primary">{{ $expense->status === 'rejected' ? 'Resubmit for approval' : 'Submit for approval' }}</button>
</form>@endif
@forelse($history as $instance)<h3>Attempt {{ $instance->attempt }} — {{ $instance->status }}</h3>
@foreach($instance->steps as $step)<p>Step {{ $step->step_no }}: {{ $step->snapshot['role_name'] ?? 'Reviewer' }} — {{ $step->status }} @if($step->comment !== null) · {{ $step->comment }} @endif</p>
@foreach(['approve','reject'] as $action)@if($runtime->eligible($subject,$expense,$instance,$step,auth()->user(),$action) && $instance->currentStep()?->id === $step->id)
<form method="POST" action="{{ route('admin.site-expenses.'.$action,$expense) }}">@csrf<input type="hidden" name="instance_id" value="{{ $instance->id }}"><input type="hidden" name="step_id" value="{{ $step->id }}"><label for="{{ $action }}-{{ $step->id }}">{{ $action==='reject' ? 'Rejection reason (required)' : 'Approval comment' }}</label><input class="input" name="comment" id="{{ $action }}-{{ $step->id }}" maxlength="1000" @required($action==='reject')><button class="btn {{ $action==='approve' ? 'primary' : 'outline' }}">{{ ucfirst($action) }}</button></form>
@endif @endforeach @endforeach
@empty<p>Not yet submitted. All configured required steps must approve.</p>@endforelse
</section>
<section class="card" id="accounting" style="padding:20px"><h2>Accounting</h2>
@if($expense->status === 'approved_pending_posting')<p>Approved; accounting is awaiting Finance. Your approval history is safe.</p>@endif
@if($expense->payment_type === 'Supplier Credit')<p>Site Expense approval creates one draft Supplier Bill. Its separate required bill approvals must complete before accounting; no separate Site Expense AP journal is created.</p>@endif
@if($expense->payment_type === 'Employee Reimbursement')<p>Employee-paid expense is recorded as reimbursement payable, not a Cash/Bank payment. Settlement: {{ $expense->settlement_journal_id ? 'Reimbursed' : 'Not reimbursed' }}.</p>@endif
@if($finance)
    @if($expense->settlementJournal && auth()->user()->hasPermission('Journal Entries','view'))<p><a href="{{ route('admin.accounting.journal-entries.show',$expense->settlementJournal) }}">Reimbursement journal {{ $expense->settlementJournal->journal_number }}</a></p>@endif
    @if($expense->reversalJournal && auth()->user()->hasPermission('Journal Entries','view'))<p><a href="{{ route('admin.accounting.journal-entries.show',$expense->reversalJournal) }}">Reversal journal {{ $expense->reversalJournal->journal_number }}</a> — {{ $expense->reversal_reason }}</p>@endif
    @if($expense->status==='posted' && !$expense->settlement_journal_id && auth()->user()->hasPermission('Site Expenses','post') && auth()->user()->hasPermission('Site Expenses','process'))
        @if($expense->payment_type==='Employee Reimbursement')<form method="POST" action="{{ route('admin.site-expenses.settle',$expense) }}">@csrf<label for="settlement_account">Reimburse employee from</label><select class="select" name="payment_account_id" id="settlement_account" required><option value="">Select Cash / Bank account</option>@foreach($settlementAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select><button class="btn primary">Record full reimbursement</button></form>@endif
        @if(!$expense->supplier_bill_id)<form method="POST" action="{{ route('admin.site-expenses.reverse',$expense) }}">@csrf<label for="reversal_reason">Reason for reversal (required)</label><input class="input" name="reason" id="reversal_reason" required maxlength="500"><button class="btn outline">Reverse expense</button></form><p>Posts an opposite journal and retains the original. Sealed VAT periods and settled reimbursements cannot be reversed here.</p>@endif
    @endif
    @if($expense->posting_error)<div class="alert">{{ $expense->posting_error }}</div>@endif
    @if($expense->journalEntry && auth()->user()->hasPermission('Journal Entries','view'))<p><a href="{{ route('admin.accounting.journal-entries.show',$expense->journalEntry) }}">Journal {{ $expense->journalEntry->journal_number }}</a> — {{ $expense->journalEntry->status }}</p>@endif
    @if($expense->supplierBill && auth()->user()->hasPermission('Accounts Payable','view'))
        <p><a href="{{ route('admin.accounting.accounts-payable.show',$expense->supplierBill) }}">Supplier Bill {{ $expense->supplierBill->bill_number }}</a> — {{ $expense->supplierBill->approvalLabel() }} / {{ $expense->supplierBill->status }}</p>
        @if(app(\App\Services\Approvals\SupplierBillApprovalSubject::class)->canSubmit($expense->supplierBill, auth()->user()))<a class="btn outline" href="{{ route('admin.accounting.accounts-payable.show',$expense->supplierBill) }}#approvals">Review and submit Bill for Approval</a>@endif
    @endif
    @if($expense->status==='approved_pending_posting' && auth()->user()->hasPermission('Site Expenses','post') && auth()->user()->hasPermission('Site Expenses','retry'))<form method="POST" action="{{ route('admin.site-expenses.retry',$expense) }}">@csrf<button class="btn primary">Retry accounting</button></form><p>If a review-mode journal exists, post that journal in Finance; retry does not create another.</p>@endif
@else<p>Accounting details are available to authorized Finance users.</p>@endif
</section>
<section class="card" id="activity" style="padding:20px"><h2>Activity</h2>@forelse($activity as $event)<p>{{ $event->created_at->format('Y-m-d H:i') }} · {{ $event->action }}@if($finance) — {{ $event->description }}@endif</p>@empty<p>No visible activity.</p>@endforelse</section>
@if($expense->status==='draft' && auth()->user()->hasPermission('Site Expenses','delete'))<form method="POST" action="{{ route('admin.site-expenses.destroy',$expense) }}">@csrf @method('DELETE')<button class="btn outline">Cancel draft (retain history)</button></form>@endif
@endsection
