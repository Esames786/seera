# Open findings — 26 October 2026

## Closure update — 27 October 2026

See [release defects closure](SEERA-DEFECT-CLOSURE-2026-10-27.md) for the candidate code, test evidence and artifact. The original table below is preserved as the **26 October baseline**, not the current code status.

| ID | Local candidate status |
| --- | --- |
| GAP-01 / R22-02 | Fixed (purchase, 27 Oct): central Super Admin-only manual PO VAT override with reason and transactional audit; configured non-VAT retained; approved source rates carried to receiving/matched billing and grouped VAT register. Fixed (sales, 28 Oct, branch `feature/seera-sales-vat-authorization-2026-10-28`): `SalesVatPolicy` on customer invoice create / update — 15 % default, Super Admin override with logged reason, forged line rates refused, posted invoices immutable, notes inherit the rate. Open: sales has no zero-rated / exempt classification model (Finance decision) |
| UI-01 | Fixed: targeted wrap/shrink rules, all three screens at 320/375/390/430/768px; 46 focused browser checks passed; no global overflow hiding |
| GAP-02 | Priority Dashboard/Employee/Customer scope fixed, including shared controls, related panels and common validation. Wider Finance/Equipment translation and client language review remain separate |
| GATE-01 / GATE-02 | Unchanged external gates; this sprint does not enable production ZATCA, perform client sign-off or run production operations |

### Integrated-branch QA — 28 October 2026

See [integrated branch QA](SEERA-INTEGRATION-QA-2026-10-28.md) (branch `feature/seera-release-integration-2026-10-28`).

| ID | Status |
| --- | --- |
| INT-01 | Found and fixed (`5b5ec29`): the Arabic Coming Soon page heading and breadcrumb showed the title-cased slug ("Hr Reports") in English while the sidebar entry was translated; the route now resolves the sidebar's own label so both locales use one dictionary key |
| GAP-02 (integration) | Equipment register / add / edit / view and My Approvals: labels, headings, buttons and table headers translated; form field names, option values and permissions unchanged (verified in the browser). Remaining English: long help paragraphs on the Equipment asset page and the pre-existing Customer Invoice form labels (Customer, Invoice Number, Invoice Date, Due Date, Project, Cost Center, Notes, line headers), which were outside this integration |

Affected tests: 112 passed / 1,283 assertions. JavaScript: 12 passed. Complete Laravel suite on the final sources: 768 tests, 9,344 assertions, 0 failures (a double-escaped ampersand regression found by the first full run was fixed and re-verified). Committed as `cc0e5f9` and `7106173`; production stays at the owner's reported baseline `aca78ee` until the owner deploys.

### Arabic GAP-02 verification — 28/29 October 2026

See [Arabic GAP-02 verification](SEERA-ARABIC-GAP02-QA-2026-10-28.md) (branch `feature/seera-arabic-gap02-2026-10-28`, verified HEAD `78b0252`, fix `b7c4dcf`).

| ID | Status |
| --- | --- |
| GAP-02 (Customer Invoice form, Equipment asset page) | Fields, line headers, section titles, placeholders, tooltips and help text translated by the sprint and verified in the browser at 320 / 390 / 768 / 1440 in both locales; English fallback, field names, option values, VAT authorization and equipment permissions unchanged |
| AR-01 | Found and fixed (`b7c4dcf`): invoice create / edit page heading, description, breadcrumb and the "View Details" action stayed English in Arabic |
| AR-02 | Found and fixed (`b7c4dcf`): equipment asset page empty-state rows, tracker readiness line, "Bill" / "Receipt" / "Invoice" / "Download" links, expiry suffixes and document / maintenance type labels stayed English in Arabic |
| GAP-02 (remaining) | Accounts Receivable index / detail pages and the wider client language review are outside this sprint |

### Accounts Receivable register Arabic verification — 29 October 2026

