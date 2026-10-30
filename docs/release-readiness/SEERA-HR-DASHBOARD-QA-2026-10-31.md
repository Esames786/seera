# HR dashboard truthful UI — verification, 31 October 2026

Branch `feature/seera-hr-dashboard-truthful-ui-2026-10-30` (base `588d83b`). Verified HEAD before fixes: `5c138ee`. Fix commit: see the branch log (controller parse error, action-button translation). Evidence commit: see the branch log.

Scope: the HR dashboard (`admin/hr/dashboard`) after the sprint that gates attendance counts behind Attendance view, payroll figures behind Payroll view, replaces the chart placeholder with a truthful note, and localises the new copy. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

## 1. Safe checkout and diff review

- Working tree before checkout: clean on `feature/seera-test-speed-2026-10-30` at `6be5720` (one pre-existing untracked handoff note left untouched). `5c138ee` matched the expected HEAD; merge base with `588d83b` is `588d83b`.
- `5c138ee` changes 4 files in 5 commits: `HrDashboardController` (attendance and payroll queries run only when the user holds the matching view permission, otherwise `null`), the dashboard view (cards, approval rows, the attendance summary table and its action gated by the same flags; header actions gated by Attendance create and HR create; placeholder replaced by a note), 3 Arabic entries and the new `HrDashboardPermissionsTest`. **No migration, seeder, model, service, payroll or accounting code is touched**; `database/` is unchanged and no payroll calculation is reimplemented (the "Current Month Payroll" card still reads the stored `net_amount` of the latest run of the current month).
- Permission map (`EnsureUserHasPermission`): `hr.dashboard` → HR view; `hr.attendance` → Attendance; `hr.overtime`, `hr.payroll`, `hr.eosb` → Payroll. Gating overtime by Payroll view therefore matches the route protection, and the sidebar (`SidebarMenu::link`) hides the same entries through the same map.
- Scope: `Employee`, `AttendanceRecord`, `EmployeeDocument`, `LeaveRequest`, `OvertimeRecord`, `EndOfServiceRecord` and `PayrollRunItem` carry the `user_access` global scope, so every count on the dashboard follows the user's project / site scope. `PayrollRun` itself is company-level by design (one run covers all employees); its total is only shown with Payroll view.
- Dictionary: 949 keys, 0 duplicates, valid JSON; the controller and the view lint clean.

## 2. Focused and regression tests (SQLite in-memory, seeded)

| Run | Result |
| --- | --- |
| `--filter=HrDashboardPermissionsTest\|HrPayrollTest\|HrReportsOverviewTest` on `5c138ee` | **red: PHP parse error** in `HrDashboardController` line 44 (HR-01 below); every test that boots the controller errored. `HrReportsOverviewTest` does not exist on this branch (no HR Reports overview was delivered yet) |
| `--filter=HrDashboardPermissionsTest\|HrPayrollTest` after the fix | 23 passed / 143 assertions |
| Regression filter (HR dashboard, payroll, employee workspace, attendance, finance scope, permission catalogue, admin screens, Arabic UI, topbar, sidebar) through ParaTest, 8 workers, CLI OPcache | **238 passed / 2,688 assertions, 0 failures** (317 s) |

The accelerated runner: ParaTest is installed in `vendor/` on this workstation (from the test-speed branch); this branch's `composer.json` does not list it, so `php artisan test --parallel --processes=8` was used directly with the isolated OPcache ini. Serial fallback remains `php artisan test`.

## 3. Browser verification (headless Edge via Playwright, isolated synthetic fixture on 127.0.0.1:18769)

Harness: `tests/manual/hr-dashboard-browser.cjs` (three phases; Arabic expectations read from `lang/ar.json`). Results: `evidence/hr-dashboard-2026-10-31/hr-dashboard-browser-results.json`. Screenshots stay outside Git. Fixture adjustments (all restored afterwards, verified): five attendance rows for today (project 1: 2 present + 1 late; project 3: 1 present + 1 absent); HR view temporarily granted to the Site In-Charge role for the scope phase; Attendance and Payroll permissions temporarily removed from the HR Manager role for the restricted phase. Fixture facts: 20 employees (19 active; 6 in project 1, 4 in project 2, 3 in project 3 / site 4); payroll run 10/2026 processed, net 262,588.13; 3 pending leaves, 3 pending overtime, 2 draft EOSB, 2 IQAMAs and 5 documents expiring within 60 days.

**First run on `5c138ee`: the dashboard answered HTTP 500 for every user (HR-01), so no check could pass. Final run on the fixed sources: 76 checks, 76 passed, 0 page errors** (phase 1: 37, phase 2: 11, phase 3: 28). The recorded 403 / 404 responses are the intended refusals. Fixture restore verified after the run (marker attendance rows 0; both roles back to their original 37 permission rows).

