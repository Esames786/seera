@extends('layouts.admin')

@section('title', 'Purchase Request')
@section('breadcrumb', 'Inventory / Purchase Requests / Purchase Request')

@section('content')
    @php
        /** @var \App\Models\PurchaseRequest $pr */
        $user = auth()->user();
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        $canEdit = $pr->isEditable() && $user->hasPermission('Purchase Requests', 'edit');
        $canApprove = $pr->isEditable() && $user->hasPermission('Purchase Requests', 'approve');
        $canReject = in_array($pr->status, ['draft', 'pending'], true) && $user->hasPermission('Purchase Requests', 'reject');
        $canCreateOrder = $pr->status === 'approved' && $user->hasPermission('Purchase Orders', 'create');
        $canViewProject = $pr->project && $user->hasPermission('Projects', 'view');
        $canViewWarehouse = $pr->warehouse && $user->hasPermission('Warehouses', 'view');
        $orders = $pr->purchaseOrders->reject(fn ($order) => $order->status === 'cancelled');
        $requested = (float) $pr->lines->sum('quantity');
        $orderedTotal = (float) $orderedByItem->sum();
        $orderingState = match (true) {
            $pr->status === 'rejected' => 'Rejected',
            $orders->isEmpty() => 'No purchase order yet',
            $orderedTotal + 0.0005 >= $requested => 'Fully ordered',
            default => 'Partly ordered',
        };
        $sections = ['information' => 'Request Information', 'lines' => 'Requested Items', 'orders' => 'Purchase Orders'];
        if ($activity !== null) $sections['activity'] = 'Activity';
    @endphp

    <x-admin.page-header :title="'Purchase Request: '.$pr->pr_number" :description="($pr->project?->name ?? 'No project').' - requested '.$pr->request_date->toDateString()">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.inventory.purchase-requests.edit', $pr) }}">Edit</a>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('admin.inventory.purchase-requests.approve', $pr) }}">
                @csrf
                <button type="submit" class="btn primary">Approve</button>
            </form>
        @endif
        @if ($canCreateOrder)
            <a class="btn primary" href="{{ route('admin.inventory.purchase-orders.create', ['purchase_request' => $pr->id]) }}">Create Purchase Order</a>
        @endif
        <a class="btn outline" href="{{ route('admin.inventory.purchase-requests.index') }}">Back to Purchase Requests</a>
    </x-admin.page-header>

    {{-- Persistent request identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $pr->pr_number }}</h2>
            <div>
                <x-admin.status-badge :status="$pr->status"/>
                <span class="small">· {{ $orderingState }}</span>
            </div>
        </div>
        <dl>
            <dt>Requested by</dt><dd>{{ $pr->requester?->name ?? '-' }} <span class="small">· {{ $pr->request_date->toDateString() }}</span></dd>
            <dt>Project / Site</dt>
            <dd>
                @if ($canViewProject)<a href="{{ route('admin.master.projects.show', $pr->project) }}">{{ $pr->project->name }}</a>@else{{ $pr->project?->name ?? '-' }}@endif
                @if ($pr->site) / {{ $pr->site->name }}@endif
            </dd>
            <dt>Warehouse</dt>
            <dd>@if ($canViewWarehouse)<a href="{{ route('admin.master.warehouses.show', $pr->warehouse) }}">{{ $pr->warehouse->name }}</a>@else{{ $pr->warehouse?->name ?? '-' }}@endif</dd>
            <dt>Required by</dt><dd>{{ $pr->required_date?->toDateString() ?? '-' }} <span class="small">· priority {{ ucfirst($pr->priority) }}</span></dd>
        </dl>
        <dl>
            <dt>Estimated total</dt><dd>SAR {{ number_format($pr->estimated_total, 2) }}</dd>
            <dt>Approval</dt>
            <dd>
                @if ($pr->status === 'approved') Approved{{ $pr->approver ? ' by '.$pr->approver->name : '' }}{{ $pr->approved_at ? ' · '.$pr->approved_at->format('Y-m-d H:i') : '' }}
                @elseif ($pr->status === 'rejected') Rejected{{ $pr->rejection_reason ? ' · '.$pr->rejection_reason : '' }}
                @elseif ($pr->status === 'converted') Approved and converted to a purchase order
                @else Waiting for approval
                @endif
            </dd>
            <dt>Purchase orders</dt><dd>{{ $orders->count() }} <span class="small">· ordered {{ $qty($orderedTotal) }} of {{ $qty($requested) }} requested</span></dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="Purchase request sections">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card-grid" id="information">
        <x-admin.metric-card color="blue" :value="$pr->lines->count()" label="Requested Lines"/>
        <x-admin.metric-card color="cyan" :value="'SAR '.number_format($pr->estimated_total, 2)" label="Estimated Total"/>
        <x-admin.metric-card color="yellow" :value="ucfirst($pr->priority)" label="Priority"/>
        <x-admin.metric-card color="green" :value="ucfirst($pr->status)" label="Status"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="Request Information" class="detail-table">
            <tbody>
                <tr><th>PR Number</th><td>{{ $pr->pr_number }}</td></tr>
                <tr><th>Request Date</th><td>{{ $pr->request_date->toDateString() }}</td></tr>
                <tr><th>Required Date</th><td>{{ $pr->required_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Requested By</th><td>{{ $pr->requester?->name ?? '-' }}</td></tr>
                <tr><th>Project / Site</th><td>{{ $pr->project?->name ?? '-' }}@if($pr->site) / {{ $pr->site->name }}@endif</td></tr>
                <tr><th>Warehouse</th><td>{{ $pr->warehouse?->name ?? '-' }}</td></tr>
                <tr><th>Priority</th><td>{{ ucfirst($pr->priority) }}</td></tr>
                <tr><th>Reason</th><td>{{ $pr->reason ?? '-' }}</td></tr>
                <tr><th>Status</th><td><x-admin.status-badge :status="$pr->status"/></td></tr>
                <tr><th>Approved By</th><td>{{ $pr->approver?->name ?? '-' }}</td></tr>
                <tr><th>Approved At</th><td>{{ $pr->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Rejection Reason</th><td>{{ $pr->rejection_reason ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            @if ($canReject)
                <div class="form-section">
                    <div class="section-title">Reject Request</div>
                    <div class="section-body">
                        <form method="POST" action="{{ route('admin.inventory.purchase-requests.reject', $pr) }}">
                            @csrf
                            <label for="rejection_reason">Rejection Reason *</label>
                            <textarea id="rejection_reason" name="rejection_reason" class="textarea" required></textarea>
                            <div class="form-actions" style="margin-top:12px">
                                <button type="submit" class="btn danger">Reject Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <x-admin.data-table title="Purchase Orders From This Request" id="orders">
                <thead>
                    <tr><th>PO Number</th><th>Supplier</th><th>Total</th><th>Received</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($pr->purchaseOrders as $order)
                        @php $ordered = (float) $order->lines->sum('quantity'); $received = (float) $order->lines->sum('received_quantity'); @endphp
                        <tr>
                            <td>@if ($canViewOrders)<a href="{{ route('admin.inventory.purchase-orders.show', $order) }}" style="color:var(--blue);font-weight:700">{{ $order->po_number }}</a>@else{{ $order->po_number }}@endif</td>
                            <td>{{ $order->supplier->name }}</td>
                            <td>SAR {{ number_format($order->total_amount, 2) }}</td>
                            <td>{{ $qty($received) }} of {{ $qty($ordered) }}</td>
                            <td><x-admin.status-badge :status="$order->status"/></td>
                            <td>@if ($canViewOrders)<a class="btn sm outline" href="{{ route('admin.inventory.purchase-orders.show', $order) }}">View PO</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">No purchase order raised from this request yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table title="Requested Items" subtitle="Ordered so far counts the lines of the purchase orders raised from this request." id="lines">
        <thead>
            <tr><th>Item</th><th>Description</th><th>Quantity</th><th>Ordered so far</th><th>Still to order</th><th>Unit</th><th>Est. Unit Cost</th><th>Est. Total</th><th>Budget Line</th></tr>
        </thead>
        <tbody>
            @forelse ($pr->lines as $line)
                @php $ordered = (float) ($orderedByItem[$line->item_id] ?? 0); @endphp
                <tr>
                    <td>{{ $line->item?->label() ?? '-' }}</td>
                    <td>{{ $line->description ?? '-' }}</td>
                    <td>{{ $qty($line->quantity) }}</td>
                    <td>{{ $qty($ordered) }}</td>
                    <td><strong>{{ $qty(max((float) $line->quantity - $ordered, 0)) }}</strong></td>
                    <td>{{ $line->unit?->code ?? '-' }}</td>
                    <td>SAR {{ number_format($line->estimated_unit_cost, 2) }}</td>
                    <td><strong>SAR {{ number_format($line->estimated_total, 2) }}</strong></td>
                    <td>{{ $line->budget_line ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No lines on this request.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    @if ($activity !== null)
        <x-admin.data-table title="Activity" subtitle="Latest entries that name this request" id="activity">
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
                    <tr><td colspan="5" class="table-empty">No activity recorded for this request yet.</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <a class="btn sm outline" href="{{ route('admin.activity-logs.index', ['search' => $pr->pr_number]) }}">View all</a>
            </x-slot:footer>
        </x-admin.data-table>
    @endif
@endsection
