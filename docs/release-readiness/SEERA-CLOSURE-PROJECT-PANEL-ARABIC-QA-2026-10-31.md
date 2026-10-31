# Project workspace connected panels in Arabic (CU-03 closure) — verification, 31 October 2026

Branch `feature/seera-closure-project-panel-arabic-2026-10-31` (base `05b183b`, the closure-UX projects branch). Verified HEAD before fixes: `8f40c70`. Fix commit and evidence commit: see the branch log. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

Scope: the connected panels of the Project workspace (sites and locations, project staff, equipment and warehouses, suppliers and procurement, customer invoices and receipts, financial / cost summary, activity history) rendered in Arabic: tabs, column headings, action buttons, empty states, notes and the financial summary labels, both as the AJAX fragments the workspace tabs load and as the "View all (paged)" pages.

## 1. Safe checkout and diff review

- Working tree clean on `05b183b`; `8f40c70` matched the expected HEAD; merge base with `05b183b` is `05b183b`.
- `8f40c70` changes 4 files in 6 commits (+216 / −30): the panel partial (`_panel.blade.php`: card title, "View all (paged)", "+ Add Location", "Add Site Expense", "Assign Equipment", equipment / ZATCA / activity notes, finance summary labels, detailed-budget labels, "No detailed budget…" note, the two finance action buttons, supplier-bill heading / columns / empty row, list column headings, "Actions", row action labels, empty states, the "Showing from–to of total" footer and the Previous / Next pager, all through `__()`), the paged page (`panel.blade.php`: document title, breadcrumb, "Back to Project"), 119 Arabic entries and the new `ProjectPanelArabicClosureTest` (4 tests).
- **No controller, route, permission, model, support class, budget, cost or accounting code is touched**: `ProjectWorkspaceController`, `ProjectWorkspacePanels` (queries, scope, summary figures) and `ProjectPanelTable` (cells, authorized actions) are unchanged; `database/` is unchanged. Every amount is still produced by the same `number_format` calls on the same values; data cells are still printed raw (`{{ $cell }}`), so stored values are never passed through the translator.
- Coverage audit (script over the two views and the two support classes against `lang/ar.json`): all 16 panel titles, every column heading of the 15 list panels, every row action label, every empty state, the 20 summary labels of the identity header, the 7 finance labels, the 4 detailed-budget labels, the supplier-bill columns and the pager / footer strings have an Arabic entry. The only strings without one are module names, date formats and the `available` query value, which are not rendered.
- Dictionary after fixes: 1,134 keys, 0 duplicates, valid JSON; both views lint clean.

## 2. Tests

| Run | Result |
| --- | --- |
| `ProjectPanelArabicClosureTest`, `ProjectWorkspaceTest`, `ProjectWorkspaceClosureUxTest`, `ProjectReportsOverviewTest` on `8f40c70` through `scripts/test-speed.ps1 -Mode parallel -Processes 4 -OpCache` | **34 passed / 336 assertions, 0 failures** (143.8 s ParaTest, 193.6 s wall) |
| `ProjectPanelArabicClosureTest` after the fix (5 tests, see CU-04) | **5 passed / 27 assertions**; with the original `panel.blade.php` restored the same class fails 2 of 5 (both breadcrumb assertions), which proves the defect and the fix |
| Full Laravel suite on the final sources (8 workers, OPcache) | **823 tests, 823 passed, 10,053 assertions, 0 failures, 0 skipped, 0 errors, exit 0, 464.2 s** (base `05b183b`: 818 / 10,026; the difference is exactly the 5 new tests / 27 assertions) |
| `npm run test:js` | 12 passed, 0 failed |

## 3. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| CU-03 | Closed by this branch: the connected panel titles, column headings, action buttons, empty states, notes and the financial summary labels now render in Arabic; figures identical to English (see §4). | — |
| CU-04 | The paged panel page's breadcrumb was built as `__('Master Setup / Projects /').__($title)`, so the separating space was lost in both languages ("Master Setup / Projects /Sites / Locations"): an English regression against the base, where the breadcrumb was "Master Setup / Projects / Sites / Locations". | Fix commit: the space is restored between the translated prefix and the translated title; two assertions added (English and Arabic breadcrumb) and one test that the Arabic finance fragment translates its labels and keeps every SAR amount of the English fragment. |
| CU-05 | Open localisation gap: the amount notes of the customer, stock and materials panels ("Project approved invoices: SAR …", "Current positive stock value: SAR …", "Material used: SAR …", "Total issued quantity …") and the two calculation caveat notes of the financial summary are still English in Arabic. The dictionary already carries five partial entries for the note prefixes ("Material used: SAR", "Current positive stock value: SAR", …) that nothing renders, because the view prints these notes without `__()`. | Open: wrap the notes as whole sentences with `:amount` placeholders and have the Arabic-speaking reviewer confirm the accounting caveats. |
| CU-06 | Outside CU-03 scope, recorded: data cells stay English in Arabic: stored status words ("active", "received", "pending"), the computed words "Yes / No", "Posted / Not posted", "Restricted", "Whole project", the receiving states ("Received but not invoiced", "Fully invoiced", …) and "No positive stock". The dictionary already has entries for "Not posted", the receiving states and "No positive stock" that are not used because cells are printed raw. | Open: decide which computed cell words to translate inside `ProjectPanelTable` (never user data), with the Arabic reviewer. |

