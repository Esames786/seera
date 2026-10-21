@extends('layouts.admin')

@php
    $credit = $type === \App\Models\CustomerAdjustmentNote::TYPE_CREDIT;
    $title = $note ? __('customer_notes.edit_draft').': '.$note->note_number : ($credit ? __('customer_notes.new_credit') : __('customer_notes.new_debit'));
    $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
    $existing = $note ? $note->lines->keyBy('customer_invoice_line_id') : collect();
    $extraLines = $note ? $note->lines->whereNull('customer_invoice_line_id')->values() : collect();
    $back = $returnTo ?? ($note ? route('admin.accounting.accounts-receivable.notes.show', $note) : route('admin.accounting.accounts-receivable.show', $invoice));
@endphp

@section('title', $title)
@section('breadcrumb', 'Accounting / Accounts Receivable / '.__('customer_notes.title'))

@section('content')
    <x-admin.page-header :title="$title" :description="__('customer_notes.original_invoice').' '.$invoice->invoice_number.' · '.$invoice->customer->name.' · '.$money($invoice->total_amount).' · '.__('zatca.type_'.$invoice->invoice_type)">
        <a class="btn outline" href="{{ $back }}">{{ __('workspace.inv_back_origin') }}</a>
    </x-admin.page-header>

    <div class="note">{{ $credit ? __('customer_notes.form_help_credit') : __('customer_notes.form_help_debit') }} {{ __('customer_notes.totals_server') }}</div>
    <br/>

    @if ($errors->any())
        <div class="alert flash"><ul style="margin:0;padding-left:18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $note ? route('admin.accounting.accounts-receivable.notes.update', $note) : route('admin.accounting.accounts-receivable.notes.store') }}">
        @csrf
        @if ($note)@method('PUT')@endif
        <input type="hidden" name="customer_invoice_id" value="{{ $invoice->id }}"/>
        <input type="hidden" name="note_type" value="{{ $type }}"/>
        <input type="hidden" name="{{ \App\Support\SaveAction::RETURN_FIELD }}" value="{{ old(\App\Support\SaveAction::RETURN_FIELD, $returnTo) }}"/>

        <x-admin.form-section :title="__('customer_notes.sec_summary')" columns="3">
            <div><label for="note_date">{{ __('customer_notes.note_date') }} *</label><input id="note_date" name="note_date" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old('note_date', $note?->note_date?->toDateString() ?? now()->toDateString()) }}" required/></div>
            <div>
                <label for="reason_code">{{ __('customer_notes.reason_code') }} *</label>
                <select id="reason_code" name="reason_code" class="select" required>
                    @foreach ($reasonCodes as $code)
                        <option value="{{ $code }}" @selected(old('reason_code', $note?->reason_code) === $code)>{{ __('customer_notes.reason_'.$code) }}</option>
                    @endforeach
                </select>
                <div class="small">{{ __('customer_notes.reason_help') }}</div>
            </div>
            <div><label>{{ __('zatca.invoice_type') }}</label><input class="input" value="{{ __('zatca.type_'.$invoice->invoice_type) }} · {{ $credit ? '381' : '383' }}" disabled/></div>
            <div class="full"><label for="reason">{{ __('customer_notes.reason') }} *</label><textarea id="reason" name="reason" class="textarea" maxlength="1000" required>{{ old('reason', $note?->reason) }}</textarea></div>
            <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $note?->notes) }}</textarea></div>
        </x-admin.form-section>

        <x-admin.data-table :title="__('customer_notes.sec_lines')" :subtitle="__('customer_notes.original_invoice').' '.$invoice->invoice_number.' · VAT '.number_format($invoice->vat_rate, 2).'%'">
            <thead>
                <tr><th>{{ __('customer_notes.line_source') }}</th><th>Original qty × price</th>@if ($credit)<th>{{ __('customer_notes.line_remaining') }}</th>@endif<th>Description</th><th>Quantity</th><th>Unit price</th><th>Revenue account</th><th>Cost center</th></tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $i => $line)
                    @php($row = old('lines.'.$i, $existing->get($line->id)?->toArray() ?? []))
                    <tr>
                        <td class="small">#{{ $i + 1 }} {{ $line->description }}</td>
                        <td class="small">{{ number_format($line->quantity, 2) }} × {{ number_format($line->unit_price, 2) }} = {{ number_format($line->taxable_amount, 2) }}</td>
                        @if ($credit)<td class="small">{{ number_format($remaining[$line->id] ?? 0, 2) }}</td>@endif
                        <td>
                            <input type="hidden" name="lines[{{ $i }}][invoice_line_id]" value="{{ $line->id }}"/>
                            <input name="lines[{{ $i }}][description]" class="input" value="{{ $row['description'] ?? $line->description }}"/>
                        </td>
                        <td><input name="lines[{{ $i }}][quantity]" type="number" step="0.01" min="0" class="input" value="{{ $row['quantity'] ?? ($credit && ! $note ? number_format(min((float) $line->quantity, (float) $line->quantity * (($remaining[$line->id] ?? 0) / max((float) $line->taxable_amount, 0.01))), 2, '.', '') : 0) }}"/></td>
                        <td><input name="lines[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="input" value="{{ $row['unit_price'] ?? number_format($line->unit_price, 2, '.', '') }}"/></td>
                        <td>
                            <select name="lines[{{ $i }}][revenue_account_id]" class="select">
                                <option value="">{{ $line->revenueAccount ? $line->revenueAccount->account_code.' (original)' : 'Default (4100)' }}</option>
                                @foreach ($revenueAccounts as $account)
                                    <option value="{{ $account->id }}" @selected(($row['revenue_account_id'] ?? null) == $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[{{ $i }}][cost_center_id]" class="select">
                                <option value="">Same as original</option>
                                @foreach ($costCenters as $center)
                                    <option value="{{ $center->id }}" @selected(($row['cost_center_id'] ?? null) == $center->id)>{{ $center->name }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
                @if (! $credit)
                    @for ($k = 0; $k < max(2, $extraLines->count() + 1); $k++)
                        @php($j = $invoice->lines->count() + $k)
                        @php($row = old('lines.'.$j, $extraLines->get($k)?->toArray() ?? []))
                        <tr>
                            <td class="small" colspan="2">{{ __('customer_notes.new_line') }}</td>
                            <td><input name="lines[{{ $j }}][description]" class="input" value="{{ $row['description'] ?? '' }}" placeholder="Additional charge"/></td>
                            <td><input name="lines[{{ $j }}][quantity]" type="number" step="0.01" min="0" class="input" value="{{ $row['quantity'] ?? 1 }}"/></td>
                            <td><input name="lines[{{ $j }}][unit_price]" type="number" step="0.01" min="0" class="input" value="{{ $row['unit_price'] ?? '' }}"/></td>
                            <td>
                                <select name="lines[{{ $j }}][revenue_account_id]" class="select">
                                    <option value="">Default (4100)</option>
                                    @foreach ($revenueAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(($row['revenue_account_id'] ?? null) == $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $j }}][cost_center_id]" class="select">
                                    <option value="">Invoice cost center</option>
                                    @foreach ($costCenters as $center)
                                        <option value="{{ $center->id }}" @selected(($row['cost_center_id'] ?? null) == $center->id)>{{ $center->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endfor
                @endif
            </tbody>
        </x-admin.data-table>

        <div class="note">{{ $credit ? 'This note posts: debit revenue and Output VAT, credit Accounts Receivable.' : 'This note posts: debit Accounts Receivable, credit revenue and Output VAT.' }} {{ __('customer_notes.flash_saved', ['number' => '…']) }}</div>

        <div class="form-actions">
            <button type="submit" class="btn primary">{{ $note ? 'Save Draft' : 'Save Draft' }}</button>
            <a class="btn outline" href="{{ $back }}">Cancel</a>
        </div>
    </form>
@endsection
