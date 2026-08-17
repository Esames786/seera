@extends('layouts.admin')

@section('title', 'ZATCA E-Invoicing')
@section('breadcrumb', 'Accounting / ZATCA E-Invoicing')

@section('content')
    <x-admin.page-header title="ZATCA E-Invoicing" description="UUID, QR, XML, digital signature and clearance status for every approved customer invoice"/>

    <div class="card-grid">
        <x-admin.metric-card color="green" :value="$clearedCount" label="Cleared"/>
        <x-admin.metric-card color="yellow" :value="$pendingCount" label="Pending Clearance"/>
        <x-admin.metric-card color="red" :value="$failedCount" label="Failed"/>
        <x-admin.metric-card color="blue" :value="$draftCount" label="Draft"/>
    </div>

    <div class="note">
        This phase builds the ZATCA e-invoicing foundation inside the ERP. Production ZATCA integration, certificate onboarding, the clearance API, cryptographic stamping and compliance testing are handled in the dedicated ZATCA integration phase before going live.
    </div>
    <br/>

    <x-admin.filter-bar>
        <input class="input" style="width:260px" type="search" name="search" value="{{ request('search') }}" placeholder="UUID or invoice number..."/>
        <select class="select" style="width:170px" name="status">
            <option value="">All Clearance Status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-slot:actions>
            <a class="btn outline" href="{{ route('admin.accounting.zatca.index') }}">Reset</a>
        </x-slot:actions>
    </x-admin.filter-bar>

    <x-admin.data-table title="ZATCA Invoice Records">
        <thead>
            <tr>
                <th>Invoice</th><th>UUID</th><th>Customer</th><th>Issue Date</th>
                <th>QR</th><th>XML</th><th>Signature</th><th>Clearance</th>
                <th>ZATCA Response</th><th>Retries</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>
                        @if ($record->customerInvoice)
                            <a href="{{ route('admin.accounting.accounts-receivable.show', $record->customerInvoice) }}" style="color:var(--blue);font-weight:700">{{ $record->customerInvoice->invoice_number }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td class="small">{{ Str::limit($record->uuid, 13) }}</td>
                    <td>{{ $record->customerInvoice?->customer?->name ?? '-' }}</td>
                    <td>{{ $record->customerInvoice?->invoice_date?->toDateString() ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$record->qrStatus()"/></td>
                    <td><x-admin.status-badge :status="$record->xmlStatus()"/></td>
                    <td><x-admin.status-badge :status="$record->digital_signature_status"/></td>
                    <td><x-admin.status-badge :status="$record->clearance_status"/></td>
                    <td class="small">{{ $record->zatca_response_code ? $record->zatca_response_code.' — '.Str::limit($record->zatca_response_message, 28) : '-' }}</td>
                    <td>{{ $record->retry_count }}</td>
                    <td>
                        <div class="actions">
                            <a class="btn sm primary" href="{{ route('admin.accounting.zatca.show', $record) }}">View</a>
                            @if ($record->clearance_status === 'failed')
                                <form method="POST" action="{{ route('admin.accounting.zatca.retry', $record) }}">
                                    @csrf
                                    <button type="submit" class="btn sm warning">Retry</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No ZATCA records yet. Approve a customer invoice to generate one.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $records->firstItem() ?? 0 }}-{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</span>
            {{ $records->links() }}
        </x-slot:footer>
    </x-admin.data-table>
@endsection
