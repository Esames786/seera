{{-- Renders the stored ZATCA QR (TLV base64) as a scannable code. The data is the record's own QR string, never recomputed. --}}
@props(['qr', 'record'])
@if ($qr)
    <div class="qr-wrap">
        <div id="zatca-qr" data-qr="{{ $qr }}" aria-label="ZATCA QR"></div>
        <div>
            <strong>رمز الاستجابة السريعة / ZATCA QR</strong><br/>
            <span class="small">UUID {{ $record?->uuid }} · {{ $record?->statusLabel() }}@if ($record && ! $record->isAccepted()) · {{ __('zatca.not_verified') }}@endif</span>
            <div class="qr-data">{{ $qr }}</div>
        </div>
    </div>
    <script src="{{ asset('vendor/qrcode-generator/qrcode.js') }}"></script>
    <script>
        (function () {
            var el = document.getElementById('zatca-qr');
            if (!el || typeof qrcode !== 'function') { return; }
            try {
                var code = qrcode(0, 'M');
                code.addData(el.getAttribute('data-qr'), 'Byte');
                code.make();
                el.innerHTML = code.createSvgTag({ cellSize: 3, margin: 2, scalable: true });
            } catch (e) {
                el.textContent = 'QR could not be drawn';
            }
        })();
    </script>
@endif
