# Project Reports overview (MVP) — verification, 30 October 2026

Branch `feature/seera-project-reports-mvp-2026-10-29` (base `70b31e0`). Verified HEAD before fixes: `ee3c606`. Fix commit: `5120f18` (tests and two Arabic entries only). Evidence commit: see the branch log.

Scope: the new read-only Project Reports overview (`admin/reports/projects`): scoped listing, metrics, search and status filters, Financial Reports link visibility, permission denial, English and Arabic rendering at 320 / 390 / 768 / 1440, and the unchanged Project Cost Report. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

## 1. Safe checkout and diff review

- Working tree before checkout: clean on `feature/seera-invoice-reopen-messages-2026-10-29` at `70b31e0` (one pre-existing untracked handoff note left untouched). `ee3c606` matched the expected HEAD; merge base with `70b31e0` is `70b31e0`.
- `ee3c606` changes 7 files in 7 commits: `ProjectReportController` (new, read-only, validated `search` / `status`, `Project` query with customer / manager / site count, pagination), one route (`admin.reports.projects.index`, inside the existing `auth` / `active` / `password.changed` / `permission` / `scope` group), one middleware map entry (`reports.projects` → module `Projects`, so `index` requires Projects view), the sidebar entry switched from "coming soon" to the real link (gated by `SidebarMenu::link` through the same permission map), the Blade view, 15 Arabic entries and the new test. **No migration, seeder, model, service, accounting or report code is touched**; `database/` is unchanged and the controller reimplements no financial figure (cost / revenue / margin stay in the existing Project Cost Report, linked only when the user holds Financial Reports view).
- Scoping: `Project` is in the `user_access` global-scope list registered in `AppServiceProvider`, and `UserAccessScopeService` limits `projects` to `projectIdsFor()` (home project plus managed projects) for project-level users and to the site's project for site-level users; company-level users see every project. The counters are computed on the same scoped query, so they follow the scope and the filters.
- Dictionary: 944 keys, 0 duplicates, valid JSON; all PHP files and the view lint clean; `public/build.zip` unchanged (SHA-256 `B1FF72DA…39C4E`), no frontend source changed.

## 2. Focused and regression tests (SQLite in-memory, seeded)

| Run | Result |
| --- | --- |
| `--filter=ProjectReportsOverviewTest` on `ee3c606` | **4 tests, 3 passed, 1 failed** (PR-01 below) |
| `--filter=ProjectReportsOverviewTest` on the corrected test | 7 passed / 46 assertions |
| Regression filter `ProjectReportsOverviewTest\|ProjectWorkspaceTest\|ProjectBudgetTest\|ProjectBudgetReportTest\|FinanceScopeAuthorizationTest\|FinancialReport\|ProjectCost\|ReleaseArabicUiTest\|TopbarAvailabilityUiTest\|AccessScope\|Sidebar\|Permission` | **125 passed / 1,545 assertions, 0 failures** (440 s) |

