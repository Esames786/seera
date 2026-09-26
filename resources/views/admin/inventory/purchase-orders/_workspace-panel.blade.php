@php
    /** @var \App\Models\PurchaseOrder $order */
    $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp
<div class="card" style="padding:18px" data-panel-content id="{{ $panel }}">
    <h2>{{ __($title) }} — {{ $order->po_number }}</h2>
    <div role="status" aria-live="polite" data-panel-status></div>

    @switch($panel)
        @case('goods-receipts')
            <p class="small">Every goods receipt recorded against this order. Received quantities only count once the receipt is posted.</p>
            <div class="table-wrap"><table>
                <thead><tr><th>GRN</th><th>Date</th><th>Warehouse</th><th>Received by</th><th>Accepted qty</th><th>Value (excl. VAT)</th><th>Stock</th><th>Status</th><th>Invoicing</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $grn)
                        @php
                            $accepted = (float) $grn->lines->sum('accepted_quantity');
                            $invoiced = (float) $grn->lines->sum('invoiced_quantity');
                            $uninvoiced = max($accepted - $invoiced, 0);
                        @endphp
                        <tr>
                            <td>@if ($canViewReceipts)<a href="{{ route('admin.inventory.goods-receipts.show', $grn) }}" style="font-weight:700">{{ $grn->grn_number }}</a>@else{{ $grn->grn_number }}@endif</td>
                            <td>{{ $grn->received_date?->toDateString() }}</td>
                            <td>{{ $grn->warehouse?->name ?? '-' }}</td>
                            <td>{{ $grn->receiver?->name ?? '-' }}</td>
                            <td>{{ $qty($accepted) }}</td>
                            <td>{{ $money($grn->taxable_amount) }}</td>
                            <td><x-admin.status-badge :status="$grn->stock_updated ? 'posted' : 'pending'"/></td>
                            <td><x-admin.status-badge :status="$grn->status"/></td>
                            <td>
                                @if ($grn->status !== 'posted') <span class="small">Not posted yet</span>
                                @elseif ($uninvoiced <= 0.0005) <span class="badge green">Invoiced</span>
                                @elseif ($invoiced > 0) <span class="badge yellow">{{ $qty($uninvoiced) }} received but not invoiced</span>
                                @else <span class="badge yellow">Received but not invoiced</span>
                                @endif
                            </td>
                            <td>
                                @if ($canViewReceipts)<a class="btn sm outline" href="{{ route('admin.inventory.goods-receipts.show', $grn) }}">View GRN</a>@endif
                                @if ($canViewJournals && $grn->journalEntry)<a class="btn sm outline" href="{{ route('admin.accounting.journal-entries.show', $grn->journalEntry) }}">{{ $grn->journalEntry->journal_number }}</a>@endif
                                @if ($canCreateBill && $grn->status === 'posted' && $uninvoiced > 0.0005)
                                    <a class="btn sm primary" href="{{ route('admin.accounting.accounts-payable.create', ['goods_receipt' => $grn->id, 'return_to' => route('admin.inventory.purchase-orders.show', $order, false).'#billing']) }}">Create Supplier Bill</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="table-empty">Nothing received against this order yet.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('billing')
            <p class="small">Per line: what was ordered, what arrived, what the supplier has invoiced (approved bills matched to the goods receipts) and what is still open. "Received but not invoiced" is the goods-received-not-invoiced accrual (account {{ \App\Services\Accounting\PostingService::GRNI }}) that clears when the bill is approved.</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Still to receive</th><th>Invoiced</th><th>Received but not invoiced</th></tr></thead>
                <tbody>
                    @foreach ($matrix as $row)
                        <tr>
                            <td>{{ $row['line']->item?->label() ?? '-' }} <span class="small">{{ $row['line']->item?->unit?->code }}</span></td>
                            <td>{{ $qty($row['ordered']) }}</td>
                            <td>{{ $qty($row['received']) }}</td>
                            <td>{{ $qty($row['outstanding']) }}</td>
                            <td>{{ $qty($row['invoiced']) }}</td>
                            <td>@if ($row['uninvoiced'] > 0.0005)<strong>{{ $qty($row['uninvoiced']) }}</strong>@else{{ $qty(0) }}@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>

            <h3 style="margin-top:18px">Supplier bills for this order</h3>
            <p class="small">Bills are linked to an order through their goods receipt matches. A bill entered without a goods receipt match does not appear here; find it under Accounts Payable.</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Bill</th><th>Date</th><th>Due</th><th>Matched GRNs</th><th>Total</th><th>Paid</th><th>Outstanding payment</th><th>Status</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $bill)
                        <tr>
                            <td><a href="{{ route('admin.accounting.accounts-payable.show', $bill) }}" style="font-weight:700">{{ $bill->bill_number }}</a></td>
                            <td>{{ $bill->bill_date?->toDateString() }}</td>
                            <td>{{ $bill->due_date?->toDateString() ?? '-' }}</td>
                            <td>
                                @foreach ($bill->grnMatches->pluck('goodsReceipt')->filter()->unique('id') as $matchedGrn)
                                    @if ($canViewReceipts)<a href="{{ route('admin.inventory.goods-receipts.show', $matchedGrn) }}">{{ $matchedGrn->grn_number }}</a>@else{{ $matchedGrn->grn_number }}@endif
                                @endforeach
                            </td>
                            <td>{{ $money($bill->total_amount) }}</td>
                            <td>{{ $money($bill->paid_amount) }}</td>
                            <td>{{ $money($bill->balance_amount) }}</td>
                            <td><x-admin.status-badge :status="$bill->status"/></td>
                            <td>
                                <a class="btn sm outline" href="{{ route('admin.accounting.accounts-payable.show', $bill) }}">View bill</a>
                                @if ($canEditBill && $bill->isEditable())<a class="btn sm outline" href="{{ route('admin.accounting.accounts-payable.edit', ['accounts_payable' => $bill, 'return_to' => $returnTo]) }}">Edit draft</a>@endif
                                @if ($canPay && in_array($bill->status, ['unpaid', 'partially_paid'], true))<a class="btn sm primary" href="{{ route('admin.accounting.accounts-payable.payment', ['accounts_payable' => $bill, 'return_to' => $returnTo]) }}">Record Payment</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="table-empty">No supplier bill has been matched to this order's goods receipts yet.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('accounting')
            <p class="small">Journal entries posted by this order's goods receipts, the supplier bills matched to them and their payments. Posting a goods receipt debits Inventory and credits Goods Received Not Invoiced ({{ $grniAccount }}); approving the matched bill clears that accrual and records the supplier payable and input VAT. View only: journals are managed under Accounting.</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Journal</th><th>Date</th><th>Source</th><th>Description</th><th>Debit</th><th>Credit</th><th>Status</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $entry)
                        <tr>
                            <td><a href="{{ route('admin.accounting.journal-entries.show', $entry) }}" style="font-weight:700">{{ $entry->journal_number }}</a></td>
                            <td>{{ $entry->journal_date?->toDateString() }}</td>
                            <td>{{ $entry->source_module }}</td>
                            <td class="small">{{ $entry->description }}</td>
                            <td>{{ number_format($entry->total_debit, 2) }}</td>
                            <td>{{ number_format($entry->total_credit, 2) }}</td>
                            <td><x-admin.status-badge :status="$entry->status"/></td>
                            <td><a class="btn sm outline" href="{{ route('admin.accounting.journal-entries.show', $entry) }}">View journal</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No accounting entry for this order yet. Entries appear once a goods receipt is posted.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('activity')
            <div class="table-wrap"><table>
                <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($rows as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $log->user_name }}</td>
                            <td>{{ $log->module }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">{{ __('No activity recorded for this order yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break
    @endswitch

    <div class="form-actions">
        <span class="small">{{ __('Showing the latest :count of :total', ['count' => $rows->count(), 'total' => $rows->total()]) }}</span>
        @if ($rows->hasPages())
            @if ($inline)
                {{ $rows->links() }}
            @else
                @if ($rows->previousPageUrl())<button type="button" class="btn sm outline" data-panel-load="{{ route('admin.inventory.purchase-orders.workspace.panel', [$order, $panel, 'page' => $rows->currentPage() - 1]) }}">{{ __('Previous') }}</button>@endif
                @if ($rows->hasMorePages())<button type="button" class="btn sm outline" data-panel-load="{{ route('admin.inventory.purchase-orders.workspace.panel', [$order, $panel, 'page' => $rows->currentPage() + 1]) }}">{{ __('Next') }}</button>@endif
            @endif
        @endif
        @if ($viewAll)<a class="btn sm outline" href="{{ $viewAll }}">{{ __('View all') }}</a>@endif
    </div>
</div>
