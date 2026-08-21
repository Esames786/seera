# Seera Construction ERP — Full System Test Report

**Test date:** 2026-08-22 (Asia/Karachi)  
**Commit tested:** `8f32d7283b79fe689845dbe80c0eb24364151d5c` plus the pre-existing uncommitted client-change worktree  
**Scope:** Laravel 12 application, phases 1–6 as described in the test brief; no feature work or architectural refactor was performed.

## Executive result

**Release recommendation: do not release for multi-user or production use.** The core happy-path accounting and inventory tests are generally strong, but the system has release-blocking authorization, money, inventory, and audit-record defects.

- 312 registered routes; 301 `/admin/*` routes have only `web,auth` middleware.
- A seeded Operator can open user creation, the permission matrix, Accounts Payable creation, payroll, and stock adjustment screens.
- A pending user can log in and reach the dashboard.
- 98 automated tests ran: 96 passed, one errored, and one failed.
- Production asset build passed.
- 171 linked admin GET route patterns and 500 concrete seeded admin URLs were crawled: zero 500s, missing views, or undefined-variable failures.
- A temporary SQLite adversarial test passed all 15 assertions confirming six regressions described below; the temporary test file was removed after execution.

## 1. Critical bugs

### C-01 — The permission system is not enforced anywhere

**Impact:** Any authenticated user can administer users and roles, change permissions, approve/post financial and inventory documents, and read data outside their project/site/warehouse scope.

**Proof:** All admin routes sit under only `auth` at `routes/web.php:81-82`; source-wide searches found no policies, `authorize()`, Gate checks, `can:` middleware, or equivalent enforcement. Of 312 routes, 301 admin routes report middleware exactly `web,auth`.

**Reproduction:**

1. Log in as seeded Operator `shaban@example.com` / `password`.
2. GET `/admin/users/create`, `/admin/roles/permission-matrix`, `/admin/accounting/accounts-payable/create`, `/admin/hr/payroll`, and `/admin/inventory/stock-adjustments`.
3. Every request returns HTTP 200. The temporary SQLite test repeated the permission-matrix and AP checks successfully.

**Files:** `routes/web.php:81-242`, `app/Http/Controllers/Admin/PermissionMatrixController.php:36-47`.

### C-02 — Pending/inactive/locked accounts are still allowed to log in

**Impact:** Deactivation does not revoke access. A terminated or locked employee can continue using the ERP.

**Proof:** Login validates only email/password and calls `Auth::attempt($credentials)`; user status is absent from the credentials and there is no post-login status check.

**Reproduction:**

1. Fresh-seed the database; Kamran is seeded with `status=pending`.
2. Log in as `kamran@example.com` / `password`.
3. The response lands on `/admin/dashboard` with HTTP 200.

**Files:** `app/Http/Controllers/Auth/AuthController.php:23-42`, `database/seeders/DatabaseSeeder.php:419`.

### C-03 — Filtering the permission matrix silently revokes every hidden permission

**Impact:** Saving a filtered matrix causes bulk access loss. This is the previously known regression and it has returned.

**Proof:** The GET filters permission rows by `search`, but the form submits only rendered checkbox IDs. The update uses `sync()` with exactly that partial list, detaching every non-rendered grant.

**Reproduction:**

1. Open `/admin/roles/permission-matrix?role=<role>&search=Payroll` for a role that also has Inventory permissions.
2. Leave its displayed Payroll selections intact and click Save.
3. Reload without the search filter; all Inventory and other hidden grants are gone.
4. The temporary SQLite test selected two existing grants, submitted one, and confirmed that the hidden grant was detached.

**Files:** `app/Http/Controllers/Admin/PermissionMatrixController.php:22-41`, `resources/views/admin/roles/permission-matrix.blade.php:46-54`.

### C-04 — Stock adjustments can move one quantity but post accounting for another

**Impact:** Stock valuation, the stock ledger, adjustment document, and general ledger diverge.

**Concrete failing case:**

1. Warehouse stock is 10 units at SAR 5.
2. Create an adjustment target of 8. The draft snapshot records a difference of -2.
3. Before posting, receive 10 units, making live stock 20.
4. Post the approved adjustment.
5. `StockService` correctly issues 12 units to reach 8, but the adjustment controller recomputes difference as `target 8 - stale current_quantity 10 = -2` and posts only SAR 10 instead of SAR 60.

The temporary adversarial test confirmed a 12-unit ledger issue alongside a stored `difference_quantity=-2`.

**Files:** `app/Http/Controllers/Admin/Inventory/StockAdjustmentController.php:143-169`, especially `:159-165`; `app/Services/Inventory/StockService.php:100-115`; `app/Services/Accounting/PostingService.php:426-456`.

