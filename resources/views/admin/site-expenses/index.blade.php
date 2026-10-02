@extends('layouts.admin')
@section('title', 'Site Expenses')
@section('breadcrumb', 'Site Expenses')
@section('content')
<x-admin.page-header title="Site Expenses" description="Receipts, approvals and accounting in one place.">
    @if(auth()->user()->hasPermission('Site Expenses', 'create'))<a class="btn primary" href="{{ route(auth()->user()->mobile_access ? 'admin.site-expenses.mobile' : 'admin.site-expenses.create') }}">Add Site Expense</a>@endif
</x-admin.page-header>
<form method="GET" class="card" style="padding:16px"><div class="form-grid">
    <div><label for="search">Search</label><input class="input" name="search" id="search" value="{{ request('search') }}" placeholder="Expense number / description"></div>
    @foreach(['project_id'=>['Project',$projects], 'site_id'=>['Site',$sites], 'expense_category_id'=>['Category',$categories], 'submitted_by_user_id'=>['Submitted by',$submitters]] as $field=>$config)<div><label for="{{ $field }}">{{ $config[0] }}</label><select class="select" name="{{ $field }}" id="{{ $field }}"><option value="">All permitted</option>@foreach($config[1] as $option)<option value="{{ $option->id }}" @selected(request($field)==$option->id)>{{ $option->name }}</option>@endforeach</select></div>@endforeach
    <div><label for="payment_type">Payment type</label><select class="select" name="payment_type" id="payment_type"><option value="">All</option>@foreach(\App\Models\SiteExpense::PAYMENT_TYPES as $type)<option @selected(request('payment_type')===$type)>{{ $type }}</option>@endforeach</select></div>
    <div><label for="status">Approval / document status</label><select class="select" name="status" id="status"><option value="">All</option>@foreach(\App\Models\SiteExpense::STATUSES as $state)<option value="{{ $state }}" @selected(request('status')===$state)>{{ str_replace('_',' ',ucfirst($state)) }}</option>@endforeach</select></div>
    <div><label for="accounting_posted">Accounting</label><select class="select" name="accounting_posted" id="accounting_posted"><option value="">All</option><option value="1" @selected(request('accounting_posted')==='1')>Posted</option><option value="0" @selected(request('accounting_posted')==='0')>Not posted</option></select></div>
    @foreach(['from','to'] as $date)<div><label for="{{ $date }}">{{ ucfirst($date) }}</label><input class="input" type="date" name="{{ $date }}" id="{{ $date }}" value="{{ request($date) }}"></div>@endforeach
</div><div class="form-actions"><button class="btn primary">Filter</button><a class="btn outline" href="{{ route('admin.site-expenses.index') }}">Reset</a></div></form>
<x-admin.data-table title="Expense register"><thead><tr><th>Expense / date</th><th>Project / site</th><th>Submitted by</th><th>Category / supplier</th><th>Payment</th><th>Amount / VAT / total (SAR)</th><th>Approval status</th><th>Accounting</th><th>Action</th></tr></thead><tbody>
@forelse($expenses as $expense)<tr><td>{{ $expense->expense_number }}<br>{{ $expense->expense_date->format('Y-m-d') }}</td><td>{{ $expense->project?->name }}<br>{{ $expense->site?->name }}</td><td>{{ $expense->submitter?->name }}</td><td>{{ $expense->category?->name }}<br>{{ $expense->supplier?->name ?? '—' }}</td><td>{{ $expense->payment_type }}</td><td>{{ $expense->taxable_amount }} / {{ $expense->vat_amount }} / {{ $expense->total_amount }}</td><td>{{ str_replace('_',' ',$expense->status) }}</td><td>{{ $expense->accounting_posted ? 'Posted' : 'Not posted' }}</td><td><a class="btn sm outline" href="{{ route('admin.site-expenses.show',$expense) }}">View</a>@if(app(\App\Services\Approvals\SiteExpenseApprovalSubject::class)->canSubmit($expense, auth()->user())) <a class="btn sm outline" href="{{ route('admin.site-expenses.edit',$expense) }}">Edit</a>@endif</td></tr>
@empty<tr><td colspan="9">No expenses match your access and filters.</td></tr>@endforelse
</tbody><x-slot:footer>{{ $expenses->links() }}</x-slot:footer></x-admin.data-table>
@endsection
