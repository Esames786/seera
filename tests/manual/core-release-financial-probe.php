<?php

// Read-only reconciliation of the browser-created invoice in the disposable fixture.
$root = realpath($argv[1] ?? '');
if (! $root || ! preg_match('/^seera-core-release-qa-[a-f0-9]{32}$/', basename($root))) {
    throw new RuntimeException('Only the disposable core release fixture is permitted.');
}
$journey = json_decode(file_get_contents(__DIR__.'/../../docs/release-readiness/evidence/core-integration-review-2026-11-01/financial-journey.json'), true, flags: JSON_THROW_ON_ERROR);
if (! preg_match('~/accounts-receivable/(\d+)$~', $journey['invoicePath'], $match)) {
    throw new RuntimeException('Missing browser invoice identity.');
}
$db = new PDO('sqlite:'.$root.'/uat.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA query_only=ON');
$one = function (string $sql, array $args = []) use ($db): array {
    $statement = $db->prepare($sql);
    $statement->execute($args);

    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
};
$checks = [];
$check = function (string $name, bool $ok, mixed $actual = null) use (&$checks): void {
    $checks[] = compact('name', 'ok', 'actual');
};
$invoice = $one('select * from customer_invoices where id = ?', [$match[1]]);
foreach (['taxable_amount' => 200, 'vat_amount' => 30, 'total_amount' => 230, 'received_amount' => 100, 'balance_amount' => 130] as $key => $expected) {
    $check($key, isset($invoice[$key]) && round((float) $invoice[$key], 2) === (float) $expected, $invoice[$key] ?? null);
}
$check('partially paid status', ($invoice['payment_status'] ?? '') === 'partially_paid', $invoice['payment_status'] ?? null);
$vat = $one('select count(*) as rows, sum(taxable_amount) as taxable, sum(vat_amount) as vat from vat_transactions where source_module = ? and source_id = ?', ['Customer Invoice', $match[1]]);
$check('one output VAT record, 200 taxable / 30 VAT', (int) $vat['rows'] === 1 && (float) $vat['taxable'] === 200.0 && (float) $vat['vat'] === 30.0, $vat);
$receipt = $one('select * from customer_receipts where customer_invoice_id = ? and reference_number = ?', [$match[1], 'CORE-RELEASE-QA']);
$check('one receipt for 100', isset($receipt['amount']) && (float) $receipt['amount'] === 100.0, $receipt['amount'] ?? null);
foreach (['invoice' => $invoice['journal_entry_id'] ?? 0, 'receipt' => $receipt['journal_entry_id'] ?? 0] as $label => $id) {
    $totals = $one('select count(*) as rows, sum(debit) as debit, sum(credit) as credit from journal_entry_lines where journal_entry_id = ?', [$id]);
    $check($label.' journal balanced', (int) $totals['rows'] >= 2 && round((float) $totals['debit'] - (float) $totals['credit'], 2) === 0.0, $totals);
}
$ar = $one('select sum(l.debit - l.credit) as balance from journal_entry_lines l join chart_of_accounts a on a.id = l.chart_of_account_id where a.account_code = ? and l.journal_entry_id in (?, ?)', ['1200', $invoice['journal_entry_id'] ?? 0, $receipt['journal_entry_id'] ?? 0]);
$check('AR journal net equals invoice outstanding 130', isset($ar['balance']) && round((float) $ar['balance'], 2) === 130.0, $ar);
$failed = count(array_filter($checks, fn ($check) => ! $check['ok']));
echo json_encode(['total' => count($checks), 'passed' => count($checks) - $failed, 'failed' => $failed, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed ? 1 : 0);
