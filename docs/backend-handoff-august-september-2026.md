# Seera ERP — backend architect handoff: August–24 September 2026

Prepared for the owner's existing GPT/backend planning conversation. **Not sent to that conversation:** upload/paste this document there and return its response here. No credentials, production data exports or private recordings are included. New feedback and proposed work are not labelled implemented.

## 1. Checkpoint and evidence levels

- Repository `Esames786/seera`; branch `feature/seera-connected-workspaces-2026-09-23`; latest implementation **2d0f9d5**. Its push was verified in the preceding work; main was not merged by this feature work.
- Owner's production terminal confirms fast-forward `9df25b1 → 2d0f9d5`, successful `optimize:clear`, all listed migrations through September 23 Ran, and the named seeder adding three shifts. Four leave types already existed and were unchanged.
- Matching frontend deployment is **not confirmed** by that output. Matching archive: `seera-build-2026-09-24-employee-fixes.zip`; JS `app-BgdO7_Bb.js`; extract into `public` to obtain `public/build/manifest.json`.
- Last recorded implementation verification: **215 PHPUnit tests / 2,028 assertions**, **81 browser-fixture checks**, Vite/Pint/diff checks passed. SQLite in-memory tests and mocked-HTTP fixtures do not prove production MySQL concurrency, authenticated UAT, full Arabic acceptance or statutory compliance.
- Live DB contents were not independently queried: MySQL did not advertise TLS; SSH lacked a verified host key. No unencrypted DB authentication or host-verification bypass. Production facts above are owner-terminal evidence.
- This September 24 source/media review creates documentation/evidence only; the newly decoded fixes and remaining workspaces are not implemented here.

## 2. Architecture and ownership

Construction ERP covering administration, organization masters, partners/projects/locations/warehouses, HR/payroll, accounting/VAT/e-invoice foundations, inventory/purchasing and Marketing. Operational Project/Site Expenses and Equipment/Vehicle phases remain reference packages.

Current `composer.json`: PHP `^8.3`, Laravel `^13.8`. UI is Blade + ordinary JavaScript + shared ERP CSS + Vite, not a React SPA. Keep locked dependencies; a single-screen workspace does not require a framework rewrite.

| Concern | Source ownership |
|---|---|
| Routes / middleware | `routes/web.php`; EnsureActiveUser, EnsurePasswordChanged, EnsureUserHasPermission, EnsureRequestWithinScope |
| Scope | UserAccessScopeService; selected-model global scopes in AppServiceProvider; route/write checks. Scope coverage is not universal to every model. |
| Authorization catalogue | Permission, Role, User, PermissionGroups, RoleController, PermissionMatrixController |
| Navigation / language | SidebarMenu, SetLocale, LocaleController, `lang/en`, `lang/ar`, `lang/ar.json` |
| Employee workspace | EmployeeController, EmployeeWorkspaceController, EmployeeWorkspacePanels, employee Blade partials |
| Browser state | employee-workspace.js, employee-related-panels.js, employee-user-search.js, unsaved-changes.js, quick-create.blade.php |
| Financial effects | `Accounting/PostingService`, AP/AR/journal/VAT controllers and models |
| Inventory effects | `Inventory/StockService`, movement controllers; weighted-average valuation and stock ledger |
| HR calculation | PayrollRunController, salary structures/items, `Hr/GratuityCalculator`, employee payroll defaults |
| Numbering / files | DocumentNumberService; authenticated private-storage attachment handlers for HR/leave/PO |

Core relationships: Customer → Projects and Invoices → Receipts; Supplier → Bills → Payments plus payable account/payment terms and many Projects; Project → Locations (`sites`), warehouses, staff and suppliers; PO → lines/quotations/GRNs; Warehouse → stocks/ledger; Employee → documents, salary structures, attendance, leave, overtime, shifts, EOSB and optional User.

**Identity invariant:** `employees.user_id` is the actual account link. `users.employee_id` is a human-readable code, not `employees.id`. Never relink records merely because their display names match.

## 3. Timeline

