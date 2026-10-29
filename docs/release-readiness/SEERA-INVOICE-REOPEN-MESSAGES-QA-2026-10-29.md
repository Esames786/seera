# Invoice reopening server messages — bilingual verification, 29 October 2026

Branch `feature/seera-invoice-reopen-messages-2026-10-29` (base `f448fb0`, the reopen-dialog branch). Verified HEAD: `692ca45`. No source fix was needed; evidence commit: see the branch log.

Scope: the English / Arabic server messages of the invoice reopening path (403 text, required-reason validation, unpaid-without-receipts rule, success flash with the reversal journal number) and the unchanged reopening behaviour behind them. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

## 1. Safe checkout and diff review

- Working tree before checkout: clean on `feature/seera-invoice-localization-final-2026-10-29` at `f448fb0` (one pre-existing untracked handoff note left untouched). `692ca45` matched the expected HEAD; merge base with `f448fb0` is `f448fb0`.
- `692ca45` changes 4 files in 4 commits: new `lang/en/invoice_reopen.php` and `lang/ar/invoice_reopen.php` (5 keys each, identical key sets, the `:journal` placeholder present in both `success_with_reversal` strings), four message call sites in `AccountsReceivableController::reopen`, and two assertions plus one parity test in `InvoiceReopenLocalizationTest`. No view, route, migration, model, policy or service is touched.
- The five English strings are byte-identical to the former hardcoded texts (asserted by the browser harness against the catalogue). The controller logic around the messages is unchanged: Super Admin `abort_unless` 403, `reason` required ≤ 500, unpaid-without-receipts rule, ZATCA lock rule (already bilingual through `zatca.err_reopen_locked`), VAT withdrawal, journal reversal, record cancellation, notes and activity log entry. The new group name `invoice_reopen` is used only through `__('invoice_reopen.*')`, so the case-insensitive-filesystem trap recorded under AR-03 does not apply. All three PHP files lint clean.

## 2. Focused tests (SQLite in-memory, seeded)

`--filter=InvoiceReopenLocalizationTest|CustomerInvoiceWorkspaceTest|AccountsReceivableArabicRegisterTest|SalesVatAuthorizationTest` on `692ca45`: **31 passed / 473 assertions, 0 failures** (252 s): CustomerInvoiceWorkspaceTest 15, SalesVatAuthorizationTest 8, InvoiceReopenLocalizationTest 4, AccountsReceivableArabicRegisterTest 4.

A throwaway probe test (run once, not committed) confirmed that a non-Super-Admin who holds Accounts Receivable "approve" reaches the controller's abort, that the exception message is the localized `invoice_reopen.only_super_admin` text in English and in Arabic, and that the 403 page body contains it.

## 3. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness: `tests/manual/invoice-reopen-browser.cjs` (extended: reads both message catalogues, asserts the English copy unchanged, and adds the server-message checks). Results: `evidence/invoice-reopen-2026-10-29/invoice-reopen-browser-results.json`. Synthetic fixture adjustments for the run (restored afterwards): invoice 3 given a `zatca_locked_at`, ZATCA records of invoices 1 and 2 given `validation_status` passed / failed; the Accounts Manager role already held AR approve, so no permission change was needed.

**Result: 34 checks, 34 passed, 0 page errors.** The four recorded 403 responses are the intended refusals of the forged non-Super-Admin requests.

Covered on top of the previous dialog run (which is repeated in full: default state, open / focus / translated copy, Escape, Cancel, empty and blank reason, Tab order and Enter confirmation, dialog inside the viewport at 320 / 390 / 768 / 1440, locked-invoice refusal, validation labels):

- Success flash: after the Arabic keyboard confirmation the flash equals the Arabic `success_with_reversal` text with the reversal journal interpolated ("… تم ترحيل القيد العكسي JV-2026-0039 …"), after the English one the English text ("… reversing entry JV-2026-0040 posted …"); in both cases the journal number in the flash is the one kept in the invoice notes.
- Required reason: a forged POST without a reason by the Super Admin is redirected back and the page shows the localized `reason_required` message (Arabic and English); the invoice stays unpaid and reopenable.
- Unpaid-without-receipts rule: a forged POST with a reason on a draft invoice shows the localized `unpaid_without_receipts` message in both locales; nothing is written.
- Unauthorized reopening: the Accounts Manager (who holds AR approve but is not a Super Admin) sees no reopen action or dialog markup in either locale; a forged POST with a reason is answered 403 and the response body carries the localized `only_super_admin` message in Arabic and in English; the invoice stays unpaid.
- ZATCA-locked invoice: the action is hidden and a forged POST is refused with the bilingual `zatca.err_reopen_locked` message; nothing is written.
- Accounting reversal, VAT withdrawal and audit history (fixture database after the run): JV-2026-0039 and JV-2026-0040 posted at 230.00 / 230.00 with lines Cr 1200 230.00, Dr 4100 200.00, Dr 2210 30.00 and the description "Reopened invoice INV-…: <reason>"; the original journals untouched; no `vat_transactions` row remains for either invoice; both invoices draft with `journal_entry_id` cleared and the ZATCA record cancelled; the reason kept in the notes; an activity log row "Reopened customer invoice" per reopening.

## 4. Full regression

- `php artisan test` on `692ca45`: **795 tests, 795 passed, 9,640 assertions, 0 failures** (3,111 s). Compared with the reopen-dialog branch (`f448fb0`: 794 / 9,623): the one new test is the message parity test.
- `npm run test:js`: 12 passed, 0 failed.
- Assets: no file under `resources/css`, `resources/js`, `public/` or the build configuration changed between `f448fb0` and HEAD; `public/build.zip` unchanged (SHA-256 `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`).

## 5. Findings

No confirmed regression. Observations for later sprints (not blocking): activity log entries and the reversal journal description are still stored in English at write time (audit text); the invoice notes line "Reopened <date> by <user>: <reason> (reversal JV-…)" is likewise stored in English.

## 6. Recommendation

**GO for integration approval of `feature/seera-invoice-reopen-messages-2026-10-29` at `692ca45`** (plus the evidence commit): the four server messages are bilingual with the English copy unchanged and the journal number interpolated, the required reason, success path, unauthorized 403, ZATCA lock, accounting reversal, VAT withdrawal and stored audit history behave exactly as before, and focused tests, the full suite, JavaScript tests and the browser verification are green. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