### C-05 — Saudi gratuity under/overpayment at legal boundaries and wage basis

**Impact:** Wrong end-of-service money.

**Confirmed mismatch:** Article 85 gives one-third after at least two years and **not more than five years**, then two-thirds only when service **exceeds five years**. Code starts the two-thirds tier at exactly `5.0`, so a five-year resignation receives 66.67% instead of 33.33%. The existing test explicitly encodes the wrong result.

At SAR 10,000 final wage and five years, the base award is SAR 25,000. The application pays SAR 16,667.50 instead of SAR 8,332.50: an SAR 8,335 overpayment.

Two additional legal-scope mismatches need a business/legal decision:

- Article 84 uses the last wage; the UI explicitly calculates on “Final Basic Salary,” excluding allowances.
- Article 87 also grants the full award for a female worker leaving within six months of marriage or three months of childbirth. The application has only a generic `force_majeure` exception and cannot represent these cases.

The base half-month/full-month formula and force-majeure full entitlement otherwise match Articles 84 and 87. Official source: [Saudi Ministry of Human Resources and Social Development, Labor Relations, Articles 84–87](https://www.hrsd.gov.sa/en/%D8%B9%D9%84%D8%A7%D9%82%D8%A7%D8%AA-%D8%A7%D9%84%D8%B9%D9%85%D9%84).

**Files:** `app/Services/Hr/GratuityCalculator.php:28-32`, `:38-47`, `:56-70`; `resources/views/admin/hr/eosb/_form.blade.php:26-27`; `tests/Feature/GratuityTest.php:58-65`.

### C-06 — Financial statements omit chart-of-account opening balances

**Impact:** Balance sheet, profit/loss, and trial balance can materially understate balances even while the trial balance appears balanced.

**Reproduction:**

1. Create or edit an account with a non-zero `opening_balance`.
2. Open its account detail; `postedBalance()` includes the opening balance.
3. Open Trial Balance or Balance Sheet; the opening balance is absent because reports start exclusively from posted journal lines.

The trial balance equality test therefore proves only that posted journal lines balance, not that the complete books balance.

**Files:** `app/Http/Controllers/Admin/Accounting/ChartOfAccountController.php:108-125`, `app/Models/ChartOfAccount.php:50-64`, `app/Http/Controllers/Admin/Accounting/FinancialReportController.php:173-209`.

### C-07 — Approved and paid EOSB settlements can be edited and permanently deleted

**Impact:** Final payroll liabilities and their approval audit trail can be rewritten or erased.

**Reproduction:**

1. Open an approved seeded EOSB record.
2. Directly submit PUT `/admin/hr/eosb/{id}` or DELETE `/admin/hr/eosb/{id}`.
3. Update and delete execute without checking `isEditable()` or status.

**Files:** `app/Http/Controllers/Admin/Hr/EndOfServiceController.php:66-87`; `app/Models/EndOfServiceRecord.php:37-40` defines the unused draft-only rule.

## 2. High-severity functional and security bugs

### H-01 — Concurrent payments, receipts, stock issues, transfers, and approvals are raceable

Balance/status checks happen before transactions, and the affected bill, invoice, document, and `warehouse_stocks` rows are never selected `FOR UPDATE`.

**Concrete interleaving:** Two requests read a supplier balance of SAR 1,000; both validate an SAR 800 payment; both insert and post; final paid amount becomes SAR 1,600 and balance becomes SAR -600. The same lost-update pattern can oversell stock or double-post a GRN/adjustment/transfer.

**Files:** `app/Http/Controllers/Admin/Accounting/AccountsPayableController.php:170-198`, `AccountsReceivableController.php:171-199`, `app/Services/Inventory/StockService.php:58-75`, and inventory posting controllers. No `lockForUpdate()` exists in the application.

### H-02 — Employee create/update is not atomic with attachment validation

The employee is inserted or updated before nested document validation and file storage run.

**Reproduction:** Submit a valid new employee plus a 6 MB attachment. The response contains `documents.0.file` validation errors, but the employee row already exists; retrying the form then fails on duplicate employee code. The temporary audit test confirmed this partial commit.

**Files:** `app/Http/Controllers/Admin/Hr/EmployeeController.php:56-60`, `:98-102`, `:127-145`.

### H-03 — Public employee uploads accept arbitrary file types

Only `file|max:5120` is enforced, with no MIME/extension allowlist, malware handling, or private download authorization. Files are stored on the public disk and linked directly from the register. An authenticated attacker can upload active HTML/SVG content under the application origin; all authenticated roles can retrieve any file because authorization is absent.

**Files:** `app/Http/Controllers/Admin/Hr/EmployeeController.php:129-145`, `EmployeeDocumentController.php:82-99`, `resources/views/admin/hr/documents/index.blade.php:54-56`.

### H-04 — Server accepts mismatched designation/department and site/project pairs

JavaScript clears a stale child selection, but server validation checks only that each ID exists. A crafted or JavaScript-disabled request stores internally inconsistent organization and project scope. The temporary audit test saved both mismatches in one employee record.

The same defect exists in both Employee and User controllers.

**Files:** `resources/views/components/admin/dependent-select.blade.php:29-51`, `app/Http/Controllers/Admin/Hr/EmployeeController.php:168-172`, `app/Http/Controllers/Admin/UserController.php:122-126`.

### H-05 — “Read-only” Employee Documents register is writable and destructive

The page describes itself as read-only, but each row renders Edit and Delete buttons, and edit/update/destroy routes remain live. The temporary test confirmed an Operator receives HTTP 200 on a document edit route.

**Files:** `resources/views/admin/hr/documents/index.blade.php:7`, `:62-66`; `routes/web.php:127`; `app/Http/Controllers/Admin/Hr/EmployeeDocumentController.php:71-79`.

### H-06 — Authentication has no rate limit

The login POST is under `guest` only. There is no `throttle` middleware or controller rate limiter, making the default/common seeded passwords brute-forceable.

**Files:** `routes/web.php:65-72`, `app/Http/Controllers/Auth/AuthController.php:23-35`.

## 3. Medium/low functional and UX issues

### M-01 — The current test suite is stale after the EOSB client changes

- `test_eosb_record_can_be_created_and_approved` omits required `termination_reason` and `manual_override`, then expects the manually supplied amount; it errors at `tests/Feature/HrPayrollTest.php:426`.
- `test_seed_data_covers_every_phase3_table` expects one EOSB record, while the seeder intentionally creates three; it fails at `tests/Feature/HrPayrollTest.php:460` versus `database/seeders/Phase3HrSeeder.php:437-467`.

### M-02 — Employee document help copy contradicts the workflow

Section E correctly uploads from the employee form, but Section C says the files “are uploaded from Employee Documents,” which is the opposite workflow and links users to the supposedly read-only register.

**File:** `resources/views/admin/hr/employees/_form.blade.php:118-127`.

### M-03 — Sequence generators are raceable

Several document numbers use `count()+1`/next-number lookups without a unique retry strategy or lock. Concurrent creates can generate duplicate payroll/document numbers and return a database exception.

**Example:** `app/Http/Controllers/Admin/Hr/PayrollRunController.php:210-215`.

### L-01 — Unused uploaded files are orphaned

Replacing/deleting a document updates or deletes only the database row; the old public file is not deleted. This leaks storage and keeps sensitive documents reachable if their URL is known.

**File:** `app/Http/Controllers/Admin/Hr/EmployeeDocumentController.php:71-99`.

## 4. Performance

A request-level `DB::listen` counter was installed temporarily and removed after measurement. Counts include the known four uncached sidebar badge queries.

| Screen | SQL count | SQL time on seeded MySQL | Finding |
|---|---:|---:|---|
| Accounting dashboard | 35 | 20.21 ms | Highest fixed query count; multiple status aggregates |
| Project cost report | 34 | 18.08 ms | **N+1: about six queries per project** |
| HR dashboard | 29 | 17.97 ms | Fixed aggregate-heavy page |
| Inventory dashboard | 29 | 18.38 ms | Fixed aggregate-heavy page |
| Accounts Payable index | 28 | 16.76 ms | No row-dependent duplicate pattern found |
| Accounts Receivable index | 27 | 14.17 ms | No row-dependent duplicate pattern found |
| Attendance / Stock index | 26 each | 15–18 ms | Repeated status counts, not row N+1 |

The clear N+1 is `FinancialReportController::projectCostReport()`: account-ID lookups and four aggregates run inside the project map. With three seeded projects it executes 34 queries; the core portion grows by approximately six per additional project.

**File:** `app/Http/Controllers/Admin/Accounting/FinancialReportController.php:132-150`.

Other tested indexes eager-load their row relationships; no row-dependent duplicate query pattern was found. Sidebar badge queries are confirmed at `app/Support/SidebarMenu.php:202-217` and remain uncached as expected.

## 5. Confirmed business rules and client changes

The following passed either existing functional tests, the route crawl, or direct inspection:

- Manual journal imbalance is rejected on save and post.
- Posted journal edit is blocked.
- Supplier bill approval maps expense + input VAT against AP.
- Supplier payment maps AP against the selected cash/bank account; the existing test omits the credit-side assertion, but implementation is correct.
- Customer invoice approval maps AR against revenue + output VAT.
- Customer receipt maps selected cash/bank against AR; the existing test omits the AR credit assertion, but implementation is correct.
- Single-request overpayments and over-receipts are rejected. Concurrent requests remain unsafe (H-01).
- VAT period recomputation is output VAT minus input VAT.
- GRN posts accepted quantity, not received quantity.
- Stock issue uses warehouse average cost and single-request insufficient-stock failure rolls back the whole document.
- Transfer dispatch removes only from source; receive later adds to destination.
- Adjustment changes no stock before approval and posting.
- Posted GRN edit/repost is blocked.
- Payroll item formula is basic + allowances + approved overtime - deductions.
- Attendance duplicate employee/date is rejected.
- Employee form has A–F sections with the requested names; classification is required and persisted; payroll has all five allowance fields.
- Department “Equipment” is absent; Equipment remains only a permission/module/category label.
- Seeded names/designations match the requested organization list.
- Both forms render department/designation and project/site filtering; the client clears mismatched saved values, but the server bypass remains H-04.
- Sidebar has eight collapsible groups, active-group forcing, and `localStorage` persistence. No dead linked screen was found.
- Permission model exposes 15 actions, but filtered persistence is broken (C-03).

## 6. Known gaps confirmed and not fixed

- **Phase 5:** Projects + Site Expenses has only Coming Soon links/reference workflow/posting-rule strings; there is no site-expense implementation.
- **ZATCA:** Only local UUID, QR payload, XML path, hash, retry state, and UI exist. No HTTP client or live clearance call exists.
- **Language:** Default locale is English; there is no translation catalog or RTL layout implementation. The Arabic company-name input alone is not localization.
- **Build artifact:** `public/build.zip` is tracked and stale. It contains `app-CslXbDDw.css`; the successful current build emits `app-CmF3Ra2m.css`.
- **Sidebar badges:** Four uncached count computations run on every rendered admin page.

## 7. Test coverage gaps

Rules not adequately covered by permanent tests:

- Authorization by role/action and project/site/warehouse scope; pending/inactive/locked login denial.
- Permission-matrix save while filtered and preservation of non-rendered grants.
- Posted journal **delete** denial (only edit denial is tested).
- Supplier-payment bank/cash credit and customer-receipt AR credit assertions.
- Concurrent payment/receipt limits and concurrent stock/posting idempotency.
- Opening balances in trial balance, balance sheet, and profit/loss.
- Stock issue price equals the pre-issue warehouse average cost.
- Stock-ledger replay equals current warehouse balance; the existing test checks only that each movement type exists.
- Posted stock issue and posted stock adjustment edit/delete denial.
- Stock changes between adjustment creation and posting.
- Exact five-year resignation boundary against Article 85; current test expects the wrong tier.
- Article 87 marriage/childbirth exceptions and Article 84 last-wage basis.
- Department/designation and project/site relationship validation on both Employee and User endpoints.
- Employee Classification missing/valid persistence as a dedicated regression test.
- Exact A–F headings, absence of “Saudi Documents,” all document-number/expiry inputs, and all allowance inputs.
- Documents register truly read-only (no edit/update/delete), no-add assertion, MIME allowlist, private downloads, and failed-upload atomicity.
- “Equipment” absent from the Department table and the full requested organization mapping.
- Empty-submit validation across every create form; current tests cover selected rules only.
- Rounding edge cases using repeated fractional values in VAT, payroll aggregation, weighted-average stock, transfers, and adjustments.
- Sidebar collapse persistence in an actual browser; source logic is present but no JS/browser test exists.

## Test commands and evidence

- `php artisan migrate:fresh --seed --force` — passed on local MySQL `seera`.
- `php artisan test` — 98 tests, 96 passed, one error, one failure, 628 assertions, 549.620 s.
- Isolated EOSB test — reproduced the error at `HrPayrollTest.php:426`.
- Temporary adversarial SQLite test — 1 test, 15 assertions, passed; confirmed C-01, C-03, C-04, C-05 boundary, H-02, H-04, and H-05; file removed.
- `npm run build` with bundled Node on `PATH` — passed in 14.87 s.
- Authenticated route-pattern crawl — 171 admin GET patterns, zero failures.
- Breadth crawl — 500 seeded admin URLs, zero failures before the 500-URL cap.
- SQL instrumentation — representative indexes, dashboards, and reports measured; temporary counter removed.

No application bug was fixed as part of this review.
