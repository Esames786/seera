# Connected Workspace Standard — suitability matrix and roadmap (27 September 2026)

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
| Site / Location | Master CRUD with map | PARTIAL | Code / name / project | Project, map & geofence, assigned employees, warehouse, later attendance and site expenses | Site map component, employees.site_id, warehouses.site_id | Attendance register | Site-scoped users | Batch 4 (after mobile attendance and site expenses) |
| Item / Material | Master CRUD + global stock/ledger screens | YES | Code / name / unit / category | Profile, accounts & valuation, warehouse balances, ledger (paged), receipts, issues, transfers, adjustments, project consumption | `WarehouseStock`, `StockLedgerEntry`, inventory reports | Stock on hand register, valuation report | Warehouse-scoped users see only their warehouse rows; on-hand never editable (StockService only) | Batch 4 |
| Warehouse | Master CRUD | YES | Code / name / project / site | Profile, balances, ledger, GRNs, issues, transfers, adjustments, low stock | Same as item, filtered by warehouse | Inventory reports | Warehouse scope; write forms still need scoped `exists` rules (audit gap) | Batch 4 |
| Purchase Order | Header/lines/attachments show; GRN created via link | YES (document workspace) | PO number / supplier / status | Header, lines, quotations, approval, receipts with received/outstanding, bill-matching status per GRN line, activity | PO show, `outstandingQuantity`, F04 `invoiced_quantity`, attachments | PR/PO registers, approval queue | Approve = approve permission; receive = Goods Receipts; matching view = AP view | Batch 4 — done |
| Supplier Bill | Show with journal, payments, lines + GRN matches (Batch 1) | YES (document workspace, mostly present) | Bill number / supplier / status | Bill, lines, GRN matches, journal, VAT row, payments, balance, activity | Already on show page; add activity panel | AP register | Approve/pay/reopen stay explicit; F04 untouched | Batch 4 — done (light) |
| Customer Invoice | Show with journal, receipts | YES (document workspace, mostly present) | Invoice number / customer / status | Invoice, lines, VAT, ZATCA local record (labelled foundation), journal, receipts, balance, activity | Already on show page | AR register | Approve/receive/reopen explicit; no live ZATCA claim | Pending (light; not started) |
| User | Create/Edit form with employee search; Save & Stay | YES | Username / name / status | Identity & login, primary role & extra roles, scope, temporary access, linked employee card, mobile access, security, activity | `LinkedIdentityNavigation`, `visibleUserIds`, activity log | Permission matrix, hierarchy, assign-users | **Fix first:** User update wipes Assign-Users roles (`UserController::update` sync); temporary access columns on users are unenforced | Batch 5 (after the role-sync fix) |
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
