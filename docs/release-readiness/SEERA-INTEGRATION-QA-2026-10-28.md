# Integrated branch QA and release gate — 28 October 2026

Branch `feature/seera-release-integration-2026-10-28` (baseline `dc048ff`; integrates the Sales VAT branch `1c446a6` plus the Equipment Arabic and bilingual approvals/availability work in `c9e2636`). Verified HEAD before fixes: `c9e2636`. Fix commit: `5b5ec29`. Evidence commit: see the branch log.

Scope: integration regressions of the combined release (Sales VAT authorization, Equipment & Vehicles Arabic localization, My Approvals English/Arabic UI, scoped approval project filters, Coming Soon and topbar changes). The historical business UAT programme was not repeated. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

## 1. Safe checkout

- Working tree before checkout: clean on `feature/seera-sales-vat-authorization-2026-10-28` at `1c446a6` (one pre-existing untracked handoff note left untouched).
- `c9e2636` changes 18 files on top of `1c446a6`; it does not touch any Sales VAT source, view or test file (diff of those paths: 0 lines).
- Arabic dictionary at `c9e2636`: valid JSON, 795 entries, 0 duplicate keys, 0 entries without Arabic text; all 7 Sales VAT strings present by exact key; 189 entries added by the integration (one duplicate `this record` line removed, the key is still defined once).
- `approvals_ui` and `ui` PHP dictionaries: English and Arabic key sets identical and in the same order.
- Equipment, approvals and accounts-receivable views: the set of `name=`, `value=`, `route(...)`, `method=`, `type=` attributes and `@can` guards is identical before and after translation (only display text is wrapped in `__()`); all changed Blade files compile.
- `MyApprovalController` now limits the project filter options through `UserAccessScopeService`; task queries were already scoped.

## 2. Focused tests (SQLite in-memory, seeded)

`php artisan test --filter='SalesVatAuthorizationTest|EquipmentArabicUiTest|EquipmentTest|EquipmentCostTest|TopbarAvailabilityUiTest|ApprovalRuntimeTest|ReleaseArabicUiTest|CustomerInvoiceWorkspaceTest'`

| Class | Tests |
| --- | --- |
| ApprovalRuntimeTest | 48 |
| SupplierBillApprovalRuntimeTest (matched by the filter) | 26 |
| CustomerInvoiceWorkspaceTest | 15 |
| EquipmentCostTest | 9 |
| EquipmentTest | 8 |
| SalesVatAuthorizationTest | 8 |
| TopbarAvailabilityUiTest | 5 |
| EquipmentArabicUiTest | 3 |
| ReleaseArabicUiTest | 3 |
| **Total** | **125 passed / 1,260 assertions, 0 failures** |

After the INT-01 fix, `TopbarAvailabilityUiTest` was re-run: 5 passed / 33 assertions.

## 3. Browser QA (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness: `tests/manual/integration-release-browser.cjs`. Results: `evidence/integration-2026-10-28/integration-browser-results.json`. Widths 320, 375, 390, 430, 768 and 1440 in English and Arabic. Screenshots stay outside Git.

**Result: 145 checks, 145 passed, 0 page errors.** The three HTTP 403 responses recorded are intentional (a Project Manager without Equipment permission opening the register; an HR Manager without approval modules opening My Approvals).

Covered:

- Equipment register, Add, Edit and View in both locales at all widths: no horizontal overflow, no untranslated buttons or links. A new asset was created through the English form; the Arabic edit form holds exactly the same field values and the same select option values (type, ownership, meter, status). The Arabic asset page keeps every section (overview, assignment, fuel, add-fuel, maintenance, add-maintenance, costs, documents, tracking, activity), the fuel / maintenance / document forms submit the same field names and option values (`Cash`, `Bank`, `Supplier Credit`, document and maintenance types), and GPS tracking reads "غير متصل".
- Scoped visibility: after assigning the asset to project 3 / site 4, the site-level user (site 4) sees it in the register and the project-level user does not (403, no Equipment permission on that role).
- My Approvals: a runtime approval was started on PR-2026-0007 (synthetic fixture adjusted for this: workflow 2 department constraint and amount limits cleared, step 2 optional, requester changed to a non-approver). The company-level actor sees all three projects; the project-level user (home project 1, manager of all three fixture projects) sees exactly the three the scope service resolves; the site-level user sees only project 3 and no task. Action links (View, Review & approve, Review & reject) all target the document; filters by module and project keep their values; a forged out-of-scope project id matches nothing. Arabic labels, headers and action links translated with the same submit values.
- Topbar: My Approvals shortcut present for users with an approval module, absent otherwise; decorative search / notification / settings icons removed; locale switch persists across navigation and flips `dir` / `lang`.
- Coming Soon (hr-reports, project-reports, settings): truthful text in both locales, no obsolete "Phase 1" claim, dashboard link present. INT-01 found here (see below) and re-verified after the fix in both locales at 390px.
- Sales invoice VAT controls: Super Admin sees an editable rate (15 default) and the reason field; override without reason refused, with reason saved. Accounts Manager sees a read-only rate and no reason field; a forged 5 % request (readonly removed in the DOM) is refused server-side with the English or Arabic policy message; a standard 15 % invoice saves.

Remaining English text in the Arabic UI (not priority actions): the long help paragraphs on the Equipment asset page (fuel, maintenance, cost, document and tracking explanations) and the pre-existing Customer Invoice form labels and line headers (Customer, Invoice Number, Invoice Date, Due Date, Project, Cost Center, Notes, Item / Description, Qty, Unit Price, Revenue Account). Both were outside this integration and are listed in the defect register under GAP-02.

## 4. Full regression

- `php artisan test` on `c9e2636`: **784 tests, 784 passed, 9,476 assertions, 0 failures** (3,087 s).
- `php artisan test` on the fixed tree (`5b5ec29` sources): see section 6.
- `npm run test:js`: 12 passed.
- Assets: no file under `resources/css`, `resources/js`, `public/` or the build configuration changed between `dc048ff` and HEAD, so no rebuild was required; `public/build.zip` unchanged (SHA-256 `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`, archive test clean).

## 5. Bugs found and fixed

| ID | Finding | Fix |
| --- | --- | --- |
| INT-01 | Arabic Coming Soon heading and breadcrumb showed "Hr Reports" (title-cased slug, no dictionary key) while the sidebar showed the translated label | `5b5ec29`: the route resolves each sidebar slug to the sidebar's own label; four inventory placeholder labels added to `lang/ar.json`; `TopbarAvailabilityUiTest` asserts the translated titles |

No security or accounting regression was found: permission guards and submit values are unchanged by the translations, the approval project filter is now scoped like the task list, and the Sales VAT policy behaves identically through the browser (server-side refusal of forged rates, audit on override).

## 6. Final full suite on the fixed tree

`php artisan test` on the `5b5ec29` sources: **784 tests, 784 passed, 9,485 assertions, 0 failures** (5,440 s under concurrent machine load). The nine additional assertions are the new Coming Soon title checks.

## 7. Recommendation

**GO for the next gate (owner review / client UAT of the integrated branch)** on the technical evidence above: no integration regression remains open, one bilingual defect (INT-01) was found and fixed, and tests, browser checks and the release archive are green. **Not a production go**: the external gates in the defect register (GATE-01 official SDK / company simulation / Finance rounding decision, GATE-02 device, client and operational evidence) and the untranslated Customer Invoice form and Equipment help text (GAP-02) remain as recorded. No merge into the original feature branch, no deployment, no production migration or seeding, and production ZATCA stays disabled.