See [AR register Arabic verification](SEERA-AR-REGISTER-ARABIC-QA-2026-10-29.md) (branch `feature/seera-ar-register-arabic-2026-10-29`, verified HEAD `982447a`, fix `5fb0a8b`).

| ID | Status |
| --- | --- |
| GAP-02 (AR register, invoice detail) | Navigation, filters, metric cards, table headers, read-only labels, VAT / balance sections and the posting note translated; amounts, filter values, actions and permissions identical between locales; approval posting verified through the Arabic UI |
| AR-03 | Found and fixed (`5fb0a8b`): `__('ZATCA')` resolved to the `zatca` lang group on a case-insensitive filesystem and both pages failed with HTTP 500 (18 focused tests red); replaced by `zatca.label` |
| AR-04 | Found and fixed (`5fb0a8b`): shared "Filter" / "View" / "Edit" / "Delete" controls, ZATCA status option labels, "Both" and the receipt count stayed English in Arabic |
| GAP-02 (remaining) | Activity log entries stored in English, the reopen `prompt()` question, raw ZATCA validation status; the wider client language review |

### Invoice reopening dialog verification — 29 October 2026

See [invoice reopen verification](SEERA-INVOICE-REOPEN-QA-2026-10-29.md) (branch `feature/seera-invoice-localization-final-2026-10-29`, verified HEAD `81e9178`, no source fix needed).

| ID | Status |
| --- | --- |
| GAP-02 (reopen dialog, ZATCA validation) | The browser `prompt()` is replaced by a bilingual in-page dialog (Cancel / Escape / empty reason / keyboard verified at 320–1440); local ZATCA validation states translate without changing stored values; Super Admin-only visibility, server refusals (403, ZATCA lock) and the accounting reversal / audit trail verified unchanged |
| GAP-02 (remaining) | Reopen server messages (flash, 403, validation rule texts), activity log and reversal journal descriptions stored in English; the wider client language review |

### Invoice reopening server messages — 29 October 2026

See [reopen messages verification](SEERA-INVOICE-REOPEN-MESSAGES-QA-2026-10-29.md) (branch `feature/seera-invoice-reopen-messages-2026-10-29`, verified HEAD `692ca45`, no source fix needed).

| ID | Status |
| --- | --- |
| GAP-02 (reopen server messages) | 403, required-reason, unpaid-without-receipts and success messages bilingual through `lang/*/invoice_reopen.php`; English copy unchanged; journal number interpolated; verified in the browser in both locales with the reversal / VAT / audit behaviour unchanged |
| GAP-02 (remaining) | Activity log, reversal journal description and the stored notes line in English (audit text); the wider client language review |

### Project Reports overview (MVP) — 30 October 2026

See [Project Reports verification](SEERA-PROJECT-REPORTS-QA-2026-10-30.md) (branch `feature/seera-project-reports-mvp-2026-10-29`, verified HEAD `ee3c606`, test / dictionary fix on the branch).

| ID | Status |
| --- | --- |
| PR-01 | Found and fixed: the new test assumed the seeded Project Manager lacks Financial Reports (it holds view / export) and matched the echoed search term instead of a row; the application behaviour was correct. Test made deterministic with company-level, site-level and status-filter scenarios |
| PR-02 | Found and fixed: "Completed" / "On hold" status options untranslated in the Arabic filter |
| Project Reports | Scoped read-only portfolio verified for Super Admin, Project Manager, Site In-Charge, Marketing Manager and HR Manager (403); Financial Reports link only with that permission; no migration, no accounting change |

### HR dashboard truthful UI — 31 October 2026

See [HR dashboard verification](SEERA-HR-DASHBOARD-QA-2026-10-31.md) (branch `feature/seera-hr-dashboard-truthful-ui-2026-10-30`, verified HEAD `5c138ee`, fix on the branch).

