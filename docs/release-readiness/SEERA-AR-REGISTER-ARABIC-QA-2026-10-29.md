# Accounts Receivable register Arabic verification — 29 October 2026

Branch `feature/seera-ar-register-arabic-2026-10-29` (base `cd212a0`, the Arabic GAP-02 branch). Verified HEAD before fixes: `982447a`. Fix commit: `5fb0a8b`. Evidence commit: see the branch log.

Scope: the Accounts Receivable register (index) and the customer invoice detail page in English and Arabic. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

## 1. Safe checkout and diff review

- Working tree before checkout: clean on `feature/seera-arabic-gap02-2026-10-28` at `cd212a0` (one pre-existing untracked handoff note left untouched). The branch was fetched and checked out as a tracking branch; `982447a` matched the expected HEAD and its merge base with `cd212a0` is `cd212a0`.
- `982447a` changes 4 files in 5 commits: `lang/ar.json` (+55 entries, 911 keys, 0 duplicates), the register view, the invoice detail view and the new `AccountsReceivableArabicRegisterTest` (3 tests). No controller, model, policy, route, migration or JavaScript file is touched.
- Attribute, route, guard and amount expressions of both views (`name=`, `value=`, `type=`, `id=`, `href=`, `route(...)`, `@can`, `$can*`, `@if (...)`, `$money(...)`, `number_format(...)`, model amount attributes) are identical before and after the translation; both views compile.

## 2. Focused tests (SQLite in-memory, seeded)

`--filter=AccountsReceivableArabicRegisterTest|SalesVatAuthorizationTest|CustomerInvoiceWorkspaceTest|ArabicGap02UiTest`

| Run | Result |
| --- | --- |
| On `982447a` | **29 tests, 11 passed, 18 failed** (HTTP 500 on the register and the invoice detail, see AR-03) |
| After AR-03 fix | 29 passed / 438 assertions |
| On the final sources (`5fb0a8b`) | **30 passed / 456 assertions, 0 failures** (193 s) |

Class breakdown on the final sources: CustomerInvoiceWorkspaceTest 15, SalesVatAuthorizationTest 8, AccountsReceivableArabicRegisterTest 4, ArabicGap02UiTest 3.

## 3. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness: `tests/manual/ar-register-browser.cjs`. Results: `evidence/ar-register-2026-10-29/ar-register-browser-results.json`. Widths 320, 390, 768 and 1440 in English and Arabic. Screenshots stay outside Git.

**First run (after AR-03): 47 checks, 38 passed** (AR-04 and a harness detail). **Final run: 47 checks, 47 passed, 0 page errors.** The single recorded 403 is intentional (an HR Manager without Accounts Receivable permission opening the register).

Covered:

- Register (Super Admin and Accounts Manager): English baseline asserted by exact string (title, description, add action, four metric labels, search placeholder, the three "All …" options, Reset, 12 invoice headers, 5 receipt headers, posting note, pagination footer). Arabic asserted by exact string for the same set plus the breadcrumb; no Latin-only header, metric label, filter option or priority action remains.
- Register amounts and controls: metric values, every table cell's numbers and dates, filter field names and option values, row action forms (approve) and links are identical between locales. The filters `status=draft`, `status=unpaid`, `zatca=not_generated`, `search=INV-2026-0003`, `customer=1&status=draft` and a forged status value return the same invoice sets in both locales.
- Invoice detail (partially paid invoice with a receipt and journal; a 15 % draft): 35 English labels asserted byte-for-byte; 21 Arabic labels asserted by exact string; no Latin-only table label or section tab remains; every table's numbers, the header amounts, the action forms and links are identical between locales, for the Super Admin and for the Accounts Manager.
- Posting behaviour through the Arabic UI: "اعتماد وترحيل" on a 2 × 100 draft at 15 % posted a balanced journal (Dr 1200 Accounts Receivable 230.00; Cr 4100 Project Revenue 200.00; Cr 2210 Output VAT 30.00), the VAT section reads 15.00 / 200.00 / 30.00 / 230.00, the approve action disappears, the register's draft count fell by one and the outstanding receivable rose by exactly 230.00 (synthetic fixture only).
- Permissions unchanged: the Accounts Manager sees the same action set in both locales; a role without Accounts Receivable still gets 403.

Remaining Latin text in the Arabic UI of these pages is data only: the "S Seera" brand mark, invoice numbers, project / cost centre labels, chart-of-account names and activity log entries stored in English.

## 4. Full regression

- `php artisan test` on the final sources (`5fb0a8b`): **791 tests, 791 passed, 9,573 assertions, 0 failures** (2,497 s). Compared with the GAP-02 branch (`cd212a0`: 787 / 9,535): the four new tests are `AccountsReceivableArabicRegisterTest`.
- `npm run test:js`: 12 passed, 0 failed.
- Assets: no file under `resources/css`, `resources/js`, `public/` or the build configuration changed between `cd212a0` and HEAD; `public/build.zip` unchanged (SHA-256 `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`).

## 5. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| AR-03 | Both pages called `__('ZATCA')` for the ZATCA column. On a case-insensitive filesystem (every Windows dev / test machine) that key resolves to the whole `lang/zatca.php` group, so the register and the invoice detail rendered an array into `htmlspecialchars()` and failed with HTTP 500 (18 focused tests red, including the pre-existing CustomerInvoiceWorkspaceTest). On Linux it would have fallen back to untranslated English. | `5fb0a8b`: dedicated `zatca.label` entry ("ZATCA" / "زاتكا"); comment in the English group explains the trap |
| AR-04 | In Arabic the shared filter-bar "Filter" button and the action-buttons "View" / "Edit" / "Delete" row actions stayed English (untranslated priority actions at every width); ZATCA status option labels, "Both" and the "(n receipt/receipts)" fragment were hardcoded or missing from the dictionary. | `5fb0a8b`: wrap the component labels and the delete-confirmation name in `__()`; add the missing entries and a pluralised receipt count; tests extended |

Neither finding is a behavioural regression of the sprint's intent, but AR-03 was a confirmed defect blocking the pages. No field name, option value, route, amount expression, validation rule or permission guard changed; the English rendering is unchanged (asserted by exact string in the tests and in the browser).

## 6. Remaining localization gaps (outside the two pages' translated copy)

- Audit / activity log entries ("Approved customer invoice", "Created customer invoice") are stored in English at write time and shown as stored.
- The reopen-for-correction confirmation uses a browser `prompt()` with an English question (inline JavaScript); a later sprint should move it to a translated in-page confirmation.
- Chart-of-account names, project / cost centre labels and customer names are data values, not translation gaps.
- The ZATCA record block reuses the `zatca.*` dictionary; `ucfirst($record->validation_status)` is still raw.

## 7. VAT, accounting, permissions

No regression found. The branch touches no controller, policy, model, route or migration; `SalesVatPolicy` tests are green; approval posting through the Arabic UI produced the same balanced journal and the same register movement as the English path; amounts, dates and option values are identical between locales; permission-gated actions are identical per user in both locales.

## 8. Recommendation

**GO for integration approval of `feature/seera-ar-register-arabic-2026-10-29`** on the evidence above: the two findings were fixed on this branch, focused tests, the full suite, JavaScript tests and the browser verification are green, and the release archive is unchanged. This is not a production go: GATE-01 / GATE-02 and the wider client language review remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
