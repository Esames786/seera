<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title>{{ $note->note_number }}</title>
    <style>
        body { font-family: Arial, "Segoe UI", Tahoma, sans-serif; color: #111; margin: 24px; font-size: 13px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .row { display: flex; justify-content: space-between; gap: 24px; }
        .box { border: 1px solid #999; padding: 8px 10px; flex: 1; }
        .ar { direction: rtl; text-align: right; font-family: "Segoe UI", Tahoma, Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 5px 6px; }
        th { background: #eee; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals td { font-weight: bold; }
        .small { font-size: 11px; color: #444; }
        .qr { margin-top: 12px; word-break: break-all; font-size: 9px; color: #666; }
        @media print { .noprint { display: none; } body { margin: 10mm; } }
    </style>
</head>
<body>
    @php
        $money = fn ($v) => number_format((float) $v, 2);
        $seller = $note->seller_snapshot ?: ['name' => $company?->name, 'name_ar' => $company?->name_ar, 'vat_number' => $company?->vat_number, 'street' => $company?->street, 'building_number' => $company?->building_number, 'district' => $company?->district, 'city' => $company?->city, 'postal_code' => $company?->postal_code];
        $buyer = $note->buyer_snapshot ?: ['name' => $note->customer->name, 'vat_number' => $note->customer->vat_number, 'street' => $note->customer->street, 'city' => $note->customer->city];
        $record = $note->zatcaRecord;
    @endphp
    <div class="noprint" style="margin-bottom:12px"><button onclick="window.print()">Print / طباعة</button></div>

    <div class="row">
        <div>
            <h1>{{ $note->isCredit() ? __('customer_notes.print_title_credit') : __('customer_notes.print_title_debit') }}</h1>
            <div class="small">{{ __('zatca.type_'.$note->invoice_type) }} · ZATCA {{ $note->zatcaTypeCode() }}</div>
        </div>
        <div class="ar">
            <div><strong>رقم الإشعار / Note No.:</strong> {{ $note->note_number }}</div>
            <div><strong>التاريخ / Date:</strong> {{ $note->note_date->toDateString() }} {{ $note->issue_time }}</div>
            <div><strong>الفاتورة الأصلية / Original invoice:</strong> {{ $note->invoice?->invoice_number }}</div>
            @if ($note->supply_date)<div><strong>تاريخ التوريد / Supply date:</strong> {{ $note->supply_date->toDateString() }}</div>@endif
        </div>
    </div>

    <div class="row" style="margin-top:12px">
        <div class="box">
            <strong>البائع / Seller</strong><br/>
            {{ $seller['name'] ?? '' }}@if (! empty($seller['name_ar'])) · <span class="ar" style="display:inline">{{ $seller['name_ar'] }}</span>@endif<br/>
            <span class="small">الرقم الضريبي / VAT: {{ $seller['vat_number'] ?? '-' }}</span><br/>
            <span class="small">{{ implode(', ', array_filter([$seller['street'] ?? null, $seller['building_number'] ?? null, $seller['district'] ?? null, $seller['city'] ?? null, $seller['postal_code'] ?? null])) }}</span>
        </div>
        <div class="box">
            <strong>المشتري / Buyer</strong><br/>
            {{ $buyer['name'] ?? '' }}<br/>
            <span class="small">الرقم الضريبي / VAT: {{ $buyer['vat_number'] ?? '-' }}</span><br/>
            <span class="small">{{ implode(', ', array_filter([$buyer['street'] ?? null, $buyer['building_number'] ?? null, $buyer['district'] ?? null, $buyer['city'] ?? null, $buyer['postal_code'] ?? null])) }}</span>
        </div>
    </div>

    <div class="box" style="margin-top:12px"><strong>سبب الإصدار / Reason:</strong> {{ $note->reasonLabel() }} — {{ $note->reason }}</div>

    <table>
        <thead>
            <tr><th>#</th><th>الوصف / Description</th><th>الكمية / Qty</th><th>سعر الوحدة / Unit price</th><th>المبلغ الخاضع / Taxable</th><th>الضريبة / VAT ({{ number_format($note->vat_rate, 2) }}%)</th><th>الإجمالي / Total</th></tr>
        </thead>
        <tbody>
            @foreach ($note->lines as $i => $line)
                <tr><td>{{ $i + 1 }}</td><td>{{ $line->description }}</td><td class="num">{{ $money($line->quantity) }}</td><td class="num">{{ $money($line->unit_price) }}</td><td class="num">{{ $money($line->taxable_amount) }}</td><td class="num">{{ $money($line->vat_amount) }}</td><td class="num">{{ $money($line->total_amount) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals"><td colspan="4">الإجمالي الخاضع للضريبة / Total taxable (SAR)</td><td class="num" colspan="3">{{ $money($note->taxable_amount) }}</td></tr>
            <tr class="totals"><td colspan="4">إجمالي ضريبة القيمة المضافة / Total VAT (SAR)</td><td class="num" colspan="3">{{ $money($note->vat_amount) }}</td></tr>
            <tr class="totals"><td colspan="4">الإجمالي شامل الضريبة / Total with VAT (SAR)</td><td class="num" colspan="3">{{ $money($note->total_amount) }}</td></tr>
        </tfoot>
    </table>

    @if ($record && $record->qr_code)
        <div class="qr"><strong>QR (ZATCA):</strong> {{ $record->qr_code }}<br/><span>UUID {{ $record->uuid }} · {{ $record->statusLabel() }}</span></div>
    @endif
    <p class="small" style="margin-top:16px">{{ __('customer_notes.print_disclaimer') }}</p>
</body>
</html>
