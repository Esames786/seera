# Project workspace closure UX — verification, 31 October 2026

Branch `feature/seera-closure-ux-projects-2026-10-31` (base `5f852e2`, the combined HR release candidate). Verified HEAD before fixes: `4d46fb5`. Fix commit and evidence commit: see the branch log. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

Scope: the Project workspace (read-only view and Edit / Manage) after the sprint that localises its headings, tabs and identity labels and adds direct links from a project to the Project Reports portfolio (pre-filtered by code) and to its Budget / BOQ page.

## 1. Safe checkout and diff review

- Working tree clean on `5f852e2`; `4d46fb5` matched the expected HEAD; merge base with `5f852e2` is `5f852e2`.
- `4d46fb5` changes 6 files in 6 commits: the view and edit pages (title, breadcrumb, header, actions through `__()`), the shared workspace partial (tabs, read-only overview rows, section labels), the identity header (labels through `__()`, two new links gated by Projects view), 19 Arabic entries and the new `ProjectWorkspaceClosureUxTest`. **No controller, route, permission, model, budget, cost or accounting code is touched**; `database/` is unchanged. The links target existing routes (`admin.reports.projects.index` with `search=<code>`, `admin.master.projects.budget.show`), both already protected by Projects view and the project scope.
- Read-only boundary: the view page renders the overview table and no form (`_save_action` absent); the edit page renders the profile form with its save actions; the view's "Edit / Manage" button appears only with Projects edit.
- Dictionary after fixes: 1,011 keys, 0 duplicates, valid JSON; all four views lint clean.

## 2. Tests

| Run | Result |
| --- | --- |
| `ProjectWorkspaceClosureUxTest`, `ProjectWorkspaceTest`, `ProjectReportsOverviewTest` through `scripts/test-speed.ps1 -Mode parallel -Processes 4 -OpCache` | **30 passed / 319 assertions, 0 failures** (406 s; `ProjectWorkspaceTest` is the heavy class) |
| Project, budget, reports, finance scope, permission, admin screens, Arabic UI, sidebar regression set (8 workers, OPcache) | **226 passed / 3,145 assertions, 0 failures** (238 s) |
| Full Laravel suite on the final sources (8 workers, OPcache) | **818 tests, 818 passed, 10,026 assertions, 0 failures, 0 skipped, 0 errors, exit 0, 406.7 s** (base `5f852e2`: 815 / 10,008; the difference is exactly the 3 new tests / 18 assertions) |
| `npm run test:js` | 12 passed, 0 failed |

## 3. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| CU-01 | The Budget / BOQ tab used `:title="__('…')"` on a plain anchor. Blade only evaluates `:attr` on components, so the tooltip was lost and the raw expression was emitted as a `:title` attribute in both languages. | Fix commit: ordinary `title="{{ __('…') }}"` |
| CU-02 | "Budget / BOQ" (tab and the new link), "Not set" and "Location" were wrapped in `__()` but had no Arabic entry, so they stayed English in Arabic; the identity section's aria-label was hard-coded English. | Fix commits: four Arabic entries added, aria-label translated; a second commit adds the six quick-create dialog labels of the project form ("New Client" / "Create Client", "Project Classification" / "Save Classification", "New Project Manager Account" / "Create Account") that the browser sweep found still English on the Arabic Edit / Manage page (the shared component already translates them; only the entries were missing) |
| CU-03 | The shared workspace panel titles and summary labels from `ProjectWorkspacePanels` (21 strings such as "Sites / Locations", "Project Team / Staff", "Committed (SAR)", "Posted cost (SAR)") pass through `__()` but have no Arabic entries, so the related-record tabs and the financial summary line stay English in Arabic. Pre-existing and shared with the other workspaces. | Open, for the Arabic coverage sprint (financial terms need the Arabic-speaking reviewer) |

No permission, scope, budget or financial-figure regression was found.

## 4. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness `tests/manual/project-workspace-browser.cjs` (Arabic expectations read from `lang/ar.json`; the only write is a no-op save of the PRJ-001 profile with its current values by the Super Admin). Results: `evidence/closure-ux-projects-2026-10-31/project-workspace-browser-results.json`. Screenshots stay outside Git.

**First run on the fixed sources: 70 / 75** (the four failures were the quick-create buttons of CU-02 and one harness selector that counted the status badge as an identity label). **Final run: 75 checks, 75 passed, 0 page errors.** An earlier attempt timed out at login only because it overlapped a ParaTest run on the same workstation; the harness now uses 90-second navigation timeouts and the rerun was done with the machine idle. The recorded 403 / 404 responses are the intended refusals.

Covered:

1. **Read-only view** (Super Admin, PRJ-001): title, breadcrumb, description, "Edit / Manage" and "Back to Projects", identity labels, master budget "SAR 2,500,000.00", "Detailed current budget: none yet", overview rows Location / Description / Workflow, no save control and no form; every `/admin/` link on the page answers 200 or 302.
2. **Connected navigation**: "Open Project Reports" opens the portfolio pre-filtered to PRJ-001 (one row, search box carrying the code); "Budget / BOQ" opens the project's budget page; the tab carries the real tooltip and no stray `:title` attribute (CU-01).
3. **Edit stays functional**: the edit page renders the profile form with its save actions; saving the unchanged profile succeeds ("Project saved successfully."), the name, master budget and summary are unchanged afterwards.
4. **Arabic**: view and edit titles, breadcrumbs, descriptions, header actions, identity labels, the two links, Overview / Budget tabs with tooltip, section aria-labels and the read-only overview table are the exact dictionary strings; figures identical to English. Sweeps at 320 / 390 / 768 / 1440 in both locales on view and edit with no horizontal overflow and no untranslated action button; the remaining Latin tabs and summary labels (CU-03) are recorded.
5. **Roles**: Project Manager (Projects edit, project 1) sees Edit / Manage, opens edit, reports pre-filtered and budget. Marketing Manager (Projects view only) opens the view without Edit / Manage, edit URL 403, reports and budget open. Site In-Charge (project 3 / site 4) sees PRJ-003 with its links and budget "SAR 3,800,000.00", reports pre-filtered to PRJ-003 only, a forged search for PRJ-001 returns no row, forged view / edit / budget links to PRJ-001 answer 404, own edit 403 without Projects edit. HR Manager (Financial Reports view only) and Finance Manager get 403 on view, edit, budget and reports.

## 5. Recommendation

**GO for integration approval of `feature/seera-closure-ux-projects-2026-10-31`** at the final HEAD: the read-only view carries no form, Edit / Manage still saves, the new Project → Project Reports and Project → Budget / BOQ links resolve to the existing scoped and permission-gated pages for every role tested, forged links are refused, master budget and summary figures are identical in both locales and unchanged by a save, the tooltip defect and the missing Arabic entries are fixed, and the focused classes, the regression set, the full suite, the JavaScript tests and the browser verification are green. CU-03 (shared workspace panel titles and financial summary labels in Arabic) stays open for the Arabic coverage sprint. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
