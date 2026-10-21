@extends('layouts.admin')

@section('title', __('zatca.record_title'))
@section('breadcrumb', 'Accounting / '.__('zatca.title').' / '.__('zatca.record_title'))

@section('content')
    @php
        $ready = $readiness['errors'] === [];
        $deadline = $record->reportingDeadline();
    @endphp
    <x-admin.page-header
        :title="__('zatca.record_title').': '.($invoice?->zatcaNumber() ?? $record->uuid)"
        :description="$record->statusLabel().' · '.__('zatca.env_'.($record->environment ?: $environment)).' · '.($record->invoice_type ? $record->documentTypeLabel() : '')">
        @if ($invoice && $record->isNote())
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.notes.show', $invoice) }}">Open Note</a>
        @elseif ($invoice)
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.show', $invoice) }}">Open Invoice</a>
        @endif
        <a class="btn outline" href="{{ route('admin.accounting.zatca.index') }}">Back to register</a>
    </x-admin.page-header>

    @if (session('warning'))
        <div class="alert flash">{{ session('warning') }}</div>
    @endif
    @if ($record->last_error_message && ! $record->isAccepted())
        <div class="alert flash"><strong>{{ __('zatca.last_error') }}:</strong> {{ $record->last_error_message }} @if ($record->last_error_code)<span class="small">({{ $record->last_error_category }} / {{ $record->last_error_code }})</span>@endif</div>
    @endif
    @if ($record->status === 'outcome_unknown')
        <div class="alert flash">{{ __('zatca.status_outcome_unknown') }}: {{ __('zatca.err_timeout') }}</div>
    @endif

    <div class="card-grid">
        <x-admin.metric-card :color="$record->statusColor()" :value="$record->statusLabel()" label="Status"/>
        <x-admin.metric-card :color="$record->validation_status === 'passed' ? 'green' : ($record->validation_status === 'failed' ? 'red' : 'gray')" :value="$record->validation_status ? ucfirst($record->validation_status) : '-'" :label="__('zatca.validation')"/>
        <x-admin.metric-card :color="$record->signed_at ? 'green' : 'gray'" :value="$record->signed_at ? $record->signed_at->format('Y-m-d H:i') : '-'" :label="__('zatca.signature')"/>
        <x-admin.metric-card :color="$ready ? 'green' : 'red'" :value="$ready ? __('zatca.ready') : __('zatca.not_ready')" :label="__('zatca.readiness')"/>
    </div>

    @if ($invoice && ! $record->isAccepted())
        <div class="card" style="padding:16px;margin-bottom:16px">
            <div class="actions" style="display:flex;gap:8px;flex-wrap:wrap">
                @if ($canPost && ! $record->isLocked())
                    <form method="POST" action="{{ route('admin.accounting.zatca.generate', $record) }}">@csrf<button class="btn outline">{{ __('zatca.generate') }}</button></form>
                    <form method="POST" action="{{ route('admin.accounting.zatca.submit', $record) }}">@csrf<button class="btn primary" @disabled(! $ready)>{{ __('zatca.submit') }}</button></form>
                @endif
                @if ($canRetry && in_array($record->status, ['signed', 'submission_pending', 'rejected', 'outcome_unknown'], true))
                    <form method="POST" action="{{ route('admin.accounting.zatca.retry', $record) }}">@csrf<button class="btn warning">{{ __('zatca.retry') }}</button></form>
                @endif
                @if ($canPost && in_array($record->status, ['rejected', 'failed', 'validation_failed', 'validated', 'generated'], true))
                    <form method="POST" action="{{ route('admin.accounting.zatca.regenerate', $record) }}">@csrf<button class="btn outline">{{ __('zatca.regenerate') }}</button></form>
                @endif
            </div>
            <p class="small" style="margin-top:8px">{{ __('zatca.sdk_note') }}</p>
        </div>
    @endif

    <div class="split even">
        <x-admin.data-table :title="__('zatca.record_title')" class="detail-table">
            <tbody>
                <tr><th>{{ __('zatca.document_kind') }}</th><td>{{ $invoice?->zatcaNumber() ?? '-' }}@if ($record->isNote() && $record->customerInvoice) <span class="small">· {{ __('customer_notes.original_invoice') }} {{ $record->customerInvoice->invoice_number }}</span>@endif</td></tr>
                <tr><th>Customer</th><td>{{ $invoice?->customer?->name ?? '-' }}</td></tr>
                <tr><th>{{ __('zatca.invoice_type') }}</th><td>{{ $record->invoice_type ? $record->documentTypeLabel() : ($invoice ? __('zatca.type_'.$invoice->invoice_type) : '-') }} @if ($record->transaction_code)<span class="small">({{ $record->invoice_type_code }} / {{ $record->transaction_code }})</span>@endif</td></tr>
                @if ($record->isNote() && $invoice)<tr><th>{{ __('customer_notes.reason') }}</th><td class="small">{{ $invoice->zatcaNoteReason() }}</td></tr>@endif
                <tr><th>Issue date / {{ __('zatca.issue_time') }}</th><td>{{ $invoice?->zatcaIssueDate()?->toDateString() ?? '-' }} {{ $invoice?->issue_time ?? '' }}</td></tr>
                <tr><th>{{ __('zatca.supply_date') }}</th><td>{{ $invoice?->supply_date?->toDateString() ?? '-' }}</td></tr>
                <tr><th>Total / VAT</th><td>SAR {{ number_format((float) ($invoice?->total_amount ?? 0), 2) }} / SAR {{ number_format((float) ($invoice?->vat_amount ?? 0), 2) }}</td></tr>
                <tr><th>{{ __('zatca.uuid') }}</th><td class="small">{{ $record->uuid }}</td></tr>
                <tr><th>{{ __('zatca.environment') }}</th><td>{{ $record->environment ? __('zatca.env_'.$record->environment) : '-' }} @if ($record->unit)<span class="small">· {{ $record->unit->name }}</span>@endif</td></tr>
                <tr><th>{{ __('zatca.icv') }}</th><td>{{ $record->icv ?? '-' }}</td></tr>
                <tr><th>{{ __('zatca.invoice_hash') }}</th><td class="small" style="word-break:break-all">{{ $record->invoice_hash ?? '-' }}</td></tr>
                <tr><th>{{ __('zatca.previous_hash') }}</th><td class="small" style="word-break:break-all">{{ $record->previous_hash ?? '-' }}</td></tr>
                <tr><th>{{ __('zatca.submission') }}</th><td>{{ $record->submitted_at?->format('Y-m-d H:i') ?? '-' }} · {{ __('zatca.attempts') }}: {{ $record->submission_attempts }}</td></tr>
                <tr><th>{{ __('zatca.zatca_reference') }}</th><td>{{ $record->zatca_status ?? __('zatca.not_verified') }}@if ($record->accepted_with_warnings) ({{ __('zatca.warnings') }})@endif</td></tr>
                <tr><th>{{ __('zatca.cleared_at') }} / {{ __('zatca.reported_at') }}</th><td>{{ $record->cleared_at?->format('Y-m-d H:i') ?? $record->reported_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                @if ($deadline)
                    <tr><th>{{ __('zatca.reporting_deadline') }}</th><td>{{ $deadline->format('Y-m-d H:i') }} @if (! $record->isAccepted() && $deadline->isPast())<span class="badge red">overdue</span>@endif</td></tr>
                @endif
                @if ($invoice?->zatca_locked_at)
                    <tr><th>Locked</th><td class="small">{{ __('zatca.locked') }}</td></tr>
                @endif
            </tbody>
            @if ($canExport && ($record->xml_path || $record->signed_xml_path || $record->cleared_xml_path))
                <x-slot:footer>
                    @if ($record->xml_path)<a class="btn sm outline" href="{{ route('admin.accounting.zatca.xml', [$record, 'unsigned']) }}">{{ __('zatca.download_xml') }}</a>@endif
                    @if ($record->signed_xml_path)<a class="btn sm outline" href="{{ route('admin.accounting.zatca.xml', [$record, 'signed']) }}">{{ __('zatca.download_signed') }}</a>@endif
                    @if ($record->cleared_xml_path)<a class="btn sm outline" href="{{ route('admin.accounting.zatca.xml', [$record, 'cleared']) }}">{{ __('zatca.download_cleared') }}</a>@endif
                </x-slot:footer>
            @endif
        </x-admin.data-table>

        <div>
            <x-admin.data-table :title="__('zatca.readiness')">
                <tbody>
                    @forelse ($readiness['errors'] as $item)
                        <tr><td><span class="badge red">{{ $item['rule'] }}</span> {{ $item['message'] }}</td></tr>
                    @empty
                        <tr><td><span class="badge green">{{ __('zatca.ready') }}</span></td></tr>
                    @endforelse
                    @foreach ($readiness['warnings'] as $item)
                        <tr><td><span class="badge yellow">{{ $item['rule'] }}</span> {{ $item['message'] }}</td></tr>
                    @endforeach
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table :title="__('zatca.validation')">
                <tbody>
                    @forelse ($record->validation_errors ?? [] as $item)
                        <tr><td><span class="badge red">{{ $item['rule'] ?? '' }}</span> {{ $item['message'] ?? '' }}</td></tr>
                    @empty
                        <tr><td class="small">{{ $record->validation_status === 'passed' ? __('zatca.ready') : '-' }}</td></tr>
                    @endforelse
                    @foreach ($record->validation_warnings ?? [] as $item)
                        <tr><td><span class="badge yellow">{{ $item['rule'] ?? '' }}</span> {{ $item['message'] ?? '' }}</td></tr>
                    @endforeach
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table :title="__('zatca.errors').' / '.__('zatca.warnings').' (ZATCA)'">
                <tbody>
                    @forelse ($record->response_errors ?? [] as $item)
                        <tr><td><span class="badge red">{{ $item['code'] ?? 'error' }}</span> {{ $item['message'] ?? '' }}</td></tr>
                    @empty
                        <tr><td class="small">-</td></tr>
                    @endforelse
                    @foreach ($record->response_warnings ?? [] as $item)
                        <tr><td><span class="badge yellow">{{ $item['code'] ?? 'warning' }}</span> {{ $item['message'] ?? '' }}</td></tr>
                    @endforeach
                </tbody>
            </x-admin.data-table>

            <x-admin.data-table :title="__('zatca.qr')">
                <tbody>
                    <tr><td class="small" style="word-break:break-all">{{ $record->qr_code ?? '-' }}</td></tr>
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <x-admin.data-table :title="__('zatca.submission').' — '.__('zatca.attempts')">
        <thead><tr><th>#</th><th>Endpoint</th><th>HTTP</th><th>Outcome</th><th>{{ __('zatca.zatca_reference') }}</th><th>{{ __('zatca.warnings') }}</th><th>{{ __('zatca.errors') }}</th><th>When</th></tr></thead>
        <tbody>
            @forelse ($record->submissions as $submission)
                <tr>
                    <td>{{ $submission->attempt }}</td><td class="small">{{ $submission->endpoint }} ({{ $submission->environment }})</td><td>{{ $submission->http_status ?? '-' }}</td>
                    <td><x-admin.status-badge :status="$submission->outcome"/></td><td class="small">{{ $submission->zatca_status ?? '-' }}</td>
                    <td>{{ count($submission->warnings ?? []) }}</td><td>{{ count($submission->errors ?? []) }}</td><td class="small">{{ $submission->created_at->format('Y-m-d H:i:s') }} ({{ $submission->duration_ms }} ms)</td>
                </tr>
            @empty
                <tr><td colspan="8" class="table-empty">{{ __('zatca.not_verified') }}</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>
@endsection
