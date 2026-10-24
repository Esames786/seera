<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="robots" content="noindex"/>
    <title>{{ $note->note_number }}</title>
    @include('admin.accounting.partials._print-styles')
</head>
<body>
    @php
        $money = fn ($v) => number_format((float) $v, 2);
        // Issued values only: the snapshots frozen when the note was posted. A draft (no snapshot yet) shows the current masters and says so.
        $seller = $note->seller_snapshot ?: ['name' => $company?->name, 'name_ar' => $company?->name_ar, 'vat_number' => $company?->vat_number, 'id_scheme' => $company?->seller_id_scheme, 'id_value' => $company?->cr_number, 'street' => $company?->street, 'building_number' => $company?->building_number, 'district' => $company?->district, 'city' => $company?->city, 'postal_code' => $company?->postal_code, 'country_code' => $company?->country_code];
        $buyer = $note->buyer_snapshot ?: ['name' => $note->customer->name, 'vat_number' => $note->customer->vat_number, 'street' => $note->customer->street, 'building_number' => $note->customer->building_number, 'district' => $note->customer->district, 'city' => $note->customer->city, 'postal_code' => $note->customer->postal_code, 'country_code' => $note->customer->country_code];
        $record = $note->zatcaRecord;
        $address = fn (array $p) => implode(', ', array_filter([$p['building_number'] ?? null, $p['street'] ?? null, $p['district'] ?? null, $p['city'] ?? null, $p['postal_code'] ?? null, $p['country_code'] ?? null]));
    @endphp
    <div class="sheet">
        <div class="noprint"><button onclick="window.print()">Print / Save as PDF · طباعة</button></div>

        <div class="row">
            <div>
                @if ($logo ?? null)<img class="logo" src="{{ $logo }}" alt="{{ $seller['name'] ?? '' }}"/><br/>@endif
                <h1>{{ $note->isCredit() ? 'Credit Note' : 'Debit Note' }}</h1>
                <div class="ar" style="font-size:18px;font-weight:700">{{ $note->isCredit() ? 'إشعار دائن' : 'إشعار مدين' }}</div>
                <div class="small">{{ __('zatca.type_'.$note->invoice_type) }} · ZATCA {{ $note->zatcaTypeCode() }}@if ($note->isDraft()) · {{ __('customer_notes.state_draft') }}@endif</div>
            </div>
            <div class="ar" dir="rtl">
                <div><strong>رقم الإشعار / Note No.:</strong> <span dir="ltr">{{ $note->note_number }}</span></div>
                <div><strong>التاريخ / Date:</strong> <span dir="ltr">{{ $note->note_date->toDateString() }} {{ $note->issue_time }}</span></div>
                <div><strong>الفاتورة الأصلية / Original invoice:</strong> <span dir="ltr">{{ $note->invoice?->invoice_number }}</span></div>
                @if ($note->supply_date)<div><strong>تاريخ التوريد / Supply date:</strong> <span dir="ltr">{{ $note->supply_date->toDateString() }}</span></div>@endif
                <div><strong>حالة هيئة الزكاة / ZATCA status:</strong> <span class="status">{{ $record ? $record->statusLabel().($record->isAccepted() ? '' : ' · '.__('zatca.not_verified')) : __('workspace.inv_einv_none') }}</span></div>
            </div>
        </div>

        <div class="row" style="margin-top:12px">
            <div class="box">
                <div class="bi"><strong>Seller</strong><strong class="ar">البائع</strong></div>
                <div>{{ $seller['name'] ?? '-' }}</div>
                @if (! empty($seller['name_ar']))<div class="ar">{{ $seller['name_ar'] }}</div>@endif
                <div class="small">VAT No. / الرقم الضريبي: <span dir="ltr">{{ $seller['vat_number'] ?? '-' }}</span></div>
                <div class="small">{{ $address($seller) ?: '-' }}</div>
            </div>
            <div class="box">
                <div class="bi"><strong>Buyer</strong><strong class="ar">المشتري</strong></div>
                <div>{{ $buyer['name'] ?? '' }}</div>
                @if (! empty($buyer['vat_number']))<div class="small">VAT No. / الرقم الضريبي: <span dir="ltr">{{ $buyer['vat_number'] }}</span></div>@endif
                <div class="small">{{ $address($buyer) ?: '-' }}</div>
            </div>
        </div>

        <div class="box" style="margin-top:12px"><strong>سبب الإصدار / Reason:</strong> {{ $note->reasonLabel() }} — {{ $note->reason }}</div>

        <div class="table-wrap"><table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description<br/><span class="ar">الوصف</span></th>
                    <th class="num">Qty<br/><span class="ar">الكمية</span></th>
                    <th class="num">Unit price<br/><span class="ar">سعر الوحدة</span></th>
                    <th class="num">Taxable amount<br/><span class="ar">المبلغ الخاضع</span></th>
                    <th class="num">VAT {{ $money($note->vat_rate) }}%<br/><span class="ar">الضريبة</span></th>
                    <th class="num">Total incl. VAT<br/><span class="ar">الإجمالي شامل الضريبة</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($note->lines as $i => $line)
                    <tr><td>{{ $i + 1 }}</td><td>{{ $line->description }}</td><td class="num">{{ $money($line->quantity) }}</td><td class="num">{{ $money($line->unit_price) }}</td><td class="num">{{ $money($line->taxable_amount) }}</td><td class="num">{{ $money($line->vat_amount) }}</td><td class="num">{{ $money($line->total_amount) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="totals"><td colspan="4"><span class="bi"><span>Total taxable amount (SAR)</span><span class="ar">الإجمالي الخاضع للضريبة (ريال)</span></span></td><td class="num" colspan="3">{{ $money($note->taxable_amount) }}</td></tr>
                <tr class="totals"><td colspan="4"><span class="bi"><span>Total VAT (SAR)</span><span class="ar">إجمالي ضريبة القيمة المضافة (ريال)</span></span></td><td class="num" colspan="3">{{ $money($note->vat_amount) }}</td></tr>
                <tr class="totals"><td colspan="4"><span class="bi"><span>Total incl. VAT (SAR)</span><span class="ar">الإجمالي شامل الضريبة (ريال)</span></span></td><td class="num" colspan="3">{{ $money($note->total_amount) }}</td></tr>
            </tfoot>
        </table></div>

        @include('admin.accounting.partials._print-qr', ['qr' => $record?->qr_code, 'record' => $record])

        <p class="small" style="margin-top:16px">{{ __('customer_notes.print_disclaimer') }}</p>
    </div>
</body>
</html>
