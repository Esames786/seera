# HR dashboard Arabic localisation (HR-03) — delivery and verification, 31 October 2026

Branch `feature/seera-hr-dashboard-arabic-2026-10-31`, created from the verified base `feature/seera-hr-dashboard-truthful-ui-2026-10-30` at `a4d0322`. Final HEAD: see the branch log. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

Objective: close HR-03 from the release defect register, the remaining English copy on the HR dashboard in Arabic, without touching the verified business logic or the Attendance / Payroll visibility rules.

## 1. Changed files

| File | Change |
| --- | --- |
| `resources/views/admin/hr/dashboard.blade.php` | `title` and `breadcrumb` sections go through `__()`; the eleven column headers, the five approval-queue names and the two empty-state messages are wrapped in `__()`. Metric-card labels, table titles, the page title and the description already pass through the shared components' `__()` and needed only dictionary entries. No PHP expression, condition, query, route or permission check changed |
| `lang/ar.json` | 25 Arabic entries added (breadcrumb, description, 10 metric labels, 4 table titles, 3 headers, 4 queue names, 2 empty states); 974 keys, 0 duplicates, valid JSON. Existing entries reused for "Total Employees", "Active Employees", "Status", "Employees", "Department", "Expiry", "Leave Type", "Start", "End", "Days", "End of Service", the status badges and the action buttons |
| `tests/Feature/HrDashboardArabicUiTest.php` | New: English copy unchanged (title, breadcrumb, description, every label, title, header and queue), Super Admin dashboard fully Arabic with no English label left in a cell or header, restricted HR Manager sees none of the attendance / payroll copy in either language, empty states in Arabic |
| `tests/manual/hr-dashboard-browser.cjs` | Arabic sweep now fails on any English label, title, header, queue name, note, empty state, breadcrumb or page title (previously recorded only); exact dictionary checks for metric labels, table titles, queue names, headers, title, breadcrumb and description |
| `docs/release-readiness/…` | This report, the register update and the browser results |

Not changed: controller, routes, middleware, models, seeders, migrations, payroll or attendance code, salary structures, audit history, components. `git diff --stat a4d0322` lists only the files above.

## 2. Tests (SQLite in-memory, seeded)

| Run | Result |
| --- | --- |
| `--filter=HrDashboardArabicUiTest\|HrDashboardPermissionsTest` | 7 passed / 294 assertions |
| HR, payroll, attendance, employee workspace, finance scope, permission catalogue, admin screens, Arabic UI, topbar, sidebar regression set (ParaTest, 8 workers, CLI OPcache) | **245 passed / 3,012 assertions, 0 failures** (261 s) |
| Full Laravel suite on the final sources (ParaTest, 8 workers, CLI OPcache) | **809 tests, 809 passed, 9,980 assertions, 0 failures, exit 0** (1,124 s; base `a4d0322`: 805 / 9,706, the difference is exactly the 4 new tests / 274 assertions) |
| `npm run test:js` | 12 passed, 0 failed |

The accelerated runner was used as `php artisan test --parallel --processes=8` with the isolated OPcache ini: ParaTest is present in `vendor/` on this workstation, and no other branch was merged to obtain tooling.

## 3. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Same three-phase harness and fixture adjustments as the base verification (five attendance rows for today; HR view temporarily granted to the Site In-Charge role; Attendance and Payroll temporarily removed from the HR Manager role; everything restored and verified afterwards). Results: `evidence/hr-dashboard-arabic-2026-10-31/hr-dashboard-browser-results.json`. Screenshots stay outside Git.

**89 checks, 89 passed, 0 page errors** (phase 1: 46, phase 2: 11, phase 3: 32), after one expectation fix in the harness itself: the fixture has an approved leave starting 31 October, so "On Leave Today" became 1 when the UTC date rolled over; the harness now takes that count from the fixture instead of a constant (the first run on the new sources showed 85 / 89 with exactly those four date-dependent count checks failing and every Arabic check passing). The recorded 403 / 404 responses are the intended refusals. Fixture restore verified after each run.

Confirmed:

1. **Super Admin dashboard functional**: 200, metrics 20 / 19 / 3 / 1 / 0 / 3 / 3 / SAR 262,588 / 2 / 5 / 2 / 1 in both locales, real attendance summary, all dashboard links answer 200 or 302.
2. **Restricted HR Manager**: no attendance or payroll figure, card, row, "SAR" string or sidebar entry in English or Arabic; hidden-figures note in both; direct register URLs 403.
3. **Arabic labels**: page title, breadcrumb, description, all 12 metric labels, 4 table titles, 15 column headers, 5 queue names, status badges, action buttons, notes and (in the feature test) both empty states are the exact dictionary strings; the Arabic sweep finds no English label anywhere it looks.
4. **English unchanged**: the English baseline checks of the base harness still pass unchanged (same strings, same order).
5. **Routes and actions**: Manual Attendance, Add Employee, Open Attendance, the five Open links and View All resolve to the same routes as before; sidebar entries follow the permission map.
6. **No horizontal overflow** at 320 / 390 / 768 / 1440 in both locales for the Super Admin and the restricted HR Manager (the longer Arabic labels wrap inside the cards).

## 4. Remaining gaps

- The payroll amount keeps the Latin currency code ("SAR 262,588") in Arabic, as every other Arabic finance screen does; a localised currency format is a product decision for the Arabic review, not a dashboard change.
- Employee names, department names and dates are data and stay as stored.
- The IQAMA status badge values come from the shared status component and were already translated; no new value was introduced.
- An Arabic-speaking reviewer should sign off the wording of the 25 new entries (they follow the existing dictionary's register).

## 5. Recommendation

**GO for integration approval of `feature/seera-hr-dashboard-arabic-2026-10-31`** at the final HEAD: HR-03 is closed through dictionary entries and `__()` wrappers only, the English copy is byte-for-byte unchanged, the Attendance / Payroll visibility rules verified on `a4d0322` hold in both languages, every route and action button resolves as before, nothing overflows at 320 / 390 / 768 / 1440, and the focused tests, the regression set, the full suite, the JavaScript tests and the browser verification are green. Arabic wording still needs the Arabic-speaking reviewer. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