No permission, scope, budget or financial-figure regression was found.

## 4. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness `tests/manual/project-panels-browser.cjs` (Arabic expectations read from `lang/ar.json`; read-only, no fixture write). Results: `evidence/closure-project-panel-arabic-2026-10-31/project-panels-browser-results.json`. Screenshots stay outside Git.

**Final run: 133 checks, 133 passed, 0 page errors.** The first run scored 132 / 133: the one failure was a wrong harness expectation (the fixture's Project Manager is `manager_id` of all three projects, so NR-34 grants PRJ-003; the check now expects the managed project to open scoped to its own site), not an application defect. The recorded 403 / 404 responses are the intended refusals.

Covered:

1. **English baseline** (Super Admin, PRJ-001): all 16 connected tabs in order; every panel fragment answers 200 with its title "… — PRJ-001", "View all (paged)", the exact column list plus "Actions" (finance: the 7 summary labels and the two action buttons), and the exact empty state where the fixture has no rows (site-expenses, equipment) or rows where it has them (the other 13). The paged Sites page keeps the breadcrumb "Master Setup / Projects / Sites / Locations" and "Back to Project" (CU-04).
2. **Arabic fragments** (same user, every panel): tab titles are the exact dictionary strings in the same order; card title, "View all (paged)", "+ Add Location", "Add Site Expense", "Assign Equipment (open register)" exact; column headings exact for all 15 list panels; empty states exact for the two empty panels; for the 13 populated panels the row count and the number of row actions equal English, every action label is the exact Arabic of its English label ("View", "Edit / Manage", "View Site", "Edit Site", "Stock On Hand", "Stock Ledger", "View Issue", "View Invoice", "Record Receipt"), and the footer "عرض 1–n من أصل n" carries the same numbers as "Showing 1–n of n"; the equipment, ZATCA and activity notes exact.
3. **Financial summary in Arabic**: the 7 labels exact and every amount identical to the English fragment (master budget, posted cost, budget used %, posted revenue, supplier billed, customer invoiced, margin); the "No detailed budget…" note and the "Open Project Cost Report" / "Open Budget / BOQ" buttons exact; the supplier-bill context heading, its 4 columns and its rows match. The two calculation caveat notes are recorded as still English (CU-05).
4. **Paged pages in Arabic** (sites, finance, invoices): `dir="rtl"`, document title, breadcrumb "الإعدادات الأساسية / المشاريع / <title>" and "العودة إلى المشروع" exact.
5. **Sweeps** at 320 / 390 / 768 / 1440 on the Sites, Financial / Cost Summary and Project Purchases pages in both locales: no horizontal overflow; in Arabic no Latin button, card title, column heading, h3, empty state or footer at any width. Remaining Latin notes (CU-05) and data cells (CU-06) recorded per width.
6. **Roles**: Project Manager (project level) sees five Arabic tabs (site-expenses, sites, stock, requests, finance), each fragment loads with its Arabic title, and the managed PRJ-003 opens scoped to its single site (NR-34). Marketing Manager (Projects view only) sees no module-gated panel and every hidden panel answers 403 by direct URL. Site In-Charge (project 3 / site 4) sees only SITE-D1 in the Sites panel with Arabic headings, and the forged PRJ-001 panel answers 404 as page and as fragment. HR Manager and Finance Manager get 403 on the panel page and fragment.

## 5. Recommendation

**GO for integration approval of `feature/seera-closure-project-panel-arabic-2026-10-31`** at the final HEAD: CU-03 is closed for tabs, column headings, action buttons, empty states, notes and the financial summary labels; every amount is identical in both languages and produced by unchanged code; routes, authorization, project / site scope and the read-only / edit boundaries are unchanged and re-verified for six roles; the breadcrumb regression (CU-04) is fixed with tests; the focused classes, the full suite, the JavaScript tests and the browser verification are green. CU-05 (amount and caveat notes) and CU-06 (computed cell words) stay open as recorded localisation gaps. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
