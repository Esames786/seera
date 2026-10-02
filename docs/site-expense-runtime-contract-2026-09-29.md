# Site Expense integration contract — historical F08 design

**Superseded for current implementation on 2 October 2026:** the owner's next-sprint brief approved the four payment modes and implementation now lives in [the implementation ledger](site-expense-implementation-2026-10-02.md) and the authoritative [User Guide](user-guide/SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#site-expenses). The following text is retained as the F08-era design/audit record, not current feature status. No production deployment is claimed.

Prepared 30 September 2026. Filename retained as requested. Evidence: current
`ApprovalRuntimeService`, `PurchaseRequestApprovalSubject`, `PostingService`,
`UserAccessScopeService`, workflow builder, and the owner's F08 sprint brief.
This is a design contract, not a claim of accountant approval or production release.

## Scope and lifecycle

Draft → explicit Submit → pending Approval Instance → ALL required steps approved
→ Approved → explicit Accounting Posted transition.

Any required rejection → rejected expense; correct through authorized edit → explicit
Resubmit → **new instance**. Keep every old snapshot and decision. Do not edit an
approved/posted expense; define a separate controlled reversal/correction contract
before adding that action. No deletion of documents with approval or posting history.

No Site Expense model, migration, UI, seed transaction or posting method is introduced
by F08. Its builder configuration and Expense Category master alone are not a module.

## Required data and validation contract

| Data | Proposed validation / ownership |
|---|---|
| Expense Number | Server-generated unique reference; use existing document-number service |
| Expense Date | Required date; accounting-period policy confirmed by Finance |
| Employee / Submitted By | Optional/required employee policy to confirm; authenticated submitter is server-owned, never supplied as authority by browser |
| Project and Site | Required operational attribution; site must belong to project; enforce actor's current scope on reads and every write |
| Expense Category | Active master selection; required expense/project-cost account mapping, no guessed fallback |
| Supplier | Required for credit; paid merchant requirement to confirm; use existing Supplier identity/subaccount rules |
| Description | Required meaningful description |
| Payment Type | Confirm supported types: cash-paid, bank-paid, supplier-credit; separate selection from accounting status |
| Cash / Bank account | Required eligible payment account for paid types; absent for credit, never an arbitrary ledger ID |
| Amount and VAT | Define net/gross entry convention, precision, tax evidence, rounding and allowed adjustments with Finance; recompute server-side |
| Invoice / Receipt Photo | Private storage, size/MIME limits, authorized preview/download; requiredness by payment/type to confirm |
| Notes | Optional escaped text |
| Status | Server-owned lifecycle, no arbitrary posted/approved status accepted from a form |

Also preserve original requester, approval instance, submitted/approved/posted actor
and times, journal reference, VAT reference, project/cost-centre attribution and
concurrency/version controls. These are proposed next-sprint fields, not current tables.

## Approval adapter contract

1. Implement `ApprovalSubject` for a server-allowlisted `site_expense` type; do not let
   requests submit a PHP model class. Add module permission mapping before exposing routes.
2. Adapter defines scoped queries, an internal source-row mutex, submit/edit eligibility, immutable document snapshot,
   pending/rejected/approved transitions and workspace URL. Generic service owns steps,
   locks, decisions, retries, completion and audit. No duplicate approval engine.
   The mutex is acquired before ordinary database reads; authorization is reloaded
   after waiting. Do not expose the internal unscoped locking query as a read endpoint.
3. Configure workflow module `Site Expenses`, trigger `Expense Submitted`. The reference
   chain Site Supervisor → Project Manager → Finance Manager is **client example only**.
   The saved ordered required role/user steps determine actual actors. Do not expand
   reporting parents or infer a supervisor from a person's name.
4. Current F08 supports sequential required slots; one eligible role member per slot,
   or the configured explicit user. Multiple people required means multiple required
   slots. All required slots approve. No parallel groups are stored by the builder.
5. Snapshot resolved eligible users and configuration on Submit; recheck current active
   user, role assignment, permission and project/site scope on every decision. Requester
   and submitting editor cannot approve. Reject requires a reason. No delegation or
   timeout escalation is executed by F08.
