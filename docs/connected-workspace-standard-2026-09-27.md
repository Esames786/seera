# Connected Workspace Standard — suitability matrix and roadmap (27 September 2026)

## Current update — 8 October 2026, Wave 2 Batch B — Items + Warehouses

**Batch B implemented and development-verified:** read-only Item/Warehouse View and connected Manage now share the existing workspace kit, persistent identity, guarded master saves, permissioned lazy document panels and scoped stored-stock summaries. Warehouse quantities remain unit-wise. Item/Warehouse history-loss deletion paths are protected; existing warehouse Project/Site ownership is fixed, and new ownership is validated. No new inventory engine, StockService/valuation/GRNI/accounting/runtime change, migration or production seeder.

Final full suite: **520 tests / 5,656 assertions / zero failures**. Requested broad regressions: **338 / 3,815**. Local mocked-browser checks: **77 passed**. See [44-point delivery report](wave2-batch-b-delivery-report-2026-10-07.md), [audit and verification ledger](workspace-wave2-batch-b-2026-10-07.md), and [owner deployment procedure](deployment-wave2-batch-b-2026-10-07.md). Documents were started on 7 October; release verification/handoff is 8 October.

Transfer Receive permission is still absent from standard seeded roles. Project/Site transfer scope remains source-only; no new grants or scope expansion. Runtime subjects remain PR, Site Expense and Supplier Bill. No production deployment, merge, owner/live ERP browser acceptance, new MySQL concurrency certification, or production load benchmark is claimed. **Next candidate: Wave 2 Batch C — Customer Invoice document-workspace refinement, NOT STARTED; separate approval required.** No automatic AR approval-runtime rollout. Offline, Equipment, live ZATCA, GPS, BOQ and payroll-to-GL status is unchanged.

## Historical update — 7 October 2026, Wave 2 Batch A — Users + Sites

**Batch A implemented and development-verified:** Users and Sites now use read-only View and connected Manage workspaces, persistent identity, independently saved sections, safe origins, shared unsaved-change protection, child permissions and scoped lazy lists. The pre-existing User edit role-sync wipe is fixed; permanent/temporary assignments are preserved. No new migration or production seeder. Final full suite: **500 tests / 5,287 assertions / zero failures**. See [delivery report](wave2-batch-a-delivery-report-2026-10-06.md) and [release verification](workspace-wave2-batch-a-2026-10-06.md).

Existing runtime subjects remain Purchase Requests, Site Expenses and Supplier Bills. GPS attendance, offline, Equipment, BOQ and payroll-to-GL are NOT complete. No merge, production deployment or independent owner browser acceptance is claimed. At this historical boundary, Batch B was the next unstarted candidate; the current update above supersedes that recommendation. Older entries below retain their historical release boundaries, not current next-step instructions.

## Historical update — 2 October 2026, Site Expenses / Project Phase B

