@extends('layouts.admin')

@section('title', 'Customer Details')
@section('breadcrumb', 'Master Setup / Customers / Customer Details')

@section('content')
    @php
        $ratingColor = match ($customer->rating) { 'Green' => 'green', 'Amber' => 'yellow', 'Red' => 'red', default => 'cyan' };
        $canEdit = auth()->user()->hasPermission('Customers', 'edit');

    @endphp

    <x-admin.page-header :title="$customer->name" description="Customer overview with projects, contacts and shared notes">
        @if ($canEdit)
            <a class="btn primary" href="{{ route('admin.master.customers.edit', $customer) }}">Edit Customer</a>
        @endif
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$customer->projects->count()" label="Projects"/>
        <x-admin.metric-card color="yellow" :value="'SAR '.number_format($customer->opening_receivable)" label="Receivable"/>
        <x-admin.metric-card :color="$ratingColor" :value="$customer->rating ?? 'Not rated'" label="Customer Rating"/>
        <x-admin.metric-card :color="$overdue['days'] > 0 ? 'red' : 'green'" :value="$overdue['days'] > 0 ? $overdue['days'].' days' : 'None'" label="Overdue"/>
    </div>

    @if ($overdue['days'] > 0)
        <div class="alert flash">
            <strong>Payment overdue:</strong> the oldest unpaid invoice is {{ $overdue['days'] }} days past its agreed due date.
            {{ $overdue['count'] }} invoice(s) still open, SAR {{ number_format($overdue['amount'], 2) }} outstanding.
            <a href="{{ route('admin.accounting.accounts-receivable.index', ['customer' => $customer->id]) }}" style="font-weight:700">Open receivables</a>.
        </div>
    @endif

    <div class="split even">
        <div>
            <x-admin.data-table title="Customer Information" class="detail-table">
                <tbody>
                    <tr><th>Customer Name</th><td>{{ $customer->name }}</td></tr>
                    <tr><th>Customer Code</th><td>{{ $customer->code }}</td></tr>
                    <tr><th>Type</th><td>{{ $customer->type }}</td></tr>
                    <tr><th>Rating</th><td>@if($customer->rating)<span class="badge {{ $ratingColor }}">{{ $customer->rating }}</span>@else - @endif</td></tr>
                    <tr><th>{{ __('ui.payment_types') }}</th><td>{{ __('ui.'.strtolower($customer->allowed_payment_types ?? 'Both')) }}</td></tr>
                    <tr><th>VAT Number</th><td>{{ $customer->vat_number ?? '-' }}</td></tr>
                    <tr><th>CR Number</th><td>{{ $customer->cr_number ?? '-' }}</td></tr>
                    <tr><th>Contact Person</th><td>{{ $customer->contact_person ?? '-' }}</td></tr>
                    <tr><th>Phone</th><td>{{ $customer->phone ?? '-' }}</td></tr>
                    <tr><th>Email</th><td>{{ $customer->email ?? '-' }}</td></tr>
                    <tr><th>Credit Limit</th><td>SAR {{ number_format($customer->credit_limit) }}</td></tr>
                    <tr><th>Linked Receivable Account</th><td>{{ $customer->linked_account ?? '-' }}</td></tr>
                    <tr><th>Billing Address</th><td>{{ $customer->billing_address ?? '-' }}</td></tr>
                    <tr><th>Status</th><td><x-admin.status-badge :status="$customer->status"/></td></tr>
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Projects for this Customer">
                <thead>
                    <tr><th>Code</th><th>Project</th><th>Manager</th><th>Budget</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($customer->projects as $project)
                        <tr>
                            <td>{{ $project->code }}</td>
                            <td><a href="{{ route('admin.master.projects.show', $project) }}" style="color:var(--blue);font-weight:700">{{ $project->name }}</a></td>
                            <td>{{ $project->manager?->name ?? '-' }}</td>
                            <td>SAR {{ number_format($project->budget) }}</td>
                            <td><x-admin.status-badge :status="$project->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">No projects for this customer yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>

        <div>
            <x-admin.data-table title="Office & Site Contacts" subtitle="Who to meet, and where">
                <thead>
                    <tr><th>Where</th><th>Name</th><th>Phone</th><th>Email</th></tr>
                </thead>
                <tbody>
                    @forelse ($customer->contacts as $contact)
                        <tr>
                            <td>
                                <span class="badge {{ $contact->location_type === 'office' ? 'blue' : 'purple' }}">{{ ucfirst($contact->location_type) }}</span>
                                @if ($contact->site)<div class="small">{{ $contact->site->name }}</div>@endif
                                @if ($contact->address)<div class="small">{{ $contact->address }}</div>@endif
                            </td>
                            <td>{{ $contact->name }}@if($contact->title)<div class="small">{{ $contact->title }}</div>@endif</td>
                            <td>{{ $contact->phone ?? '-' }}</td>
                            <td>{{ $contact->email ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No office or site contacts recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Shared Notes" subtitle="Visible to everyone who can open this customer">
                <tbody>
                    @forelse ($customer->notes as $note)
                        <tr>
                            <td>
                                <div style="white-space:pre-line">{{ $note->note }}</div>
                                <div class="small" style="margin-top:4px">{{ $note->user?->name ?? 'Unknown' }} · {{ $note->created_at->format('d M Y H:i') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="table-empty">No notes yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>
@endsection
