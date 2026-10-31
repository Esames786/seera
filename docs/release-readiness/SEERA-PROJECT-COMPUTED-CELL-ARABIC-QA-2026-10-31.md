# Project workspace computed cells in Arabic (CU-06 closure) — verification, 31 October 2026

Branch `feature/seera-project-computed-cell-arabic-2026-10-31` (base `5de645d`, the panel-Arabic branch). Verified HEAD before fixes: `b47941b`. Fix commit and evidence commit: see the branch log. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

Scope: the system-generated cell values of the Project workspace panels (computed status words, "Yes / No", "Restricted", "Posted / Not posted", receiving states, "No positive stock", "Whole project", equipment type / status labels, stored enumeration statuses) and the supplier-bill approval labels, rendered in Arabic without touching user-entered text, codes, dates or amounts.

## 1. Safe checkout and diff review

- Working tree clean on `5de645d`; `b47941b` matched the expected HEAD and has `5de645d` as ancestor.
- `b47941b` changes 4 files in 4 commits (+140 / −4): `ProjectPanelTable::displayCell()` (a per-panel map of column indices that may carry a system label, and a whitelist of 59 system labels; anything else is returned unchanged), the `$yes` closure and the equipment type / status / "Whole project" cells now translated at build time, the panel partial calling `displayCell()` per cell, 23 Arabic entries (approval labels, lower-case statuses, valuation methods) and the new `ProjectComputedCellArabicTest` (4 tests).
- **Design is safe by construction**: a cell is translated only when its column index is in the map *and* its exact value is in the whitelist, so customer / supplier / site names, codes, references, dates, amounts and stored activity text can never be altered even if they happen to equal a dictionary key (a site literally named "Restricted" in the name column stays "Restricted"; covered by a test). No query, scope, permission or amount code changed; `row()` builds the same cells as before apart from the translation of its own literals.
- Translator audit through the application translator in `en` and `ar`: all 59 whitelist labels, the 13 equipment type / status labels and the 7 approval labels resolve to strings, English unchanged; after the fix none falls back to English. Dictionary after fixes: 1,168 keys, 0 duplicates, valid JSON; the partial lints clean.

## 2. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| CC-01 | Three money columns were missing from the index map although their restricted placeholder "Restricted" is whitelisted: site-expense total (index 7), equipment posted fuel / maintenance (6, 7) and warehouse stocked items (6). A reader without Financial Reports / Warehouse Stock view saw a mixed row in Arabic ("مقيّد" in some columns, "Restricted" in others). The warehouse map also listed a non-existent index 9. | Fix commit: indices added, index 9 dropped; test asserts the Arabic placeholder in every restricted money column and that a name column is never translated. |
| CC-02 | "posted", "cancelled" and "planning" are whitelisted but had no Arabic entry, so the Material Used status column (always "posted") and cancelled / planning statuses stayed English in Arabic. | Fix commit: three entries consistent with the existing "Posted" / "Cancelled" / "Planning" capitalised entries; covered by the same test. |
| CC-03 | The delivered test expected "تم الاستلام ولم تتم الفوترة" for "Received but not invoiced", but the dictionary entry (introduced on the CU-03 branch) is "مستلم ولم تتم فوترته", so `ProjectComputedCellArabicTest` failed on `b47941b` as committed (1 of 4). | Fix commit: the test asserts the dictionary value through `__()` and the exact current entry; the dictionary is unchanged so the other workspaces keep their wording. |
| CU-06 | Closed: system-generated cell values and supplier-bill approval labels render in Arabic; everything else identical (see §4). | — |

No permission, scope or financial-figure regression was found.

## 3. Tests

| Run | Result |
| --- | --- |
| `ProjectComputedCellArabicTest`, `ProjectPanelArabicClosureTest`, `ProjectWorkspaceTest` on the fix sources, before the test correction (runner, 8 workers, OPcache) | 30 tests, 29 passed, 322 assertions, 1 failure = CC-03 (196 s) |
| `ProjectComputedCellArabicTest` + `ProjectPanelArabicClosureTest` after the fixes (5 + 6 tests) | **11 passed / 92 assertions** |
| Finance, inventory, receivables, permission, scope, sidebar, closure-UX and project-report regressions (runner, 8 workers, OPcache) | **270 passed / 2,980 assertions, 0 failures** (302.9 s) |
| Full Laravel suite on the final sources (runner, 8 workers, OPcache) | **829 tests, 829 passed, 10,118 assertions, 0 failures, 0 skipped, 0 errors, exit 0, 866.3 s** (base `5de645d` carries 824; the difference is the 4 delivered tests plus the 1 added here) |
| `npm run test:js` | 12 passed, 0 failed |

## 4. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness `tests/manual/project-panels-browser.cjs`, extended with a per-cell comparison between the English and Arabic fragments of every populated panel: a cell whose column index is in the map and whose English value is a whitelisted label must be the exact Arabic entry; the mixed cells built in `row()` (Yes / No parts, equipment type / status / "Whole project") must keep their digits and lose their English words; **every other cell must be byte-identical** to English. Results: `evidence/closure-project-panel-arabic-2026-10-31/project-panels-browser-results.json` (same evidence folder as the panel-Arabic branch; screenshots stay outside Git).

**168 checks, 168 passed, 0 page errors** (first and only run on the fixed sources). Across the 13 populated panels of PRJ-001: 44 rows, **40 system cells translated and 271 cells identical** between locales (customer, supplier, site, employee and item names, codes, references, dates, contact details, amounts, stored activity descriptions). No whitelisted label remains in English in any Arabic row. Supplier-bill approval labels: the finance panel's "Supplier Bill approval context" keeps its heading and columns; the five approval labels resolve to Arabic (checked through the translator and asserted by the test; the fixture's two bills show "Approved").

Also re-verified unchanged: tab titles, column headings, action buttons, empty states, notes and the financial summary (figures identical to the English fragment and to the `9a6316d` / `5de645d` evidence: SAR 2,500,000.00 / 130,847.97 / 5.2 % / 320,000.00 / 138,000.00 / 368,000.00 / 189,152.03; customer notes 368,000.00 and 0.00; stock 481,405.75; materials 10,741.41); the paged pages' breadcrumbs; sweeps at 320 / 390 / 768 / 1440 on the Sites, Financial / Cost Summary and Project Purchases pages in both locales with no overflow and no Latin button, heading, note, empty state or footer in Arabic.

Restricted users: the Site In-Charge (project 3 / site 4, no Financial Reports) sees only SITE-D1, no English system label in any of their five Arabic panels, and the equipment fuel / maintenance columns as "مقيّد" (CC-01) rather than amounts; the forged PRJ-001 panel answers 404 as page and fragment. The Marketing Manager gets 403 on every module-gated panel by direct URL; the Project Manager's managed projects open scoped to their own sites; the HR Manager and Finance Manager get 403 on page and fragment.

## 5. Recommendation

**GO for integration approval of `feature/seera-project-computed-cell-arabic-2026-10-31`** at the final HEAD: computed status labels and supplier-bill approval labels translate, user-entered text, codes, dates and amounts are byte-identical in both locales, restricted placeholders are consistent after CC-01, the delivered test is green after CC-03, and the focused classes, regressions, full suite, JavaScript tests and browser verification are green. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
