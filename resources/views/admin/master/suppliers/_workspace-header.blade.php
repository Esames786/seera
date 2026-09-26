{{-- Persistent supplier identity for the View page and the Edit workspace. --}}
@php
    /** @var \App\Models\Supplier $supplier */
    $summary = $summary ?? null;
@endphp
<div class="card workspace-header" data-workspace-header>
    <div class="identity">
        <h2>{{ $supplier->code }} — {{ $supplier->name }}</h2>
        <div>
            <x-admin.status-badge :status="$supplier->status"/>
            @if ($supplier->category)
                <span class="small">· {{ $supplier->category }}</span>
            @endif
            @if ($supplier->city)
                <span class="small">· {{ $supplier->city }}</span>
            @endif
        </div>
    </div>
    <dl>
        <dt>VAT number</dt><dd>{{ $supplier->vat_number ?? '-' }}</dd>
        <dt>CR number</dt><dd>{{ $supplier->cr_number ?? '-' }}</dd>
        <dt>Contact</dt><dd>{{ $supplier->contact_person ?? '-' }}@if($supplier->phone) · {{ $supplier->phone }}@endif</dd>
        <dt>Payment terms</dt><dd>{{ $supplier->paymentTerm?->label() ?? $supplier->payment_terms ?? '-' }} · {{ $supplier->allowed_payment_types ?? 'Both' }}</dd>
    </dl>
    <dl>
        <dt>Payable account</dt><dd>{{ $supplier->linkedAccount?->label() ?? $supplier->linked_account ?? '-' }}</dd>
        <dt>Projects</dt><dd>{{ $supplier->projects()->count() }}</dd>
        @if ($summary)
            <dt>Outstanding payable</dt><dd>SAR {{ number_format($summary['outstanding'], 2) }} <span class="small">({{ $summary['open_bills'] }} open bill{{ $summary['open_bills'] === 1 ? '' : 's' }})</span></dd>
            <dt>Paid to date</dt><dd>SAR {{ number_format($summary['paid'], 2) }}</dd>
        @endif
    </dl>
</div>
