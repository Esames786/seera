# Invoice reopening dialog and ZATCA validation labels — verification, 29 October 2026

Branch `feature/seera-invoice-localization-final-2026-10-29` (base `bf3e6b0`, the AR register Arabic branch). Verified HEAD: `81e9178`. No source fix was needed; evidence commit: see the branch log.

Scope: the new in-page invoice reopening dialog (replacing the browser `prompt()`), the bilingual local ZATCA validation labels, and the unchanged server-side reopening rules. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

## 1. Safe checkout and diff review

- Working tree before checkout: clean on `feature/seera-ar-register-arabic-2026-10-29` at `bf3e6b0` (one pre-existing untracked handoff note left untouched). `81e9178` matched the expected HEAD; merge base with `bf3e6b0` is `bf3e6b0`.
- `81e9178` changes 5 files in 6 commits: `lang/ar.json` (+4 entries, 927 keys, 0 duplicates), `lang/en|ar/zatca.php` (+2 keys each, 209 keys, EN/AR key sets identical), the invoice detail view, and the new `InvoiceReopenLocalizationTest` (3 tests). No controller, service, model, policy, route or migration is touched: `AccountsReceivableController::reopen` (Super Admin `abort_unless`, `reason` required ≤ 500, unpaid-without-receipts rule, ZATCA lock rule, VAT withdrawal, journal reversal, record cancellation, activity log) and the workspace `canReopen` flag (unpaid, Super Admin, approve permission, no receipts, no ZATCA lock) are unchanged.
- View: the inline `onsubmit="prompt(...)"` is removed; the reopen form submits only after a native `<dialog>` (title, help, labelled textarea `maxlength=500`, Cancel, Confirm) collects a non-blank reason, which is copied into the existing hidden `reason` field before `requestSubmit()`. The dialog and script render only when `$canReopen` is true; the script is pushed to the layout's existing `scripts` stack. The ZATCA validation cell maps `passed` / `failed` to `zatca.validation_passed` / `zatca.validation_failed` and leaves other stored values as before; the stored value is not touched. Both views compile.

## 2. Focused tests (SQLite in-memory, seeded)

`--filter=InvoiceReopenLocalizationTest|CustomerInvoiceWorkspaceTest|AccountsReceivableArabicRegisterTest|ZatcaPhase2Test` on `81e9178`: **59 passed / 809 assertions, 0 failures** (624 s; the ZATCA suite signs real XML).

Skip audit: no `markTestSkipped`, `markTestIncomplete`, conditional early return or extension check exists in the four classes or the shared ZATCA test support; `InvoiceReopenLocalizationTest` builds its own posted invoice through the store and approve routes instead of depending on a seeded one, and asserts the eligibility preconditions (unpaid, not ZATCA-locked, no receipts) before each scenario.

## 3. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness: `tests/manual/invoice-reopen-browser.cjs`. Results: `evidence/invoice-reopen-2026-10-29/invoice-reopen-browser-results.json`. Screenshots stay outside Git. Synthetic fixture adjustments for the run (restored afterwards): invoice 3 given a `zatca_locked_at`, ZATCA records of invoices 1 and 2 given `validation_status` passed / failed.

**Result: 27 checks, 27 passed, 0 page errors.** The two recorded 403 responses are the intended refusals of the forged non-Super-Admin requests.

Covered:

- Dialog flow, Arabic (desktop) and English (390 px), on two eligible posted invoices: dialog closed by default, hidden reason empty, no inline `prompt()` handler; clicking "Reopen for Correction" opens the modal with focus in the textarea, the title / help / label / Cancel / Confirm texts exact in each locale, and the dialog box inside the viewport; Escape closes without navigation or value change; Cancel closes and the action stays available; Confirm with an empty or whitespace-only reason keeps the dialog open, shows the localized required message as the field's validity message and does not navigate; Tab moves textarea → Cancel → Confirm; Enter on Confirm submits: the page returns to the invoice as a draft with the success flash, the approve action back, the reopen action gone, the reason and the reversal journal number in the notes and the "Reopened customer invoice" activity entry.
- Mobile widths: dialog opened on a third eligible invoice at 320, 390, 768 and 1440 in both locales: no horizontal overflow, dialog box inside the viewport, Escape closes.
- Visibility and authorization: the Accounts Manager sees neither the reopen form nor the dialog markup in either locale, and a forged POST with a reason is refused with 403; the invoice stays unpaid and unchanged.
- ZATCA lock: for the locked invoice the action is hidden for the Super Admin and a forged POST with a reason is redirected back with the `zatca.err_reopen_locked` message in Arabic and in English; nothing is written.
- Accounting reversal and audit trail (fixture database after the run): each reopened invoice has a posted reversal journal (JV-2026-0031, JV-2026-0032, 230.00 / 230.00, description "Reopened invoice INV-…: <reason>") whose lines mirror the original posting (Cr 1200 Accounts Receivable 230.00; Dr 4100 Project Revenue 200.00; Dr 2210 Output VAT 30.00) while the original journal (JV-2026-0028 / 0029) stays posted and untouched, the invoice's `journal_entry_id` cleared and status draft, the VAT ledger row for the invoice withdrawn (no `vat_transactions` row remains for the source), the ZATCA record cancelled, the reason kept in the notes and an activity log row "Reopened customer invoice".
- ZATCA validation labels: with stored `validation_status` passed / failed the detail row reads "اجتاز التحقق" / "فشل التحقق" in Arabic and "Passed" / "Failed" in English, with the validated-at timestamp; stored values unchanged (verified in the database).

## 4. Full regression

- `php artisan test` on `81e9178`: **794 tests, 794 passed, 9,623 assertions, 0 failures** (3,015 s). Compared with the AR register branch (`bf3e6b0`: 791 / 9,573): the three new tests are `InvoiceReopenLocalizationTest`.
- `npm run test:js`: 12 passed, 0 failed.
- Assets: no file under `resources/css`, `resources/js`, `public/` or the build configuration changed between `bf3e6b0` and HEAD; `public/build.zip` unchanged (SHA-256 `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`).

## 5. Findings

No confirmed defect. Observations recorded for later sprints (not blocking):

- Server-side messages of the reopen path remain hardcoded English: the success flash ("Invoice reopened as a draft; reversing entry … posted…"), the 403 text, the `reason` validation message and the "Only an approved invoice with no receipts can be reopened" rule message. The ZATCA lock message is already bilingual.
- Activity log entries and the reversal journal description are stored in English at write time (audit text, shown as stored).
- The dialog is rendered inside the page-header actions container; it behaves correctly as a top-layer modal in Edge, but a future shared confirmation component could host it alongside the existing `#unsaved-changes` dialog.

## 6. Recommendation

**GO for integration approval of `feature/seera-invoice-localization-final-2026-10-29` at `81e9178`** (plus the evidence commit): the dialog replaces the browser prompt in both locales with Cancel, Escape, empty-reason and keyboard paths verified; only eligible Super Admins see the action; the server still refuses unauthorized and ZATCA-locked reopening; the accounting reversal, VAT withdrawal, record cancellation and audit trail are intact; validation labels translate without changing stored values; focused tests, the full suite, JavaScript tests and the browser verification are green. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
