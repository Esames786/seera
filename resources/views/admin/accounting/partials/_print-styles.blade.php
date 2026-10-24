{{-- Shared print styling for customer-facing finance documents (invoice, credit / debit note). A4-friendly, bilingual, browser print. --}}
<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { font-family: Arial, "Segoe UI", Tahoma, sans-serif; color: #111; background: #fff; margin: 0; padding: 16px; font-size: 13px; line-height: 1.4; }
    .sheet { max-width: 210mm; margin: 0 auto; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .row { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .row > * { flex: 1 1 260px; min-width: 0; }
    .box { border: 1px solid #999; padding: 8px 10px; }
    .ar { direction: rtl; text-align: right; font-family: "Segoe UI", Tahoma, Arial, sans-serif; unicode-bidi: isolate; }
    .bi { display: flex; justify-content: space-between; gap: 12px; }
    .bi .ar { flex: 1; }
    .logo { max-height: 64px; max-width: 220px; object-fit: contain; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #999; padding: 5px 6px; vertical-align: top; word-break: break-word; }
    th { background: #eee; font-weight: 600; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .totals td { font-weight: bold; }
    .small { font-size: 11px; color: #444; }
    .status { display: inline-block; padding: 2px 8px; border: 1px solid #666; border-radius: 10px; font-size: 11px; }
    .qr-wrap { display: flex; gap: 14px; align-items: flex-start; margin-top: 14px; }
    .qr-wrap canvas, .qr-wrap img, .qr-wrap svg { width: 140px; height: 140px; image-rendering: pixelated; }
    .qr-data { font-size: 9px; color: #666; word-break: break-all; }
    .noprint { margin-bottom: 12px; display: flex; gap: 8px; }
    .noprint button { padding: 6px 14px; }
    .table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .ar, .row > *, .box { overflow-wrap: anywhere; max-width: 100%; }
    @media (max-width: 600px) {
        body { padding: 10px; font-size: 12px; }
        .row { flex-direction: column; }
        .row > * { flex-basis: auto; }
        .bi { flex-direction: column; gap: 2px; }
        .table-wrap table { min-width: 640px; }
        .qr-wrap { flex-direction: column; }
    }
    @media print {
        .noprint { display: none; }
        body { margin: 0; padding: 0; }
        .sheet { max-width: none; }
        @page { size: A4; margin: 12mm; }
        tr, .box { break-inside: avoid; }
    }
</style>