Scenarios now covered by `ProjectReportsOverviewTest`: Super Admin search and ledger link; Arabic labels with unchanged status values; project-level user (home project + managed projects only, forged search returns no row, ledger link shown while the role holds Financial Reports and the hidden-figures note once that permission is detached); company-level user without Financial Reports (every project, no ledger link); site-level user (only the site's project, forged search empty); status filter (real value filters, forged value rejected by validation); HR Manager without Projects view → 403.

## 3. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness: `tests/manual/project-reports-browser.cjs` (Arabic expectations are read from `lang/ar.json`). Results: `evidence/project-reports-2026-10-30/project-reports-browser-results.json`. Screenshots stay outside Git. Fixture facts used: projects PRJ-001 (active, 2 sites), PRJ-002 (planning, 1 site), PRJ-003 (active, 1 site), all managed by the Project Manager; Super Admin; Project Manager (home project 1, manager of all three, holds Financial Reports); Site In-Charge (project 3 / site 4, Projects view, no Financial Reports); Marketing Manager (company level, Projects view only, no Sites view); HR Manager (no Projects view).

**First run on `ee3c606`: 31 checks, 29 passed** ("On hold" untranslated in the Arabic status filter; one harness URL defect). **Final run: 31 checks, 31 passed, 0 page errors.** The recorded 403 / 404 responses are the intended refusals.

Covered:

1. Super Admin: PRJ-001 / 002 / 003 listed in code order, metrics 3 projects / 2 active, Project Cost Report link and note shown, Sites column present, one View link per row to the project record, sidebar entry present.
2. Project Manager: the three permitted projects, metrics 3 / 2, ledger link shown (role holds Financial Reports); searches for own projects return one row each.
3. Site In-Charge: only PRJ-003, metrics 1 / 1, hidden-figures note and no ledger link; forged searches for PRJ-001 / PRJ-002 return no row, the empty-state message and 0 / 0 metrics; a forged direct link to the out-of-scope project record is refused (404 through the scoped binding) while the own record opens. Marketing Manager: every project at company level, no ledger link, no Sites column (no Sites permission).
4. HR Manager: no sidebar entry; the report answers 403 in English and in Arabic.
5. Financial Reports link only for the Super Admin and the Project Manager; the link opens the existing Project Cost Report (21 ledger figures rendered).
6. Filters: `status=planning` → PRJ-002 (metrics 1 / 0); `status=active` → PRJ-001 + PRJ-003 (2 / 2); `search=jeddah` → PRJ-002 by name; combined search + status keeps both inputs; a forged status value is answered with the validation message, no server error; Reset shows everything.
7. English baseline asserted by exact string (title, breadcrumb, description, metric labels, placeholder, status option labels and values, nine headers, table title, link label). Arabic asserted by exact dictionary string for the same set, status values unchanged, same rows, Arabic sidebar label, Arabic empty state and hidden-figures note; sweeps for the Super Admin and the Site In-Charge at 320 / 390 / 768 / 1440 in both locales with no horizontal overflow and no untranslated priority action.
8. Project Cost Report unchanged: the branch does not touch `FinancialReportController`, the budget / cost report services or any accounting code (diff), the financial-report, budget-report and finance-scope test classes are green, and the report renders from the link as before.
9. No migration, seeder or production write: `database/` unchanged; the report is a GET that writes nothing.

Remaining Latin text in the Arabic UI is data only (the "S Seera" brand mark, project codes).

## 4. Full regression

- `php artisan test` on the final sources (`5120f18`): **802 tests, 802 passed, 9,686 assertions, 0 failures** (base `70b31e0`: 795 / 9,640; the difference is exactly the 7 new tests / 46 assertions). Wall-clock 47,475 s because the workstation slept between 06:54 and 13:01 during the run; the single-process suite needs roughly 35 CPU-minutes. Speed-up actions (parallel runner, CLI opcache, lighter seeding) are requested for the next sprint.
- `npm run test:js`: 12 passed, 0 failed.

## 5. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| PR-01 | `ProjectReportsOverviewTest` assumed the seeded Project Manager has no Financial Reports permission; that role holds Financial Reports view / export, so the "no ledger link" assertion failed on every run (red suite). Its forged-search assertion also matched the term echoed back into the search box rather than a row. The application behaviour was correct. | Fix commit: assert the link for the permitted manager, detach the permission to assert the hidden-figures note, match table cells for the forged search, add company-level, site-level and status-filter scenarios |
| PR-02 | The Arabic status filter showed "Completed" and "On hold" in English (dictionary entries missing). | Fix commit: two Arabic entries added |

No application-code regression was found: scope, permission gating, filters, metrics and the ledger link behave as specified, and nothing in accounting or the existing reports changed.

## 6. Recommendation

**GO for integration approval of `feature/seera-project-reports-mvp-2026-10-29`** at the final HEAD: the overview lists only permitted projects for every scope level, denies users without Projects view, shows the Financial Reports link only to authorized users, filters correctly, renders in both locales at all four widths, requires no migration or production write, and leaves the Project Cost Report and accounting untouched; the corrected test, the regression set, the full suite, JavaScript tests and the browser verification are green. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
