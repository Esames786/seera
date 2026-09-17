@extends('layouts.admin')

@section('title', 'Customer Details')
@section('breadcrumb', 'Master Setup / Customers / Customer Details')

@section('content')
    @php
        $ratingColor = match ($customer->rating) { 'Green' => 'green', 'Amber' => 'yellow', 'Red' => 'red', default => 'cyan' };
        $canEdit = auth()->user()->hasPermission('Customers', 'create');
        $canDelete = auth()->user()->hasPermission('Customers', 'delete');
    @endphp

    <x-admin.page-header :title="$customer->name" description="Customer overview with projects, contacts and shared notes">
        <a class="btn primary" href="{{ route('admin.master.customers.edit', $customer) }}">Edit Customer</a>
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
                    <tr><th>Where</th><th>Name</th><th>Phone</th><th>Email</th><th></th></tr>
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
                            <td>
                                @if ($canDelete)
                                    <button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.master.customers.contacts.destroy', [$customer, $contact]) }}" data-delete-name="{{ $contact->name }}">Remove</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="table-empty">No office or site contacts recorded yet.</td></tr>
                    @endforelse
                </tbody>
                @if ($canEdit)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.master.customers.contacts.store', $customer) }}" style="width:100%">
                            @csrf
                            <div class="form-grid three">
                                <div>
                                    <label for="contact_location_type">Where</label>
                                    <select id="contact_location_type" name="location_type" class="select">
                                        <option value="office">Office</option>
                                        <option value="site">Site</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="contact_site_id">Site (if site contact)</label>
                                    <select id="contact_site_id" name="site_id" class="select">
                                        <option value="">Not a specific site</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div><label for="contact_name">Name *</label><input id="contact_name" name="name" class="input" required/></div>
                                <div><label for="contact_title">Title / Role</label><input id="contact_title" name="title" class="input" placeholder="Site engineer, Accounts officer..."/></div>
                                <div><label for="contact_phone">Phone</label><input id="contact_phone" name="phone" class="input" placeholder="+966..."/></div>
                                <div><label for="contact_email">Email</label><input id="contact_email" name="email" type="email" class="input"/></div>
                                <div class="full"><label for="contact_address">Address / Directions</label><input id="contact_address" name="address" class="input" placeholder="Office floor, site gate, landmark..."/></div>
                            </div>
                            <div style="margin-top:10px;text-align:right"><button type="submit" class="btn sm primary">Add Contact</button></div>
                        </form>
                    </x-slot:footer>
                @endif
            </x-admin.data-table>

            <x-admin.data-table title="Shared Notes" subtitle="Visible to everyone who can open this customer">
                <tbody>
                    @forelse ($customer->notes as $note)
                        <tr>
                            <td>
                                <div style="white-space:pre-line">{{ $note->note }}</div>
                                <div class="small" style="margin-top:4px">{{ $note->user?->name ?? 'Unknown' }} · {{ $note->created_at->format('d M Y H:i') }}</div>
                            </td>
                            <td style="width:90px;text-align:right">
                                @if ($canDelete || $note->user_id === auth()->id())
                                    <button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.master.customers.notes.destroy', [$customer, $note]) }}" data-delete-name="this note">Remove</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="table-empty">No notes yet.</td></tr>
                    @endforelse
                </tbody>
                @if ($canEdit)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.master.customers.notes.store', $customer) }}" style="width:100%;display:flex;gap:8px;align-items:flex-start">
                            @csrf
                            <textarea name="note" class="textarea" rows="2" style="min-height:60px;flex:1" placeholder="Leave a note for colleagues: gate pass needed, accounts contact changed, payment promised on..." required></textarea>
                            <button type="submit" class="btn sm primary">Add Note</button>
                        </form>
                    </x-slot:footer>
                @endif
            </x-admin.data-table>
        </div>
    </div>
@endsection
