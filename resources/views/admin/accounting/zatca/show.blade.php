@extends('layouts.admin')

@section('title', 'ZATCA Record')
@section('breadcrumb', 'Accounting / ZATCA E-Invoicing / ZATCA Record')

@section('content')
    <x-admin.page-header
        :title="'ZATCA Record: '.($record->customerInvoice?->invoice_number ?? $record->uuid)"
        :description="'Clearance status '.$record->clearance_status.' · '.$record->retry_count.' retries'">
        @if ($record->customerInvoice)
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.show', $record->customerInvoice) }}">Open Invoice</a>
        @endif
        @if ($record->clearance_status === 'failed')
            <form method="POST" action="{{ route('admin.accounting.zatca.retry', $record) }}">
                @csrf
                <button type="submit" class="btn primary">Retry Clearance</button>
            </form>
        @endif
    </x-admin.page-header>

    @if ($record->clearance_status === 'failed')
        <div class="alert flash">
            Clearance failed: {{ $record->failed_reason ?? $record->zatca_response_message ?? 'No reason recorded.' }}
        </div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card :color="$record->clearance_status === 'cleared' ? 'green' : ($record->clearance_status === 'failed' ? 'red' : 'yellow')" :value="ucfirst($record->clearance_status)" label="Clearance Status"/>
        <x-admin.metric-card color="blue" :value="ucfirst($record->digital_signature_status)" label="Digital Signature"/>
        <x-admin.metric-card color="cyan" :value="ucfirst($record->tamperProofStatus())" label="Tamper-Proof Storage"/>
        <x-admin.metric-card color="yellow" :value="$record->retry_count" label="Retry Count"/>
    </div>

    <div class="split even">
        <x-admin.data-table title="ZATCA Record" class="detail-table">
            <tbody>
                <tr><th>Invoice</th><td>{{ $record->customerInvoice?->invoice_number ?? '-' }}</td></tr>
                <tr><th>Customer</th><td>{{ $record->customerInvoice?->customer?->name ?? '-' }}</td></tr>
                <tr><th>Issue Date</th><td>{{ $record->customerInvoice?->invoice_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Total Amount</th><td>SAR {{ number_format($record->customerInvoice?->total_amount ?? 0, 2) }}</td></tr>
                <tr><th>VAT Amount</th><td>SAR {{ number_format($record->customerInvoice?->vat_amount ?? 0, 2) }}</td></tr>
                <tr><th>UUID</th><td class="small">{{ $record->uuid }}</td></tr>
                <tr><th>QR Code</th><td><x-admin.status-badge :status="$record->qrStatus()"/></td></tr>
                <tr><th>XML File</th><td>{{ $record->xml_file_path ?? 'Not generated' }}</td></tr>
                <tr><th>Digital Signature</th><td><x-admin.status-badge :status="$record->digital_signature_status"/></td></tr>
                <tr><th>Clearance Status</th><td><x-admin.status-badge :status="$record->clearance_status"/></td></tr>
                <tr><th>Response Code</th><td>{{ $record->zatca_response_code ?? '-' }}</td></tr>
                <tr><th>Response Message</th><td>{{ $record->zatca_response_message ?? '-' }}</td></tr>
                <tr><th>Cleared At</th><td>{{ $record->cleared_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Failed Reason</th><td>{{ $record->failed_reason ?? '-' }}</td></tr>
            </tbody>
        </x-admin.data-table>

        <div>
            <x-admin.data-table title="QR Code Payload">
                <tbody>
                    <tr>
                        <td class="small" style="word-break:break-all">{{ $record->qr_code_data ?? 'QR payload not generated yet.' }}</td>
                    </tr>
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table title="Tamper-Proof Hash">
                <tbody>
                    <tr>
                        <td class="small" style="word-break:break-all">{{ $record->tamper_proof_hash ?? 'Hash not generated yet.' }}</td>
                    </tr>
                </tbody>
            </x-admin.data-table>

            <div class="note">
                UUID, QR payload, XML path and the tamper-proof hash are generated locally in this phase. Cryptographic stamping and live clearance against the ZATCA API are added in the ZATCA integration phase.
            </div>
        </div>
    </div>
@endsection
