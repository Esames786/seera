{{-- Persistent purchase order identity: shown above every section of the document workspace. --}}
@php
    /** @var \App\Models\PurchaseOrder $order */
    /** @var array<string, mixed> $summary */
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    $unit = $order->lines->first()?->item?->unit?->code;
    $units = $order->lines->map(fn ($line) => $line->item?->unit?->code)->unique()->count() === 1 && $unit ? ' '.$unit : '';
    $user = auth()->user();
    $canViewSupplier = $user->hasPermission('Suppliers', 'view');
    $canManageSupplier = $user->hasPermission('Suppliers', 'edit');
    $canViewProject = $order->project && $user->hasPermission('Projects', 'view');
    $canViewWarehouse = $order->warehouse && $user->hasPermission('Warehouses', 'view');
@endphp
<div class="card workspace-header" data-workspace-header>
    <div class="identity">
        <h2>{{ $order->po_number }}</h2>
        <div>
            <x-admin.status-badge :status="$order->status"/>
            <span class="small">· {{ $summary['received_state'] }}</span>
            @if ($order->approved_at)
                <span class="small">· approved {{ $order->approved_at->format('Y-m-d') }}@if($order->approver) by {{ $order->approver->name }}@endif</span>
            @endif
        </div>
    </div>
    <dl>
        <dt>Supplier</dt>
        <dd>
            {{ $order->supplier->name }}
            @if ($canViewSupplier)<a class="small" href="{{ route('admin.master.suppliers.show', $order->supplier) }}">View</a>@endif
            @if ($canManageSupplier)<a class="small" href="{{ route('admin.master.suppliers.edit', $order->supplier) }}">Manage</a>@endif
        </dd>
        <dt>PO date</dt><dd>{{ $order->po_date->toDateString() }}</dd>
        <dt>Expected delivery</dt><dd>{{ $order->expected_delivery_date?->toDateString() ?? '-' }}</dd>
        <dt>Project / Site</dt>
        <dd>
            @if ($canViewProject)<a href="{{ route('admin.master.projects.show', $order->project) }}">{{ $order->project->name }}</a>@else{{ $order->project?->name ?? '-' }}@endif
            @if ($order->site) / {{ $order->site->name }}@endif
        </dd>
        <dt>Deliver to</dt>
        <dd>@if ($canViewWarehouse)<a href="{{ route('admin.master.warehouses.show', $order->warehouse) }}">{{ $order->warehouse->name }}</a>@else{{ $order->warehouse?->name ?? '-' }}@endif</dd>
    </dl>
    <dl>
        <dt>Order total</dt><dd>SAR {{ number_format($order->total_amount, 2) }} <span class="small">(VAT SAR {{ number_format($order->vat_amount, 2) }})</span></dd>
        <dt>Received</dt><dd>{{ $qty($summary['received']) }} of {{ $qty($summary['ordered']) }}{{ $units }} <span class="small">· still to receive {{ $qty($summary['outstanding']) }}{{ $units }}</span></dd>
        <dt>Invoiced</dt><dd>{{ $qty($summary['invoiced']) }}{{ $units }} <span class="small">· received but not invoiced {{ $qty($summary['uninvoiced']) }}{{ $units }}</span></dd>
        <dt>Billing state</dt><dd>{{ $summary['billing_state'] }}@if (array_key_exists('bills', $summary)) <span class="small">· {{ $summary['bills'] }} bill{{ $summary['bills'] === 1 ? '' : 's' }}, outstanding payment SAR {{ number_format($summary['outstanding_payment'], 2) }}</span>@endif</dd>
        <dt>Goods receipts</dt><dd>{{ $summary['receipts'] }}@if ($summary['draft_receipts'] > 0) <span class="small">({{ $summary['draft_receipts'] }} draft, not yet posted)</span>@endif</dd>
    </dl>
</div>
