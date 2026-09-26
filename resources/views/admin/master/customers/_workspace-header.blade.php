{{-- Persistent customer identity for the View page and the Edit workspace. --}}
@php
    /** @var \App\Models\Customer $customer */
    $summary = $summary ?? null;
    // Days past the oldest agreed due date (NR-06); counts only invoices the user may see.
    $overdue = $summary ? $customer->overdueSummary() : null;
@endphp
@if ($overdue && $overdue['days'] > 0)
    <div class="alert flash">
        <strong>Payment overdue:</strong> the oldest unpaid invoice is {{ $overdue['days'] }} days past its agreed due date.
        {{ $overdue['count'] }} invoice(s) still open, SAR {{ number_format($overdue['amount'], 2) }} outstanding.
        <a href="{{ route('admin.accounting.accounts-receivable.index', ['customer' => $customer->id]) }}" style="font-weight:700">Open receivables</a>.
    </div>
@endif
<div class="card workspace-header" data-workspace-header>
    <div class="identity">
        <h2>{{ $customer->code }} — {{ $customer->name }}</h2>
        <div>
            <x-admin.status-badge :status="$customer->status"/>
            @if ($customer->type)
                <span class="small">· {{ $customer->type }}</span>
            @endif
            @if ($customer->rating)
                <span class="badge {{ match ($customer->rating) { 'Green' => 'green', 'Amber' => 'yellow', default => 'red' } }}">{{ $customer->rating }}</span>
            @endif
        </div>
    </div>
    <dl>
        <dt>VAT number</dt><dd>{{ $customer->vat_number ?? '-' }}</dd>
        <dt>CR number</dt><dd>{{ $customer->cr_number ?? '-' }}</dd>
        <dt>Contact</dt><dd>{{ $customer->contact_person ?? '-' }}@if($customer->phone) · {{ $customer->phone }}@endif</dd>
        <dt>Payment channel</dt><dd>{{ __('ui.'.strtolower($customer->allowed_payment_types ?? 'Both')) }}@if((float) $customer->credit_limit > 0) · limit SAR {{ number_format($customer->credit_limit, 2) }}@endif</dd>
    </dl>
    <dl>
        <dt>Receivable account</dt><dd>{{ $customer->linked_account ?? '-' }}</dd>
        <dt>Projects</dt><dd>{{ $customer->projects()->count() }}</dd>
        @if ($summary)
            <dt>Outstanding receivable</dt><dd>SAR {{ number_format($summary['outstanding'], 2) }} <span class="small">({{ $summary['open_invoices'] }} open invoice{{ $summary['open_invoices'] === 1 ? '' : 's' }})</span></dd>
            <dt>Received to date</dt><dd>SAR {{ number_format($summary['received'], 2) }}</dd>
            <dt>Overdue</dt><dd>{{ $overdue['days'] > 0 ? $overdue['days'].' days · SAR '.number_format($overdue['amount'], 2) : 'None' }}</dd>
        @endif
    </dl>
</div>