| Date / commits | Delivered or recorded | Boundary |
|---|---|---|
| July: 47b8135 / 1671e61 / 54fac7e | Phase 1/2 admin, users/roles/matrix/workflow builder, dashboard and masters | Earlier context; old demo/reset instructions are not production instructions |
| Aug 17: bb2b184 | Phase 3 HR/payroll and Phase 4 accounting foundations | Foundation is not all policies/compliance completed |
| Aug 17: b45bf9d | Phase 5 Project/Site Expenses reference | Not operational implementation |
| Aug 18: 680cba9 | Phase 6 inventory/warehouse: item/category/unit, PR/PO/GRN, movements/stock/ledger | Preserve stock valuation and posting |
| Aug 18: 8f32d72 | Phase 7 Equipment/Vehicle Tracking reference | Commit explicitly excludes implementation migrations/models/controllers/routes/views/tests |
| Aug 22: ce3e05a / 96662cc / 33a0c6d / 7cb1dd3 | Hardening checkpoint, organization login setup/forced password change, bootstrap test, domain command/app naming | Do not regenerate production accounts or reset passwords |
| Sep 7: 17aeba0 / 5b04a59 / 9558430 | CR-01..13 fixes, client review page, production bootstrap | Bootstrap is for appropriate fresh setup, not a routine used-database upgrade |
| Sep 9: 4bbc89d / 40d33b9 | CR-15..18 classifications/map/terms/payable links; production COA seeder | Preserve used account codes/history |
| Sep 18: c7c4303 / 2a7b4a1 / cde7028 / 6bc7147 | NR-01..34 stages across access/masters/HR/accounting/reports/Marketing | Several policy decisions still open |
| Sep 21: 663b0b1 / aef3cb8 / 9e295b7 | FR-01..06/OBS-01 fixes, round-4 walkthrough, deployment notes | Some salary/leave decisions remain partly open |
| Sep 23: b6c4d86 | R22 customer fixes, locale/unsaved foundations, Employee → User conversion | Multi-parent approvals and VAT override policy not delivered |
| Sep 23: cd6699e | Employee navigation and contextual salary return | Superseded by related-panel workspace |
| Sep 24: 9df25b1 | Employee related forms/history, grouped HR registers, one Open action | Latest client explicitly rejects the generic Open action |
| Sep 24: 2d0f9d5 | Inline masters, consistent saves, safe default seeder, employee search explanations | Linked-account deep links still absent; full translation/UAT incomplete |

## 4. What the September rounds changed

### Round 1/2 — CR register

Inline master creation; people classification; dependent project/location/warehouse selectors; permission grouping/bulk selection; stable role codes and name conventions; inline Role/template creation; Organization Structure hub; expandable employee document rows; private PO quotation uploads; PO description/discount/VAT/live line totals with server recalculation and discounted stock receipt valuation.

Later: project classifications with + New; actual location map/geofence; payment terms with days; supplier payable account dropdown/creation and linked posting/control totals. Primary role hierarchy remains single-parent. Workflow-definition screens do not establish runtime staged approvals.

### Round 3 — September 18 NR register

- Supplier city/projects, category/nationality quick-create, ratings, bank/channel settings; customer ratings/overdue summary, office/site contacts and shared notes.
- Classification-based employee codes; Locations wording retains `sites`; project staff panel; field-specific date rules.
- One document source with subtype/edits/renewals, private files, consistent expiry summaries and filters; leave defaults/attachments/annual entitlement balance.
- Cash/bank cards, payment method/purpose, named default line accounts; clear failed-posting approval errors; draft VAT forecast; restricted reopen with reversing entries.
- CSV/date-range reports; Marketing leads/visits/follow-ups/conversion/manager reports.
- Hierarchy-aware activity visibility and project-manager access correction.
- Open: advance/on-behalf ledger treatment, Project/Location merger, alert delivery, leave accrual/carry-forward/per-type balances, Marketing policy details and staged approvals.

### Round 4 — September 21

Contract Start rejects future dates; Contract End may be future. Reusable Document Type + New; saved and selected-file preview. First salary structure generated from employee basic plus allowances when eligible; later profile saves preserve old structures/payroll; mismatch notice and deliberate prefilled new structure. Payroll fallback includes profile allowances. Inclusive leave-day calculation with explicit override and server recalculation. Visible code preview; actual code allocated on save. HR ampersand label cleanup.

Open: salary effective-date acceptance, future automatic vs deliberate structure creation, authorized backfill, working/calendar-day leave and exceptions, document-type retirement permissions, Joining Date versus Contract Start equality. No blanket backfill authorized.

### September 22–24 connected work

