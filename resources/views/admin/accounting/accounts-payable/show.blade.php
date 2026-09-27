@extends('layouts.admin')

@section('title', 'Supplier Bill')
@section('breadcrumb', 'Accounting / Accounts Payable / Supplier Bill')

@section('content')
    @php
        /** @var \App\Models\SupplierBill $bill */
        $user = auth()->user();
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        $selfUrl = route('admin.accounting.accounts-payable.show', $bill, false);
        $origin = $returnTo ?? $selfUrl;
        $canEdit = $bill->isEditable() && $user->hasPermission('Accounts Payable', 'edit');
        $canApprove = $bill->status === 'draft' && $user->hasPermission('Accounts Payable', 'approve');
        $canPay = in_array($bill->status, ['unpaid', 'partially_paid'], true) && $user->hasPermission('Accounts Payable', 'process');
        $canReopen = $bill->status === 'unpaid' && $bill->payments->isEmpty() && $user->isSuperAdmin();
        $canViewSupplier = $user->hasPermission('Suppliers', 'view');
        $matches = $bill->lines->map(fn ($line) => $line->grnMatch)->filter()->values();
        $matchedReceipts = $matches->pluck('goodsReceipt')->filter()->unique('id')->values();
        $matchedOrders = $matchedReceipts->pluck('purchaseOrder')->filter()->unique('id')->values();
        $paymentState = match ($bill->status) {
            'paid' => 'Paid in full',
            'partially_paid' => 'Partly paid',
            'unpaid' => $bill->due_date && $bill->due_date->isPast() ? 'Overdue' : 'Awaiting payment',
            'draft' => 'Draft, not posted',
            default => ucfirst(str_replace('_', ' ', $bill->status)),
        };
        $sections = ['information' => 'Bill Info', 'lines' => 'Lines', 'grn-matches' => 'GRN Matches', 'vat' => 'VAT'];
        if ($canViewJournals) $sections['accounting'] = 'Accounting Entry';
        $sections += ['payments' => 'Payments', 'balance' => 'Balance'];
        if ($activity !== null) $sections['activity'] = 'Activity';
    @endphp

    <x-admin.page-header :title="'Bill: '.$bill->bill_number" :description="$bill->supplier->name.' · '.$bill->bill_date->toDateString()">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.edit', ['accounts_payable' => $bill, 'return_to' => $returnTo]) }}">Edit</a>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('admin.accounting.accounts-payable.approve', $bill) }}">
                @csrf
                <button type="submit" class="btn primary">Approve &amp; Post</button>
            </form>
        @endif
        @if ($canPay)
            <a class="btn primary" href="{{ route('admin.accounting.accounts-payable.payment', ['accounts_payable' => $bill, 'return_to' => $returnTo ?? $selfUrl]) }}">Record Payment</a>
        @endif
        @if ($canReopen)
            <form method="POST" action="{{ route('admin.accounting.accounts-payable.reopen', $bill) }}" onsubmit="var reason = prompt('Reason for reopening this bill (kept on the record):'); if (!reason) return false; this.reason.value = reason;">
                @csrf
                <input type="hidden" name="reason" value=""/>
                <button type="submit" class="btn danger" title="Reverse the posting and return the bill to draft for correction">Reopen for Correction</button>
            </form>
        @endif
        @if ($returnTo)
            <a class="btn outline" href="{{ $returnTo }}">Back</a>
        @else
            <a class="btn outline" href="{{ route('admin.accounting.accounts-payable.index') }}">Back to Accounts Payable</a>
        @endif
    </x-admin.page-header>

    {{-- Persistent bill identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $bill->bill_number }}</h2>
            <div>
                <x-admin.status-badge :status="$bill->status"/>
                <span class="small">· {{ $paymentState }}</span>
            </div>
        </div>
        <dl>
            <dt>Supplier</dt>
            <dd>{{ $bill->supplier->name }} @if ($canViewSupplier)<a class="small" href="{{ route('admin.master.suppliers.show', $bill->supplier) }}">View</a>@endif</dd>
            <dt>Bill date / Due</dt><dd>{{ $bill->bill_date->toDateString() }} / {{ $bill->due_date?->toDateString() ?? '-' }}</dd>
            <dt>Project / Site</dt><dd>{{ $bill->project?->name ?? '-' }}{{ $bill->site ? ' / '.$bill->site->name : '' }}</dd>
            <dt>Matched receipts</dt>
            <dd>
                @forelse ($matchedReceipts as $receipt)
                    @if ($canViewReceipts)<a href="{{ route('admin.inventory.goods-receipts.show', $receipt) }}">{{ $receipt->grn_number }}</a>@else{{ $receipt->grn_number }}@endif
                @empty
                    None (direct / service bill)
                @endforelse
                @foreach ($matchedOrders as $matchedOrder)
                    <span class="small">· @if ($canViewOrders)<a href="{{ route('admin.inventory.purchase-orders.show', $matchedOrder) }}#billing">{{ $matchedOrder->po_number }}</a>@else{{ $matchedOrder->po_number }}@endif</span>
                @endforeach
            </dd>
        </dl>
        <dl>
            <dt>Total</dt><dd>SAR {{ number_format($bill->total_amount, 2) }}</dd>
            <dt>Paid</dt><dd>SAR {{ number_format($bill->paid_amount, 2) }}</dd>
            <dt>Outstanding payment</dt><dd>SAR {{ number_format($bill->balance_amount, 2) }}</dd>
            <dt>Accounting</dt><dd>@if ($bill->journalEntry) Posted @if($canViewJournals)· <a href="{{ route('admin.accounting.journal-entries.show', $bill->journalEntry) }}">{{ $bill->journalEntry->journal_number }}</a>@endif @else Not posted yet @endif</dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="Supplier bill sections">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card-grid" id="information">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($bill->taxable_amount, 2)" label="Taxable Amount"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($bill->vat_amount, 2)" label="Input VAT"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($bill->total_amount, 2)" label="Total Amount"/>
        <x-admin.metric-card color="red" :value="'SAR '.number_format($bill->balance_amount, 2)" label="Outstanding Balance"/>
    </div>

    <x-admin.data-table title="Bill Information" class="detail-table">
        <tbody>
            <tr><th>Supplier</th><td>{{ $bill->supplier->name }}</td></tr>
            <tr><th>Bill Number</th><td>{{ $bill->bill_number }}</td></tr>
            <tr><th>Reference</th><td>{{ $bill->reference_number ?? '-' }}</td></tr>
            <tr><th>Bill Date</th><td>{{ $bill->bill_date->toDateString() }}</td></tr>
            <tr><th>Due Date</th><td>{{ $bill->due_date?->toDateString() ?? '-' }}</td></tr>
            <tr><th>Project / Site</th><td>{{ $bill->project?->name ?? '-' }}@if($bill->site) / {{ $bill->site->name }}@endif</td></tr>
            <tr><th>Cost Center</th><td>{{ $bill->costCenter ? $bill->costCenter->code.' - '.$bill->costCenter->name : '-' }}</td></tr>
            <tr><th>VAT Rate</th><td>{{ number_format($bill->vat_rate, 2) }}%</td></tr>
            <tr><th>Paid Amount</th><td>SAR {{ number_format($bill->paid_amount, 2) }}</td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$bill->status"/> <span class="small">{{ $paymentState }}</span></td></tr>
            <tr><th>Notes</th><td>{{ $bill->notes ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Bill Lines" id="lines">
        <thead>
            <tr><th>Description</th><th>Received Goods</th><th>Category</th><th>Account</th><th>Qty</th><th>Unit Price</th><th>Taxable</th><th>VAT</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse ($bill->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>
                        @if ($line->grnMatch)
                            @if ($canViewReceipts)<a href="{{ route('admin.inventory.goods-receipts.show', $line->grnMatch->goods_receipt_id) }}">{{ $line->grnMatch->goodsReceipt?->grn_number }}</a>@else{{ $line->grnMatch->goodsReceipt?->grn_number }}@endif
                            × {{ $qty($line->grnMatch->matched_quantity) }}
                            (accrued SAR {{ number_format($line->grnMatch->matched_taxable_amount, 2) }})
                        @else
                            <span class="small">Direct / service</span>
                        @endif
                    </td>
                    <td>{{ $line->expenseCategory?->name ?? '-' }}</td>
                    <td>{{ $line->account?->label() ?? '-' }}</td>
                    <td>{{ number_format($line->quantity, 2) }}</td>
                    <td>{{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($line->total_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No lines on this bill.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="GRN Matches" subtitle="Goods receipt lines this bill invoices (F04). The accrued amount is what the receipt posted to Goods Received Not Invoiced; any difference to the billed amount is posted as a price variance when the bill is approved." id="grn-matches">
        <thead>
            <tr><th>Goods receipt</th><th>Purchase order</th><th>Item</th><th>Matched qty</th><th>Accrued amount</th><th>Billed amount</th><th>Variance</th><th>Match state</th></tr>
        </thead>
        <tbody>
            @forelse ($bill->lines->filter(fn ($line) => $line->grnMatch) as $line)
                @php $match = $line->grnMatch; $variance = round((float) $line->taxable_amount - (float) $match->matched_taxable_amount, 2); @endphp
                <tr>
                    <td>@if ($canViewReceipts && $match->goodsReceipt)<a href="{{ route('admin.inventory.goods-receipts.show', $match->goodsReceipt) }}" style="font-weight:700">{{ $match->goodsReceipt->grn_number }}</a>@else{{ $match->goodsReceipt?->grn_number ?? '-' }}@endif</td>
                    <td>@if ($canViewOrders && $match->goodsReceipt?->purchaseOrder)<a href="{{ route('admin.inventory.purchase-orders.show', $match->goodsReceipt->purchaseOrder) }}#billing">{{ $match->goodsReceipt->purchaseOrder->po_number }}</a>@else{{ $match->goodsReceipt?->purchaseOrder?->po_number ?? '-' }}@endif</td>
                    <td>{{ $match->goodsReceiptLine?->item?->label() ?? $line->description }}</td>
                    <td>{{ $qty($match->matched_quantity) }}</td>
                    <td>SAR {{ number_format($match->matched_taxable_amount, 2) }}</td>
                    <td>SAR {{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ $variance == 0.0 ? '-' : 'SAR '.number_format($variance, 2) }}</td>
                    <td>{{ $match->committed_at ? 'Invoiced (bill approved)' : 'Provisional (bill still draft)' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">No goods receipt is matched to this bill. It is a direct or service bill.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="VAT" class="detail-table" id="vat">
        <tbody>
            <tr><th>VAT Rate</th><td>{{ number_format($bill->vat_rate, 2) }}%</td></tr>
            <tr><th>Taxable Amount</th><td>SAR {{ number_format($bill->taxable_amount, 2) }}</td></tr>
            <tr><th>Input VAT</th><td>SAR {{ number_format($bill->vat_amount, 2) }}</td></tr>
            <tr><th>Total incl. VAT</th><td>SAR {{ number_format($bill->total_amount, 2) }}</td></tr>
            <tr><th>Recorded in VAT ledger</th><td>{{ $bill->journalEntry ? 'Yes, when the bill was approved and posted' : 'Not yet; input VAT is recorded when the bill is approved' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    @if ($canViewJournals)
        <x-admin.data-table title="Accounting Entry" id="accounting">
            @if ($bill->journalEntry)
                <thead>
                    <tr><th>Account</th><th>Debit</th><th>Credit</th></tr>
                </thead>
                <tbody>
                    @foreach ($bill->journalEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account->label() }}</td>
                            <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                            <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <x-slot:footer>
                    <span class="small">Journal</span>
                    <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $bill->journalEntry) }}">{{ $bill->journalEntry->journal_number }}</a>
                </x-slot:footer>
            @else
                <tbody>
                    <tr><td class="table-empty">No accounting entry yet. Approve the bill to post it.</td></tr>
                </tbody>
            @endif
        </x-admin.data-table>
    @endif

    <x-admin.data-table title="Payments" id="payments">
        <thead>
            <tr><th>Date</th><th>Account</th><th>Method</th><th>Purpose</th><th>Amount</th><th>Reference</th>@if($canViewJournals)<th>Journal</th>@endif</tr>
        </thead>
        <tbody>
            @forelse ($bill->payments as $payment)
                <tr>
                    <td>{{ $payment->payment_date->toDateString() }}</td>
                    <td>{{ $payment->paymentAccount?->label() ?? '-' }}</td>
                    <td>{{ $payment->payment_method ?? '-' }}</td>
                    <td>{{ $payment->purpose ?? 'Bill payment' }}</td>
                    <td>SAR {{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->reference_number ?? '-' }}</td>
                    @if ($canViewJournals)<td>@if ($payment->journalEntry)<a href="{{ route('admin.accounting.journal-entries.show', $payment->journalEntry) }}">{{ $payment->journalEntry->journal_number }}</a>@else - @endif</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $canViewJournals ? 7 : 6 }}" class="table-empty">No payments recorded against this bill.</td></tr>
            @endforelse
        </tbody>
        @if ($canPay)
            <x-slot:footer>
                <a class="btn sm primary" href="{{ route('admin.accounting.accounts-payable.payment', ['accounts_payable' => $bill, 'return_to' => $returnTo ?? $selfUrl]) }}">Record Payment</a>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    <x-admin.data-table title="Balance" class="detail-table" id="balance">
        <tbody>
            <tr><th>Bill total</th><td>SAR {{ number_format($bill->total_amount, 2) }}</td></tr>
            <tr><th>Paid to date</th><td>SAR {{ number_format($bill->paid_amount, 2) }} <span class="small">({{ $bill->payments->count() }} payment{{ $bill->payments->count() === 1 ? '' : 's' }})</span></td></tr>
            <tr><th>Outstanding payment</th><td><strong>SAR {{ number_format($bill->balance_amount, 2) }}</strong></td></tr>
            <tr><th>Due date</th><td>{{ $bill->due_date?->toDateString() ?? '-' }}@if($bill->due_date && $bill->due_date->isPast() && (float) $bill->balance_amount > 0) <span class="badge red">Overdue {{ $bill->due_date->diffInDays(now()) }} days</span>@endif</td></tr>
            <tr><th>Payment state</th><td>{{ $paymentState }}</td></tr>
        </tbody>
    </x-admin.data-table>

    @if ($activity !== null)
        <x-admin.data-table title="Activity" subtitle="Latest entries that name this bill" id="activity">
            <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
                @forelse ($activity as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->user_name }}</td>
                        <td>{{ $log->module }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No activity recorded for this bill yet.</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <a class="btn sm outline" href="{{ route('admin.activity-logs.index', ['search' => $bill->bill_number]) }}">View all</a>
            </x-slot:footer>
        </x-admin.data-table>
    @endif
@endsection
