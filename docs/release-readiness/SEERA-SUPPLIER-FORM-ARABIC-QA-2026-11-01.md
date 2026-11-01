# Supplier form Arabic closure (MW-03) — verification, 1 November 2026

Branch: `feature/seera-supplier-form-arabic-2026-11-01`. Expected/delivered HEAD `8efd42ff5dac26986a5d25365a7a06277cdb19aa` was fetched and verified against base `2997db4`. The checkout initially remained on the base branch; it was switched to the requested tracking branch after confirming ancestry. The unrelated untracked `docs/FABLE-HANDOFF-2026-10-08.md` was preserved and excluded.

## Scope and review

Reviewed the three delivered files and kept application/regression-test changes within them:

1. `resources/views/admin/master/suppliers/_form.blade.php`: existing Laravel translations for field labels, placeholders, help text and static option labels. Names, IDs, values, validation attributes, form endpoints, project selections and Save controls remain intact.
2. `lang/ar.json`: Arabic form vocabulary. User-entered supplier, category, payment-term and account names are not translated or rewritten.
3. `tests/Feature/SupplierFormArabicClosureTest.php`: delivered English/Arabic edit tests plus new create/dialog, form-contract and submission/accounting regressions.

No controller, model, route, permission, project-scope, database, accounting calculation or Equipment file changed. Additional files are the browser harness, this report, the register update and verification evidence.

## Confirmed findings fixed

| Finding | Correction |
| --- | --- |
| SF-01: the dictionary key for the payable-account explanation started with a space before the comma, while the form requested the key without that space. The final clause remained English. | Corrected the exact dictionary key; regression checks the entire Arabic clause and rejects the English fallback. The missing-control-account fallback now uses the existing translation. |
| SF-02: Account Code lacked an Arabic entry. The generated ` (45 days)` part of payment-term options remained English. | Added Account Code; localized only the generated day suffix, preserving the stored term name, option ID and English rendering. A form-local Arabic event handler applies the same suffix localization to options returned by quick-create. Browser tests verify the newly created term before reloading. |
| Create page visible heading/description had no dictionary entries. | Added the two entries used by the existing shared page-header translator. The wrapper itself was not changed. See remaining gaps below for its document title and breadcrumb. |

English labels, original punctuation in the payable explanation, default select values and payment-term labels remain as before. New payment-term/account/category records are still created by their existing endpoints and permissions; this work does not create alternate posting or validation paths.

## Test evidence

All tests used local SQLite test databases. Production ZATCA remained disabled. The full suite ran after the final application changes, including the Arabic quick-create suffix handler.

| Run | Result |
| --- | --- |
| Focused `SupplierFormArabicClosureTest`, `SupplierWorkspaceTest`, `MasterWorkspaceUxClosureTest` | **19 passed / 261 assertions / 0 failures**, exit 0; 32.56 s runner wall time |
| Accelerated full Laravel, 8 workers + CLI OPcache | **838 passed / 10,221 assertions / 0 failures**, exit 0; 475.43 s runner wall time |
| JavaScript (`npm run test:js`) | **12 passed / 0 failed / 0 skipped**, exit 0; 330.648 ms |
| Headless Edge / Playwright | **113 passed / 0 failed / 0 page errors**, exit 0 |

Commands:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode focused -OpCache -Filter 'SupplierFormArabicClosureTest|SupplierWorkspaceTest|MasterWorkspaceUxClosureTest'
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode parallel -Processes 8 -OpCache
npm run test:js
node tests/manual/supplier-form-arabic-browser.cjs
```

The combined filter executes all three requested classes. The full run also covers existing finance, HR, permission and project-scope regressions. Worker-cache targets were checked to be ordinary directories within this repository before invoking the parallel runner.

Logs and results: [evidence directory](evidence/supplier-form-arabic-2026-11-01). `source-manifest.json` records exact hashes of the three application/test files, the harness and the release ZIP.

## Browser verification

Used a separate disposable copy of the existing synthetic UAT SQLite fixture on **127.0.0.1:18771**, with isolated session, view and bootstrap caches. The harness refuses other targets. Login/locale changes and QA supplier/category/term/account creation occur only in that disposable fixture.

- Create and Edit in **English and Arabic**, at **320 / 390 / 768 / 1440px**.
- Main form labels, placeholders, section headings, help text and actions; VAT/CR/IBAN format masks are intentionally unchanged.
- Category, payment-term and payable-account dialogs opened on both screens at every width. Checks cover translated labels/help/placeholders, document width and modal card bounds.
- Main form plus all three dialogs have identical names, control values, selected options, required flags and endpoints across locales (CSRF tokens excluded).
- Actual quick-create submissions for all three dialogs in each locale; returned IDs are selected and user-provided names preserved. A new 45-day term has the correct locale suffix immediately.
- Actual Supplier Create with Save, then Edit with Save & Close, in each locale. Persisted bank details, payment channel and `1234.56` opening balance are verified; edit reopens with the updated account-holder name.
- Supplier View still contains no record-writing form or Save control. Edit retains all seven related-record tabs.
- Finance and HR users without Supplier permissions receive 403 for Create and Edit. Existing SupplierWorkspaceTest separately verifies project scoping, hidden financial rows, forbidden direct panels and cross-record writes.
- PHP submission regression confirms supplier create/update does not add journals or VAT transactions. Existing financial calculations and database field types are untouched.

The first browser attempt never reached the login form because Windows drive-qualified bootstrap-cache paths were interpreted as repository-relative by the fixture setup. This was corrected using a unique ignored cache directory under `storage/framework/testing/`; no application change was required. `browser-setup-failed.txt` preserves that infrastructure failure. The subsequent completed run is `browser-results.json` / `browser-console.txt`.

Screenshots retained in evidence include Create/Edit at 390px in both languages and all three Arabic quick-create dialogs. The payable explanation and form layouts were also visually reviewed.

## Build and accounting boundaries

No Vite asset source changed. The Arabic handler lives in Blade and is rendered by Laravel. No asset rebuild was needed: all **18 ZIP entries** were decompressed and hash-compared successfully against `public/build`.

`public/build.zip` SHA-256: `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`.

No historical-data mutation, migration, rounding change, payment-channel policy change or accounting write was introduced. No merge, deployment, production migration/seeding or production ZATCA activation was performed.

## Remaining Arabic gaps and decision

**GO for integration of the scoped Supplier form MW-03 closure.** Not production acceptance or whole-module Arabic sign-off.

- The unchanged `suppliers/create.blade.php` still supplies an English browser document title and breadcrumb. The visible heading/description now translate through their shared component. This wrapper gap is outside the three-file form review and is not claimed closed.
- Existing supplier controller success messages and custom quick-create validation errors remain outside these three files. They are not covered by the form-label completion claim.
- Stored names/categories/payment-term names/account labels intentionally retain their original language. Other views using `PaymentTerm::label()` can still show its English generated day suffix; this change is local to the Create/Edit form, including newly added options.
- Arabic business wording should receive the normal client language review. Existing production/ZATCA acceptance gates remain unchanged.
