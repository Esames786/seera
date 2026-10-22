@extends('layouts.admin')

@section('title', $note->typeLabel())
@section('breadcrumb', 'Accounting / Accounts Receivable / '.__('customer_notes.title').' / '.$note->note_number)

@section('content')
    @php
        /** @var \App\Models\CustomerAdjustmentNote $note */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $origin = $returnTo ?? $selfUrl;
        $record = $note->zatcaRecord;
    @endphp

    <x-admin.page-header :title="$note->typeLabel().': '.$note->note_number" :description="__('customer_notes.readonly')">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.notes.edit', ['note' => $note, 'return_to' => $returnTo]) }}">{{ __('customer_notes.edit_draft') }}</a>
        @endif
        @if ($canPost)
            <form method="POST" action="{{ route('admin.accounting.accounts-receivable.notes.approve', ['note' => $note, 'return_to' => $returnTo]) }}">
                @csrf
                <button type="submit" class="btn primary" title="{{ __('customer_notes.post_help') }}">{{ __('customer_notes.post') }}</button>
            </form>
        @endif
        @if ($canViewJournals && $note->journalEntry)
            <a class="btn outline" href="{{ route('admin.accounting.journal-entries.show', $note->journalEntry) }}">{{ __('workspace.inv_view_journal') }}</a>
        @endif
        <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.notes.print', $note) }}" target="_blank">{{ __('customer_notes.print') }}</a>
        @if ($returnTo)
            <a class="btn outline" href="{{ $returnTo }}">{{ __('workspace.inv_back_origin') }}</a>
        @else
            <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.notes.index') }}">{{ __('customer_notes.back_list') }}</a>
        @endif
    </x-admin.page-header>

    @if ($errors->any())
        <div class="alert flash"><ul style="margin:0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $note->note_number }}</h2>
            <div>
                <x-admin.status-badge :status="$note->status"/>
                <span class="small">· {{ $state }}</span>
                @if ($note->isZatcaLocked())<span class="small">· {{ __('customer_notes.immutable') }}</span>@endif
            </div>
        </div>
        <dl>
            <dt>{{ __('workspace.customer') }}</dt>
            <dd>{{ $note->customer->name }} @if ($canViewCustomer)<a class="small" href="{{ route('admin.master.customers.show', $note->customer) }}">{{ __('workspace.view') }}</a>@endif</dd>
            <dt>{{ __('customer_notes.original_invoice') }}</dt>
            <dd>{{ $invoice?->invoice_number ?? '-' }} @if ($invoice && $canViewInvoice)<a class="small" href="{{ route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $invoice, 'return_to' => $selfUrl]) }}">{{ __('workspace.view') }}</a>@endif</dd>
            <dt>{{ __('customer_notes.note_date') }}</dt><dd>{{ $note->note_date->toDateString() }}</dd>
            <dt>{{ __('workspace.inv_total') }}</dt><dd><strong>{{ $money($note->total_amount) }}</strong> <span class="small">({{ $note->isCredit() ? '−' : '+' }} on the receivable)</span></dd>
            <dt>{{ __('workspace.inv_einvoice') }}</dt><dd>{{ $eInvoiceState }}</dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav">
        @foreach ($sections as $key => $label)
            <a href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- 1. Summary --}}
    <x-admin.data-table :title="__('customer_notes.sec_summary')" class="detail-table" id="summary">
        <tbody>
            <tr><th>Type</th><td>{{ $note->typeLabel() }} <span class="small">({{ __('zatca.type_'.$note->invoice_type) }} · {{ $note->zatcaTypeCode() }})</span></td></tr>
            <tr><th>{{ __('customer_notes.reason_code') }}</th><td>{{ $note->reasonLabel() }}</td></tr>
            <tr><th>{{ __('customer_notes.reason') }}</th><td>{{ $note->reason }}</td></tr>
            <tr><th>{{ __('zatca.supply_date') }}</th><td>{{ $note->supply_date?->toDateString() ?? '-' }}</td></tr>
            <tr><th>Taxable / VAT / Total</th><td>{{ $money($note->taxable_amount) }} / {{ $money($note->vat_amount) }} / <strong>{{ $money($note->total_amount) }}</strong></td></tr>
            @if ($note->isPosted())
                <tr><th>{{ __('customer_notes.applied') }}</th><td>{{ $money($note->applied_amount) }}</td></tr>
                @if ((float) $note->unapplied_amount > 0)
                    <tr><th>{{ __('customer_notes.unapplied') }}</th><td><span class="badge yellow">{{ $money($note->unapplied_amount) }}</span> <span class="small">{{ __('customer_notes.unapplied_help') }}</span></td></tr>
                @endif
                <tr><th>Posted</th><td>{{ $note->posted_at?->format('Y-m-d H:i') }} @if ($note->postedBy)by {{ $note->postedBy->name }}@endif</td></tr>
            @endif
            @if ($note->notes)<tr><th>Notes</th><td>{{ $note->notes }}</td></tr>@endif
        </tbody>
    </x-admin.data-table>

    {{-- 2. Original invoice --}}
    <x-admin.data-table :title="__('customer_notes.sec_invoice')" class="detail-table" id="invoice">
        <tbody>
            @if ($invoice)
                <tr><th>Invoice</th><td>{{ $invoice->invoice_number }} · {{ $invoice->invoice_date->toDateString() }} · <x-admin.status-badge :status="$invoice->payment_status"/></td></tr>
                <tr><th>Invoice total / received / outstanding</th><td>{{ $money($invoice->total_amount) }} / {{ $money($invoice->received_amount) }} / <strong>{{ $money($invoice->balance_amount) }}</strong></td></tr>
                <tr><th>{{ __('workspace.inv_project') }}</th><td>{{ $note->project?->name ?? '-' }} / {{ $note->costCenter?->name ?? '-' }}</td></tr>
            @else
                <tr><td class="table-empty">-</td></tr>
            @endif
        </tbody>
    </x-admin.data-table>

    {{-- 3. Lines --}}
    <x-admin.data-table :title="__('customer_notes.sec_lines')" id="lines">
        <thead><tr><th>#</th><th>{{ __('customer_notes.line_source') }}</th><th>Description</th><th>Qty</th><th>Unit price</th><th>Taxable</th><th>VAT</th><th>Total</th><th>Revenue account</th><th>Cost center</th></tr></thead>
        <tbody>
            @foreach ($note->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="small">{{ $line->invoiceLine?->description ?? __('customer_notes.new_line') }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ number_format($line->quantity, 2) }}</td>
                    <td>{{ number_format($line->unit_price, 2) }}</td>
                    <td>{{ number_format($line->taxable_amount, 2) }}</td>
                    <td>{{ number_format($line->vat_amount, 2) }}</td>
                    <td>{{ number_format($line->total_amount, 2) }}</td>
                    <td class="small">{{ $line->revenueAccount?->account_code ?? $line->invoiceLine?->revenueAccount?->account_code ?? '4100' }}</td>
                    <td class="small">{{ $line->costCenter?->name ?? $line->invoiceLine?->costCenter?->name ?? $note->costCenter?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.data-table>

    {{-- 4. VAT --}}
    <x-admin.data-table :title="__('customer_notes.sec_vat')" class="detail-table" id="vat">
        <tbody>
            <tr><th>Rate</th><td>{{ number_format($note->vat_rate, 2) }}%</td></tr>
            <tr><th>VAT correction</th><td>{{ $note->isCredit() ? '−' : '+' }}{{ $money($note->vat_amount) }} <span class="small">{{ $note->isPosted() ? 'recorded in the VAT ledger when the note was posted' : 'recorded when the note is posted' }}</span></td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- 5. Accounting --}}
    @if (isset($sections['accounting']))
        <x-admin.data-table :title="__('customer_notes.sec_accounting')" id="accounting">
            <thead><tr><th>Account</th><th>Description</th><th>Debit</th><th>Credit</th><th>Cost center</th></tr></thead>
            <tbody>
                @forelse ($note->journalEntry?->lines ?? [] as $jl)
                    <tr><td>{{ $jl->account?->label() ?? '-' }}</td><td>{{ $jl->description }}</td><td>{{ number_format($jl->debit, 2) }}</td><td>{{ number_format($jl->credit, 2) }}</td><td>{{ $jl->costCenter?->name ?? '-' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="table-empty">{{ __('workspace.inv_no_journal') }}</td></tr>
                @endforelse
            </tbody>
            @if ($note->journalEntry)
                <x-slot:footer><span class="small">{{ $note->journalEntry->journal_number }} · {{ $note->journalEntry->status }}</span></x-slot:footer>
            @endif
        </x-admin.data-table>
    @endif

    {{-- 5b. Credit resolution: what happened to the unapplied credit --}}
    @if (isset($sections['resolution']) && $credit)
        <x-admin.data-table :title="__('customer_notes.sec_resolution')" :subtitle="__('customer_notes.credits_help')" class="detail-table" id="resolution">
            <tbody>
                <tr><th>{{ __('customer_notes.credit_amount') }}</th><td>{{ $money($credit['credit']) }} <span class="small">{{ __('customer_notes.unapplied_help') }}</span></td></tr>
                <tr><th>{{ __('customer_notes.applied_to_invoices') }}</th><td>{{ $money($credit['applied']) }}</td></tr>
                <tr><th>{{ __('customer_notes.refunded') }}</th><td>{{ $money($credit['refunded']) }}</td></tr>
                <tr><th>{{ __('customer_notes.available') }}</th><td><strong>{{ $money($credit['available']) }}</strong> <span class="badge {{ $credit['status'] === 'fully_used' ? 'gray' : ($credit['status'] === 'partially_used' ? 'yellow' : 'purple') }}">{{ __('customer_notes.credit_status_'.$credit['status']) }}</span></td></tr>
            </tbody>
        </x-admin.data-table>

        <x-admin.data-table :title="__('customer_notes.applications')" :subtitle="__('customer_notes.apply_credit_help')" id="applications">
            <thead><tr><th>{{ __('customer_notes.target_invoice') }}</th><th>Date</th><th>Amount</th><th>Reference</th><th>By</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($allocations as $allocation)
                    <tr>
                        <td>@if ($allocation->invoice)<a href="{{ route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $allocation->invoice, 'return_to' => $selfUrl]) }}">{{ $allocation->invoice->invoice_number }}</a>@else - @endif</td>
                        <td>{{ $allocation->allocation_date->toDateString() }}</td>
                        <td>{{ $money($allocation->amount) }}</td>
                        <td>{{ $allocation->reference ?? '-' }}</td>
                        <td>{{ $allocation->createdBy?->name ?? '-' }}</td>
                        <td>@if ($allocation->isReversed())<span class="badge gray">{{ __('customer_notes.reversed') }}</span> <span class="small">{{ $allocation->reversal_reason }}</span>@else <span class="badge green">{{ __('customer_notes.applied') }}</span>@endif</td>
                        <td>
                            @if ($canReverseCredit && ! $allocation->isReversed())
                                <form method="POST" action="{{ route('admin.accounting.accounts-receivable.credits.allocations.reverse', [$note, $allocation]) }}" style="display:inline" onsubmit="var r = prompt(@json(__('customer_notes.reverse_allocation_prompt'))); if (!r) return false; this.reason.value = r;">
                                    @csrf<input type="hidden" name="reason" value=""/><button class="btn sm danger">{{ __('customer_notes.reverse') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table-empty">{{ __('customer_notes.no_allocations') }}</td></tr>
                @endforelse
            </tbody>
            @if ($canAllocateCredit && $credit['available'] > 0)
                <x-slot:footer>
                    <form method="POST" action="{{ route('admin.accounting.accounts-receivable.credits.allocate', $note) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}"/>
                        <div><label class="small">{{ __('customer_notes.target_invoice') }}</label>
                            <select name="customer_invoice_id" class="select" required>
                                @forelse ($eligibleTargets as $target)
                                    <option value="{{ $target->id }}">{{ $target->invoice_number }} · {{ __('customer_notes.unapplied') === '' ? '' : '' }}{{ 'SAR '.number_format($target->balance_amount, 2) }} open</option>
                                @empty
                                    <option value="">{{ __('customer_notes.no_targets') }}</option>
                                @endforelse
                            </select></div>
                        <div><label class="small">Amount</label><input name="amount" type="number" step="0.01" min="0.01" max="{{ $credit['available'] }}" class="input" value="{{ old('amount', $credit['available']) }}" required/></div>
                        <div><label class="small">{{ __('customer_notes.allocation_date') }}</label><input name="allocation_date" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old('allocation_date', now()->toDateString()) }}" required/></div>
                        <div><label class="small">Reference</label><input name="reference" class="input" value="{{ old('reference') }}"/></div>
                        <button class="btn sm primary" @disabled($eligibleTargets->isEmpty())>{{ __('customer_notes.apply_credit') }}</button>
                    </form>
                </x-slot:footer>
            @endif
        </x-admin.data-table>

        <x-admin.data-table :title="__('customer_notes.refunds')" :subtitle="__('customer_notes.refund_help')" id="refunds">
            <thead><tr><th>{{ __('customer_notes.payment_date') }}</th><th>Account</th><th>Method</th><th>Amount</th><th>Reference</th><th>Journal</th><th>By</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($refunds as $refund)
                    <tr>
                        <td>{{ $refund->payment_date->toDateString() }}</td>
                        <td>{{ $refund->paymentAccount?->label() ?? '-' }}</td>
                        <td>{{ $refund->payment_method ?? '-' }}</td>
                        <td>{{ $money($refund->amount) }}</td>
                        <td>{{ $refund->reference ?? '-' }}</td>
                        <td class="small">{{ $refund->journalEntry?->journal_number ?? '-' }}@if ($refund->reversalJournal) / {{ $refund->reversalJournal->journal_number }}@endif</td>
                        <td>{{ $refund->createdBy?->name ?? '-' }}</td>
                        <td>@if ($refund->isReversed())<span class="badge gray">{{ __('customer_notes.reversed') }}</span> <span class="small">{{ $refund->reversal_reason }}</span>@else <span class="badge green">{{ __('customer_notes.refunded') }}</span>@endif</td>
                        <td>
                            @if ($canReverseCredit && ! $refund->isReversed())
                                <form method="POST" action="{{ route('admin.accounting.accounts-receivable.credits.refunds.reverse', [$note, $refund]) }}" style="display:inline" onsubmit="var r = prompt(@json(__('customer_notes.reverse_refund_prompt'))); if (!r) return false; this.reason.value = r;">
                                    @csrf<input type="hidden" name="reason" value=""/><button class="btn sm danger">{{ __('customer_notes.reverse') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="table-empty">{{ __('customer_notes.no_refunds') }}</td></tr>
                @endforelse
            </tbody>
            @if ($canRefundCredit && $credit['available'] > 0)
                <x-slot:footer>
                    <form method="POST" action="{{ route('admin.accounting.accounts-receivable.credits.refund', $note) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}"/>
                        <div><label class="small">{{ __('customer_notes.payment_account') }}</label>
                            <select name="payment_account_id" class="select" required>
                                @foreach ($refundAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->label() }}</option>
                                @endforeach
                            </select></div>
                        <div><label class="small">Method</label>
                            <select name="payment_method" class="select">
                                <option value="">Follows the account</option>
                                @foreach (\App\Models\CustomerCreditRefund::METHODS as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach
                            </select></div>
                        <div><label class="small">Amount</label><input name="amount" type="number" step="0.01" min="0.01" max="{{ $credit['available'] }}" class="input" value="{{ old('amount', $credit['available']) }}" required/></div>
                        <div><label class="small">{{ __('customer_notes.payment_date') }}</label><input name="payment_date" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old('payment_date', now()->toDateString()) }}" required/></div>
                        <div><label class="small">Reference</label><input name="reference" class="input" value="{{ old('reference') }}" placeholder="Transfer / cheque number"/></div>
                        <button class="btn sm primary" @disabled($refundAccounts->isEmpty())>{{ __('customer_notes.refund_customer') }}</button>
                    </form>
                </x-slot:footer>
            @endif
        </x-admin.data-table>
    @endif

    {{-- 6. ZATCA --}}
    @if (isset($sections['zatca']))
        <x-admin.data-table :title="__('zatca.section')" :subtitle="__('workspace.inv_zatca_help')" class="detail-table" id="zatca">
            @if ($record)
                <tbody>
                    <tr><th>{{ __('zatca.document_kind') }}</th><td>{{ $record->documentTypeLabel() }}</td></tr>
                    <tr><th>{{ __('zatca.uuid') }}</th><td class="small">{{ $record->uuid }}</td></tr>
                    <tr><th>Status</th><td><span class="badge {{ $record->statusColor() }}">{{ $record->statusLabel() }}</span>@if (! $record->isAccepted())<span class="small">· {{ __('zatca.not_verified') }}</span>@endif</td></tr>
                    <tr><th>{{ __('zatca.signature') }}</th><td>{{ $record->signed_at ? $record->signed_at->format('Y-m-d H:i').' · ICV '.$record->icv : '-' }}</td></tr>
                    <tr><th>{{ __('zatca.zatca_reference') }}</th><td>{{ $record->zatca_status ?? '-' }}</td></tr>
                    @if ($record->last_error_message && ! $record->isAccepted())<tr><th>{{ __('zatca.last_error') }}</th><td>{{ $record->last_error_message }}</td></tr>@endif
                </tbody>
                <x-slot:footer>
                    <a class="btn sm outline" href="{{ route('admin.accounting.zatca.show', $record) }}">{{ __('zatca.record_title') }}</a>
                    @if ($canPostZatca && ! $record->isAccepted() && ! $record->isLocked())
                        <form method="POST" action="{{ route('admin.accounting.zatca.generate', $record) }}" style="display:inline">@csrf<button class="btn sm outline">{{ __('zatca.generate') }}</button></form>
                        <form method="POST" action="{{ route('admin.accounting.zatca.submit', $record) }}" style="display:inline">@csrf<button class="btn sm primary">{{ __('zatca.submit') }}</button></form>
                    @endif
                    @if ($canRetryZatca && in_array($record->status, ['signed', 'submission_pending', 'rejected', 'outcome_unknown'], true))
                        <form method="POST" action="{{ route('admin.accounting.zatca.retry', $record) }}" style="display:inline">@csrf<button class="btn sm warning">{{ __('zatca.retry') }}</button></form>
                    @endif
                    @if ($canExportZatca && $record->signed_xml_path)
                        <a class="btn sm outline" href="{{ route('admin.accounting.zatca.xml', [$record, 'signed']) }}">{{ __('zatca.download_signed') }}</a>
                    @endif
                </x-slot:footer>
            @else
                <tbody><tr><td class="table-empty">{{ $note->isPosted() ? __('zatca.no_record') : 'Created when the note is posted.' }}</td></tr></tbody>
            @endif
        </x-admin.data-table>
    @endif

    {{-- 7. Activity --}}
    @if ($activity !== null)
        <x-admin.data-table :title="__('workspace.activity')" id="activity">
            <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
                @forelse ($activity as $log)
                    <tr><td>{{ $log->created_at?->format('Y-m-d H:i') }}</td><td>{{ $log->user_name }}</td><td>{{ $log->module }}</td><td>{{ $log->action }}</td><td>{{ $log->description }}</td></tr>
                @empty
                    <tr><td colspan="5" class="table-empty">{{ __('workspace.inv_no_activity') }}</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    @endif
@endsection
