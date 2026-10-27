# Release defects closure — 27 October 2026

Branch: `feature/seera-connected-workspaces-2026-09-23`.
Base HEAD: `aca78eea1388c249012239ffe207c6b2454db951` (also the owner's last reported production commit).
Committed on 27 October 2026 as `cc0e5f9` (purchase VAT override policy) and `7106173` (Arabic mobile overflow and priority-screen Arabic labels), documentation in the following commit; pushed to the feature branch. No deployment, merge, production migration or production seeder was performed.

## Scope and preservation

The previous review's uncommitted integration test, manual reproducer, browser harness, readiness documents and unrelated `FABLE-HANDOFF-2026-10-08.md` were present at the start. They were preserved. The original VAT reproducer now checks rejection; the prior result remains recorded in the baseline evidence. The old 30/33 browser result and screenshots remain separate from closure evidence.

## VAT policy (GAP-01 / R22-02)

- `PurchaseVatPolicy` is the central server decision for PO header/line overrides. Store and update validate both; request-supplied permission flags, exemption/category claims and totals are not trusted.
- Default is 15% for VAT-applicable items. Persisted `Item.vat_applicable = false` supplies a legitimate zero, including mixed orders. The existing catalogue does not distinguish statutory zero-rated versus exempt classifications; this sprint does not invent that taxonomy.
- A nonstandard manual header/line rate requires the authenticated Super Admin and a nonblank reason, at most 500 characters. Finance permission alone does not qualify. Every nonstandard draft save requires that review; ordinary edits do not silently carry an override forward.
- Override logs contain actor, document number, reason and configured/requested rates. They are written inside the document transaction. Validation failure writes neither a new document nor an override log.
- PO update rechecks editable state under a row lock. Approval uses a draft-only conditional update. GRN update also rechecks its editable state under lock. Historical approved/posted records are not recalculated, backfilled or rewritten.
- PO-linked receipts sum expected VAT from their approved PO lines. Matched supplier-bill lines inherit their source PO rates, or the saved header rate of a standalone legacy receipt. A forged header/line rate cannot retax a matched line. Input VAT is posted once on bill approval; matched mixed-rate bills create VAT-register groups at the actual line rates, with unchanged balanced GL totals.
- Unmatched supplier-service lines retain their existing bill-default policy. Site Expense, equipment and sales authorization policies remain outside this PO-specific change. Previously approved source exceptions can still flow downstream without being treated as a new manual override.

Regression cases cover purchasing and Finance actors, forged header/line/exemption fields, missing Super Admin reason, audited create/update, configured non-VAT, default 15%, rejection without line replacement, approved-order immutability, mixed 5%/15%/0% receiving/billing, VAT register grouping, balanced GL and duplicate approval rejection.

## RTL layout (UI-01)

Actual baseline DOM measurements at 390px identified `.topbar-right` overflowing to the left: Dashboard 463px, Employee 446px, Customer 447px document widths. Employee save/previous controls also exceeded the viewport. Fixes wrap the top bar/actions, permit grid/flex children to shrink, constrain inputs and identity grids, and wrap long identity text. Wide tables keep their existing internal horizontal scroll. No global `overflow-x: hidden` was added.

The closure harness checks Dashboard, Employee and Customer at **320, 375, 390, 430 and 768px**. All measured document widths equal the viewport. It traverses all 13 Employee tabs and 11 Customer tabs at 390px, checks application labels in Arabic, tests long content and the unsaved-changes dialog, opens the leave-type master dialog at 320px, and checks the three English pages at 390px. Routes, permission checks and save/previous-section actions remain in place.

## Arabic coverage (GAP-02, scoped closure)

The three priority screens and their related form/tab actions now use the existing Laravel JSON and namespaced translations. Shared headings, metrics, tables, statuses, quick-create controls, delete confirmation, breadcrumbs, form labels, alerts, customer panel empty states and common validation errors have Arabic text. English remains the fallback. User-entered names, codes, account names, notes and historical audit descriptions remain original business data.

This closes the priority-screen scope, not translation of every Finance/Equipment screen, browser-native file chooser text, formal legal translation or client language sign-off. No translation engine or route/permission vocabulary was replaced.

## Verification

| Check | Result |
| --- | --- |
| First VAT/GRN/client regression run | 34 tests, 392 assertions, PASS |
| Affected procurement, VAT/GL and workspace regression | 112 tests, 1,283 assertions, PASS (before the final excess-precision negative case; final full suite includes it) |
| Complete Laravel suite (first run, found one regression) | 768 tests, 767 passed, 1 failure: `ClientFeedbackSeptember21Test::test_hr_screens_show_a_readable_ampersand` — translated component attributes written as `title="{{ __('…') }}"` were escaped twice (`&amp;amp;`) |
| Fix and re-check | 25 component attributes on the Employee / Customer templates bound as `:title="__('…')"`; three untranslated navigation entries (My Approvals, Customer Credits, Accounting Reports) given Arabic text; affected classes re-run 41 tests / 765 assertions PASS, Arabic UI + workspace classes 12 tests / 398 assertions PASS; browser harness re-run 46 / 46 PASS (screenshots refreshed) |
| **Complete Laravel suite (final sources)** | **768 tests, 768 passed, 9,344 assertions, 0 failures** ([full-laravel-final.json](evidence/defect-closure/full-laravel-final.json)); the earlier JUnit file records the first run |
| Complete JavaScript suite | 12 tests, 12 passed, 0 failed/skipped |
| Focused actual Edge browser | 46 checks passed, including final navigation-label follow-up; 0 JavaScript errors / failed resources |
| Build / ZIP | `npm run build:release` passed; 18 files, every extracted entry compared by SHA-256 with its source |
| ZIP SHA-256 | `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E` |
| Diff whitespace | `git diff --check` passed |
| Generated user guide | 453 internal links checked, including 423 anchors; no broken target |

Laravel tests use SQLite `:memory:` with an empty DB URL. Browser tests reuse the private, synthetic SQLite fixture on localhost:18769, with separate storage, blocked external requests and blocked service workers. They are not production, physical-device, offline-sync or client sign-off evidence. No full cumulative business UAT was repeated.

Evidence is in [defect-closure](evidence/defect-closure/): affected/full JUnit reports when complete, JavaScript output, browser JSON and screenshots. Key images: [Dashboard 390px](evidence/defect-closure/dashboard-ar-390.png), [Employee 390px](evidence/defect-closure/employee-ar-390.png), [Customer 390px](evidence/defect-closure/customer-ar-390.png), [leave master 320px](evidence/defect-closure/employee-ar-leave-master-320.png).

## Remaining gates

The full Laravel suite is green on the final sources; release approval remains the owner's decision. External gates from the previous review remain: company ZATCA simulation/official SDK, owner Finance/HR acceptance, physical-device GPS/offline testing and live reconciliation/restore evidence. Production ZATCA must not be enabled by this sprint.

User guide version 1.21 explains the new policy and mobile behavior in both Markdown and generated HTML. There are no new migrations or seeders in this change.

## Changed files

The [source/test SHA-256 manifest](evidence/defect-closure/source-manifest.json) lists exact paths and hashes. Groups:

| Files | Purpose |
| --- | --- |
| `app/Services/Accounting/PurchaseVatPolicy.php` (new), `app/Http/Controllers/Admin/Inventory/PurchaseOrderController.php` | Central rate authorization, defaults, precision, override audit and draft concurrency guard |
| `app/Http/Controllers/Admin/Inventory/GoodsReceiptController.php`, `app/Http/Controllers/Admin/Accounting/AccountsPayableController.php`, `app/Services/Accounting/GrnMatchingService.php`, `app/Services/Accounting/PostingService.php` | Source-rate carry-forward, posted-state guard and mixed VAT-register grouping |
| `resources/views/admin/inventory/purchase-orders/{_form,_line-row}.blade.php`, `resources/views/admin/accounting/accounts-payable/{_form,show}.blade.php` | Override reason/read-only controls, item treatment preview, matched-bill guidance and actual rate display |
| `resources/css/erp.css` | Responsive top bar, grids, actions and long text |
| `lang/ar.json`, `lang/ar/validation.php` (new) | Existing-convention Arabic labels, messages, statuses and common validation |
| `resources/views/admin/dashboard.blade.php`, Employee and Customer templates under `resources/views/admin/hr/employees/` and `resources/views/admin/master/customers/` | Priority pages, related forms/tabs and view/register actions |
| Shared admin `data-table`, `delete-modal`, `form-section`, `metric-card`, `page-header`, `quick-create`, `sidebar`, `status-badge`, `topbar` components and `resources/views/layouts/admin.blade.php` | Shared translations and safe JavaScript translation serialization |
| Employee and Customer controllers | Localized success messages; stored business text unchanged |
| `tests/Feature/PurchaseVatAuthorizationTest.php`, `tests/Feature/ReleaseArabicUiTest.php` (new) | Authorization/forgery/immutability and Arabic rendering/validation regression |
| `tests/Feature/GrnBillMatchingTest.php`, `tests/Feature/ClientChangeRequestsTest.php` | Mixed-rate financial scenario; explicit configured non-VAT fixture. The previous uncommitted integration scenario is retained |
| `tests/manual/RequirementGapReviewTest.php`, `tests/manual/release-defects-browser.cjs` | Reproducer closure and isolated targeted browser checks |
| `public/build.zip` | Rebuilt verified 18-file Vite release package |
| `docs/user-guide/SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.{md,html}` and readiness documents/evidence | User instructions, defect status, verification results and retained baseline |

The unrelated pre-existing Fable handoff and original UAT browser harness were not modified. Release commits: `cc0e5f9`, `7106173` and the documentation commit that follows this report.
