{{-- Persistent customer identity for the View page and the Edit workspace. --}}
@php
    /** @var \App\Models\Customer $customer */
    $summary = $summary ?? null;
    // Days past the oldest agreed due date (NR-06); counts only invoices the user may see.
    $overdue = $summary ? $customer->overdueSummary() : null;
@endphp
@if ($overdue && $overdue['days'] > 0)
    <div class="alert flash">
        <strong>{{ __('Payment overdue:') }}</strong> {{ __('The oldest unpaid invoice is :days days overdue. :count invoice(s) remain open, SAR :amount outstanding.', ['days' => $overdue['days'], 'count' => $overdue['count'], 'amount' => number_format($overdue['amount'], 2)]) }}
        <a href="{{ route('admin.accounting.accounts-receivable.index', ['customer' => $customer->id]) }}" style="font-weight:700">Open receivables</a>.
    </div>
@endif
<div class="card workspace-header" data-workspace-header>
    <div class="identity">
        <h2>{{ $customer->code }} — {{ $customer->name }}</h2>
        <div>
            <x-admin.status-badge :status="$customer->status"/>
            @if ($customer->type)
                <span class="small">· {{ __($customer->type) }}</span>
            @endif
            @if ($customer->rating)
                <span class="badge {{ match ($customer->rating) { 'Green' => 'green', 'Amber' => 'yellow', default => 'red' } }}">{{ __('ui.'.strtolower($customer->rating)) }}</span>
            @endif
        </div>
    </div>
    <dl>
        <dt>{{ __('VAT number') }}</dt><dd>{{ $customer->vat_number ?? '-' }}</dd>
        <dt>{{ __('CR number') }}</dt><dd>{{ $customer->cr_number ?? '-' }}</dd>
        <dt>{{ __('Contact') }}</dt><dd>{{ $customer->contact_person ?? '-' }}@if($customer->phone) · {{ $customer->phone }}@endif</dd>
        <dt>{{ __('Payment channel') }}</dt><dd>{{ __('ui.'.strtolower($customer->allowed_payment_types ?? 'Both')) }}@if((float) $customer->credit_limit > 0) · {{ __('Credit Limit') }} SAR {{ number_format($customer->credit_limit, 2) }}@endif</dd>
    </dl>
    <dl>
        <dt>{{ __('Receivable account') }}</dt><dd>{{ $customer->linked_account ?? '-' }}</dd>
        <dt>{{ __('Projects') }}</dt><dd>{{ $customer->projects()->count() }}</dd>
        @if ($summary)
            <dt>{{ __('Outstanding receivable') }}</dt><dd>SAR {{ number_format($summary['outstanding'], 2) }} <span class="small">({{ __('Open invoices') }}: {{ $summary['open_invoices'] }})</span></dd>
            <dt>{{ __('Received to date') }}</dt><dd>SAR {{ number_format($summary['received'], 2) }}</dd>
            <dt>{{ __('Overdue') }}</dt><dd>{{ $overdue['days'] > 0 ? $overdue['days'].' '.__('days').' · SAR '.number_format($overdue['amount'], 2) : __('None') }}</dd>
        @endif
    </dl>
</div>
