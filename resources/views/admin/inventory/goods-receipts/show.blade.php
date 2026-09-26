@extends('layouts.admin')

@section('title', 'Goods Receipt')
@section('breadcrumb', 'Inventory / Goods Receipt Notes / Goods Receipt')

@section('content')
    @php
        /** @var \App\Models\GoodsReceipt $grn */
        $user = auth()->user();
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        $accepted = (float) $grn->lines->sum('accepted_quantity');
        $invoiced = (float) $grn->lines->sum('invoiced_quantity');
        $uninvoiced = round(max($accepted - $invoiced, 0), 3);
        $invoicingState = match (true) {
            $grn->status !== 'posted' => 'Not posted yet',
            $uninvoiced <= 0.0005 => 'Invoiced',
            $invoiced > 0 => 'Partly invoiced',
            default => 'Received but not invoiced',
        };
        $selfUrl = route('admin.inventory.goods-receipts.show', $grn, false);
        $canEdit = $grn->isEditable() && $user->hasPermission('Goods Receipts', 'edit');
        $canPost = $grn->isEditable() && $user->hasPermission('Goods Receipts', 'post');
        $canCreateBill = $grn->status === 'posted' && $uninvoiced > 0.0005 && $user->hasPermission('Accounts Payable', 'create');
        $canViewOrder = $grn->purchaseOrder && $user->hasPermission('Purchase Orders', 'view');
        $canViewSupplier = $user->hasPermission('Suppliers', 'view');
        $canViewWarehouse = $user->hasPermission('Warehouses', 'view');
        $canViewJournal = $user->hasPermission('Journal Entries', 'view');
        $project = $grn->warehouse?->project ?? $grn->purchaseOrder?->project;
        $site = $grn->warehouse?->site ?? $grn->purchaseOrder?->site;
        $sections = ['information' => 'Receipt Information', 'lines' => 'Received Lines'];
        if ($matches !== null) $sections['bill-matches'] = 'Bill Matches';
        if ($canViewJournal) $sections['accounting'] = 'Accounting Entry';
        if ($activity !== null) $sections['activity'] = 'Activity';
    @endphp

    <x-admin.page-header :title="'Goods Receipt: '.$grn->grn_number" :description="$grn->supplier->name.' into '.$grn->warehouse->name">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.inventory.goods-receipts.edit', ['goods_receipt' => $grn, 'return_to' => $returnTo]) }}">Edit</a>
        @endif
        @if ($canPost)
            <form method="POST" action="{{ route('admin.inventory.goods-receipts.post-stock', $grn) }}">
                @csrf
                @if ($returnTo)<input type="hidden" name="{{ \App\Support\SaveAction::RETURN_FIELD }}" value="{{ $returnTo }}"/>@endif
                <button type="submit" class="btn primary">Post Stock</button>
            </form>
        @endif
        @if ($canCreateBill)
            <a class="btn primary" href="{{ route('admin.accounting.accounts-payable.create', ['goods_receipt' => $grn->id, 'return_to' => $returnTo ?? $selfUrl]) }}">Create Supplier Bill</a>
        @endif
        @if ($returnTo)
            <a class="btn outline" href="{{ $returnTo }}">Back</a>
        @elseif ($canViewOrder)
            <a class="btn outline" href="{{ route('admin.inventory.purchase-orders.show', $grn->purchaseOrder) }}#goods-receipts">Back to Purchase Order</a>
        @else
            <a class="btn outline" href="{{ route('admin.inventory.goods-receipts.index') }}">Back to Goods Receipts</a>
        @endif
    </x-admin.page-header>

    @if ($grn->status === 'posted')
        <div class="alert success flash">This goods receipt is posted and read-only. Stock and the ledger have been updated.</div>
    @endif

    {{-- Persistent receipt identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $grn->grn_number }}</h2>
            <div>
                <x-admin.status-badge :status="$grn->status"/>
                <span class="small">· received {{ $grn->received_date->toDateString() }}@if($grn->receiver) by {{ $grn->receiver->name }}@endif</span>
            </div>
        </div>
        <dl>
            <dt>Source purchase order</dt>
            <dd>@if ($canViewOrder)<a href="{{ route('admin.inventory.purchase-orders.show', $grn->purchaseOrder) }}">{{ $grn->purchaseOrder->po_number }}</a>@else{{ $grn->purchaseOrder?->po_number ?? 'Not linked' }}@endif</dd>
            <dt>Supplier</dt>
            <dd>{{ $grn->supplier->name }} @if ($canViewSupplier)<a class="small" href="{{ route('admin.master.suppliers.show', $grn->supplier) }}">View</a>@endif</dd>
            <dt>Warehouse</dt>
            <dd>@if ($canViewWarehouse)<a href="{{ route('admin.master.warehouses.show', $grn->warehouse) }}">{{ $grn->warehouse->name }}</a>@else{{ $grn->warehouse->name }}@endif</dd>
            <dt>Project / Site</dt>
            <dd>{{ $project?->name ?? '-' }}{{ $site ? ' / '.$site->name : '' }}</dd>
        </dl>
        <dl>
            <dt>Value (excl. VAT)</dt><dd>SAR {{ number_format($grn->taxable_amount, 2) }}</dd>
            <dt>Stock</dt><dd>{{ $grn->stock_updated ? 'Posted to stock' : 'Not posted yet' }}</dd>
            <dt>Accounting</dt><dd>{{ $grn->accounting_posted ? 'Posted' : 'Not posted yet' }}@if($grn->journalEntry && $canViewJournal) · <a href="{{ route('admin.accounting.journal-entries.show', $grn->journalEntry) }}">{{ $grn->journalEntry->journal_number }}</a>@endif</dd>
            <dt>Invoicing</dt><dd>{{ $invoicingState }}@if($grn->status === 'posted') <span class="small">· invoiced {{ $qty($invoiced) }}, received but not invoiced {{ $qty($uninvoiced) }}</span>@endif</dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="Goods receipt sections">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card-grid" id="information">
        <x-admin.metric-card color="blue" :value="'SAR '.number_format($grn->taxable_amount, 2)" label="Taxable Amount"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($grn->vat_amount, 2)" label="Expected VAT (recorded on the supplier bill)"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($grn->total_amount, 2)" label="Total Amount"/>
        <x-admin.metric-card :color="$grn->stock_updated ? 'green' : 'yellow'" :value="$grn->stock_updated ? 'Updated' : 'Pending'" label="Stock Status"/>
    </div>

    <x-admin.data-table title="Receipt Information" class="detail-table">
        <tbody>
            <tr><th>GRN Number</th><td>{{ $grn->grn_number }}</td></tr>
            <tr><th>Purchase Order</th><td>{{ $grn->purchaseOrder?->po_number ?? '-' }}</td></tr>
            <tr><th>Supplier</th><td>{{ $grn->supplier->name }}</td></tr>
            <tr><th>Warehouse</th><td>{{ $grn->warehouse->name }}</td></tr>
            <tr><th>Received Date</th><td>{{ $grn->received_date->toDateString() }}</td></tr>
            <tr><th>Received By</th><td>{{ $grn->receiver?->name ?? '-' }}</td></tr>
            <tr><th>Delivery Note</th><td>{{ $grn->delivery_note_number ?? '-' }}</td></tr>
            <tr><th>Supplier Invoice Number</th><td>{{ $grn->invoice_number ?? '-' }}</td></tr>
            <tr><th>VAT Rate</th><td>{{ number_format($grn->vat_rate, 2) }}%</td></tr>
            <tr><th>Stock Updated</th><td><x-admin.status-badge :status="$grn->stock_updated ? 'yes' : 'no'"/></td></tr>
            <tr><th>Accounting Posted</th><td><x-admin.status-badge :status="$grn->accounting_posted ? 'posted' : 'pending'"/></td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$grn->status"/></td></tr>
            <tr><th>Notes</th><td>{{ $grn->notes ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    <x-admin.data-table title="Received Lines" subtitle="Accepted quantity enters stock. Invoiced is the accepted quantity already covered by an approved supplier bill." id="lines">
        <thead>
            <tr><th>Item</th><th>Unit</th><th>Ordered</th><th>Received</th><th>Accepted</th><th>Rejected</th><th>Invoiced</th><th>Received but not invoiced</th><th>Unit Cost</th><th>Total Cost</th></tr>
        </thead>
        <tbody>
            @forelse ($grn->lines as $line)
                <tr>
                    <td>{{ $line->item->label() }}</td>
                    <td>{{ $line->item->unit?->code ?? '-' }}</td>
                    <td>{{ $qty($line->ordered_quantity) }}</td>
                    <td>{{ $qty($line->received_quantity) }}</td>
                    <td><strong>{{ $qty($line->accepted_quantity) }}</strong></td>
                    <td>{{ $qty($line->rejected_quantity) }}</td>
                    <td>{{ $qty($line->invoiced_quantity) }}</td>
                    <td>{{ $grn->status === 'posted' ? $qty($line->uninvoicedQuantity()) : '-' }}</td>
                    <td>SAR {{ number_format($line->unit_cost, 2) }}</td>
                    <td><strong>SAR {{ number_format($line->total_cost, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">No lines on this goods receipt.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    @if ($matches !== null)
        <x-admin.data-table title="Bill Matches" subtitle="Supplier bill lines matched to this receipt (F04). A draft bill holds a provisional match until it is approved." id="bill-matches">
            <thead>
                <tr><th>Bill</th><th>Bill date</th><th>Bill status</th><th>Item</th><th>Matched qty</th><th>Accrued amount</th><th>Match state</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($matches as $match)
                    <tr>
                        <td><a href="{{ route('admin.accounting.accounts-payable.show', $match->bill) }}" style="font-weight:700">{{ $match->bill?->bill_number }}</a></td>
                        <td>{{ $match->bill?->bill_date?->toDateString() }}</td>
                        <td><x-admin.status-badge :status="$match->bill?->status ?? 'draft'"/></td>
                        <td>{{ $match->goodsReceiptLine?->item?->label() ?? '-' }}</td>
                        <td>{{ $qty($match->matched_quantity) }}</td>
                        <td>SAR {{ number_format($match->matched_taxable_amount, 2) }}</td>
                        <td>{{ $match->committed_at ? 'Invoiced (bill approved)' : 'Provisional (bill still draft)' }}</td>
                        <td><a class="btn sm outline" href="{{ route('admin.accounting.accounts-payable.show', $match->bill) }}">View bill</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="table-empty">No supplier bill has been matched to this receipt yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    @endif

    @if ($canViewJournal)
        <x-admin.data-table title="Accounting Entry" subtitle="Posting a receipt debits Inventory and credits Goods Received Not Invoiced (2150); the matched supplier bill clears that accrual." id="accounting">
            @if ($grn->journalEntry)
                <thead>
                    <tr><th>Account</th><th>Debit</th><th>Credit</th></tr>
                </thead>
                <tbody>
                    @foreach ($grn->journalEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account->label() }}</td>
                            <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                            <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <x-slot:footer>
                    <span class="small">Journal</span>
                    <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $grn->journalEntry) }}">{{ $grn->journalEntry->journal_number }}</a>
                </x-slot:footer>
            @else
                <tbody>
                    <tr><td class="table-empty">No accounting entry yet. Post the receipt to create it.</td></tr>
                </tbody>
            @endif
        </x-admin.data-table>
    @endif

    @if ($activity !== null)
        <x-admin.data-table title="Activity" subtitle="Latest entries that name this receipt" id="activity">
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
                    <tr><td colspan="5" class="table-empty">No activity recorded for this receipt yet.</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <a class="btn sm outline" href="{{ route('admin.activity-logs.index', ['search' => $grn->grn_number]) }}">View all</a>
            </x-slot:footer>
        </x-admin.data-table>
    @endif
@endsection