6. Block invalid/no-actor workflows atomically. Existing sample amount limits and the
   `Create Accounting Entry` setting cannot simply be reused as executable rules: F08
   deliberately rejects these for PR. Site Expense must define explicit Finance-approved
   routing/posting policy and tests before enabling them. Do not edit seeded workflow
   rules silently to make submission succeed.
7. Reuse embedded approval panel + global My Approvals integration. Keep document-view,
   approval-history-view, approve, reject, configuration and posting permissions separate.

## Accounting contract — proposals requiring Finance confirmation

Current evidence: `app/Services/Accounting/PostingService.php` uses control codes Cash
1110, Bank 1120, Input VAT 1300, Accounts Payable 2100 and GRNI 2150. `payableAccountFor`
uses a supplier's active linked account or existing AP control fallback. Accounts and
balanced lines are validated by `createEntry`; posting rules' Auto Post flag can create
a draft journal instead of posting. VAT period locks and reversal helpers already exist.
There is **no** `postSiteExpense` method. Category account selection cannot be inferred
from the Material Expense 5200 constant or from the sample automatic posting rule.

| Candidate case | Proposed debit | Proposed credit | Decision still open |
|---|---|---|---|
| Cash-paid expense | Approved expense/project-cost account; valid recoverable input VAT separately | Selected allowed cash account | Petty-cash/custodian handling, settlement timing, proof and eligible accounts |
| Bank-paid expense | Same net expense + valid input VAT separation | Selected allowed bank account | Already paid vs payment instruction; bank evidence and reconciliation |
| Supplier credit | Expense/project cost + valid input VAT | Supplier AP subaccount/control | Prefer creating a linked existing Supplier Bill OR direct AP posting, **never both**; decide ownership of outstanding balance/payment |

These are proposals, not legal/tax guidance. No automatic input VAT claim without
Finance-approved evidence, treatment and period rules. Nonrecoverable VAT may belong
in cost; the policy must be confirmed. Do not use GRNI for a normal service expense;
received-stock purchases must remain in the existing PR/PO/GRN/AP F04 flow.

Posting implementation gates for next sprint:

- Full required approval is necessary but not alone permission to post. Explicitly
  authorize posting and scope, then lock expense → relevant accounting/period rows
  in a documented consistent order. A failed posting leaves no partial journal/VAT.
- Idempotent source-linked journal and VAT references, with tests for concurrent calls
  and retry after network failure. Never double count a linked Supplier Bill.
- Respect draft/review mode: do not label an expense Accounting Posted while its
  journal is still draft. Define how the later manual post synchronizes status.
- Preserve project/site/cost-centre on lines; define how Project Phase B aggregates
  actual posted cost and excludes drafts, reversals and duplicate supplier bills.
- Reuse PostingService validation, journal reversal and VAT-period checks; do not
  recreate account lookup or write ledger rows directly from controllers.

## Owner / accountant decisions needed before full module

Net vs gross entry and rounding; valid/recoverable VAT evidence; category-to-account
mapping; project/cost-centre mapping; payment account eligibility and petty cash;
employee reimbursement/advance settlement (not equivalent to paying a supplier);
credit expense vs Supplier Bill ownership; approval thresholds (if any); exact configured
role chain; explicit posting vs automatic posting; closed periods and backdating;
attachment requirements; reversal, cancellation and correction permissions.

## Acceptance gates for next sprint

Scope-negative GET/POST tests; required-role/no-self approval; rejection/resubmission;
private receipt authorization; decimal calculations; full approval before posting;
balanced entries; VAT closed-period refusal; repeat/concurrent posting; credit balance
and payment consistency; reversal/audit preservation; Project workspace drill-through
and cost totals. Retain regression suites for PR/P2P/F04/AP/AR and all existing workspaces.

Recommended next sprint: **FULL SITE EXPENSE MODULE + PROJECT PHASE B INTEGRATION**,
after the decisions above are approved. BOQ, equipment and payroll posting are separate
scope decisions, not implicit additions to this contract.