| ID | Status |
| --- | --- |
| HR-01 | Found and fixed: PHP parse error in `HrDashboardController` (`pendingOvertime` ternary without its `null` branch) made the dashboard and every test that boots it fail on the delivered HEAD |
| HR-02 | Found and fixed: dashboard action buttons ("+ Add Employee", "Open Attendance", "Open", "View All") stayed English in Arabic |
| HR-03 | Closed on branch `feature/seera-hr-dashboard-arabic-2026-10-31` (see [HR dashboard Arabic](SEERA-HR-DASHBOARD-ARABIC-QA-2026-10-31.md)): page title, breadcrumb, description, metric labels, table titles, column headers, approval-queue names and empty states translated through the existing `__()` pattern with 25 dictionary entries; no logic, route, permission or data change; Arabic wording awaits the Arabic-speaking reviewer |
| HR dashboard | Attendance counts only with Attendance view, payroll figures only with Payroll view, chart placeholder replaced by a truthful note, counts follow project / site scope, sidebar and direct URLs consistent with the permission map; verified in the browser for five roles in both locales |

### Combined HR release candidate — 31 October 2026

See [HR release candidate QA](SEERA-HR-RELEASE-CANDIDATE-QA-2026-10-31.md) (branch `feature/seera-hr-release-candidate-2026-10-31`, verified HEAD `91820dd`, fix on the branch).

| ID | Status |
| --- | --- |
| RC-01 | Found and fixed: HR Reports filtered leave start dates and attendance dates with a plain string `whereBetween`, which dropped last-day-of-month records on engines that keep the time part of the date cast (SQLite tests and fixture); now compared by calendar date, with a regression test |
| HR Reports MVP | Read-only workforce, leave and attendance summary gated by HR view (route and sidebar) and Attendance view (attendance table); no payroll, salary or bank data; project / site scope preserved; 14 Arabic entries; verified in the browser for five roles in both locales |
| Candidate composition | Dashboard permission gates (`a4d0322`), HR-03 Arabic (`5cc6819`) and the test tooling (`6be5720`) are carried unchanged |

### Project workspace closure UX — 31 October 2026

See [closure UX verification](SEERA-CLOSURE-UX-PROJECTS-QA-2026-10-31.md) (branch `feature/seera-closure-ux-projects-2026-10-31`, verified HEAD `4d46fb5`, fix on the branch).

| ID | Status |
| --- | --- |
| CU-01 | Found and fixed: the Budget / BOQ tab used `:title="__()"` on a plain anchor, so the tooltip was lost and the raw expression was emitted as an attribute in both languages |
| CU-02 | Found and fixed: "Budget / BOQ", "Not set" and "Location" had no Arabic entry, the identity section aria-label was hard-coded English, and the project form quick-create dialogs ("Create Client", "Save Classification", "Create Account" and their titles) had no Arabic entries |
| CU-03 | Open: the shared `ProjectWorkspacePanels` titles and summary labels (21 strings incl. financial terms) have no Arabic entries, so related-record tabs and the summary line stay English in Arabic (pre-existing, shared with other workspaces) |
| Project workspace | Read-only view without a form, functional Edit / Manage with a verified no-op save, direct links to the pre-filtered Project Reports portfolio and to Budget / BOQ, permissions and project / site scope unchanged, master budget figures identical in both locales; verified for six roles |

### Project workspace connected panels in Arabic (CU-03 closure) — 31 October 2026

See [connected panel Arabic verification](SEERA-CLOSURE-PROJECT-PANEL-ARABIC-QA-2026-10-31.md) (branch `feature/seera-closure-project-panel-arabic-2026-10-31`, verified HEAD `8f40c70`, fix on the branch).

