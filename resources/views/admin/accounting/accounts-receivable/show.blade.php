@extends('layouts.admin')

@section('title', 'Customer Invoice')
@section('breadcrumb', 'Accounting / Accounts Receivable / Customer Invoice')

@section('content')
    @php
        /** @var \App\Models\CustomerInvoice $invoice */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $origin = $returnTo ?? $selfUrl;
        $record = $invoice->zatcaRecord;
    @endphp

    <x-admin.page-header :title="'Invoice: '.$invoice->invoice_number" :description="__('workspace.inv_readonly')">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.edit', ['accounts_receivable' => $invoice, 'return_to' => $returnTo]) }}">{{ __('workspace.inv_edit_draft') }}</a>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('admin.accounting.accounts-receivable.approve', $invoice) }}">
                @csrf
                <button type="submit" class="btn primary">Approve &amp; Post</button>
            </form>
        @endif
        @if ($canReceive)
            <a class="btn primary" href="{{ route('admin.accounting.accounts-receivable.receipt', ['accounts_receivable' => $invoice, 'return_to' => $origin]) }}">{{ __('workspace.inv_record_receipt') }}</a>
        @endif
        @if ($canViewJournals && $invoice->journalEntry)
            <a class="btn outline" href="{{ route('admin.accounting.journal-entries.show', $invoice->journalEntry) }}">{{ __('workspace.inv_view_journal') }}</a>
        @endif
        @if ($canReopen)
            <form method="POST" action="{{ route('admin.accounting.accounts-receivable.reopen', $invoice) }}" onsubmit="var reason = prompt('Reason for reopening this invoice (kept on the record):'); if (!reason) return false; this.reason.value = reason;">
                @csrf
                <input type="hidden" name="reason" value=""/>
                <button type="submit" class="btn danger" title="Reverse the posting, cancel the local e-invoice record and return the invoice to draft">Reopen for Correction</button>
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
                <span class="small">· {{ $paymentState }}</span>
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
            <dt>{{ __('workspace.inv_einvoice') }}</dt><dd class="small">{{ $eInvoiceState }}</dd>
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

    <nav class="tabs workspace-nav" aria-label="Customer invoice sections">
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
            <tr><th>Invoice Number</th><td>{{ $invoice->invoice_number }}</td></tr>
            <tr><th>Customer</th><td>{{ $invoice->customer->name }}</td></tr>
            <tr><th>Invoice Date</th><td>{{ $invoice->invoice_date->toDateString() }}</td></tr>
            <tr><th>Due Date</th><td>{{ $invoice->due_date?->toDateString() ?? '-' }}</td></tr>
            <tr><th>Project</th><td>{{ $invoice->project?->name ?? '-' }}</td></tr>
            <tr><th>Cost Center</th><td>{{ $invoice->costCenter ? $invoice->costCenter->code.' - '.$invoice->costCenter->name : '-' }}</td></tr>
            <tr><th>Subtotal (taxable)</th><td>{{ $money($invoice->taxable_amount) }}</td></tr>
            <tr><th>VAT ({{ number_format($invoice->vat_rate, 2) }}%)</th><td>{{ $money($invoice->vat_amount) }}</td></tr>
            <tr><th>Total</th><td><strong>{{ $money($invoice->total_amount) }}</strong></td></tr>
            <tr><th>Received</th><td>{{ $money($invoice->received_amount) }}</td></tr>
            <tr><th>Outstanding</th><td><strong>{{ $money($invoice->balance_amount) }}</strong></td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$invoice->payment_status"/> <span class="small">{{ $paymentState }}</span></td></tr>
            <tr><th>Local e-invoice state</th><td class="small">{{ $eInvoiceState }}</td></tr>
            <tr><th>Notes</th><td style="white-space:pre-line">{{ $invoice->notes ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- 2. Invoice Lines --}}
    <x-admin.data-table :title="__('workspace.inv_lines')" subtitle="Amounts in SAR; VAT per line at the invoice rate" id="lines">
        <thead>
            <tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Revenue Account</th><th>Cost Center</th><th>Taxable</th><th>VAT %</th><th>VAT</th><th>Line Total</th></tr>
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
                <tr><td colspan="9" class="table-empty">No lines on this invoice.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    {{-- 3. Customer context --}}
    @if (isset($sections['customer']))
        @php $customer = $invoice->customer; @endphp
        <x-admin.data-table :title="__('workspace.customer')" class="detail-table" id="customer">
            <tbody>
                <tr><th>Code / Name</th><td>{{ $customer->code }} — {{ $customer->name }}</td></tr>
                <tr><th>VAT Number</th><td>{{ $customer->vat_number ?? '-' }}</td></tr>
                <tr><th>CR Number</th><td>{{ $customer->cr_number ?? '-' }}</td></tr>
                <tr><th>Contact</th><td>{{ $customer->contact_person ?? '-' }}{{ $customer->phone ? ' · '.$customer->phone : '' }}{{ $customer->email ? ' · '.$customer->email : '' }}</td></tr>
                <tr><th>Accepted Payment Types</th><td>{{ $customer->allowed_payment_types ?? 'Both' }}</td></tr>
                <tr><th>{{ __('workspace.inv_customer_outstanding') }}</th><td>{{ $money($customerOutstanding) }} <span class="small">({{ $customerOpenInvoices }} {{ __('workspace.inv_open_invoices') }})</span></td></tr>
                <tr><th>Customer Status</th><td><x-admin.status-badge :status="$customer->status"/></td></tr>
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
                <tr><th>Project</th><td>{{ $project->code }} — {{ $project->name }}</td></tr>
                <tr><th>Project Customer</th><td>{{ $project->customer?->name ?? '-' }}</td></tr>
                <tr><th>Project Status</th><td><x-admin.status-badge :status="$project->status"/></td></tr>
                <tr><th>Cost Center</th><td>{{ $invoice->costCenter ? $invoice->costCenter->code.' - '.$invoice->costCenter->name : '-' }}</td></tr>
                <tr><th>Site</th><td class="small">Customer invoices carry a project only; a site is not recorded on an invoice.</td></tr>
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
            <tr><th>VAT Rate</th><td>{{ number_format($invoice->vat_rate, 2) }}%</td></tr>
            <tr><th>Taxable Amount</th><td>{{ $money($invoice->taxable_amount) }}</td></tr>
            <tr><th>Output VAT</th><td>{{ $money($invoice->vat_amount) }}</td></tr>
            <tr><th>Total incl. VAT</th><td>{{ $money($invoice->total_amount) }}</td></tr>
            <tr><th>VAT treatment</th><td>{{ (float) $invoice->vat_amount > 0 ? 'Standard rated output VAT (account 2210)' : 'Zero VAT amount: no VAT ledger row is created' }}</td></tr>
            <tr><th>Recorded in VAT ledger</th><td>{{ $invoice->journalEntry ? __('workspace.inv_vat_ledger_yes') : __('workspace.inv_vat_ledger_no') }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- 6. Accounting --}}
    @if (isset($sections['accounting']))
        <x-admin.data-table :title="__('workspace.inv_accounting')" :subtitle="__('workspace.inv_accounting_help')" id="accounting">
            @if ($invoice->journalEntry)
                <thead>
                    <tr><th>Account</th><th>Project / Cost Center</th><th>Debit</th><th>Credit</th></tr>
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
            <tr><th>Date</th><th>Account</th><th>Method</th><th>Amount</th><th>Reference</th><th>Journal</th></tr>
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
            <tr><th>Invoice Total</th><td>{{ $money($invoice->total_amount) }}</td></tr>
            <tr><th>Received</th><td>{{ $money($invoice->received_amount) }} <span class="small">({{ $receipts->total() }} receipt{{ $receipts->total() === 1 ? '' : 's' }})</span></td></tr>
            <tr><th>Outstanding</th><td><strong>{{ $money($invoice->balance_amount) }}</strong></td></tr>
            <tr><th>Due Date</th><td>{{ $invoice->due_date?->toDateString() ?? '-' }}</td></tr>
            <tr><th>{{ __('workspace.inv_days_overdue') }}</th><td>@if ($overdueDays > 0)<span class="badge red">{{ $overdueDays }}</span>@else 0 @endif</td></tr>
            @if ($ageingBucket)
                <tr><th>{{ __('workspace.inv_ageing_bucket') }}</th><td>{{ $ageingBucket }}</td></tr>
            @endif
            <tr><th>Payment state</th><td>{{ $paymentState }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- 9. Local e-invoice record --}}
    @if (isset($sections['zatca']))
        <x-admin.data-table :title="__('workspace.inv_zatca')" :subtitle="__('workspace.inv_zatca_help')" class="detail-table" id="zatca">
            @if ($record)
                <tbody>
                    <tr><th>UUID</th><td class="small">{{ $record->uuid }}</td></tr>
                    <tr><th>Local clearance status</th><td><x-admin.status-badge :status="$record->clearance_status"/> <span class="small">local record, not verified live</span></td></tr>
                    <tr><th>QR payload</th><td>{{ $record->qr_code_data ? 'Local payload stored (not a ZATCA-issued QR)' : 'Not generated' }}</td></tr>
                    <tr><th>XML</th><td>{{ $record->xml_file_path ? 'Path reference only: '.$record->xml_file_path.' (file not generated)' : 'Not generated' }}</td></tr>
                    <tr><th>Digital signature</th><td>{{ $record->digital_signature_status === 'signed' ? 'Marked "signed" locally; no certificate or real signing exists' : ucfirst($record->digital_signature_status) }}</td></tr>
                    <tr><th>Local hash</th><td class="small">{{ $record->tamper_proof_hash ?? '-' }}</td></tr>
                    <tr><th>Retries</th><td>{{ $record->retry_count }}</td></tr>
                    <tr><th>Response</th><td>{{ $record->zatca_response_code ?? '-' }} {{ $record->zatca_response_message ?? '' }}</td></tr>
                    <tr><th>Failed reason</th><td>{{ $record->failed_reason ?? '-' }}</td></tr>
                </tbody>
                <x-slot:footer>
                    <a class="btn sm outline" href="{{ route('admin.accounting.zatca.show', $record) }}">Open Local Record</a>
                </x-slot:footer>
            @else
                <tbody>
                    <tr><td class="table-empty">{{ __('workspace.inv_no_zatca') }}</td></tr>
                </tbody>
            @endif
        </x-admin.data-table>
    @endif

    {{-- 10. Activity --}}
    @if ($activity !== null)
        <x-admin.data-table :title="__('workspace.activity')" :subtitle="__('workspace.inv_activity_help')" id="activity">
            <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
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