Delivered: visible rating colors; Customer Type + New; Customer Cash/Bank/Both enforced on receipts; Office/Site contacts and notes on Create/Edit; read-only Customer View. EN/AR preference and RTL foundation, shared dirty-form warning, employee search/autofill and atomic user link with explicit roles/security and no salary/bank copying.

Employee Edit sections: Personal, Employment, Salary & payment, Documents, Access, Salary Structures, Attendance, Leaves, Overtime, Shift Assignments, EOSB, Payroll History, System Account. Related AJAX saves preserve other drafts and files; profile retains accepted multipart behavior. Global HR registers/approval queues remain under a collapsible group. Historical salary/approved record rules remain intact.

Latest: consistent Save & stay/next/close; canonical saved-record URL; guards on close; Access continues into related tabs. Shift/Leave Type/Role and basic Project/Site inline dialogs with permission/dependency rules; human labels. Optional ProductionEmployeeMastersSeeder only fills absent baseline Shift/Leave codes; no overwrites, assignments, accounts or transactions. Defaults need company review. Search now explains linked/inactive/code-conflict matches, but has no direct linked-account link.

## 5. New September 24 feedback

Local package: **3 OGG recordings, 63.859 seconds total, 1 JPEG**. Source ordering and SHA-256 evidence retained; local Urdu ASR reviewed, not guaranteed verbatim. See [feedback review](client-feedback-2026-09-24.md).

- Owner wants a visible linked Employee/User and an authorized link. Browser Ctrl+F on Users page 2 does not prove absence. Use actual relationship, not guessed name/code matching.
- Client explicitly wants **View / Edit / Deactivate**, not Open. View must not enter an editable form; single-screen management remains behind Edit. This supersedes the earlier one-Open-action UX, not the workspace itself.
- Finance is a priority for the end of the recording's month; provisional date 30 September 2026 needs confirmation. Speaker asks where finance stands. The commercial invoice-payment comment is project coordination, not ERP invoice-clearance scope.
- A product/workflow explanation deliverable is requested; confirm format/audience. Do not assume the existing static client-review page fulfills it.
- Owner wants other modules to use the same record-context approach. [Module plan](remaining-modules-connected-workspaces-2026-09-24.md) is proposed, not built.
- GPT screenshot's permission mismatch is confirmed: RoleController FORM_ACTIONS has 7; Permission ACTIONS has 15 and main matrix uses it. Grouping/visible-only bulk selection/horizontal overflow already exist; improve remaining usability, do not duplicate delivered controls.

## 6. Finance readiness — no invented percentage

| Area | Source-backed state | Acceptance gap |
|---|---|---|
| COA/journal/GL/cost center/posting rules | Implemented foundations | Company setup, balancing, reversals and scoped access review |
| AP/payments | Bill/payment posting, payable link, channel/method/purpose | Partial/repeated payment tests, period rules and accountant reconciliation; advance purpose is not complete advance accounting |
| AR/receipts | Invoice/receipt posting, channel enforcement, balances/ageing | Partial/repeated receipts, rounding expectations and production reconciliation |
| VAT | Transactions/periods, draft forecast, controlled correction withdrawal | R22 default 15% / Super Admin override still pending across entry paths; agreed exceptions; no historic-rate rewrite |
| ZATCA | Records, statuses/errors, retry-to-pending | Controller explicitly calls retry foundation-only; live clearance belongs to a future phase. Do not claim compliance completed. |
| Inventory/finance | Stock service and GRN/issue/adjustment posting hooks | Quantity/value/ledger reconciliation and real MySQL concurrent-operation proof |
| Payroll/EOSB | Calculation/structure/process/approval foundations | Confirm finance milestone inclusion and payroll journal/payment scope separately; HR/accountant policy sign-off |
| Reports | Financial CSV/date ranges, cash/bank and ageing | Reconcile reports to permitted transaction states and ledger |
| Approvals | Direct approval handlers + workflow definition UI | All-required runtime/decision log/order/actors/rejection/revision and existing pending-request treatment missing |

Recommended audit: agreed purchase-to-pay and invoice-to-receipt scenarios with expected journals/VAT/balances, rejection/partial/duplicate/correction paths and accountant sign-off in staging. Do not reset production for acceptance tests.

## 7. Decisions and constraints already established

