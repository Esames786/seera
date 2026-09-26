@php
    /** @var \App\Models\Supplier $supplier */
    $readonly = $readonly ?? false;
    $panelUrl = route('admin.master.suppliers.workspace.panel', [$supplier, $panel]);
    $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    $dims = fn ($project, $site) => ($project?->name ?? '-').($site ? ' / '.$site->name : '');
@endphp
<div class="card" style="padding:18px" data-panel-content id="{{ $panel }}">
    <h2>{{ __($title) }} — {{ $supplier->code }} / {{ $supplier->name }}</h2>
    <div role="status" aria-live="polite" data-panel-status></div>

    @switch($panel)
        @case('projects')
            @if ($canEdit)
                <form method="POST" action="{{ route('admin.master.suppliers.workspace.save', [$supplier, 'projects']) }}" data-related-save>
                    @csrf
                    <input type="hidden" name="supplier_id" value="{{ $supplier->id }}"/>
                    <div class="alert" data-related-errors role="alert" hidden></div>
                    <div class="form-grid three">
                        <div>
                            <label for="supplier-{{ $supplier->id }}-project">{{ __('Link a project') }}</label>
                            <select id="supplier-{{ $supplier->id }}-project" name="project_id" class="select" required>
                                <option value="">{{ __('Select...') }}</option>
                                @foreach ($assignable as $project)
                                    <option value="{{ $project->id }}">{{ $project->code }} — {{ $project->name }}</option>
                                @endforeach
                            </select>
                            <div class="field-error" data-field-error="project_id"></div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="_save_action" value="stay" class="btn primary" data-save-default>{{ __('Link project') }}</button>
                    </div>
                </form>
            @endif
            <div class="table-wrap"><table>
                <thead><tr><th>Code</th><th>Project</th><th>Client</th><th>Status</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $project)
                        <tr>
                            <td>{{ $project->code }}</td>
                            <td>{{ $project->name }}</td>
                            <td>{{ $project->customer?->name ?? '-' }}</td>
                            <td><x-admin.status-badge :status="$project->status"/></td>
                            <td>
                                @if ($canViewProjects)<a class="btn sm outline" href="{{ route('admin.master.projects.show', $project) }}">View project</a>@endif
                                @if ($canEdit)<button type="button" class="btn sm outline" data-related-action="{{ route('admin.master.suppliers.workspace.action', [$supplier, 'projects', $project->id, 'remove']) }}" data-action="remove">{{ __('Unlink') }}</button>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">{{ __('Not linked to any project yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('purchase-orders')
            <div class="table-wrap"><table>
                <thead><tr><th>PO</th><th>Date</th><th>Project / Site</th><th>Amount</th><th>Received</th><th>Status</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $order)
                        @php $ordered = (float) $order->lines->sum('quantity'); $received = (float) $order->lines->sum('received_quantity'); @endphp
                        <tr>
                            <td>{{ $order->po_number }}</td>
                            <td>{{ $order->po_date?->toDateString() }}</td>
                            <td>{{ $dims($order->project, $order->site) }}</td>
                            <td>{{ $money($order->total_amount) }}</td>
                            <td>{{ $qty($received) }} / {{ $qty($ordered) }}@if($ordered - $received > 0.0005) <span class="small">({{ $qty($ordered - $received) }} outstanding)</span>@endif</td>
                            <td><x-admin.status-badge :status="$order->status"/></td>
                            <td>@if ($canViewOrders)<a class="btn sm outline" href="{{ route('admin.inventory.purchase-orders.show', $order) }}">View PO</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">{{ __('No purchase orders for this supplier in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('goods-receipts')
            <div class="table-wrap"><table>
                <thead><tr><th>GRN</th><th>PO</th><th>Project / Site</th><th>Date</th><th>Value</th><th>Status</th><th>Invoiced</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $grn)
                        @php
                            $accepted = (float) $grn->lines->sum('accepted_quantity');
                            $invoiced = (float) $grn->lines->sum('invoiced_quantity');
                            $uninvoiced = max($accepted - $invoiced, 0);
                        @endphp
                        <tr>
                            <td>{{ $grn->grn_number }}</td>
                            <td>{{ $grn->purchaseOrder?->po_number ?? '-' }}</td>
                            <td>{{ $dims($grn->warehouse?->project, $grn->warehouse?->site) }}</td>
                            <td>{{ $grn->received_date?->toDateString() }}</td>
                            <td>{{ $money($grn->taxable_amount) }}</td>
                            <td><x-admin.status-badge :status="$grn->status"/></td>
                            <td>
                                @if ($grn->status !== 'posted') <span class="small">-</span>
                                @elseif ($uninvoiced <= 0.0005) <span class="badge green">Invoiced</span>
                                @elseif ($invoiced > 0) <span class="badge yellow">{{ $qty($uninvoiced) }} uninvoiced</span>
                                @else <span class="badge yellow">Uninvoiced</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn sm outline" href="{{ route('admin.inventory.goods-receipts.show', $grn) }}">View GRN</a>
                                @if ($canCreateBill && $grn->status === 'posted' && $uninvoiced > 0.0005)
                                    <a class="btn sm primary" href="{{ route('admin.accounting.accounts-payable.create', ['goods_receipt' => $grn->id, 'return_to' => route('admin.master.suppliers.edit', $supplier, false).'#goods-receipts']) }}">Create Supplier Bill</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">{{ __('No goods receipts for this supplier in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break

        @case('bills')
            <div class="table-wrap"><table>
                <thead><tr><th>Bill</th><th>Date</th><th>Project / Site</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Matched</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $bill)
                        <tr>
                            <td>{{ $bill->bill_number }}</td>
                            <td>{{ $bill->bill_date?->toDateString() }}</td>
                            <td>{{ $dims($bill->project, $bill->site) }}</td>
                            <td>{{ $money($bill->total_amount) }}</td>
                            <td>{{ $money($bill->paid_amount) }}</td>
                            <td>{{ $money($bill->balance_amount) }}</td>
                            <td><x-admin.status-badge :status="$bill->status"/></td>
                            <td>{{ $bill->grn_matches_count > 0 ? $bill->grn_matches_count.' GRN line'.($bill->grn_matches_count === 1 ? '' : 's') : 'Direct' }}</td>
                            <td>
                                <a class="btn sm outline" href="{{ route('admin.accounting.accounts-payable.show', $bill) }}">View</a>
                                @if ($canEditBill && $bill->isEditable())<a class="btn sm outline" href="{{ route('admin.accounting.accounts-payable.edit', $bill) }}">Edit draft</a>@endif
                                @if ($canPay && in_array($bill->status, \App\Support\Workspace\SupplierWorkspacePanels::OPEN_BILL_STATUSES, true))<a class="btn sm outline" href="{{ route('admin.accounting.accounts-payable.payment', $bill) }}">Record payment</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="table-empty">{{ __('No bills for this supplier in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p class="small">{{ __('Approve, pay and reopen are done on the bill itself; the supplier profile never posts accounting.') }}</p>
            @break

        @case('payments')
            @if ($summary)
                <div class="card-grid">
                    <x-admin.metric-card color="blue" :value="$money($summary['approved'])" label="Approved bills"/>
                    <x-admin.metric-card color="green" :value="$money($summary['paid'])" label="Paid"/>
                    <x-admin.metric-card color="yellow" :value="$money($summary['outstanding'])" label="Outstanding"/>
                    <x-admin.metric-card color="cyan" :value="$summary['last_payment'] ? $summary['last_payment']->payment_date->toDateString() : '-'" label="Last payment"/>
                </div>
            @endif
            <div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>Bill</th><th>Account</th><th>Method</th><th>Amount</th><th>Reference</th><th>Journal</th></tr></thead>
                <tbody>
                    @forelse ($rows as $payment)
                        <tr>
                            <td>{{ $payment->payment_date?->toDateString() }}</td>
                            <td>@if($payment->bill)<a href="{{ route('admin.accounting.accounts-payable.show', $payment->bill) }}">{{ $payment->bill->bill_number }}</a>@else - @endif</td>
                            <td>{{ $payment->paymentAccount?->label() ?? '-' }}</td>
                            <td>{{ $payment->payment_method ?? '-' }}</td>
                            <td>{{ $money($payment->amount) }}</td>
                            <td>{{ $payment->reference_number ?? '-' }}</td>
                            <td>@if($payment->journalEntry && auth()->user()->hasPermission('Journal Entries', 'view'))<a href="{{ route('admin.accounting.journal-entries.show', $payment->journalEntry) }}">{{ $payment->journalEntry->journal_number }}</a>@else - @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-empty">{{ __('No payments recorded for this supplier in your scope.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p class="small">{{ __('Figures come only from the bills you are allowed to see. Payments are recorded on the bill page.') }}</p>
            @break

        @case('accounting')
            <p><strong>{{ __('Linked payable account') }}:</strong> {{ $linkedAccount?->label() ?? $supplier->linked_account ?? __('Accounts Payable control account') }}</p>
            <div class="table-wrap"><table>
                <thead><tr><th>Journal</th><th>Date</th><th>Source</th><th>Description</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $entry)
                        <tr>
                            <td><a href="{{ route('admin.accounting.journal-entries.show', $entry) }}">{{ $entry->journal_number }}</a></td>
                            <td>{{ $entry->journal_date?->toDateString() }}</td>
                            <td>{{ $entry->source_module }}</td>
                            <td>{{ $entry->description }}</td>
                            <td>{{ $money($entry->total_debit) }}</td>
                            <td><x-admin.status-badge :status="$entry->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">{{ __('No journal entries for this supplier in your scope.') }}</td></tr>
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
                        <tr><td colspan="5" class="table-empty">{{ __('No activity recorded for this supplier yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            @break
    @endswitch

    <div class="form-actions">
        @if ($readonly)
            <span class="small">{{ __('Showing the latest :count of :total', ['count' => $rows->count(), 'total' => $rows->total()]) }}</span>
        @else
            @if ($rows->currentPage() > 1)<button type="button" class="btn outline" data-panel-load="{{ $panelUrl.'?page='.($rows->currentPage() - 1) }}">{{ __('Previous') }}</button>@endif
            <span>{{ $rows->currentPage() }} / {{ max($rows->lastPage(), 1) }}</span>
            @if ($rows->hasMorePages())<button type="button" class="btn outline" data-panel-load="{{ $panelUrl.'?page='.($rows->currentPage() + 1) }}">{{ __('Next') }}</button>@endif
            @if ($hasNext)<button type="button" class="btn outline" data-workspace-next>{{ __('Next section') }}</button>@endif
        @endif
        @if ($viewAll)<a class="btn outline" href="{{ $viewAll }}">{{ __('View all') }}</a>@endif
    </div>
</div>
