@extends('layouts.admin')

@section('title', 'Accounts Payable')
@section('breadcrumb', 'Accounting / Accounts Payable')

@section('content')
    <x-admin.page-header title="Accounts Payable" description="Supplier bills, input VAT and payments against the payable account">
        <a class="btn primary" href="{{ route('admin.accounting.accounts-payable.create') }}">+ Add Supplier Bill</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="red" :value="'SAR '.number_format($totalPayable, 2)" label="Outstanding Payable"/>
        <x-admin.metric-card color="yellow" :value="$overdueCount" label="Overdue Bills"/>
        <x-admin.metric-card color="blue" :value="$draftCount" label="Draft Bills"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($paidThisMonth, 2)" label="Paid This Month"/>
    </div>

    <x-admin.filter-bar>
        <input class="input" style="width:220px" type="search" name="search" value="{{ request('search') }}" placeholder="Bill number or supplier..."/>
        <select class="select" style="width:200px" name="supplier">
            <option value="">All Suppliers</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(request('supplier') == $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:160px" name="status">
            <option value="">All Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="Supplier Bills">
        <thead>
            <tr>
                <th>Bill Number</th><th>Supplier</th><th>Bill Date</th><th>Due Date</th>
                <th>Taxable</th><th>VAT</th><th>Total</th><th>Paid</th><th>Balance</th>
                <th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bills as $bill)
                <tr>
                    <td><a href="{{ route('admin.accounting.accounts-payable.show', $bill) }}" style="color:var(--blue);font-weight:700">{{ $bill->bill_number }}</a></td>
                    <td>{{ $bill->supplier->name }}</td>
                    <td>{{ $bill->bill_date->toDateString() }}</td>
                    <td>
                        {{ $bill->due_date?->toDateString() ?? '-' }}
                        @if ($bill->due_date && $bill->due_date->isPast() && in_array($bill->status, ['unpaid', 'partially_paid']))
                            <span class="badge red">Overdue</span>
                        @endif
                    </td>
                    <td>{{ number_format($bill->taxable_amount, 2) }}</td>
                    <td>{{ number_format($bill->vat_amount, 2) }}</td>
                    <td>{{ number_format($bill->total_amount, 2) }}</td>
                    <td>{{ number_format($bill->paid_amount, 2) }}</td>
                    <td><strong>{{ number_format($bill->balance_amount, 2) }}</strong></td>
                    <td><x-admin.status-badge :status="$bill->status"/></td>
                    <td>
                        <x-admin.action-buttons
                            :view="route('admin.accounting.accounts-payable.show', $bill)"
                            :edit="$bill->isEditable() ? route('admin.accounting.accounts-payable.edit', $bill) : null"
                            :delete="$bill->isEditable() ? route('admin.accounting.accounts-payable.destroy', $bill) : null"
                            :name="$bill->bill_number">
                            @if ($bill->status === 'draft')
                                <form method="POST" action="{{ route('admin.accounting.accounts-payable.approve', $bill) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Approve</button>
                                </form>
                            @elseif (in_array($bill->status, ['unpaid', 'partially_paid']))
                                <a class="btn sm warning" href="{{ route('admin.accounting.accounts-payable.payment', $bill) }}">Pay</a>
                            @endif
                        </x-admin.action-buttons>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No supplier bills match the selected filters.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $bills->firstItem() ?? 0 }}-{{ $bills->lastItem() ?? 0 }} of {{ $bills->total() }}</span>
            {{ $bills->links() }}
        </x-slot:footer>
    </x-admin.data-table>

    <x-admin.data-table title="Recent Supplier Payments">
        <thead>
            <tr><th>Date</th><th>Supplier</th><th>Bill</th><th>Amount</th><th>Reference</th></tr>
        </thead>
        <tbody>
            @forelse ($recentPayments as $payment)
                <tr>
                    <td>{{ $payment->payment_date->toDateString() }}</td>
                    <td>{{ $payment->supplier->name }}</td>
                    <td>{{ $payment->bill?->bill_number ?? '-' }}</td>
                    <td>SAR {{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->reference_number ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="table-empty">No payments recorded yet.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <div class="note">
        Approving a bill posts: debit expense/inventory account and input VAT, credit accounts payable. Recording a payment posts: debit accounts payable, credit cash/bank.
    </div>
@endsection
