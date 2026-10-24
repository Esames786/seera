<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="robots" content="noindex"/>
    <title>{{ $invoice->invoice_number }}</title>
    @include('admin.accounting.partials._print-styles')
</head>
<body>
    @php
        /** @var \App\Models\CustomerInvoice $invoice */
        $money = fn ($v) => number_format((float) $v, 2);
        // Issued values only: the frozen seller / buyer snapshots taken at approval; the live masters are never consulted for an issued invoice.
        $seller = $invoice->seller_snapshot ?: [];
        $buyer = $invoice->buyer_snapshot ?: [];
        $record = $invoice->zatcaRecord;
        $simplified = $invoice->isSimplified();
        $titleEn = $simplified ? 'Simplified Tax Invoice' : 'Tax Invoice';
        $titleAr = $simplified ? 'فاتورة ضريبية مبسطة' : 'فاتورة ضريبية';
        $address = fn (array $p) => implode(', ', array_filter([$p['building_number'] ?? null, $p['street'] ?? null, $p['district'] ?? null, $p['city'] ?? null, $p['postal_code'] ?? null, $p['country_code'] ?? null]));
    @endphp
    <div class="sheet">
        <div class="noprint">
            <button onclick="window.print()">Print / Save as PDF · طباعة</button>
            <a href="{{ $returnTo }}">Back · عودة</a>
        </div>

        <div class="row">
            <div>
                @if ($logo)<img class="logo" src="{{ $logo }}" alt="{{ $seller['name'] ?? '' }}"/><br/>@endif
                <h1>{{ $titleEn }}</h1>
                <div class="ar" style="font-size:18px;font-weight:700">{{ $titleAr }}</div>
                <div class="small">{{ __('zatca.type_'.$invoice->invoice_type) }}</div>
            </div>
            <div class="ar" dir="rtl">
                <div><strong>رقم الفاتورة / Invoice No.:</strong> <span dir="ltr">{{ $invoice->invoice_number }}</span></div>
                <div><strong>تاريخ الإصدار / Issue date:</strong> <span dir="ltr">{{ $invoice->invoice_date->toDateString() }} {{ $invoice->issue_time }}</span></div>
                @if ($invoice->supply_date)<div><strong>تاريخ التوريد / Supply date:</strong> <span dir="ltr">{{ $invoice->supply_date->toDateString() }}</span></div>@endif
                @if ($invoice->due_date)<div><strong>تاريخ الاستحقاق / Due date:</strong> <span dir="ltr">{{ $invoice->due_date->toDateString() }}</span></div>@endif
                @if ($invoice->project)<div><strong>المشروع / Project:</strong> {{ $invoice->project->name }}</div>@endif
                <div><strong>حالة هيئة الزكاة / ZATCA status:</strong> <span class="status">{{ $zatcaState }}</span></div>
            </div>
        </div>

        <div class="row" style="margin-top:12px">
            <div class="box">
                <div class="bi"><strong>Seller</strong><strong class="ar">البائع</strong></div>
                <div>{{ $seller['name'] ?? '-' }}</div>
                @if (! empty($seller['name_ar']))<div class="ar">{{ $seller['name_ar'] }}</div>@endif
                <div class="small">VAT No. / الرقم الضريبي: <span dir="ltr">{{ $seller['vat_number'] ?? '-' }}</span></div>
                @if (! empty($seller['id_value']))<div class="small">{{ $seller['id_scheme'] ?? 'CRN' }} / السجل: <span dir="ltr">{{ $seller['id_value'] }}</span></div>@endif
                <div class="small">{{ $address($seller) ?: '-' }}</div>
            </div>
            <div class="box">
                <div class="bi"><strong>Buyer</strong><strong class="ar">المشتري</strong></div>
                <div>{{ $buyer['name'] ?? $invoice->customer->name }}</div>
                @if (! empty($buyer['vat_number']))<div class="small">VAT No. / الرقم الضريبي: <span dir="ltr">{{ $buyer['vat_number'] }}</span></div>@endif
                @if (! empty($buyer['id_value']))<div class="small">{{ $buyer['id_scheme'] ?? '' }} / الهوية: <span dir="ltr">{{ $buyer['id_value'] }}</span></div>@endif
                <div class="small">{{ $address($buyer) ?: '-' }}</div>
            </div>
        </div>

        <div class="table-wrap"><table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description<br/><span class="ar">الوصف</span></th>
                    <th class="num">Qty<br/><span class="ar">الكمية</span></th>
                    <th class="num">Unit price<br/><span class="ar">سعر الوحدة</span></th>
                    <th class="num">Taxable amount<br/><span class="ar">المبلغ الخاضع</span></th>
                    <th class="num">VAT {{ $money($invoice->vat_rate) }}%<br/><span class="ar">الضريبة</span></th>
                    <th class="num">Total incl. VAT<br/><span class="ar">الإجمالي شامل الضريبة</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $i => $line)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $line->description }}</td>
                        <td class="num">{{ $money($line->quantity) }}</td>
                        <td class="num">{{ $money($line->unit_price) }}</td>
                        <td class="num">{{ $money($line->taxable_amount) }}</td>
                        <td class="num">{{ $money($line->vat_amount) }}</td>
                        <td class="num">{{ $money($line->total_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="totals"><td colspan="4"><span class="bi"><span>Total taxable amount (SAR)</span><span class="ar">الإجمالي الخاضع للضريبة (ريال)</span></span></td><td class="num" colspan="3">{{ $money($invoice->taxable_amount) }}</td></tr>
                <tr class="totals"><td colspan="4"><span class="bi"><span>Total VAT (SAR)</span><span class="ar">إجمالي ضريبة القيمة المضافة (ريال)</span></span></td><td class="num" colspan="3">{{ $money($invoice->vat_amount) }}</td></tr>
                <tr class="totals"><td colspan="4"><span class="bi"><span>Total amount due (SAR)</span><span class="ar">الإجمالي المستحق شامل الضريبة (ريال)</span></span></td><td class="num" colspan="3">{{ $money($invoice->total_amount) }}</td></tr>
            </tfoot>
        </table></div>

        @if ($invoice->notes)<div class="box" style="margin-top:12px"><strong>Notes / ملاحظات:</strong> {{ $invoice->notes }}</div>@endif

        @include('admin.accounting.partials._print-qr', ['qr' => $record?->qr_code, 'record' => $record])

        <p class="small" style="margin-top:16px">{{ __('customer_notes.print_disclaimer_invoice') }}</p>
    </div>
</body>
</html>