Site Expenses now has responsive online entry, private receipts, sequential all-required runtime approvals, Cash/Bank/Reimbursement accounting and a unique draft Supplier Bill bridge for credit. Existing Finance review mode remains authoritative. Approval survives posting failure; explicit retry is idempotent. Full reimbursement and eligible open-period reversal are separate Finance actions. See [implementation and verification](site-expense-implementation-2026-10-02.md) and the authoritative [Site Expense guide](user-guide/SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#site-expenses).

Project Phase B is **PARTIAL**: scoped Site Expenses panel/summary/drill-through and posted-ledger cost integration are supplied; BOQ/budget lines, payroll/labour GL, equipment costing and construction progress are not. Project Phase A calculations are unchanged. Supplier Credit cost appears through its bill only, never twice.

Runtime module coverage: **Purchase Requests and Site Expenses**. Other modules retain legacy controls until separately integrated. Next recommendation: Supplier Bill runtime rollout, preserving GRNI and this credit bridge; do not start automatically. GPS/geofence, payroll GL/HR reports, Equipment and BOQ remain later approved scopes. No native/offline/mobile GPS or live ZATCA status upgrade. Entries below are dated historical batches; their old “next sprint” references are superseded by this update.

Owner decision: the Employee connected workspace is the default UX pattern wherever it
makes business sense. "Save / Save & Close / Save & New" is only the save layer of that
standard. This document records the standard, the suitability of each entity, and the
recommended conversion order. No conversion is started by this document.

## 1. The standard (owner addendum, condensed)

- Open one record, keep its identity visible, manage its related information from the same workspace.
- Persistent parent header (code / name / status); related sections as tabs or panels; child records viewed, created and edited without leaving the parent; after a child save stay on the same parent and tab.
- Never "Save All": every panel is its own small transaction. Approve, Post, Pay, Receive, Finalize, Process, Dispatch, Receive stock stay explicit business actions.
- Navigation: List → View (read-only) → Edit workspace (authorized). No generic "Open" where View/Edit matters.
- Global registers, reports, approval queues and batch runs stay global; they may drill into records but are not forced into an entity workspace.
- Permissions and scope are checked per panel and per child record, server-side, with the parent id verified against the child (never trusted from the browser).
- Performance: lazy panels, pagination, limited recent history, "View all" links to the global register.
- Save layer: `App\Support\SaveAction` + `x-admin.form-actions` (Batch 1). Save stays in context; Save & Close leaves to the origin; Save & New only for repeatable entry.

## 2. Reusable foundation already in the code

| Piece | Where | Reusable for |
|---|---|---|
| Panel registry with per-panel permission (module, action) | `app/Support/EmployeeWorkspacePanels.php` | Generic `WorkspacePanels` contract (key, label, permission, loader, saver) |
| Panel load / save / action endpoints, JSON, parent id verified from the route model | `EmployeeWorkspaceController` (`panel`, `save`, `action`) | Same three routes per entity |
| Panel Blade + related-panels JS (AJAX load, stay on tab after save, `seera:form-saved` event) | `resources/views/admin/hr/employees/_workspace-panel.blade.php`, `resources/js/employee-workspace.js`, `employee-related-panels.js` | Rename to generic workspace JS; no behaviour change needed |
| Section anchors and Save & Next | `EmployeeController::savedDestination` | Parent profile sections |
| Linked identity cards | `app/Support/LinkedIdentityNavigation.php`, `x-admin.linked-identity` | User ↔ Employee, Supplier ↔ payable account, Customer ↔ receivable |
| Save workflow | `app/Support/SaveAction.php`, `x-admin.form-actions` | Every parent profile and child editor |
| Unsaved-changes guard | `resources/js/unsaved-changes.js` | Already global |
| Access scoping | `UserAccessScopeService`, `EnsureRequestWithinScope` | Child queries inside a workspace inherit the global scopes automatically |

## 3. Suitability matrix

| Module / Entity | Current UX | Suitable? | Parent identity | Related sections | Existing functionality reusable | What remains global | Permission / scope risks | Recommended batch |
|---|---|---|---|---|---|---|---|---|
| Employee | Full connected workspace (reference) | YES (done) | Code / name / status | Personal, employment, payroll, documents, access + 8 related panels | All of it | Payroll runs, attendance register | Per-panel permission already enforced | Done; add Save & New (done in Batch 1) |
| Supplier | Profile form + separate AP screens | YES | Code / name / status / payable account | Profile, commercial & bank, projects (pivot exists), purchase orders, goods receipts (uninvoiced qty), bills, payments & balance, notes | AP controller, F04 matching, `supplier_projects`, PO/GRN/bill queries by supplier_id | AP register, payment run, approvals | Bill approve/pay need AP approve/process; scoped users see only their project's documents; supplier master is unscoped | Batch 2 (finance pilot) |
| Customer | Create/Edit with contacts & notes; View read-only; AR separate | YES | Code / name / channel rule | Profile, contacts, notes, projects, invoices, receipts, outstanding & ageing, linked account | AR controller, receipts, `allowed_payment_types`, ageing buckets on dashboard | AR register, VAT return, ZATCA list | Invoice approve/receipt need AR approve/process; View stays read-only | Batch 2 (finance pilot) |
| Project | Connected Workspace Phase A complete (29 September verification) | YES (phased) | Code / name / customer / manager / classification / branch / status / dates / master budget; scoped permitted summaries | Overview, customer, sites, staff, warehouses and unit-wise stock, suppliers, PR/PO/GRN, posted material issues, AR, shared financial summary, activity | Existing scoped models and generic workspace kit; shared ProjectCostReport service | Financial reports, GL, global registers and business actions remain separate | Projects view plus each panel module; route-parent Site protection; no stock/accounting writes from profile | Phase A complete; Phase B pending: BOQ/budget lines, Site Expenses, labour, equipment, expanded budget-vs-actual and site operations |
| Site / Location | Read-only View + connected Manage (Batch A) | YES (implemented for existing functionality) | Code / name / Project / supervisor / status | Overview, Project, location configuration, staff, warehouses, PR/PO/GRN, posted material issues, Site Expenses, manual attendance context, activity | Existing Site map, scoped records, P2P, Site Expense workflow and generic workspace kit | Global HR, inventory, procurement, expense and attendance registers | Sites view plus each child permission; URL parent and exact Site/Project scope; no GPS/offline runtime | Wave 2 Batch A complete; owner acceptance pending |
| Item / Material | Read-only View + connected Manage (Batch B) | YES (implemented) | Code / name / unit / category / status / valuation / VAT; permitted stock summaries | Overview, scoped warehouse balances/ledger, PR/PO, GRN, posted issues, transfers, adjustments, accounting mapping, tagged activity | Existing stock/document models, F04 quantities, generic workspace kit | All inventory/procurement registers and reports; business actions | Parent plus child permission; visible-warehouse totals; value/mapping gates; cross-scope history retained on Delete | Wave 2 Batch B complete; owner acceptance pending |
| Warehouse | Read-only View + connected Manage (Batch B) | YES (implemented) | Code / name / permitted Project/Site / incharge / status; unit-wise quantity/value summaries | Overview, balances, ledger, GRNs, posted issues, inbound/outbound transfers, adjustments, Project/Site, activity | Existing inventory relations and workspace kit | Global registers, reports and explicit stock business actions | Scoped URL parent; validated new/fixed existing ownership; deactivation retains history; existing transfer scope/Receive limitations remain | Wave 2 Batch B complete; owner acceptance pending |
| Purchase Order | Header/lines/attachments show; GRN created via link | YES (document workspace) | PO number / supplier / status | Header, lines, quotations, approval, receipts with received/outstanding, bill-matching status per GRN line, activity | PO show, `outstandingQuantity`, F04 `invoiced_quantity`, attachments | PR/PO registers, approval queue | Approve = approve permission; receive = Goods Receipts; matching view = AP view | Batch 4 — done |
| Supplier Bill | Show with journal, payments, lines + GRN matches (Batch 1) | YES (document workspace, mostly present) | Bill number / supplier / status | Bill, lines, GRN matches, journal, VAT row, payments, balance, activity | Already on show page; add activity panel | AP register | Approve/pay/reopen stay explicit; F04 untouched | Batch 4 — done (light) |
| Customer Invoice | Show with journal, receipts | YES (document workspace, mostly present) | Invoice number / customer / status | Invoice, lines, VAT, ZATCA local record (labelled foundation), journal, receipts, balance, activity | Already on show page | AR register | Approve/receive/reopen explicit; no live ZATCA claim | Wave 2 Batch C — done |
| User | Read-only View + connected Manage (Batch A) | YES (implemented) | Name / email / status / real Employee link / roles / temporary state / mobile | Profile, employment metadata, linked Employee, roles/permissions, temporary assignments, access scope, mobile, security/status, activity | Existing real Employee FK, pivot/effective permissions, scope service, workspace kit and ActivityLog | Permission matrix, reporting hierarchy, global assign-users and activity register | Role-sync wipe fixed; old primary retained as additional until explicit removal; row-locked explicit changes; user-level temporary fields remain metadata | Wave 2 Batch A complete; owner acceptance pending |
| Journal Entry | Create/Edit/Show, Post/Cancel | PARTIAL (document, already compact) | Journal number / status | Header, lines, post state, reversal link | Show page | GL, trial balance | Whole-entry scope rule (F07) | Keep as document page; no workspace |
| Marketing Lead | Show with inline visit form, follow-ups, conversion | PARTIAL (mostly present) | Lead code / name / status | Profile, assignment, visits, follow-ups, notes, conversion, customer link | Existing show page | Marketing reports | Prevent duplicate customer conversion | Batch 5 (light) |
| Equipment / Vehicle | Not built | YES when built | Asset code / plate | Profile, assignment, operator, documents, maintenance, fuel, GPS, cost | Nothing yet | Fleet reports | New permissions | With the Equipment module |
| Roles | Form + matrix + assign users + workflows | NO (configuration) | — | — | — | Matrix, hierarchy, assign, workflows stay separate | — | Not converted |
| Payroll Run | Batch process/approve | NO (batch) | — | — | — | Stays global | — | Not converted |
| GL, trial balance, balance sheet, P&L, cash flow, VAT period, approval queues, activity log, attendance register, inventory reports | Global screens | NO | — | — | Drill-through links only | Stay global | — | Not converted |

## 4. Roadmap (recommended order after Accounting UX Batch 1)

0. **Extract the generic workspace kit** from Employee (panel registry contract, panel/save/action routes, panel Blade, JS rename) so every later batch declares panels instead of copying controllers. No screen change on its own.
1. **Supplier workspace** (finance pilot): profile + bank/channel + projects + POs + GRNs (uninvoiced) + bills (with F04 matches) + payments/balance. Reuses AP and F04 code as-is; Approve/Pay stay on the bill.
2. **Customer workspace**: profile + contacts + notes + projects + invoices + receipts + ageing; View stays read-only.
3. **Purchase Order document workspace** and **Supplier Bill / Customer Invoice** light additions (activity panel, matching status), keeping PR → PO → GRN transitions. — DONE for PO, PR, GRN and Supplier Bill in Batch 4 (27 September 2026); Customer Invoice light additions remain.
4. **Project workspace**: Phase A complete and verified 29 September 2026 using existing operational data. Phase B remains pending: Site Expenses, BOQ/budget lines, labour and equipment cost, expanded budget-vs-actual and site operations. No Phase B implementation is started by this sprint.
5. **Item and Warehouse workspaces** (balances, ledger, movements, consumption), together with the scoped `exists` fix on inventory writes.
6. **User workspace** after fixing the role-sync wipe and enforcing or removing user-level temporary access.
7. **Site workspace** once mobile attendance and site expenses exist.
8. **Marketing Lead** light workspace; **Equipment** when the module is built.

Batches 2 to 8 each ship with: List → View → Edit workspace navigation, per-panel permission checks, server-side parent verification, paged panels, Save-layer buttons on every editor, and tests mirroring the Employee workspace test set.

## 5. Status log

### Batch 2 — generic kit + Supplier pilot (27 September 2026)

**Kit extracted from Employee (Employee unchanged in behaviour):**
- `app/Support/Workspace/PanelDefinition.php` (key, title, permission module, view/write action), `WorkspacePanels` interface, `PanelSet` trait (visible panels, next panel).
- `app/Http/Controllers/Concerns/ServesWorkspacePanels.php` (per-panel authorization, HTML-fragment JSON response). `EmployeeWorkspaceController` and `EmployeeWorkspacePanels` now use them.
- `resources/js/workspace-related-panels.js` (generic panel loader/saver/actions, extracted from `employee-related-panels.js`, which is now a thin wrapper) and `resources/js/workspace.js` (generic tab navigation for `[data-workspace]`).
- `x-admin.workspace-host` (related-panel host with its messages) and `x-admin.action-buttons` labels (`edit-label`, `delete-label`, `deactivate`).

**Supplier workspace:**
- List → View (read-only, no forms, panels rendered server-side with the latest 5 rows and "View all") → Edit / Manage (persistent header, Profile form with Save / Save & New / Save & Close / Cancel, lazily loaded panels: Projects, Purchase Orders, Goods Receipts, Supplier Bills, Payments & Balance, Accounting, Activity).
- Panels and their permission: Projects (Suppliers view; link/unlink needs Suppliers edit + Projects view), Purchase Orders (Purchase Orders view), Goods Receipts (Goods Receipts view; "Create Supplier Bill" needs Accounts Payable create and reuses the F04 pre-filled bill form, returning to the supplier's receipts tab), Supplier Bills (Accounts Payable view; Edit draft needs edit, Record payment needs process; approve/pay/reopen stay on the bill page), Payments & Balance (Accounts Payable view), Accounting (Journal Entries view), Activity (Activity Logs view).
- Scope: every panel query starts from a globally scoped model; header and payment totals come from the same scoped bill query, so hidden bills are neither listed nor counted. The header shows no money without Accounts Payable view.
- Parent/child: the supplier in the URL is authoritative; a forged `supplier_id` in the body is refused; linking checks the project is visible; unlinking checks the project is linked to this supplier; only the Projects panel accepts writes.
- Deactivate replaces delete for suppliers (documents and history are kept). The profile form inside the workspace no longer carries the project checklist (the panel manages links); the create form still does, and an update that does not submit the list keeps the existing links.
- Tests: `tests/Feature/SupplierWorkspaceTest.php` (10 tests). Employee, F04, UX and supplier regression classes green.

**Not done in this batch (by instruction):** Customer, Project, Item, Warehouse, Site, Equipment workspaces; approval runtime.

### Batch 3 — Customer workspace + current-system user guide (27 September 2026)

- Customer: List → View (read-only; header with identity, payment channel, receivable account, projects count, outstanding/received/overdue for Accounts Receivable viewers; server-rendered sections with the latest 5 rows) → Edit / Manage (Profile with Save / Save & New / Save & Close / Cancel; panels Contacts (add / edit in place / remove within Customers create / edit / delete), Notes (add / remove), Projects (Projects view), Invoices, Receipts & Balance, Ageing (Accounts Receivable view), Accounting (Journal Entries view), Local ZATCA Records (ZATCA Invoicing view), Activity (Activity Logs view)).
- Ageing reuses the dashboard rule, now shared in `App\Support\AgeingBuckets` (days late from due date; Current / 1–30 / 31–60 / 60+), labelled as current-state ageing.
- Scope: every panel and figure comes from the scoped invoice / receipt / journal / ZATCA queries; the header shows no money without Accounts Receivable view.
- Parent/child: contacts and notes are read and written only through the customer's own relations (foreign ids → 404); a site contact must sit on a site of one of the customer's visible projects; forged `customer_id` in the body is refused; only Contacts and Notes accept writes.
- The create form keeps inline contacts and a note; the workspace profile form no longer carries them (panels instead). Existing contact and note routes remain for compatibility.
- Tests: `tests/Feature/CustomerWorkspaceTest.php` (8 tests covering the 21 required points together with the Supplier, Employee and finance classes).
- Documentation: `docs/user-guide/SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md`, `SCREEN-INDEX.md`, `WORKFLOW-INDEX.md` (current system only, fictional training data).

**Next candidates (not started):** Purchase Order document workspace, Project workspace phase A, Item / Warehouse, User (after the role-sync fix), Site, Lead.

### Batch 4 — Procure-to-Pay document workspace (27 September 2026)

- Purchase Order View (INV-PO-003) is the connected document workspace: persistent header (number, status, receiving state, approval, supplier with View / Manage by permission, dates, project / site, deliver-to warehouse, total, Received x of y · still to receive, Invoiced · received but not invoiced, billing state with bill count and outstanding payment for Accounts Payable viewers, receipt count) and sections Overview, Order Lines, Source Purchase Request, Supplier & Commercial, Quotations, Goods Receipts, Billing & GRN Matching, Accounting, Activity. `App\Support\Workspace\PurchaseOrderWorkspacePanels` declares the sections (module per section), the scoped queries, the per-line matrix (ordered / received / still to receive / invoiced / received but not invoiced from the F04 receipt lines) and the header summary; the paged sections (receipts, billing, accounting, activity) are served by `PurchaseOrderWorkspaceController::panel` (`purchase-orders/{id}/workspace/{panel}`, JSON `{html}`, 5 rows, View all) and rendered inline on the View page with per-section paging. Read-only: no write route was added.
- Purchase Request View (INV-PR-003): header (ordering state, approval, ordered vs requested), Requested Items with Ordered so far / Still to order, Purchase Orders with received state and View PO (Purchase Orders view), Activity (Activity Logs view). Approve / Reject / Create Purchase Order now render only with their permission.
- Goods Receipt View (INV-GRN-003): header (source PO, supplier, warehouse, project / site, stock, accounting, invoicing state), Received Lines with Invoiced / Received but not invoiced, Bill Matches (Accounts Payable view; scoped bills only), Accounting Entry (Journal Entries view), Activity; Back / Back to Purchase Order. Form uses `x-admin.form-actions`; store / update go through `SaveAction` (Save stays on the receipt with the origin kept, Save & close returns to the origin, else the list); Post Stock keeps the origin.
- Supplier Bill View (FIN-AP-003): header (Bill Number, Supplier, status and payment state, dates, project / site, matched receipts and orders, Total, Paid, Outstanding payment, journal), sections Bill Info, Lines, GRN Matches (accrued vs billed, variance, provisional / invoiced), VAT, Accounting Entry, Payments (with journals), Balance, Activity; Edit and Record Payment carry the origin; the payment form and `storePayment` honour `_return_to` (safe admin paths only) and fall back to the bill.
- Activity for one document comes from `App\Support\Workspace\DocumentActivity` (entries naming the document number, restricted to the writing modules and to `ActivityLog::visibleTo`, so NR-32 still hides Super Admin actions from lower roles).
- Permissions: sections are hidden and their routes protected (`EnsureUserHasPermission` maps the panel route to Purchase Orders view; the controller then checks the section's own module). Scope: every query starts from the scoped models (a receipt in another project's warehouse or an out-of-scope bill is neither listed nor counted). Parent/child: receipts are the order's own (`purchase_order_id`), bills are those with an F04 match on those receipts and the order's supplier, journals are those documents' journals; a forged match row of another supplier and a receipt of another order are ignored; quotation files are still checked against the order id.
- Tests: `tests/Feature/PurchaseOrderWorkspaceTest.php` (20 tests, 265 assertions) plus the Supplier, Customer, Employee, F04 and finance classes as regressions.
- Documentation: guide version 1.1 (chapter openers with number / name / purpose / roles / screens on every chapter; INV-PR, INV-PO, INV-GRN, FIN-AP-003, FIN-AP-005, WF-002, WF-010, WF-011, glossary, limitations updated; example PO-2026-0012 10,000 kg received 6,000 + 4,000), Screen Index and Workflow Index rows updated, HTML edition rebuilt. No Screen ID changed or added.

### Batch 5 — Project Connected Workspace Phase A (implemented 28 September; verified 29 September 2026)

- Baseline: `b4612a6`, same feature branch. P2P is closed; PostingService, GrnMatchingService, GRNI and procurement state transitions are unchanged. No deployment or merge.
- List → read-only View → Edit / Manage. Persistent identity header and permission-scoped counts/money; profile uses Save / Save & Close / Save & New / Cancel. No Save All or implicit business actions.
- Generic workspace panels: Customer; Sites / Locations; Staff; Warehouses; Stock On Hand; Suppliers; Purchase Requests; Project Purchases; Goods Receipts; Material Used; Customer Invoices; Customer Receipts; Financial Summary; Activity. Each loads ten rows, with a standalone paged View All fallback. Overview contains no eagerly loaded child history.
- Source/ownership: employees use their one current project; warehouses use project_id; suppliers derive from the project pivot and permitted visible project documents; GRNs belong through their scoped PO, not just warehouse. Receipts follow visible project invoices. No new assignment or procurement engine.
- Stock uses stored positive WarehouseStock balances/total_value and unit-wise quantities. Material Used uses scoped issue-ledger values tied to posted Stock Issues, not receipts; total quantity is shown only for a single known unit.
- FinancialReportController and the Project panel share `App\Services\Accounting\ProjectCostReport`. Posted expense debit-credit and revenue credit-debit are net of reversals; billed/invoiced retain existing non-draft semantics, including cancelled documents. All-date Project panel; report drill-through filters to the Project. Master budget is not BOQ or a site budget. Cost-centre linkage alone does not assign project cost.
- Nested Project → Site routes reuse the existing form and validation. URL parent is authoritative; foreign child and forged project payloads fail. Site/warehouse-level actors cannot create a new out-of-scope site. Safe return paths reject external URLs, misleading admin prefixes and traversal. PO/Invoice View additions are return links only.
- Every panel checks its own permission as well as Projects view. Existing global scopes are retained in queries and totals; no new scope bypass. Staff uses the actual HR permission module. Stock figures and financial/AR figures are separately gated.
- Activity uses new exact immutable Project entity tokens plus ActivityLog::visibleTo. Old name-only logs are omitted because they are ambiguous; no historical timeline is invented.
- Verification: ProjectWorkspaceTest 20 tests / 243 assertions; connected-workspace/F04/finance/report/guide regression selection 106 / 1,530; adapted Round 3 tests 17 / 332. Full suite **356 tests / 4,049 assertions / zero failures**. Two old Project tests retain their supplier/employee/location assertions on the new lazy endpoints rather than asserting eager initial history.
- Existing authoritative guide/indexes updated to version 1.2; dark HTML regenerated. All Screen IDs retained; WF-016 added. 359 internal HTML links/anchors checked with no missing targets. `npm run build` passed again after the full suite. No new migrations or seeders required. Automated browser interaction was not run; server-rendered HTTP tests and asset build are the verification boundary.

**Phase A complete does not mean Project Management complete.** Phase B remains pending: BOQ/budget lines and approval, Site Expenses, labour/payroll-to-GL, equipment cost, expanded budget-vs-actual and the future operational site dashboard. Mobile attendance/geofence runtime, offline sync, F08 multi-step approvals and live ZATCA clearance remain outside this sprint.

**Recommended next sprint (not started):** design and implement Site Expenses with explicit project/site/warehouse authorization, expense-account/cost-centre attribution, posting/reversal and permission tests; then integrate those real transactions into Project Phase B. Confirm approval/accounting policy before that implementation. BOQ, payroll and equipment costing should follow their own approved specifications rather than appearing as placeholder totals. Inventory write-scope and user role-sync audit gaps listed above remain separate known dependencies; Phase A does not claim to resolve them.

### Batch 6 — F08 reusable approval runtime (30 September 2026)

- Starting HEAD `9f8a045` on the same feature branch (includes changes newer than the
  sprint brief's `06913c4`). Preserve earlier workspace navigation/reporting parents.
- Separate `approval_instances` and `approval_instance_steps` store per-document
  attempt/configuration/document/eligible-user snapshots and immutable-on-decision
  audit. `ApprovalSubject` adapter contract + `ApprovalRuntimeService` own submission,
  resolution, sequential eligibility, locked decisions, retries, rejection and completion.
- **AVAILABLE: Purchase Request only**, via explicit Submit. ALL configured required
  slots must approve in order. No parallel schema exists. Each role slot is satisfied
  by one eligible assigned user (or its configured explicit user), not every member
  of that role. Required people/roles must be explicitly represented by required steps.
- Requester/submitting editor self-approval blocked. Current active role assignment,
  module permission and project/site/warehouse scope checked again for every decision.
  Role hierarchy/reporting-parent links do not independently confer authority.
- Rejected PR corrections + explicit Resubmit create a new attempt. Old history remains.
  Pending instances freeze PR edits. Documents with runtime history cannot be deleted.
  Legacy approved/pending rows are not auto-enrolled or backfilled; existing authorized
  legacy decisions are retained until explicit editable-document enrolment.
- Embedded PR approval panel (APR-002) + global actionable My Approvals (APR-001).
  Queue links open document context; no blind list mutation. Approval History/view is
  a separate permission; migration adds it to existing PR-view roles, independently
  revokable. Fresh demo seed grants are consistent; no production reseed required.
- Lock source → instance → step/current step set. Unique source/attempt and step/order
  indexes; transactions include document transition and ActivityLog. Current locking
  reads protect MySQL REPEATABLE READ retries. Workflow edit/delete locks coordinate
  with configuration snapshot creation.
- No accounting/stock posting from PR approval. PO/AP/AR/HR/payroll posting logic
  unchanged. Other module runtime integrations remain **PARTIAL / not connected**.
- Unsupported: amount-limit routing, Branch Level workflow scope, parallel groups,
  delegation/reassignment, automatic reporting-parent expansion, SLA/escalation jobs,
  notifications, send-back and automatic posting. Unsupported threshold/posting
  configuration fails PR submission explicitly; existing sample configuration is not rewritten.
- Guide v1.3 adds APR-001/002 and WF-017, preserving all prior IDs, dark HTML and
  `/user-guide` routes. See `approval-runtime-verification-2026-09-30.md` for measured gates.

**Next planned item (not started): FULL SITE EXPENSE MODULE + PROJECT PHASE B INTEGRATION.**
Use `ApprovalRuntimeService`, not a second approval implementation. Accounting may post
only after all required approvals and a separately authorized/idempotent posting
transition. Follow [Site Expense contract](site-expense-runtime-contract-2026-09-29.md)
and resolve its owner/accountant decisions before implementing posting. No Site Expense
schema/module, merge or deployment is included in F08.

### Current status — Supplier Bill approval sprint (4 October 2026)

This supersedes the historical Batch 6 next-step note above. Full Site Expense and Project Phase B expense integration shipped on the feature branch at `eedc786`; this sprint starts from that commit and adds Supplier Bills to the SAME reusable Approval Runtime. No merge or production deployment is included.

- AVAILABLE runtime subjects: Purchase Requests, Site Expenses, Supplier Bills. PO, Customer Invoice, Leave and Payroll runtime are still unimplemented. All configured required sequential slots must approve. No parallel groups, amount thresholds, implicit reporting-parent expansion, delegation or escalation/notification jobs.
- Supplier Bills retain draft/unpaid/partially_paid/paid financial states, with separate approval mode/status and immutable attempt history. New bills explicitly Submit; legacy drafts only enrol through Submit, while historical paid/posted bills gain no fabricated decisions.
- Final approval commits before the single extracted existing F04/direct/mixed posting path. Source-locked Finance retry cannot duplicate AP/VAT/journal/GRN consumption. Approved history survives posting failure. Review journals are not payable until actually posted.
- Pending approval freezes bill fields/matches; submitted GRN reservations survive rejection. Correction replaces reservations transactionally. Reopen performs existing financial reversal and preserves approved history; new approval is mandatory before reposting.
- FIN-AP-003 adds Approval beside its existing sections. Supplier, PO/P2P and Project Finance contexts show approval labels without introducing profile-save approval or new Project cost formulas. APR-001 remains the only actionable queue.
- Site Expense Supplier Credit creates exactly one runtime-mode draft bill, no separate AP journal. Its original operational approval and the bill's financial approval remain independent. The expense shows linked bill status; posted cost enters through that bill once.
- One additive migration, no production seeder, no automatic permission grants/workflow policy. Administrators configure Supplier Bill / Bill Submitted / Create Accounting Entry and explicit eligible reviewers. See `supplier-bill-runtime-2026-10-03.md` for audit and measured release gates; authoritative guide v1.5 adds WF-019 and preserves Screen IDs.

### Wave 2 status — Batch A complete; Batch B requires new approval

1. **Batch A: Users + Sites — COMPLETE in development (7 October 2026).** See current update and delivery report above. Full suite and local mock-browser regressions pass; owner staging acceptance/deployment remain separate.
2. **Batch B: Items + Warehouses.** Centralize related read/work actions without duplicating stock posting, transfers, valuation or ledger logic.
3. **Batch C: Customer Invoice document workspace refinement.** Consistent document context, accounting/receipt states and return navigation; do not silently introduce AR Approval Runtime. — **COMPLETE in development (8 October 2026)**, see the Batch C status below.
4. **Batch D: HR connected optimization around Employee.** Attendance / Leave / Overtime / Payroll context improvements; preserve accepted calculations, separate permissions, approval and payroll-processing actions. — **COMPLETE in development (9 October 2026)**, see the Batch D status below.

Remaining batches require their own approved source audit, regression coverage, permission/scope checks and client review boundary. Batch A completion does not authorize Batch B, merge or deployment.

### Wave 2 Batch C — Customer Invoice document workspace (8 October 2026)

Starts from `8103bdb` (Batch B). Documentation-and-UX refinement only: no migration, no seeder, no new route, no change to AR posting, receipt idempotency, VAT-period locks, reopen or the local ZATCA foundation, and no Customer Invoice approval runtime.

- Customer Invoice View (FIN-AR-003) is a read-only finance document workspace: persistent header (number, status and payment state — Draft, not posted / Awaiting receipt / Overdue by n days / Partly received / Received in full —, customer with View / Manage by permission, invoice and due dates, project and cost center, local e-invoice state, Total with VAT, Received, Outstanding, accounting state with journal link) and sections Invoice Information, Invoice Lines, Customer (Customers view; Manage needs edit), Project / Cost Center (Projects view; a site is not recorded on an invoice, so none is invented), VAT, Accounting (Journal Entries view), Receipts (10 per page, this invoice only), Balance / Ageing (shared `AgeingBuckets` rule, days overdue), Local e-Invoice Record (ZATCA Invoicing view; every field labelled local, not verified live), Activity (Activity Logs view, NR-32 visibility). Context lives in `App\Support\Workspace\CustomerInvoiceWorkspace`; the stored received / balance columns remain the single source of the outstanding amount.
- Actions gated by state and permission together: Edit Draft (draft + edit), Approve & Post (draft + approve), Record Receipt (open + process), View Journal (Journal Entries view), Reopen for Correction (Super Admin with approve; unpaid, no receipts, local record not cleared), Back to origin / Back to Accounts Receivable.
- Return-to-context: Edit, Record Receipt, the receipt form (Back / Cancel / after recording, including a recognised replay) and Save / Save & close on a draft all honour a safe relative `/admin` origin; Customer workspace (View page and Edit / Manage) and Project workspace invoice links carry their own section as the origin.
- Scope and parent protection: invoice, receipt form, edit and receipt store resolve through the scoped invoice (not found outside the user's projects); receipts are the invoice's own relation; the customer outstanding figure counts only visible invoices; a replay key bound to another invoice or changed details is refused (existing F02 guard).
- Read-only guarantee: a GET of the invoice page issues no insert, update or delete (regression test listens to the query log).
- Localization: new header / section / state strings in `lang/en/workspace.php` and `lang/ar/workspace.php` (`inv_*`); legacy table labels remain English as on the other finance pages.
- Tests: `tests/Feature/CustomerInvoiceWorkspaceTest.php` (15 tests, 290 assertions) covering read-only / no-write, identity, customer / project / accounting / ZATCA / activity permissions, lines and VAT, journal, receipts list and paging, foreign receipt, receipt permission and open state, replay / overpayment / wrong invoice, scope, return-to, draft vs posted edit with Save / Save & close, reopen gating, customer and project workspace links, truthful local e-invoice wording. Targeted gate (Customer, Project, Supplier Bill runtime, Site Expense, Approval runtime, PO workspace, F04, Finance acceptance, VAT locks, settlement idempotency, state transitions, accounting UX, Round 3, User / Site / Item / Warehouse workspaces, user guide): 271 tests / 3,413 assertions, 0 failures. Full suite: 535 tests / 6,000 assertions, 0 failures.
- Documentation: guide v1.8 (FIN-AR-003 workspace subsection with lifecycle, View / Edit rules, sections, buttons, scope and local e-invoice truth; FIN-AR-005; CUS-004 note; WF-003 with partial receipts; glossary; limitations), Screen Index rows FIN-AR-003 / 004 / 005, Workflow Index WF-003, HTML regenerated and validated (408 internal links; 188 Screen IDs and 19 Workflow IDs unchanged, none added).
- Build: `npm run build:release` reproduced the same three assets (`app-BY7M-_4k.js`, `app-R27YIm39.css`, `erp-BneCgvYl.css`); the committed `public/build.zip` (188,884 bytes, 18 files, SHA-256 B73DAD25…6AC0) is unchanged and needs no re-extraction.

**Next candidate: Wave 2 Batch D — Employee-centred HR UX (Attendance / Leave / Overtime / Payroll context), NOT STARTED; separate approval required.** Credit notes, receipt reversal, invoice print / PDF, a site on invoices and a Customer Invoice approval runtime remain out of scope.

### Wave 2 Batch D — Employee-centred HR UX (9 October 2026)

Starts from `f7d036d` (Batch C). UX / context / navigation only: no migration, no seeder, no new route, no change to attendance, leave-balance, overtime, salary-structure, payroll or EOSB calculations, no GPS / geofence runtime, no Payroll → GL and no leave approval runtime.

- Employee View (HR-EMP-003) is the employee-centred HR context page built by `App\Support\Workspace\EmployeeHrContext`: persistent header (code / name, status, designation, project / site, classification, department, manager, contract type and period, IQAMA with validity, attendance this month, remaining annual leave, pending leave / overtime, basic salary for Payroll viewers), IQAMA / contract alerts from the existing expiry rule, and sections Overview (Employment, Contract & IQAMA with document validity counts, Payroll Information), Documents, Attendance (date, check in / out, shift, status, late / overtime minutes, project, site, source, stored geo-fence label), Leaves (existing balance rule + requests with type, days, status, reason, decider), Overtime (hours × rate as stored, linked attendance, approved hours), Salary Structures, Payroll (run, period, amounts, present / leave days, run status, Open payroll run), Activity. Each section is present only with its module: Attendance view, HR view, Payroll view, Activity Logs view; HR view alone no longer sees pay figures.
- Every section pages 10 rows (`page_attendance` …) and links to its register filtered to the employee (`?employee=` on Attendance / Leaves / Overtime, search on Salary Structures / Documents, Payroll list). Add attendance / Create leave / Add overtime / Edit links open the existing register forms with the employee preselected and a safe `return_to`; the three forms now use the shared Save / Save & close actions through `SaveAction` (Save stays on the record, Save & close returns to the origin or the list), and their edit / details pages show Back to origin. Registers show "Showing records of … only" with Back to employee / Show all.
- Edit workspace (HR-EMP-004) panels: Attendance rows gain shift, late / overtime minutes, project and site; Leave rows gain the leave type; Overtime rows the linked attendance date; Payroll History rows the period and run status; every related panel gets a View register link. Panel permissions, forms and approve / reject / delete behaviour unchanged.
- Scope: all rows come from the scoped `employee` relations; an out-of-scope employee is 404 on the View, and the register `employee` filter cannot name or list an employee outside scope. Cross-employee rows never appear.
- Read-only guarantee: a GET of the Employee View issues no insert / update / delete (query-log regression test); no payroll or balance recalculation happens on load.
- Localization: `hr_*` keys in `lang/en/workspace.php` and `lang/ar/workspace.php` (46 each) for the new labels, help texts and state wording; legacy table labels remain English.
- Tests: `tests/Feature/EmployeeHrContextTest.php` (11 tests, 144 assertions): sections and no-write, attendance permission / scope / foreign rows, leave balance and scope, overtime permission and stored amounts, payroll hidden without permission and cross-employee rows hidden, contract / IQAMA summary, activity permission and visibility, project-scope denial and register filter, pagination, register return-to and prefill (attendance, leave, overtime), Edit-workspace panel columns and permission refusal. Regression gate (Employee workspace classes, HrPayroll, User / Site / Customer / Supplier / Project / Item / Warehouse / Customer Invoice workspaces, Finance acceptance, settlement idempotency, VAT locks, user guide): 188 tests / 2,877 assertions, 0 failures. Full suite: 546 tests / 6,190 assertions, 0 failures (one stale wording assertion in ClientChangeRequestsRound3Test updated for the renamed Leaves section).
- Documentation: guide v1.9 (HR-EMP-003 rewritten with per-section permissions, sections, actions, return-to and what the page does not do; HR-EMP-004 panel columns; Attendance / Leaves / Overtime buttons and employee filter; chapter 6 status truth; limitations), Screen Index rows HR-EMP-003 / 004, HR-ATT-001..003, HR-LV-001..004, HR-OT-001..003, Workflow Index WF-001; HTML regenerated and validated. No Screen or Workflow ID changed or added.

**Connected Workspace optimization Wave 2 is now complete (Batches A–D). Broad workspace optimization STOPS here.** Next major sprint recommendation: **GPS Attendance + Geofence Runtime** (mobile check-in / check-out with device location, server-side geofence evaluation against the stored site coordinates and radius, source and geofence status set by the runtime instead of typed, offline queue later), NOT STARTED; separate approval required.

### Core functionality — GPS Attendance + Geofence Runtime, Phase 1 online (9 October 2026)

Starts from `bd3090f` (Wave 2 Batch D). First sprint after the Connected Workspace optimization wave. Online mobile-web attendance only; offline sync, device attestation and Payroll → GL remain out of scope.

- Runtime: `App\Services\Hr\AttendanceGeofenceService` (coordinate validation, Haversine distance on the IUGG mean radius, site policy → inside / outside / not_enforced / location_unavailable, no hidden tolerance, accuracy worse than 2,000 m refused) and `App\Services\Hr\MobileAttendanceService` (identity from `employees.user_id`, project / site from the employee record, site must be active and belong to the employee's project, eligibility = active employee + Mobile Access + Attendance → mobile; check-in under the employee row lock with the unique (employee, date) index as the last guard; check-out under the record lock; server clock for date / times; shift start + grace → late minutes; overtime left to the Overtime register). `GeofenceBlockedException` lets refused attempts be audited after the rollback.
- Screen HR-ATT-004 `admin/hr/attendance/mobile` (`MobileAttendanceController`: page, `locate` without write, `check-in`, `check-out`; JSON), responsive page with large touch controls, `resources/js/mobile-attendance.js` (position requested only on tap, server verdict shown, then confirm; permission denied / timeout / unavailable / unsupported messages; no background tracking). Permission map: `admin.hr.attendance.mobile*` → Attendance → mobile.
- Schema: `2026_10_09_000001_add_gps_evidence_to_attendance_records` (12 nullable evidence columns, check-in and check-out kept separately; re-runnable; fresh and additive paths verified on MySQL 8 and SQLite). Model: `source = gps` (cannot be typed by HR), `geofence_status` gains `not_enforced` / `location_unavailable`, `sourceLabel()` / `geofenceLabel()`.
- Manual attendance untouched; HR edit of a GPS row keeps source, geofence state, employee, site and evidence, shows the evidence read-only and logs "Corrected GPS attendance record". Register, Employee View and Site workspace show *GPS / Mobile* vs *Manual* with the geofence state and distance; help texts updated (en + ar) and a new `mobile_attendance` language group (en + ar).
- Audit: Mobile check-in, Mobile check-out, Blocked mobile check-in / check-out (distance and radius, no raw coordinates), Corrected GPS attendance record.
- Tests: `tests/Feature/MobileAttendanceTest.php` (18 tests, 191 assertions) covering eligibility, forged ids, assignment problems, coordinate validation, Haversine, evidence and server time, inside / outside / not-enforced / missing coordinates, duplicate and retry, check-out with fresh position and policy, manual coexistence, shift lateness, HR correction, cross-project / cross-employee, Employee and Site workspace display. Real MySQL 8 locking probe `tests/manual/mobile-attendance-concurrency.php` (disposable server, datadir `seera-attendance-lock-test-*`, port 33479): additive migration + concurrent check-in + concurrent check-out → one transition each, worker refused. Regression gate (HR / Employee / User / Site / Site Expense / Project / Finance / guide classes): 190 tests / 2,485 assertions after two stale wording assertions were updated. Full suite: 564 tests / 6,383 assertions, 0 failures.
- Browser checks: NOT RUN. The mocked Playwright harness in `tests/browser` needs a Playwright module that is not installed on this machine (only Chrome and the ms-playwright helper binaries exist). The mobile page script passed a syntax check only; mobile-width, permission-denied and RTL rendering were not verified in a browser.
- Documentation: guide v1.10 (HR-ATT section rewritten with HR-ATT-004, policy, lateness, safeguards, privacy, errors, HR corrections; Site workspace geofence wording; chapter 6 status truth; limitations), Screen Index HR-ATT-001..004, Workflow Index + chapter 17 WF-020, HTML regenerated and validated (410 links; validator now pins HR-ATT-004 and WF-020). Deployment guide `docs/deployment-gps-attendance-2026-10-09.md` (migration + new build.zip SHA 25B77A8D…850A4E).

**Status truth after this sprint:** GPS attendance (online, location-validated) AVAILABLE; geofence runtime AVAILABLE; offline attendance NOT YET OPERATIONAL; device attestation / anti-spoofing NOT YET OPERATIONAL; native app NOT YET OPERATIONAL. Next sprint recommendation: owner acceptance on real phones first, then either offline attendance (Phase 2: queued check-ins with server-side reconciliation) or Payroll → GL. Neither started.
