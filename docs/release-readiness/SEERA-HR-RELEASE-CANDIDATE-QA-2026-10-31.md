# Combined HR release candidate — integration QA, 31 October 2026

Branch `feature/seera-hr-release-candidate-2026-10-31`. Verified HEAD before fixes: `91820dd` (one integration commit on top of `5cc6819`). Final HEAD: see the branch log. Nothing was merged into the original feature branches, deployed, migrated or seeded on production; production ZATCA stays disabled.

Contents of the candidate: HR dashboard permission enforcement (verified at `a4d0322`), HR dashboard Arabic localisation HR-03 (verified at `5cc6819`), the HR Reports MVP (new in `91820dd`) and the ParaTest / CLI OPcache tooling (from `6be5720`).

## 1. Source review (diff `5cc6819..91820dd`, 19 files)

- **Tooling**: `composer.json`, `composer.lock`, `scripts/test-speed.ps1`, the acceleration document and its evidence are byte-identical to the test-speed branch at `6be5720` (`git diff 6be5720 91820dd -- <those paths>` is empty). `composer install --no-interaction` on the candidate changed nothing and ParaTest 7.20.0 is present.
- **HR Reports**: new read-only `HrReportController` (validated `month` in `Y-m`, employee counts, leave requests by start month, attendance records by status only when the user holds Attendance view), one route `admin.reports.hr.index` inside the existing auth / active / password-changed / permission / scope group, the middleware map entry `reports.hr` → module HR (the map is matched longest-prefix-first, so the entry covers the index route and nothing else), the sidebar entry switched from "coming soon" to the real link (gated through the same map), the Blade view, the test class.
- **Arabic**: exactly 14 HR Reports entries added (breadcrumb, description, "Report month", period note, two metric labels, two table titles, "Requests", "Records", two method notes, the hidden-attendance note and the GOSI / WPS disclaimer); 988 keys, 0 duplicates, valid JSON. Every dashboard entry from `5cc6819` is unchanged (the diff only appends). "Open Attendance" in the report reuses the dashboard entry "فتح الحضور"; status rows reuse the existing "Pending / Approved / Rejected / Present / Late / Absent / Leave / Half day" entries; "Reset" and "HR Reports" already existed.
- **Permissions and scope**: HR Reports requires HR view (route and sidebar); attendance figures require Attendance view; the report shows no payroll, salary, bank or IBAN data at all (the dashboard's payroll gating by Payroll view is unchanged). `Employee`, `LeaveRequest` and `AttendanceRecord` carry the `user_access` global scope, so every figure follows the project / site scope.
- **Unchanged**: no payroll calculation, posting, accounting, migration, seeder or production configuration is touched; `database/` has no new file.

## 2. Focused tests

| Run | Result |
| --- | --- |
| `HrReportsOverviewTest`, `HrDashboardArabicUiTest`, `HrDashboardPermissionsTest`, `HrPayrollTest` on the fixed sources through `scripts/test-speed.ps1 -Mode parallel -Processes 4 -OpCache` | **33 passed / 445 assertions, 0 failures** (109 s) |
| The new boundary test alone against the original `91820dd` controller | **1 failed** (RC-01 below), proving the defect before the fix |

## 3. Integration defects

| ID | Finding | Fix |
| --- | --- | --- |
| RC-01 | `HrReportController` filtered `start_date` and `attendance_date` with `whereBetween('col', [from, to])` on plain `Y-m-d` strings. The models cast both columns as dates and persist `Y-m-d 00:00:00`, so on SQLite (the test and fixture engine) every record dated the last day of the month fell outside the range; MySQL `DATE` columns truncate the time and were not affected, which is why the delivered tests passed. The HR dashboard already compares with `whereDate`. | Fix commit: both filters compare by calendar date (`whereDate >= from`, `whereDate <= to`); regression test `test_records_on_the_last_day_of_the_month_are_counted` added to `HrReportsOverviewTest` |

No other integration defect: the dashboard, HR-03 and tooling parts behave exactly as on their source branches.

## 4. Full verification (candidate's own runner, 8 workers, CLI OPcache)

- `scripts/test-speed.ps1 -Mode parallel -Processes 8 -OpCache` on the fixed sources (`e4ff196`): **815 tests, 815 passed, 10,008 assertions, 0 failures, 0 skipped, 0 errors, exit 0, 1,285.5 s wall-clock** (base `5cc6819`: 809 / 9,980; the difference is exactly the 5 HR Reports tests plus the RC-01 regression test, 28 assertions)
- `npm run test:js`: 12 passed, 0 failed.
- HR / payroll / attendance / employee / scope / permission / Arabic regression set (same runner): **258 passed / 3,086 assertions, 0 failures** (337 s)

## 5. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769, route cache rebuilt in the fixture for the new route)

Harness `tests/manual/hr-dashboard-browser.cjs` extended with HR Reports checks (figures expected from the fixture through a helper script; month filter; Reset; forged and malformed months; Arabic exactness; sweeps). Results: `evidence/hr-release-candidate-2026-10-31/hr-dashboard-browser-results.json` and `project-reports-browser-results.json`. Screenshots stay outside Git. Fixture adjustments as before (five attendance rows for today, HR view temporarily granted to the Site In-Charge role, Attendance / Payroll temporarily removed from the HR Manager role), restored and verified afterwards; the Project Reports harness ran after the restore.

**HR dashboard + HR Reports harness: 171 checks, 171 passed, 0 page errors** (phase 1: 90, phase 2: 20, phase 3: 61). **Project Reports harness on the candidate: 31 checks, 31 passed, 0 page errors**, identical to its 29 October result (its three recorded non-200 resources are the intended 404 for a forged project record and the 403 for the HR Manager without Projects view, the same as in the original run). Two harness-side corrections were needed during the run, neither an application defect: the 403 loop logged in twice in one browser context, and the driver had passed the repository instead of the fixture folder to the Project Reports harness. The recorded 403 / 404 responses are the intended refusals. Fixture restore verified after each run.

Covered: HR dashboard (all base checks unchanged); HR Reports for the Super Admin with figures equal to the fixture for the current month in both locales, month filter to September (zero attendance in this fixture), Reset, forged `2026-13` and malformed `13-2026` answered with the validation message; Site In-Charge under project 3 / site 4 scope with only project-3 employees, leaves and attendance; restricted HR Manager with workforce and leave figures but no attendance table, no "Open Attendance" and the hidden-attendance note in both locales; Project Manager, Finance Manager and Site In-Charge without HR view get 403 on HR Reports and have no sidebar entry; no "SAR", IBAN or salary string on any report page; English and Arabic sweeps at 320 / 390 / 768 / 1440 for the Super Admin and the restricted HR Manager on both pages with no horizontal overflow and no untranslated text.

## 6. Recommendation

**GO for integration approval of `feature/seera-hr-release-candidate-2026-10-31`** at the final HEAD: the candidate carries the dashboard gates, HR-03 and the tooling unchanged from their verified branches; the HR Reports MVP is gated by HR view and Attendance view, shows no payroll or banking data, follows the project / site scope and is fully bilingual; RC-01 is fixed with a regression test; the focused classes, the regression set, the full suite, the JavaScript tests and both browser harnesses are green. Not a production go: GATE-01 / GATE-02 remain as recorded, and the Arabic wording of the HR-03 and HR Reports entries still needs the Arabic-speaking reviewer. No merge into the original feature branches, no deployment, no production migration or seeding, and production ZATCA stays disabled.
