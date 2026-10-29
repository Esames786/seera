@extends('layouts.admin')

@section('title', __('Customer Invoice'))
@section('breadcrumb', __('Accounting / Accounts Receivable / Customer Invoice'))

@section('content')
    @php
        /** @var \App\Models\CustomerInvoice $invoice */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $origin = $returnTo ?? $selfUrl;
        $record = $invoice->zatcaRecord;
    @endphp

    <x-admin.page-header :title="__('Invoice:').' '.$invoice->invoice_number" :description="__('workspace.inv_readonly')">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.edit', ['accounts_receivable' => $invoice, 'return_to' => $returnTo]) }}">{{ __('workspace.inv_edit_draft') }}</a>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('admin.accounting.accounts-receivable.approve', $invoice) }}">
                @csrf
                <button type="submit" class="btn primary">{{ __('Approve & Post') }}</button>
            </form>
        @endif
        @if ($canReceive)
            <a class="btn primary" href="{{ route('admin.accounting.accounts-receivable.receipt', ['accounts_receivable' => $invoice, 'return_to' => $origin]) }}">{{ __('workspace.inv_record_receipt') }}</a>
        @endif
        @if ($canPrint)
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.print', ['accounts_receivable' => $invoice, 'return_to' => $selfUrl]) }}" target="_blank" rel="noopener">{{ __('workspace.inv_print') }}</a>
        @endif
        @if ($canViewJournals && $invoice->journalEntry)
            <a class="btn outline" href="{{ route('admin.accounting.journal-entries.show', $invoice->journalEntry) }}">{{ __('workspace.inv_view_journal') }}</a>
        @endif
        @if ($canReopen)
            <form method="POST" action="{{ route('admin.accounting.accounts-receivable.reopen', $invoice) }}" onsubmit="var reason = prompt('Reason for reopening this invoice (kept on the record):'); if (!reason) return false; this.reason.value = reason;">
                @csrf
                <input type="hidden" name="reason" value=""/>
                <button type="submit" class="btn danger" title="{{ __('Reverse the posting, cancel the e-invoice record (only before any stamp) and return the invoice to draft') }}">{{ __('Reopen for Correction') }}</button>
            </form>
        @endif
        @if ($returnTo)
            <a class="btn outline" href="{{ $returnTo }}">{{ __('workspace.inv_back_origin') }}</a>
        @else
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.index') }}">{{ __('workspace.inv_back_list') }}</a>
        @endif
    </x-admin.page-header>

    {{-- Persistent invoice identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $invoice->invoice_number }}</h2>
            <div>
                <x-admin.status-badge :status="$invoice->payment_status"/>
                <span class="small">· {{ __($paymentState) }}</span>
            </div>
        </div>
        <dl>
            <dt>{{ __('workspace.customer') }}</dt>
            <dd>
                {{ $invoice->customer->name }}
                @if ($canViewCustomer)<a class="small" href="{{ route('admin.master.customers.show', $invoice->customer) }}">{{ __('workspace.view') }}</a>@endif
                @if ($canManageCustomer)<a class="small" href="{{ route('admin.master.customers.edit', $invoice->customer) }}">{{ __('workspace.manage') }}</a>@endif
            </dd>
            <dt>{{ __('workspace.inv_invoice_date') }} / {{ __('workspace.inv_due_date') }}</dt><dd>{{ $invoice->invoice_date->toDateString() }} / {{ $invoice->due_date?->toDateString() ?? '-' }}</dd>
            <dt>{{ __('workspace.inv_project') }}</dt>
            <dd>
                @if ($canViewProject)<a href="{{ route('admin.master.projects.show', $invoice->project) }}">{{ $invoice->project->name }}</a>@else{{ $invoice->project?->name ?? '-' }}@endif
                {{ $invoice->costCenter ? ' / '.$invoice->costCenter->code : '' }}
            </dd>
            <dt>{{ __('workspace.inv_einvoice') }}</dt><dd class="small">{{ __($eInvoiceState) }}</dd>
        </dl>
        <dl>
            <dt>{{ __('workspace.inv_total') }}</dt><dd>{{ $money($invoice->total_amount) }} <span class="small">({{ __('workspace.inv_vat_amount') }} {{ $money($invoice->vat_amount) }})</span></dd>
            <dt>{{ __('workspace.inv_received') }}</dt><dd>{{ $money($invoice->received_amount) }}</dd>
            <dt>{{ __('workspace.inv_outstanding') }}</dt><dd>{{ $money($invoice->balance_amount) }}</dd>
            <dt>{{ __('workspace.inv_accounting_state') }}</dt>
            <dd>
                @if ($invoice->journalEntry)
                    {{ $invoice->journalEntry->status === 'posted' ? __('workspace.inv_posted') : __('workspace.not_posted') }}
                    @if ($canViewJournals) · <a href="{{ route('admin.accounting.journal-entries.show', $invoice->journalEntry) }}">{{ $invoice->journalEntry->journal_number }}</a>@endif
                @else
                    {{ __('workspace.inv_not_posted') }}
                @endif
            </dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="{{ __('Customer invoice sections') }}">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- 1. Invoice Information --}}
    <div class="card-grid" id="information">
        <x-admin.metric-card color="blue" :value="$money($invoice->taxable_amount)" label="Taxable Amount"/>
        <x-admin.metric-card color="yellow" :value="$money($invoice->vat_amount)" label="Output VAT"/>
        <x-admin.metric-card color="cyan" :value="$money($invoice->total_amount)" label="Total Amount"/>
        <x-admin.metric-card :color="(float) $invoice->balance_amount > 0 && $open ? 'red' : 'green'" :value="$money($invoice->balance_amount)" label="Outstanding Balance"/>
    </div>

    <x-admin.data-table :title="__('workspace.inv_information')" class="detail-table">
        <tbody>
            <tr><th>{{ __('Invoice Number') }}</th><td>{{ $invoice->invoice_number }}</td></tr>
            <tr><th>{{ __('Customer') }}</th><td>{{ $invoice->customer->name }}</td></tr>
            <tr><th>{{ __('Invoice Date') }}</th><td>{{ $invoice->invoice_date->toDateString() }}</td></tr>
            <tr><th>{{ __('Due Date') }}</th><td>{{ $invoice->due_date?->toDateString() ?? '-' }}</td></tr>
            <tr><th>{{ __('Project') }}</th><td>{{ $invoice->project?->name ?? '-' }}</td></tr>
            <tr><th>{{ __('Cost Center') }}</th><td>{{ $invoice->costCenter ? $invoice->costCenter->code.' - '.$invoice->costCenter->name : '-' }}</td></tr>
            <tr><th>{{ __('Subtotal (taxable)') }}</th><td>{{ $money($invoice->taxable_amount) }}</td></tr>
            <tr><th>{{ __('VAT') }} ({{ number_format($invoice->vat_rate, 2) }}%)</th><td>{{ $money($invoice->vat_amount) }}</td></tr>
            <tr><th>{{ __('Total') }}</th><td><strong>{{ $money($invoice->total_amount) }}</strong></td></tr>
            <tr><th>{{ __('Received') }}</th><td>{{ $money($invoice->received_amount) }}</td></tr>
            <tr><th>{{ __('Outstanding') }}</th><td><strong>{{ $money($invoice->balance_amount) }}</strong></td></tr>
            <tr><th>{{ __('Status') }}</th><td><x-admin.status-badge :status="$invoice->payment_status"/> <span class="small">{{ __($paymentState) }}</span></td></tr>
            <tr><th>{{ __('Local e-invoice state') }}</th><td class="small">{{ __($eInvoiceState) }}</td></tr>
            <tr><th>{{ __('Notes') }}</th><td style="white-space:pre-line">{{ $invoice->notes ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- 2. Invoice Lines --}}
    <x-admin.data-table :title="__('workspace.inv_lines')" :subtitle="__('Amounts in SAR; VAT per line at the invoice rate')" id="lines">
        <thead>
            <tr><th>{{ __('Description') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Unit Price') }}</th><th>{{ __('Revenue Account') }}</th><th>{{ __('Cost Center') }}</th><th>{{ __('Taxable') }}</th><th>{{ __('VAT %') }}</th><th>{{ __('VAT') }}</th><th>{{ __('Line Total') }}</th></tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td>{{ number_format($line->quantity, 2) }}</td>
                    <td>{{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ $line->revenueAccount?->label() ?? '-' }}</td>
                    <td>{{ $line->costCenter?->code ?? '-' }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_rate, 2) }}%</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td><strong>{{ number_format($line->total_amount, 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">{{ __('No lines on this invoice.') }}</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    {{-- 3. Customer context --}}
    @if (isset($sections['customer']))
        @php $customer = $invoice->customer; @endphp
        <x-admin.data-table :title="__('workspace.customer')" class="detail-table" id="customer">
            <tbody>
                <tr><th>{{ __('Code / Name') }}</th><td>{{ $customer->code }} — {{ $customer->name }}</td></tr>
                <tr><th>{{ __('VAT Number') }}</th><td>{{ $customer->vat_number ?? '-' }}</td></tr>
                <tr><th>{{ __('CR Number') }}</th><td>{{ $customer->cr_number ?? '-' }}</td></tr>
                <tr><th>{{ __('Contact') }}</th><td>{{ $customer->contact_person ?? '-' }}{{ $customer->phone ? ' · '.$customer->phone : '' }}{{ $customer->email ? ' · '.$customer->email : '' }}</td></tr>
                <tr><th>{{ __('Accepted Payment Types') }}</th><td>{{ __($customer->allowed_payment_types ?? 'Both') }}</td></tr>
                <tr><th>{{ __('workspace.inv_customer_outstanding') }}</th><td>{{ $money($customerOutstanding) }} <span class="small">({{ $customerOpenInvoices }} {{ __('workspace.inv_open_invoices') }})</span></td></tr>
                <tr><th>{{ __('Customer Status') }}</th><td><x-admin.status-badge :status="$customer->status"/></td></tr>
            </tbody>
            <x-slot:footer>
                <a class="btn sm outline" href="{{ route('admin.master.customers.show', $customer) }}">{{ __('workspace.inv_view_customer') }}</a>
                @if ($canManageCustomer)<a class="btn sm outline" href="{{ route('admin.master.customers.edit', $customer) }}#invoices">{{ __('workspace.inv_manage_customer') }}</a>@endif
            </x-slot:footer>
        </x-admin.data-table>
    @endif

    {{-- 4. Project / Cost Center context --}}
    @if (isset($sections['project']))
        @php $project = $invoice->project; @endphp
        <x-admin.data-table :title="__('workspace.inv_project')" class="detail-table" id="project">
            <tbody>
                <tr><th>{{ __('Project') }}</th><td>{{ $project->code }} — {{ $project->name }}</td></tr>
                <tr><th>{{ __('Project Customer') }}</th><td>{{ $project->customer?->name ?? '-' }}</td></tr>
                <tr><th>{{ __('Project Status') }}</th><td><x-admin.status-badge :status="$project->status"/></td></tr>
                <tr><th>{{ __('Cost Center') }}</th><td>{{ $invoice->costCenter ? $invoice->costCenter->code.' - '.$invoice->costCenter->name : '-' }}</td></tr>
                <tr><th>{{ __('Site') }}</th><td class="small">{{ __('Customer invoices carry a project only; a site is not recorded on an invoice.') }}</td></tr>
            </tbody>
            @if ($canViewProject)
                <x-slot:footer>
                    <a class="btn sm outline" href="{{ route('admin.master.projects.show', $project) }}#invoices">{{ __('workspace.inv_view_project') }}</a>
                </x-slot:footer>
            @endif
        </x-admin.data-table>
    @endif

    {{-- 5. VAT --}}
    <x-admin.data-table :title="__('workspace.inv_vat')" :subtitle="__('workspace.inv_vat_help')" class="detail-table" id="vat">
        <tbody>
            <tr><th>{{ __('VAT Rate') }}</th><td>{{ number_format($invoice->vat_rate, 2) }}%</td></tr>
            <tr><th>{{ __('Taxable Amount') }}</th><td>{{ $money($invoice->taxable_amount) }}</td></tr>
            <tr><th>{{ __('Output VAT') }}</th><td>{{ $money($invoice->vat_amount) }}</td></tr>
            <tr><th>{{ __('Total incl. VAT') }}</th><td>{{ $money($invoice->total_amount) }}</td></tr>
            <tr><th>{{ __('VAT treatment') }}</th><td>{{ (float) $invoice->vat_amount > 0 ? __('Standard rated output VAT (account 2210)') : __('Zero VAT amount: no VAT ledger row is created') }}</td></tr>
            <tr><th>{{ __('Recorded in VAT ledger') }}</th><td>{{ $invoice->journalEntry ? __('workspace.inv_vat_ledger_yes') : __('workspace.inv_vat_ledger_no') }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- 6. Accounting --}}
    @if (isset($sections['accounting']))
        <x-admin.data-table :title="__('workspace.inv_accounting')" :subtitle="__('workspace.inv_accounting_help')" id="accounting">
            @if ($invoice->journalEntry)
                <thead>
                    <tr><th>{{ __('Account') }}</th><th>{{ __('Project / Cost Center') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($invoice->journalEntry->lines as $line)
                        <tr>
                            <td>{{ $line->account->label() }}</td>
                            <td class="small">{{ $invoice->project?->name ?? '-' }}{{ $line->costCenter ? ' / '.$line->costCenter->code : '' }}</td>
                            <td>{{ (float) $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                            <td>{{ (float) $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>{{ $invoice->journalEntry->journal_number }}</strong> · {{ $invoice->journalEntry->journal_date?->toDateString() }} · <x-admin.status-badge :status="$invoice->journalEntry->status"/></td>
                        <td></td>
                        <td><strong>{{ number_format($invoice->journalEntry->total_debit, 2) }}</strong></td>
                        <td><strong>{{ number_format($invoice->journalEntry->total_credit, 2) }}</strong></td>
                    </tr>
                </tbody>
                <x-slot:footer>
                    <a class="btn sm primary" href="{{ route('admin.accounting.journal-entries.show', $invoice->journalEntry) }}">{{ __('workspace.inv_view_journal') }}</a>
                </x-slot:footer>
            @else
                <tbody>
                    <tr><td class="table-empty">{{ __('workspace.inv_no_journal') }}</td></tr>
                </tbody>
            @endif
        </x-admin.data-table>
    @endif

    {{-- 7. Receipts --}}
    <x-admin.data-table :title="__('workspace.inv_receipts')" :subtitle="__('workspace.inv_receipts_help')" id="receipts">
        <thead>
            <tr><th>{{ __('Date') }}</th><th>{{ __('Account') }}</th><th>{{ __('Method') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Journal') }}</th></tr>
        </thead>
        <tbody>
            @forelse ($receipts as $receipt)
                <tr>
                    <td>{{ $receipt->receipt_date->toDateString() }}</td>
                    <td>{{ $receipt->receiptAccount?->label() ?? '-' }}</td>
                    <td>{{ $receipt->payment_method ?? '-' }}</td>
                    <td>{{ $money($receipt->amount) }}</td>
                    <td>{{ $receipt->reference_number ?? '-' }}</td>
                    <td>
                        @if ($receipt->journalEntry)
                            @if ($canViewJournals)<a href="{{ route('admin.accounting.journal-entries.show', $receipt->journalEntry) }}">{{ $receipt->journalEntry->journal_number }}</a>@else{{ __('workspace.inv_posted') }}@endif
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">{{ __('workspace.inv_no_receipts') }}</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">{{ __('Showing the latest :count of :total', ['count' => $receipts->count(), 'total' => $receipts->total()]) }}</span>
            @if ($receipts->hasPages()){{ $receipts->links() }}@endif
            @if ($canReceive)<a class="btn sm primary" href="{{ route('admin.accounting.accounts-receivable.receipt', ['accounts_receivable' => $invoice, 'return_to' => $origin]) }}">{{ __('workspace.inv_record_receipt') }}</a>@endif
        </x-slot:footer>
    </x-admin.data-table>

    {{-- 8. Balance / Ageing --}}
    <x-admin.data-table :title="__('workspace.inv_balance')" class="detail-table" id="balance">
        <tbody>
            <tr><th>{{ __('Invoice Total') }}</th><td>{{ $money($invoice->total_amount) }}</td></tr>
            <tr><th>{{ __('customer_notes.cash_received') }}</th><td>{{ $money($invoice->received_amount) }} <span class="small">({{ trans_choice(':count receipt|:count receipts', $receipts->total(), ['count' => $receipts->total()]) }})</span></td></tr>
            <tr><th>{{ __('customer_notes.credit_applied_to_invoice') }}</th><td>{{ $money($creditAllocated) }} <span class="small">{{ __('customer_notes.credit_applied_help') }}</span></td></tr>
            <tr><th>{{ __('Outstanding') }}</th><td><strong>{{ $money($invoice->balance_amount) }}</strong></td></tr>
            <tr><th>{{ __('Due Date') }}</th><td>{{ $invoice->due_date?->toDateString() ?? '-' }}</td></tr>
            <tr><th>{{ __('workspace.inv_days_overdue') }}</th><td>@if ($overdueDays > 0)<span class="badge red">{{ $overdueDays }}</span>@else 0 @endif</td></tr>
            @if ($ageingBucket)
                <tr><th>{{ __('workspace.inv_ageing_bucket') }}</th><td>{{ $ageingBucket }}</td></tr>
            @endif
            <tr><th>{{ __('Payment state') }}</th><td>{{ __($paymentState) }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- 8b. Adjustments: credit / debit notes --}}
    <x-admin.data-table :title="__('customer_notes.adjustments')" :subtitle="__('customer_notes.adjustments_help')" id="adjustments">
        <thead><tr><th>{{ __('Note') }}</th><th>{{ __('Type') }}</th><th>{{ __('Date') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Taxable') }}</th><th>{{ __('VAT') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th>{{ __('zatca.label') }}</th><th>{{ __('Actions') }}</th></tr></thead>
        <tbody>
            @forelse ($adjustmentNotes as $note)
                <tr>
                    <td>{{ $note->note_number }}</td>
                    <td>{{ __($note->typeLabel()) }}</td>
                    <td>{{ $note->note_date->toDateString() }}</td>
                    <td class="small">{{ __($note->reasonLabel()) }}</td>
                    <td>{{ $money($note->taxable_amount) }}</td>
                    <td>{{ $money($note->vat_amount) }}</td>
                    <td>{{ $money($note->total_amount) }}</td>
                    <td><x-admin.status-badge :status="$note->status"/></td>
                    <td>@if ($note->zatcaRecord)<span class="badge {{ $note->zatcaRecord->statusColor() }}">{{ $note->zatcaRecord->statusLabel() }}</span>@else <span class="small">-</span>@endif</td>
                    <td><a class="btn sm outline" href="{{ route('admin.accounting.accounts-receivable.notes.show', ['note' => $note, 'return_to' => $selfUrl]) }}">{{ __('workspace.view') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="table-empty">{{ $invoice->isCorrectable() ? __('customer_notes.no_notes') : __('customer_notes.not_correctable') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><th colspan="4">{{ __('customer_notes.credited') }} / {{ __('customer_notes.debited') }}</th><td colspan="3">{{ $money($adjustments['credited']) }} / {{ $money($adjustments['debited']) }}</td><td colspan="3">@if ($adjustments['credit_unapplied'] > 0)<span class="badge yellow">{{ __('customer_notes.unapplied') }}: {{ $money($adjustments['credit_unapplied']) }}</span>@endif</td></tr>
            <tr><th colspan="4">{{ __('customer_notes.adjusted_balance') }}</th><td colspan="6"><strong>{{ $money($invoice->balance_amount) }}</strong> <span class="small">= {{ $money($invoice->total_amount) }} + {{ $money($adjustments['debited']) }} − {{ $money($adjustments['credit_applied']) }} − {{ $money($creditAllocated) }} ({{ __('customer_notes.credit_applied_to_invoice') }}) − {{ $money($invoice->received_amount) }}</span></td></tr>
        </tfoot>
    </x-admin.data-table>

    {{-- 8c. Customer credit applied from other credit notes --}}
    <x-admin.data-table :title="__('customer_notes.applied_credits')" :subtitle="__('customer_notes.applied_credits_help')" id="applied-credits">
        <thead><tr><th>{{ __('customer_notes.type_credit') }}</th><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Reference') }}</th><th>{{ __('By') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @forelse ($creditAllocations as $allocation)
                <tr>
                    <td><a href="{{ route('admin.accounting.accounts-receivable.notes.show', ['note' => $allocation->note, 'return_to' => $selfUrl]) }}">{{ $allocation->note?->note_number }}</a></td>
                    <td>{{ $allocation->allocation_date->toDateString() }}</td>
                    <td>{{ $money($allocation->amount) }}</td>
                    <td>{{ $allocation->reference ?? '-' }}</td>
                    <td>{{ $allocation->createdBy?->name ?? '-' }}</td>
                    <td>@if ($allocation->isReversed())<span class="badge gray">{{ __('customer_notes.reversed') }}</span> <span class="small">{{ $allocation->reversal_reason }}</span>@else <span class="badge green">{{ __('customer_notes.applied') }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">{{ __('customer_notes.no_applied_credits') }}</td></tr>
            @endforelse
        </tbody>
        @if ($canCreateNote)
            <x-slot:footer>
                <a class="btn sm outline" href="{{ route('admin.accounting.accounts-receivable.notes.create', ['invoice' => $invoice->id, 'type' => 'credit', 'return_to' => $selfUrl]) }}">{{ __('customer_notes.new_credit') }}</a>
                <a class="btn sm outline" href="{{ route('admin.accounting.accounts-receivable.notes.create', ['invoice' => $invoice->id, 'type' => 'debit', 'return_to' => $selfUrl]) }}">{{ __('customer_notes.new_debit') }}</a>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- 9. ZATCA e-invoice --}}
    @if (isset($sections['zatca']))
        <x-admin.data-table :title="__('zatca.section')" :subtitle="__('workspace.inv_zatca_help')" class="detail-table" id="zatca">
            @if ($record)
                <tbody>
                    <tr><th>{{ __('zatca.invoice_type') }}</th><td>{{ __('zatca.type_'.$invoice->invoice_type) }}</td></tr>
                    <tr><th>{{ __('zatca.uuid') }}</th><td class="small">{{ $record->uuid }}</td></tr>
                    <tr><th>{{ __('zatca.environment') }}</th><td>{{ $record->environment ? __('zatca.env_'.$record->environment) : '-' }}</td></tr>
                    <tr><th>{{ __('Status') }}</th><td><span class="badge {{ $record->statusColor() }}">{{ $record->statusLabel() }}</span>@if ($record->accepted_with_warnings) <span class="small">({{ __('zatca.warnings') }})</span>@endif @if (! $record->isAccepted())<span class="small">· {{ __('zatca.not_verified') }}</span>@endif</td></tr>
                    <tr><th>{{ __('zatca.validation') }}</th><td>{{ $record->validation_status ? ucfirst($record->validation_status) : '-' }}@if ($record->validated_at) <span class="small">({{ $record->validated_at->format('Y-m-d H:i') }})</span>@endif</td></tr>
                    <tr><th>{{ __('zatca.signature') }}</th><td>{{ $record->signed_at ? $record->signed_at->format('Y-m-d H:i').' · ICV '.$record->icv : '-' }}</td></tr>
                    <tr><th>{{ __('zatca.submission') }}</th><td>{{ $record->submitted_at?->format('Y-m-d H:i') ?? '-' }} · {{ __('zatca.attempts') }}: {{ $record->submission_attempts }}</td></tr>
                    <tr><th>{{ __('zatca.zatca_reference') }}</th><td>{{ $record->zatca_status ?? '-' }} {{ $record->cleared_at ? '· '.__('zatca.cleared_at').' '.$record->cleared_at->format('Y-m-d H:i') : '' }}{{ $record->reported_at ? '· '.__('zatca.reported_at').' '.$record->reported_at->format('Y-m-d H:i') : '' }}</td></tr>
                    <tr><th>{{ __('zatca.warnings') }} / {{ __('zatca.errors') }}</th><td>{{ count($record->response_warnings ?? []) }} / {{ count($record->response_errors ?? []) }}</td></tr>
                    @if ($record->last_error_message && ! $record->isAccepted())<tr><th>{{ __('zatca.last_error') }}</th><td>{{ $record->last_error_message }}</td></tr>@endif
                    @if ($invoice->zatca_locked_at)<tr><th>{{ __('Locked') }}</th><td class="small">{{ __('zatca.locked') }}</td></tr>@endif
                </tbody>
                <x-slot:footer>
                    <a class="btn sm outline" href="{{ route('admin.accounting.zatca.show', $record) }}">{{ __('zatca.record_title') }}</a>
                    @if (($canPostZatca ?? false) && ! $record->isAccepted() && ! $record->isLocked())
                        <form method="POST" action="{{ route('admin.accounting.zatca.generate', $record) }}" style="display:inline">@csrf<button class="btn sm outline">{{ __('zatca.generate') }}</button></form>
                        <form method="POST" action="{{ route('admin.accounting.zatca.submit', $record) }}" style="display:inline">@csrf<button class="btn sm primary">{{ __('zatca.submit') }}</button></form>
                    @endif
                    @if (($canRetryZatca ?? false) && in_array($record->status, ['signed', 'submission_pending', 'rejected', 'outcome_unknown'], true))
                        <form method="POST" action="{{ route('admin.accounting.zatca.retry', $record) }}" style="display:inline">@csrf<button class="btn sm warning">{{ __('zatca.retry') }}</button></form>
                    @endif
                    @if (($canExportZatca ?? false) && $record->signed_xml_path)
                        <a class="btn sm outline" href="{{ route('admin.accounting.zatca.xml', [$record, 'signed']) }}">{{ __('zatca.download_signed') }}</a>
                    @endif
                </x-slot:footer>
            @else
                <tbody>
                    <tr><td class="table-empty">{{ __('zatca.no_record') }}</td></tr>
                </tbody>
            @endif
        </x-admin.data-table>
    @endif
    {{-- 10. Activity --}}
    @if ($activity !== null)
        <x-admin.data-table :title="__('workspace.activity')" :subtitle="__('workspace.inv_activity_help')" id="activity">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Module') }}</th><th>{{ __('Action') }}</th><th>{{ __('Details') }}</th></tr></thead>
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
                    <tr><td colspan="5" class="table-empty">{{ __('workspace.inv_no_activity') }}</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <a class="btn sm outline" href="{{ route('admin.activity-logs.index', ['search' => $invoice->invoice_number]) }}">{{ __('View all') }}</a>
            </x-slot:footer>
        </x-admin.data-table>
    @endif
@endsection