1. English and Arabic only. Urdu is recording language. Full translation is not done; IDs/codes/permission keys must remain stable.
2. **All required reporting approvers must approve.** Owner confirmed this. Parallel/sequential configuration, actors, substitution, rejection/resubmission and legacy pending requests still need design.
3. Customer Cash/Bank/Both enforced server-side; existing customers default Both; no historical receipt rewrite.
4. Customer View stays read-only. Employee now also requires a distinct View action. Connected editing belongs in Edit.
5. Child Save is not approval/posting or Save All. Retain ownership/scope and private attachment access.
6. Preserve client-accepted fields/routes/calculations; small additive feature-branch commits with matching builds and rollback. No blanket framework/UI rewrite.
7. No production hard delete, migrate:fresh, migrate:refresh, unrestricted/demo seeding, account/password reset or MySQL test suite. Name and justify any production seeder.
8. Preserve user-modified public/build.zip, server .htaccess and private research. New recordings under public/24_09_2026 must not be accidentally deployed/published; live exposure not verified.
9. No secrets in handoff or external model messages. Never bypass TLS/SSH host validation to inspect production.
10. Source-done, tests-passed, owner-terminal-deployed and client-accepted are different states. Reference-only Phase 5/7 are not completed modules.

## 8. Recommended next sequence, for architect/owner confirmation

Close linked-account discovery and View/Edit/Deactivate first. Define the finance month-end minimum and select Supplier/AP or Customer/AR as the next connected pilot. Align permission catalogues in a separate tested correction. Treat multi-parent approval runtime, VAT policy, advances/on-behalf accounting and live ZATCA as explicit functional scopes. Do not start Phase 5/7 merely because reference folders exist.

## 9. Message to paste to the existing GPT architect

> You hold the earlier Seera backend/product context. Reconcile it against this 24 September handoff and source checkpoint 2d0f9d5, not only older assumptions. We need a careful next-build plan and finance-focused month-end outcome without breaking accepted screens.
>
> Return: (1) contradictions/unknowns and the exact evidence needed; (2) finance must-have/defer/decision/acceptance checklist, explicitly whether payroll and live ZATCA are included; (3) the next three implementation batches with requirement IDs, files/routes/tables, migrations, invariants, permissions, tests and rollback; (4) an all-required approval design covering actors/order/snapshots/rejection/resubmission/delegation/legacy requests—do not ask again whether all parents must approve; (5) outstanding VAT and advance/on-behalf accounting questions with options labelled proposals; (6) Supplier/AP versus Customer/AR workspace priority and rationale; (7) client walkthrough outline from setup through transaction, approval, ledger and reports using staging examples.
>
> Keep read-only View distinct from Edit and preserve explicit approve/post commands. Do not assume reference phases, Arabic coverage, approval runtime or ZATCA clearance are finished. Do not propose resets or expose secrets. We will reconcile your instructions with current code and owner authorization before implementation/deployment. Ask for specific source/schema evidence rather than guessing.

After the owner returns that response, compare it to these constraints, resolve contradictions explicitly, update the backlog and then implement the agreed batch. Another model's answer alone does not authorize destructive data changes or deployment.

## 10. Supporting files

- Historical registers: `docs/client-change-register-2026-09-07-status.md`, `...2026-09-16-status.md`, `...2026-09-21-status.md`.
- R22 requests: `docs/client-requirements-2026-09-22.md`; media evidence remains private/local.
- Delivered work: `docs/implementation-status-2026-09-23.md`, `docs/employee-single-workspace-requirement-2026-09-23.md`, `docs/employee-workspace-followup-2026-09-24.md`.
- New review: `docs/client-feedback-2026-09-24.md`, its evidence JSON, `docs/remaining-modules-connected-workspaces-2026-09-24.md`.
- Focused tests: EmployeeWorkspaceTest, EmployeeRelatedWorkspaceTest, EmployeeWorkspaceFollowupTest, EmployeeUserConversionTest, ProductionEmployeeMastersSeederTest; actual-script fixtures under `tests/browser`.
- Domain regression under `tests/Feature`: HR/payroll, accounting, inventory, scope/hardening and client rounds. Inspect assertions before claiming a business rule supported.
- Old deployment/QA guides contain historic main-branch assumptions, demo reset instructions and ZIP layouts. Use release-specific notes and actual server state; do not replay old instructions blindly.