| ID | Status |
| --- | --- |
| CU-03 | Closed: the connected panel tabs, column headings, action buttons, empty states, notes and the financial summary labels render in Arabic with figures identical to English; `ProjectWorkspacePanels` / `ProjectPanelTable` unchanged |
| CU-04 | Found and fixed: the paged panel breadcrumb lost its separating space in both languages (`__('Master Setup / Projects /').__($title)`), an English regression against the base; space restored, breadcrumb assertions and an amount-preservation test added |
| CU-05 | Closed by `56ca708` / `ea04f9e` (architect): the amount notes of the customer, stock and materials panels and the two finance calculation caveats are translated with the amounts kept outside the translated strings; verified on `ea04f9e` with figures identical to `9a6316d` and to English (137 / 137 browser checks, 6 / 6 closure tests) |
| CU-06 | Open (outside CU-03 scope): computed cell words ("Yes / No", "Posted / Not posted", "Restricted", receiving states, "No positive stock") and stored status words stay English in Arabic; cells are printed raw, so the existing entries for the receiving states are unused |
| Connected panels | Routes, authorization, project / site scope and read-only / edit boundaries unchanged; verified for six roles in both locales, 133 / 133 browser checks |

## Original findings

Baseline `aca78ee`. This register distinguishes missing requirements, policy decisions and reproduced UI defects from accounting failures. No new production code fix or data repair was made in this review.

| ID | Priority / type | Reproduction and evidence | Next action |
| --- | --- | --- | --- |
| GAP-01 | High business-acceptance requirement gap: R22-02 | Non-Super-Admin role with Purchase Orders view/create saves a new PO at header and line VAT 5%, net 100, total 105. `tests/manual/RequirementGapReviewTest.php` characterises this current behaviour: 1 test / 7 assertions. A PASS here means the missing restriction was reproduced, NOT that R22-02 is accepted. PO validation in `PurchaseOrderController::validated` accepts 0–100 and has no override gate. | Agree transaction/line versus global override, ordinary edits to previously authorised non-default drafts, and zero/exempt exceptions. Then implement one coordinated policy across the agreed purchase/sales paths with negative-role tests and an audited override. Preserve historical rates and later agreed Site Expense behaviour. |
| UI-01 | P2: mobile Arabic overflow | Actual Edge browser at 390×844, Arabic RTL: Dashboard document width 463px, Employee Edit 446px, Customer Edit 447px. See screenshots and browser-results.json. Shared topbar uses fixed height and a non-wrapping action row; a targeted responsive layout investigation is required. | Small responsive-header/locale/actions fix, EN/AR desktop/mobile regression and visual review. Do not merely hide overflow and conceal controls. Deferred from this financial-blocker review. |
| GAP-02 | P2: incomplete Arabic coverage | Arabic Dashboard screenshot still shows Dashboard, metrics and section labels in English. Source also has hardcoded Equipment/Finance form labels. Locale and RTL toggle itself works. | Translate agreed user-facing modules and validation/print text; preserve route/permission keys; Arabic-speaking reviewer signs off. |
| GATE-01 | External / owner gate | Company simulation, official SDK, legal/company identity confirmation and Finance rounding decision remain outstanding | Keep production ZATCA disabled; follow its separate readiness checklist. |
| GATE-02 | Acceptance evidence gap | Actual Android/iPhone GPS/offline, client tester participation, live financial reconciliation and backup restore evidence not available | Run the owner pack on authorised staging; record real testers and results rather than treating developer tests as sign-off. |

Original refund/in-flight/false-clearance blockers G01–G03 were fixed in `aca78ee`; see [correction report](../deployment-fixes-2026-10-26.md). No new financial-posting failure was observed in the 183 fresh critical tests or the added integrated procurement scenario. That is a bounded result, not an exhaustive security audit.

Evidence: [browser results](evidence/browser-results.json), [Arabic mobile Dashboard](evidence/mobile-ar_admin_dashboard.png), [Employee](evidence/mobile-ar_admin_hr_employees_1_edit.png), [Customer](evidence/mobile-ar_admin_master_customers_1_edit.png), [desktop Arabic](evidence/dashboard-ar-desktop.png).
