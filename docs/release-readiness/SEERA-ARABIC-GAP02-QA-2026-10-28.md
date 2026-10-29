# Arabic GAP-02 verification — 28/29 October 2026

Branch `feature/seera-arabic-gap02-2026-10-28` (base: the integrated release `ef3114b`). Verified HEAD before fixes: `78b0252`. Fix commit: `b7c4dcf`. Evidence commit: see the branch log.

Scope: the focused Arabic localization sprint for GAP-02 (Customer Invoice form fields, headings and help text; Equipment asset page operational explanations and GPS limitations). Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

## 1. Safe checkout and diff review

- Working tree before checkout: clean on `feature/seera-release-integration-2026-10-28` at `ef3114b` (one pre-existing untracked handoff note left untouched). The branch was fetched and checked out as a tracking branch; `78b0252` matched the expected HEAD and its merge base with `ef3114b` is `ef3114b` itself.
- `78b0252` changes 4 files on top of `ef3114b`: `lang/ar.json` (+29 entries), the customer invoice `_form` partial, the equipment asset page and the new `ArabicGap02UiTest` (2 tests). No controller, model, policy, route, migration or JavaScript file is touched.
- Arabic dictionary at `78b0252`: valid JSON, 828 keys, 0 duplicates. All view changes wrap display text in `__()`; the `form-section` and `data-table` components already translate their `title` / `subtitle`.
- Attribute and guard sets (`name=`, `value=`, `type=`, `for=`, `id=`, `route(...)`, `@can`, `$can*`, `isSuperAdmin()`, `readonly`, `required`, `@selected`) of the changed views are identical before and after the translation; both views compile.

## 2. Focused tests (SQLite in-memory, seeded)

| Run | Result |
| --- | --- |
| `--filter=ArabicGap02UiTest\|SalesVatAuthorizationTest\|EquipmentArabicUiTest\|CustomerInvoiceWorkspaceTest\|EquipmentTest` on `78b0252` | 36 passed / 599 assertions, 0 failures (170 s) |
| `--filter=ArabicGap02UiTest` on the fixed tree | 3 passed / 50 assertions |
| Same five classes on the fixed tree (`b7c4dcf` sources) | 37 passed / 634 assertions, 0 failures (75 s) |

Class breakdown on the fixed tree: CustomerInvoiceWorkspaceTest 15, EquipmentTest 8, SalesVatAuthorizationTest 8, ArabicGap02UiTest 3, EquipmentArabicUiTest 3.

## 3. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness: `tests/manual/arabic-gap02-browser.cjs`. Results: `evidence/arabic-gap02-2026-10-28/arabic-gap02-browser-results.json`. Widths 320, 390, 768 and 1440 in English and Arabic. Screenshots stay outside Git.

**First run on `78b0252`: 47 checks, 43 passed, 0 page errors.** The four failures were one defect (AR-01 below). **Run on the fixed tree: 47 checks, 47 passed, 0 page errors.** The single recorded 403 is intentional (a Project Manager without Equipment permission opening the register).

Covered:

- Customer Invoice create (Super Admin and Accounts Manager) and edit (Accounts Manager) in both locales at all widths: no horizontal overflow, no untranslated buttons or links. English baseline asserted by exact string (labels, line headers, section titles, KSA-5 and line-calculation help, placeholder, revenue account tooltip, "Default:" option). Arabic asserted by exact string for the same set plus the page heading, description, breadcrumb and the "View Details" action; no Latin-only label, header or section title remains.
- Form field names, default values and every select's option values are identical between locales on create and on edit; the saved draft shows the same values in both locales.
- Sales VAT authorization unchanged in both locales: Super Admin editable rate (15 default) with reason field; Accounts Manager read-only rate, no reason field, Arabic policy note; a forged 5 % request (readonly removed in the DOM) refused server-side with the English or Arabic message; standard 15 % saves; rate stays read-only on edit.
- Equipment asset page: the nine English help texts are present byte-for-byte in English and absent in Arabic, where their Arabic counterparts appear (meter rule, assignment, fuel posting, maintenance lifecycle, cost reaching the Project Cost Report only through posted accounting, private documents, GPS "not connected" with no position / route / speed / idle / geofence, what exists today, Phase 1 note). Empty-state rows, tracker readiness, document and maintenance type labels are Arabic; operation form field names and option values (`Cash`, `Bank`, `Supplier Credit`, document and maintenance type keys) are identical between locales.
- Equipment permissions unchanged: the site-level user without Financial Reports view sees the "cost figures are shown to users with Financial Reports view" row (Arabic and English) and no amounts; the Project Manager role without Equipment permission still gets 403.

Remaining Latin text in the Arabic UI of these pages is data only: the "S Seera" brand mark, asset code / name, and customer, project, cost centre, supplier, expense category and employee names from the fixture.

## 4. Full regression

- `php artisan test` on the fixed tree (`b7c4dcf` sources): **787 tests, 787 passed, 9,535 assertions, 0 failures** (2,161 s). Compared with the integrated release (`ef3114b`: 784 / 9,485): the three new tests are `ArabicGap02UiTest`.
- `npm run test:js`: 12 passed, 0 failed.
- Assets: no file under `resources/css`, `resources/js`, `public/` or the build configuration changed between `ef3114b` and HEAD, so no rebuild was required; `public/build.zip` unchanged (SHA-256 `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`).

## 5. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| AR-01 | The customer invoice create / edit page wrappers were not localized: heading ("Add Customer Invoice", "Edit Invoice: …"), description, breadcrumb and the "View Details" action stayed English in Arabic (the `_form` partial was translated, the page files were not). The browser harness flagged "View Details" as an untranslated priority action at every width. | `b7c4dcf`: wrap the page titles, breadcrumbs, descriptions and the action in `__()`; add the Arabic entries |
| AR-02 | The equipment asset page still rendered its empty-state rows, tracker readiness line, "Bill" / "Receipt" / "Invoice" / "Download" links, expiry suffixes, "open maintenance record(s)" / "records, cancelled excluded" fragments and the document / maintenance type labels in English (most dictionary keys already existed but the view did not call `__()`). | `b7c4dcf`: wrap those fragments; add the type labels and tracker fragments to `lang/ar.json`; `ArabicGap02UiTest` now asserts them and the unchanged English fallback |

Neither finding is a behavioural regression: no field name, option value, route, validation rule or permission guard changed, and the English rendering is unchanged (asserted by exact string in the test and in the browser).

## 6. Remaining untranslated fields (outside this sprint's two pages)

- Accounts Receivable index and invoice detail pages (breadcrumb "Accounting / Accounts Receivable", the posting explanation on the index page, detail headings) were not part of this sprint and were not re-verified here.
- Data values are shown as entered (customer, project, cost centre, supplier and employee names, asset codes); these are not translation gaps.

## 7. Security, VAT and accounting

No regression found. The translation commits touch no controller, policy, model, route or migration; `SalesVatPolicy` behaviour is identical through the browser and the test suite; posting, VAT calculation and approval logic are untouched; equipment cost visibility and register access remain permission-gated as before.

## 8. Recommendation

**GO for integration approval of `feature/seera-arabic-gap02-2026-10-28`** on the evidence above: the two findings were fixed on this branch, focused tests, the full suite, JavaScript tests and the browser verification are green, and the release archive is unchanged. This is not a production go: the external gates in the defect register (GATE-01 official SDK / company simulation / Finance rounding decision, GATE-02 device, client and operational evidence) and the wider GAP-02 client language review remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