Covered:

1. **Attendance / Payroll enforcement.** Project Manager (Attendance view, no HR view), Finance Manager (Payroll view, no HR view) and Site In-Charge (before the grant) get 403 on the dashboard. The HR Manager without Attendance / Payroll permissions sees no Present / Late / Absent cards, no "Open Attendance", no Pending Overtime / Current Month Payroll / Pending EOSB cards, no Overtime Claims / Payroll Approval / End of Service rows, the hidden-figures note instead of the truthful note, and the sidebar without Attendance, Overtime, Payroll and End of Service entries; direct URLs to those four registers answer 403.
2. **No financial figure without Payroll view**: no "SAR" string anywhere on the restricted page in either locale.
3. **No attendance count without Attendance view**: attendance cards and table absent for the restricted HR Manager.
4. **Links**: every `/admin/` link on the Super Admin dashboard (69) and the HR Manager dashboard (20) answers 200 or 302; sidebar entries follow the permission map.
5. **Real attendance summary**: Super Admin sees Present 3 / Late 1 / Absent 1 / On leave 0 from the inserted rows, the cards match, the placeholder text is gone and the truthful note is shown.
6. **Labels**: English baseline exact (title, header actions, table actions, approval rows). Arabic: title, "+ Manual Attendance", "+ Add Employee", "Open Attendance", "Open" (5 rows), "View All" and both notes exact from the dictionary; metric values identical between locales; `dir="rtl"`; sweeps at 320 / 390 / 768 / 1440 for the Super Admin and the restricted HR Manager in both locales with no horizontal overflow and no untranslated action button.
7. **Scope**: Site In-Charge with HR view sees Total 3 / Active 3 / Present 1 / Late 0 / Absent 1 (project 3 only), the attendance summary scoped the same way, no payroll cards, Manual Attendance but no Add Employee; the own-site employee record opens while a forged link to a project-1 employee answers 404.
8. **HR workflows and payroll unchanged**: the diff touches no payroll, attendance or accounting code; `HrPayrollTest`, `PayrollAccountingTest`, `PayrollSettlementTest`, `PayrollPayslipTest`, `PayrollBankExportTest`, `GratuityTest`, `MobileAttendanceTest` and `OfflineSyncAttendanceTest` are green.

Remaining English in the Arabic dashboard (recorded, pre-existing): the metric-card labels ("Total Employees", "Present Today", …), table titles ("Today's Attendance Summary", "Pending HR Approvals", "IQAMA Expiring Soon", "Latest Leave Requests"), column headers and the approval-queue row names have no dictionary entries, so the components render the English keys. The breadcrumb and description are English too. See HR-03.

## 4. Full regression

- `php artisan test --parallel --processes=8` with CLI OPcache on the final sources (`b39be84`): **805 tests, 805 passed, 9,706 assertions, 0 failures, exit 0** (base `588d83b`: 802 / 9,686; the difference is exactly the 3 new tests / 20 assertions). Wall-clock 1,780.8 s on a loaded workstation at night; the same command measured 583–759 s on the test-speed branch, so the slowdown is machine load, not the suite.
- `npm run test:js`: 12 passed, 0 failed.

## 5. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| HR-01 | `HrDashboardController` on `5c138ee` has a syntax error: the `pendingOvertime` ternary lacks its `: null` branch, so the dashboard route and every test that boots the controller fail (red suite, HTTP 500 for every user). | Fix commit: `: null` branch added |
| HR-02 | Action buttons on the dashboard stayed English in Arabic although the sprint translated the neighbouring "+ Manual Attendance": "+ Add Employee", "Open Attendance", the five "Open" links and "View All". | Fix commit: wrapped in `__()`; two Arabic entries added ("+ Add Employee", "Open Attendance"); "Open" and "View All" already existed |
| HR-03 | Metric labels, table titles, column headers, approval-queue names, breadcrumb and description remain English in Arabic (no dictionary entries; pre-existing, outside this sprint). | Open, for the Arabic coverage sprint |

No application-permission regression was found: the gates match the route protection and the sidebar, counts follow the user scope, and nothing in payroll or accounting changed.

## 6. Recommendation

**GO for integration approval of `feature/seera-hr-dashboard-truthful-ui-2026-10-30`** at the final HEAD: with the parse error fixed, attendance counts appear only with Attendance view, payroll figures only with Payroll view, the placeholder is replaced by a truthful note, counts follow project / site scope, sidebar and direct URLs agree with the permission map, the action buttons translate, and the focused tests, the regression set, the full suite, the JavaScript tests and the browser verification are green. The delivered HEAD `5c138ee` itself was not shippable (HTTP 500). HR-03 stays open for the Arabic coverage sprint. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
