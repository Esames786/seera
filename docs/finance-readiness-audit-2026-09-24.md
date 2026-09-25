# Seera Finance readiness audit and proposed acceptance gate

Audit date: 24 September 2026. Task B of the owner's next-development sprint. Source baseline inspected: `2d0f9d5`, branch `feature/seera-connected-workspaces-2026-09-23`. Task A proceeds independently; Finance code is **not changed by this audit**.

## 1. Decision summary

Finance has real implemented transaction and reporting foundations, not just menus. AP bills, AR invoices, manual journals, payment/receipt records, VAT rows and stock-accounting hooks exist. However, **do not accept the complete Finance module for operational use merely on those foundations**. Important reconciliation, failed-posting, permission/scope, duplicate-payment, closed-period and workflow gaps remain.

The proposed **30 September 2026 milestone is a bounded Finance acceptance demonstration**, conditional on an agreed scenario list and critical fixes. It is not a commitment that all Finance, payroll integration or live statutory integration will be complete in six days. If the blockers below cannot be closed and independently verified, deliver the evidence/gap review on September 30 and move transaction acceptance to the first available October gate. Do not silently lower the acceptance standard.

Recommended next workspace: **Supplier/AP first, after its financial correctness gate**, then Customer/AR. The GRN/AP control-account mismatch is a higher-priority issue than rearranging supplier screens. A supplier workspace can expose bills, payments and GRNs in one context, but must not conceal the missing allocation/reconciliation link or turn Save into Approve/Pay.

## 2. Evidence and limits

- Read completely: `backend-handoff-august-september-2026.md`, `client-feedback-2026-09-24.md`, and `remaining-modules-connected-workspaces-2026-09-24.md`.
- Inspected local controllers, services, models, routes, migrations, middleware and the named test assertions below. Paths and line numbers refer to this Finance source baseline; Task A/C may subsequently shift shared route/middleware lines.
- **No Finance tests were executed by this source-audit task.** “Tested” below means existing test-source coverage, not a fresh passing result. The earlier 215-test checkpoint is historical evidence from the handoff, not this audit's result. Use the main sprint's actual execution log separately.
- No production database, credentials, network integration, payment, migration, seed, correction or deployment was used. No claim is made about present production balances.
- **Client-accepted is not established for any complete Finance journey.** Earlier accepted individual fields/screens are context, not accountant sign-off on a reconciled end-to-end accounting scenario. Each table row explicitly keeps this distinction.
- Expected numbers below are synthetic accounting test examples using an assumed 15% rate, not tax/legal guidance. VAT policy and statutory acceptance need the accountant and the appropriate specialist; this is a source audit, not a legal-compliance certification.
- Initial working tree included the user's modified `public/build.zip`, private client-media folders and pre-existing untracked research documents. They are not inputs to a Finance deployment package and must not be indiscriminately staged.

## 3. Area-by-area readiness

| Area | Implemented | Tested (existing source only) | Client-accepted | Missing / confirmed risk | Blocked by decision |
|---|---|---|---|---|---|
| Chart of Accounts and cost centers | CRUD/tree, opening balances, used-account deactivation, account ledger and cost-center records. `ChartOfAccountController:49,84,97,118`; `CostCenterController:49,59,92`; `ChartOfAccount:46` | `AccountingTest:108,144,446`; `DeploymentHardeningTest:157` checks balanced synthetic openings | Complete setup/reconciliation sign-off not evidenced | Posted-history classification/opening values remain directly editable; account selectors validate existence, not all active/type/cost-center invariants; wider hierarchy cycle protection needs tests | Freeze policy for account code/type/normal side/opening balances once used; opening date and signed opening schedule |
| Manual journal / GL | At least two usable balanced lines; explicit Post; posted edit/delete restrictions; posted-only GL default; line filters. `JournalEntryController:55,109,155,219`; `GeneralLedgerController:18` | `AccountingTest:155,178,196,211,230,248` | Not evidenced | GL page/date opening defect F06; journal-header scope and stale-write races F07/F11; no generic posted-journal reversal UI, posted cancel explicitly blocked | Draft/approved/post separation; period locks; correction authority and reversal date |
| AP bills / partial payments | Draft lines/totals, supplier terms, row-locked approval, linked payable subaccount, payment channel validation, row-locked remaining-balance check, payment total refresh. `AccountsPayableController:54,145,266`; `SupplierBill:79`; `PostingService:67,157` | `AccountingTest:257,303,326`; `ClientChangeRequestsRound2Test:136`; `ClientChangeRequestsRound3Test:549,595` | Individual setup changes recorded, full AP scenario not evidenced | Failed/draft posting divergence, replayed partial payments, changeable payable mapping, no payment reversal route; GRN liabilities absent from AP bill register (F01/F02/F04/F10) | All-required approver configuration; advances/on-behalf; payable mapping changes; payment correction/allocation |
| AR invoices / receipts / ageing | Draft lines/totals; approval; row-locked receipt with current Customer channel check; outstanding balance/status; current ageing buckets and overdue summary. `AccountsReceivableController:58,135,239`; `CustomerInvoice:80`; `AccountingDashboardController:103`; `Customer:72` | `AccountingTest:339,382`; `ClientFeedbackSeptember22Test:133`; Round3 correction test `:595` | Customer View/read-only and channel requirements are established; end-to-end reconciliation not evidenced | Same posting/replay issues as AP; no receipt reversal/credit-note path; current rather than historical-as-of ageing; customer master opening total is not current AR total | Opening receivable migration; credit-note/refund treatment; ageing as-of semantics; approval actors |
| Cash / Bank | Posting debits/credits accounts `1110`/`1120`; active account/channel validation, dashboard posted balances, cash movement report. `PostingService:157,283`; `AccountingDashboardController:30,60`; `FinancialReportController:151` | AP/AR payment assertions above; financial report smoke/CSV assertions below | Not evidenced | Only hardcoded Cash/Bank control codes supported in settlement selector; no bank reconciliation, statement import or cash transfer workflow; date-range opening and null-journal risk | Multiple bank accounts, cash custodian controls, reconciliation, advance/on-behalf ownership and accounts |
| VAT calculation / periods / corrections | Server calculation; approved-document VAT rows; draft forecast separate; period recalculation/finalize; Super Admin reasoned reopen for unpaid/unsettled, uncleared documents; reversing journal and VAT withdrawal. `PostingService:515,549,561,590`; `VatController:15,72,87`; AP reopen `:190`; AR reopen `:173` | `AccountingTest:404`; `ClientChangeRequestsRound3Test:595` covers open-period reopen/reapproval, not all closed-period races | Not evidenced | Finalized/submitted period insertion not blocked; finalized recalculation allowed; zero-rate base omitted; no period-creation/submission/credit-note routes; rate override policy not enforced; corrections delete VAT rows (F03/F09) | Default 15% with privileged override: exceptions, reasons, permission, effective date; period boundaries, late entry and credit-note policy |
| Inventory to accounting | GRN: stock + inventory/input VAT/AP journal; issue: expense/inventory; adjustment: gain/loss; transfer stock ledger at source cost. Stock rows and posted documents locked; average-cost valuation. `GoodsReceiptController:138`; `StockIssueController:132`; `StockAdjustmentController:132`; `StockService:25,61,190`; `PostingService:315,389,452` | `InventoryTest:270,315,328,339,377,404,463,509,547`; `ClientChangeRequestsTest:370` discount/line-VAT case | Not evidenced | No GRN-to-bill linkage; potential double AP/input VAT, incomplete PO receipt bounds, nullable/draft journal flag, transfer in-transit reconciliation (F04/F05) | Receipt accrual vs supplier invoice policy, invoice matching/price variance, input VAT recognition, returns and in-transit treatment |
| Reports / trial balance | TB, P&L, balance sheet, cash movement, VAT-period and project-cost reports; CSV; date presets; openings. `FinancialReportController:33,77,108,151,205,246,307` | `AccountingTest:484,497`; `ClientChangeRequestsRound3Test:655`; `DeploymentHardeningTest:157` | Not evidenced | Range opening, incomplete filter application, gross instead of net project reversal effects, export permission and scope concerns (F06/F07); no assertion that every report reconciles after corrections | As-of versus period semantics, dimension allocation, export roles, opening cutover |
| Approval authorization / runtime | Route permission mapper separates view/create/edit/delete/approve/post/process/reject/etc; workflow-definition records and steps; direct approve handlers. `EnsureUserHasPermission:135`; `ApprovalWorkflow`, `ApprovalWorkflowStep`; PR controller `:125` | `InventoryTest:194,226,239`; `DeploymentHardeningTest:45,79` covers selected denial/scope cases, not all-required runtime | **Owner already confirmed all required approvers must approve**; runtime not accepted/delivered | Workflow definitions are not evaluated by AP/AR/PR/PO/payroll approval; no request-step decision ledger/snapshot; stored posting-rule approval_required not consumed (F08) | Order, actor resolution, thresholds, rejection/resubmission, delegation, self-approval and existing pending request treatment; do NOT ask whether all required approvers must approve again |
| Payroll to Finance | HR payroll processing/approval and immutable approved-run checks; profile allowance fallback. `PayrollRunController:104,152,170` | `HrPayrollTest:358,398`; `ClientFeedbackSeptember21Test:270` tests calculation, not GL | HR field/calculation work recorded; payroll-to-GL not evidenced | No call from payroll approval to PostingService/JournalEntry, no payroll payment journal endpoint; salary account constants/rule seed do not implement integration | September inclusion or defer; salary expense/payable split, deductions/advances, cost allocation, accrual date, bank payment and reversal |
| ZATCA | Local record, UUID, QR string, claimed XML path, statuses/hash and failed-to-pending retry. `PostingService:483`; `ZatcaInvoiceController:45` | `AccountingTest:339,421,437`: record/field/state assertions only | No live integration acceptance | No actual signed XML generation/upload/clearance client/job in traced path; `signed` is assigned as a string; retry only changes DB state, not live requeue; no compliance evidence | Separate confirmed integration scope, sandbox/production onboarding, credentials/certificates, invoice types and specialist acceptance |

## 4. High-priority source findings

These findings are supported by the paths below, but exploit/concurrency examples are **source-derived scenarios not newly executed reproductions**. Add regression tests before changing behavior. Severity describes acceptance impact, not a production incident claim.

### F01 — Posting success and financial-document success can diverge (critical acceptance blocker)

`PostingService::createEntry` (`:611`) computes debit/credit, and only sets posted when a matching active rule enables auto-post **and** totals balance (`:626`). Otherwise it returns a **draft entry**, including an unbalanced one; it does not reject the imbalance. AP/AR approve (`AccountsPayableController:145`, `AccountsReceivableController:135`) reject only a null entry, then set the document unpaid and create VAT. Missing input/output VAT accounts with a positive rate therefore can leave unpaid documents and VAT rows beside an unbalanced draft journal. Deliberate review-mode draft journals also need truthful state/UI handling: AR currently announces “posted” regardless.

`storePayment` AP `:314` and `storeReceipt` AR `:280` assign `journal_entry_id => $entry?->id`, then refresh the balance. Null posting may still settle the subledger. GRN/issue/adjustment set `accounting_posted` from `(bool) $entry`, not `entry.status === posted` (`GoodsReceiptController:182`, `StockIssueController:186`, `StockAdjustmentController:173`). Stock may be finalized while Finance is absent/draft.

Required resolution: agreed draft-review versus posted semantics; reject missing/unbalanced account mappings atomically, and require a valid entry whenever the business event requires accounting. Preserve explicit posting permissions and do not silently force every rule to auto-post. Add missing VAT/control account, inactive/type-invalid account, rule-off and transaction rollback tests.

### F02 — Payment/receipt locks prevent overpayment, not duplicate partial-payment retries (high)

Both handlers lock the bill/invoice and recheck remaining balance; this is useful existing protection. However every successful request creates a fresh payment/receipt. Neither accepts a durable idempotency key nor has a unique replay reference in `2026_08_17_000002_create_accounting_tables.php` (supplier payments `:131`, customer receipts `:190`). Example: a partial payment of 100 retried on a 1,150 bill is accepted twice while balance permits. A disabled UI button cannot prevent transport retry/replay.

Need idempotency token + unique constraint within the same transaction, stable replay response, legitimate same-amount subsequent payment test, and real isolated MySQL concurrent-writer tests. New durable keys may require additive migrations; do not retroactively deduplicate actual production payments without accountant reconciliation.

### F03 — VAT period finalization does not seal new entries (high)

`recordVat:532` inserts into `periodFor:549`, which returns the first date-containing period without status or overlap checks. `VatController::recalculate:72` refuses only submitted, not finalized periods. Finalize recalculates/updates without locking the period against concurrent posting. A late invoice can therefore change the underlying rows of a finalized/submitted period while stored report totals lag. `withdrawVat:590` blocks correction in finalized/submitted periods, but does not solve insertion/finalization races.

Need a consistent closed-period guard and lock shared by every VAT-bearing entry path, explicit late-period behavior, no/overlapping-period handling, and tests for closed-period approval, finalize/post races, correction refusal and unchanged totals. No generic GL fiscal-close lock was located in the posting paths.

### F04 — GRN and AP bill are independently posting the same liability/tax (critical reconciliation gate)

`PostingService::postGoodsReceipt:315` debits inventory/input VAT and credits supplier AP. `postSupplierBill:67` independently debits its line expense/asset and input VAT and credits AP. `SupplierBill` fields/schema have no purchase-order/GRN link; GRN only stores invoice_number text, PO FK and own journal (`2026_08_17_000003_create_inventory_tables.php:131`). There is no matching/allocation/clearing handoff in the audited controllers.

Consequences: a posted GRN creates a GL payable/input VAT without a payable bill balance in the AP register; manually entering the vendor bill for those goods can duplicate AP and VAT. AP payment UI targets a bill and cannot simply settle the unmatched GRN liability. Decide an accrual/receipt/invoice model and reconcile before making purchasing-to-pay acceptance claims. This is not solved by adding a Bills tab.

### F05 — PO/GRN input integrity and financial flags need hardening (high)

`GoodsReceiptController::validated:203` checks independent existing IDs, accepts submitted unit_cost/ordered_quantity, and caps accepted quantity only against received quantity. `postStock:175` increments the **first PO line with that item**; it does not enforce PO status, PO supplier/warehouse match, specific PO-line membership or remaining quantity under a PO-line lock. Two legitimate draft GRNs can exceed the same remaining order, and repeated same-item PO lines are ambiguous. Form dropdown limits are not server proof.

Stock service itself locks warehouse stock and blocks negative issues (`StockService:61,190`); retain that foundation. GRN/issue/adjustment accounting flags have F01's draft/null problem. Transfer dispatch removes source and later receive adds destination without an in-transit GL movement; reconcile pending transfers explicitly rather than expecting warehouse totals alone to equal the inventory asset at every instant.

### F06 — Reports can show convincing but unreconciled numbers (high)

- `GeneralLedgerController:43-57`: paginates then initializes running balance from static opening each page. Prior pages and pre-from-date posted movement are not added. Page two's first running balance and filtered-period opening can be incorrect.
- `FinancialReportController::movements:307` adds static account openings to only the selected-range movement. Balance sheet/cash closing for a midyear range omits prior-range movements unless an explicit period-only interpretation is intended. `cashFlow:155` likewise uses original opening.
- `projectCostReport:253` sums only expense debit and revenue credit, not debit-minus-credit/net credit. Reversals consequently do not undo reported costs/revenue. Bill/invoice summary sums there are not date filtered.
- `applyEntryFilters:369` applies status/date only. `movements` separately applies dimensions, but cashFlow and projectCostReport do not apply all advertised cost-center/project/site controls; branch appears in options with no matching application in these routines.
- VAT report sums stored totals for whole overlapping periods, not exact-range VAT transactions (`vatReport:205`). It can be valid as a **period report**, but must not be presented as exact arbitrary-date movement. Stored totals need controlled freshness.
- Supplier and Customer list total cards sum opening balances (`SupplierController:46`, `CustomerController:45`) whereas AP/AR lists sum transaction outstanding. Those labels/definitions need reconciliation, not silent arithmetic changes.

Add numeric assertions across two pages, pre-period opening, posted + reversal, dimensions, inactive accounts, openings and CSV equivalence. A response 200 or a “Totals” string is not report correctness.

### F07 — Finance scope and export authorization are incomplete (high)

`AppServiceProvider:68` globally scopes JournalEntryLine, AP/AR and settlements, **not JournalEntry, VatPeriod/VatTransaction, ChartOfAccount or CostCenter**. `JournalEntryController:24,87,155` uses unscoped headers for list/show/post. A restricted actor granted Journal Entries can reach headers outside their project even if lines are filtered. Write middleware checks top-level location/employee fields, not nested journal line project/site IDs (`EnsureRequestWithinScope:20`); an injected top-level allowed project does not validate the nested line dimensions.

Furthermore AP/AR posting does not stamp every control/VAT/payment line with project/site (`PostingService:119,132,180,267,297`), so scoped ledger balance may exclude one side even where the underlying document was permitted. VAT screens read unscoped party names/totals if that module is granted. Opening balances are company-level while posted movements may be restricted.

`FinancialReportController::wantsCsv:393` uses `?export=csv` on the same GET route; route permission mapping falls through to view (`EnsureUserHasPermission:171`) and does not require Financial Reports export. Confirm intended policy, then add direct URL negative tests. This is separate from Task C's display/catalogue correction.

Do not broadly remove scopes to balance reports. Decide whether full ledger/VAT/statutory reports are company-only or implement properly scoped and dimensionally balanced views. Add same-role/two-project header, posting, VAT party disclosure, nested-write and CSV denial tests.

### F08 — All-required approval runtime is absent; posting configuration overstates behavior (high)

`ApprovalWorkflow` and `ApprovalWorkflowStep` have definition metadata (including is_required, actor, order and amount limit), but source references lead to definition CRUD, dashboard counts and role views, not runtime AP/AR/PR/PO/payroll evaluation. `PurchaseRequestController::approve:125` directly writes approved_by/status, so one actor holding approve can finalize. PO approve (`:159`) and payroll approve (`:152`) follow the same direct pattern. PR reject writes one rejection reason; this is not the configured multi-parent decision history.

`AutomaticPostingRule` stores debit_account_id, credit_account_id, cost_center_rule and approval_required, but `PostingService::createEntry:621` consumes matching module/event/status and **auto_post only**; account lines remain hardcoded/chosen upstream. Do not tell an accountant those other configuration controls are enforced.

All-required is already decided. Remaining design must define actor configuration/snapshot, order (parallel or sequential), quorum failure/absence, own-request approval, reject/send-back/resubmit revision, delegation/escalation, and migration of pending requests. Until an approved runtime is implemented, label current behavior single-action approval; do not accept it as meeting all-required approval. Definition screens and Role action correction cannot close this gap.

### F09 — VAT zero-rate base and rate controls (high/policy-dependent)

`PostingService::recordVat:528` returns null for VAT amount <= 0. A zero-rate taxable transaction's base is consequently absent from this VAT transaction report. AP/AR/PO/GRN accept rates 0..100 in ordinary create/edit validators (`AccountsPayableController:361`, `AccountsReceivableController:306`, `GoodsReceiptController:212`). There is no coordinated Super-Admin-only override gate/reason in those paths.

Confirm tax category/rate exceptions, zero-rated/exempt representation, default rate, authorized override, effective-date and rounding basis (AP/AR per-line versus GRN aggregate rounding). Tests must distinguish a zero VAT amount from a transaction that should be excluded. No retroactive tax-rate rewrite is proposed.

### F10 — Mapping, opening and correction lifecycle gaps (high)

Supplier bill/payment posting resolves the supplier's **current** active linked payable account (`PostingService:55,157`), not the originally credited bill account. Reassigning/deactivating it between bill and payment can debit another liability account, leaving the first unmatched. User-selected active payment account is validated earlier than the AP bill lock; supplier policy is not reloaded/locked there as the newer AR handler does.

COA update accepts opening/type/normal side/code changes even after postings (`ChartOfAccountController:84,118`), affecting historical report presentation. Supplier/customer opening figures do not generate balancing journals/allocations in their CRUD paths. “Advance”/“on behalf” purpose labels still post ordinary Dr AP / Cr Cash-Bank against a bill; they are not an advance asset or third-party clearing subsystem.

Reopen refuses any payments/receipts and tells the user to reverse them first, but no AP payment/AR receipt reversal endpoint is present in `routes/web.php:206-216`. No credit-note endpoint was found there either. `reverseEntry:561` always force-posts a reversal, even if the referenced entry was draft; reopening a review-mode draft-origin invoice/bill needs a different tested path to avoid creating a real reverse effect for an unposted original.

### F11 — Approval locks do not protect all stale edits/deletes (high, concurrency hypothesis)

AP/AR/Journal update and destroy check route-bound isEditable/status **before** their write transaction or deletion. Approvals/posts reload a locked record; competing older updates can still use a draft object and replace lines/header after the other request finalized it. PR/PO/payroll approval has no equivalent locked state recheck. Reproduce in an isolated MySQL test database before calling concurrency safe; SQLite transaction success does not establish these interleavings. Add locked reload/expected-version/state transition protection across all mutating paths after confirming the policy.

### F12 — Payroll and ZATCA are separate integration scopes, not finished hooks

`PayrollRunController:104-167` processes payroll items/totals then stamps approval. No GL/payment integration is called. Salary account constants in PostingService do not create accounting effects.

`PostingService::createZatcaRecord:483-510` base64-encodes pipe-separated values, assigns a path/string status and hashes local values. It does not create signed invoice XML or prove an external response. `ZatcaInvoiceController::retry:45` changes state/count/message; there is no dispatch of a clearance job in that handler. **Neither “signed” nor “pending” proves live transmission or compliance.**

## 5. State/effect contract to accept explicitly

| Operation | Present source effect | Required gate / expected accepted effect |
|---|---|---|
| Save AP/AR draft | Lines/totals only; no posting/VAT | No journal, VAT or balance-sheet effect; safe edit with ownership/version checks |
| Approve AP/AR | Journal returned, VAT recorded, unpaid; AR local ZATCA foundation record | All configured required approvals before finalization; balanced entry in truthful draft/posted state; never “posted” without posted GL |
| Manual Post | Locked journal status becomes posted if balanced | Authorized, correct scope, valid accounts/dimensions, period open; repeated Post no duplicate |
| Payment / Receipt | New settlement + journal attempt + outstanding refresh | Exactly once for same operation key; correct original control account and channel; no balance reduction without required accounting |
| GRN Post / issue / adjustment | Stock ledger + stock change + accounting attempt | Atomic required financial effect or explicit recoverable unposted state; receipt limits under lock; AP/input VAT counted once |
| Transfer dispatch / receive | Source out, then destination in | Same item/value; pending transit explicitly reconciled; retries no duplicate movements |
| Reopen / correction | Super Admin only; reason; unpaid/unsettled restriction; reverse journal and withdraw VAT | Preserve original posted history; correct date/period; never reverse an unposted original into live GL; policy for settled/cleared docs via controlled separate process |
| VAT Finalize | Recalculate stored totals, status finalized | Immutable period cohort, locked against concurrent/backdated insertion; correction governed, no silent recalculation |
| PR/PO approval | Direct one-actor status update, no GL | All-required workflow once designed; no premature final status or PO conversion |
| Payroll approval / ZATCA retry | HR status / local ZATCA status only | Do not claim payroll journal or external clearance; accept only explicitly scoped delivered integration |

## 6. Proposed minimum Finance acceptance pack for September 30

Owner/accountant must approve inclusions, expected accounts and actors by **25 September**. Run on an isolated staging database with a synthetic company, zero opening balances unless stated, and a documented active COA/posting-rule setup. Nothing here authorizes seeding/resetting production.

| ID / essential scenario | Expected ledger, VAT, balance/report result | Gate / additional tests |
|---|---|---|
| FIN-01 COA/opening/manual journal | Balanced opening Dr Bank 10,000 / Cr Equity 10,000; manual draft excluded from posted GL; explicit posting adds exactly those amounts; TB Dr=Cr and balance sheet A=L+E | Approved cutover mechanism; no double-count of direct opening plus opening journal. Reject imbalance, inactive/type-invalid accounts, missing mandatory dimensions and outside-scope IDs |
| FIN-02 Standalone AP service bill | Draft net 1,000, VAT 150, gross 1,150: no GL/VAT. Accepted final approval Dr Expense 1,000, Dr Input VAT 150, Cr correct AP 1,150; bill outstanding 1,150 | F01/F08/F09/F11 resolved or explicitly out of operational scope; verify generated lines, status, source and VAT count, not only response code |
| FIN-03 AP partial/full payment | Pay 400: Dr same AP 400 / Cr Bank 400; outstanding 750. Pay 750: outstanding 0. No new VAT on settlement | Duplicate first operation key has no second effect; independent equal amount allowed when valid; overpayment/wrong channel/missing ledger map rejected atomically; ledger AP net zero |
| FIN-04 AR invoice/receipts | Net 2,000, VAT 300, gross 2,300: Dr AR 2,300 / Cr Revenue 2,000 / Cr Output VAT 300. Receive 500 then 1,800: Dr Cash/Bank / Cr AR; balances 1,800 then zero; no additional VAT | Customer Cash-only rejects Bank at endpoint, actual current policy checked; repeated receipt replay has one effect; local ZATCA record is labelled foundation only |
| FIN-05 Combined cash/TB/report tie | With FIN-02..04 only and zero openings, net Bank/Cash +1,150; Expense Dr1,000; Input VAT Dr150; Revenue Cr2,000; Output VAT Cr300; AP/AR zero. TB debits=credits=2,300; P&L profit1,000; A1,300 = L300 + E1,000 | If FIN-01 opening added, Bank becomes11,150 and Equity includes10,000; verify all routes and CSV, with old and new dates, page2 and dimension filters |
| FIN-06 VAT open/finalized/correction | FIN-02 input150; FIN-04 output300; payable150. Separate unpaid AP example reopened: original untouched, reversal neutralizes it; old VAT withdrawn only under agreed open-period policy; reapproval counts new VAT exactly once | Block finalized/submitted late insertion/correction; zero-rate100 base handled by agreed category; unauthorized override refused; rounding expected values signed off |
| FIN-07 Ageing | Open invoices due today /31/61/91 days overdue placed in agreed buckets with correct residual balances; settled/draft invoices excluded | Define no-due-date and exact boundaries, timezone and as-of date; current balances are not historical aged balances |
| FIN-08 Procurement reconciliation (conditional inclusion) | 10 units at100 -> inventory1,000 and appropriate liability; inputVAT150 once under agreed recognition; issue3 at100 -> Dr Material Expense300 / Cr Inventory300; stock7/value700 | **Blocked by F04 model decision**. AP bill/payment must reconcile to that same purchase without second liability/VAT. Concurrent/over-receipt, wrong supplier/PO-line, stock failure and accounting rollback tested |
| FIN-09 Approval and permission | First of two required approvals leaves pending; final required approval alone finalizes once; unauthorized/out-of-scope actor cannot read or act; reject/resubmit/version prevents old approval use | Configuration/order/revision decisions required; current runtime does not pass. If not delivered, exclude dependent operational approval acceptance rather than call current one-actor behavior compliant |
| FIN-10 Recovery/correction | Cancel draft has no posted GL effect; posted history immutable; permitted unpaid reversal net-zero; finalized VAT/cleared/settled documents are refused until proper correction process exists | Test null/draft/unbalanced journal, payment reverse absence, late timeout replay and stale edits. Accountant reviews reversal date across month boundary |

**Minimum sign-off evidence:** named owner + accountant, chosen actors/permissions, input/output screenshots, source document IDs with journal lines and VAT references, exported TB/GL/subledgers, one negative/direct-endpoint and one replay case per action, MySQL race results, EN/AR labels and narrow/mobile operation checks. Use synthetic data. Each exception has an explicit owner/date and either a blocking classification or a signed scope exclusion.

## 7. Dates, dependencies and next three batches

Dates below are **planning targets conditional on scope/decisions and test outcomes**, not guaranteed capacity estimates.

1. **24-25 September — close immediate identity/Employee patch and permission catalogue patch separately.** Task A View/Edit/Deactivate + safe linked navigation, then Task C shared actions and sticky matrix. Finance audit/acceptance scope reviewed in parallel. Dependencies: source tests green, no permission disclosure, owner/accountant names and FIN scenario decisions. No Finance redesign in these patches.
2. **26-30 September — bounded Finance correctness sprint / evidence gate.** Prioritize F01/F02/F03/F06/F07/F11 with tests for standalone AP/AR/manual GL, and reconcile posting-rule behavior. On September25 estimate approved remediation volume; if scope exceeds available days, September30 is the agreed audit/demo with blockers, **not operational acceptance**. All-required runtime, GRN/AP and VAT-policy decisions can block particular journeys; owner must explicitly select inclusion/defer, not implicitly waive a confirmed requirement. Present Finance patch plan before code changes.
3. **1-16 October — approved Supplier/AP workspace + required functional dependencies, then Customer/AR.** First resolve receipt/bill liability model and account mapping/settlement corrections needed by the selected workspace; implement approved all-required workflow in its own schema/runtime patch. Supplier pilot target7-9October only if dependencies green; Customer pilot target12-16October. Keep View read-only, record identity fixed, drafts independent and Approve/Pay/Receive/Post explicit. Do not implement either pilot from this proposal alone.

**17-31 October:** remaining agreed Finance gaps, payroll-to-GL if commissioned, Arabic/RTL, permission/concurrency/report regression, documentation and release freeze by development target **31 October**. Any new statutory integration or large approval/accounting policy scope must be estimated independently; if it does not fit, escalate with revised scope/date instead of assuming.

**November:** staged client UAT, accountant scenario sign-off, defects and retesting. **December:** approved opening-data import/reconciliation, training/walkthrough, access review, backups/restore drill and cutover rehearsal. **01 January 2027 target go-live:** conditional on signed UAT, reconciled openings/stock/AP/AR/GL, operational controls and statutory obligations resolved. Missing payroll or statutory capability cannot simply be labelled optional if the actual go-live business needs it.

Explicit September deferrals unless the owner approves a revised plan: live ZATCA onboarding/transmission/compliance work; payroll accrual/disbursement integration; comprehensive bank reconciliation/multi-bank; advance/on-behalf clearing; settled-document credit notes/refunds; all-module workspace conversion; operational Phase5/Phase7. GRN/AP and all-required approval are **known blockers for journeys that require them**, not features silently declared delivered by deferring their implementation.

## 8. Decisions to bring to owner/accountant (without repeating settled questions)

1. Which FIN scenarios are the September30 minimum, and who signs each? Is it a demonstration or authority to transact? No percentage claim is useful until this denominator is agreed.
2. All required approvers must approve: **confirmed**. Decide exact approvers, thresholds, order, no-actor behavior, self-approval, delegation, rejection/resubmission and legacy-pending migration.
3. Should financially approved docs produce posted entries immediately, or review-mode journals with an explicit unposted operational state? Which configured posting-rule fields are contractual and need real enforcement?
4. GRN vs supplier invoice: when liability/input VAT is recognized, matching policy, accrual/clearing account, price variance, returns and partial invoicing. Avoid duplicate payable/VAT.
5. VAT default/override/category/reason/effective-date rules, rounding, period close/late entries/credit notes. A configuration field is not sufficient policy enforcement.
6. Cash/Bank channels versus individual bank accounts; duplicate operation key ownership; payment reference uniqueness, advance/on-behalf and account-mapping changes.
7. Posting period, opening cutover, dimension scope, exact balance-sheet/cash/ageing report semantics and export permission. Do restricted users get scoped reports or company-only finance views?
8. Payroll-to-GL included when? Approve/accrue/pay dates, deductions/payables and allocation. No payroll journal currently exists in the examined workflow.
9. Live ZATCA required for which go-live invoice flows? Separate specialist/sandbox acceptance and credential access process; current local record state is not live integration.
10. Walkthrough format/audience/language: staging-only synthetic flow; do not record private payroll, credentials or imply unimplemented effects.

## 9. Schema, routes and regression map

- Accounting schema: `database/migrations/2026_08_17_000002_create_accounting_tables.php`: COA `:11`, journals `:37`, AP bills `:77`, supplier payments `:131`, AR invoices `:145`, receipts `:190`, VAT periods `:203`, VAT transactions `:219`, ZATCA `:238`, posting rules `:257`. Decimal precision exists, but there is no payment operation-key constraint or runtime-approval decision schema here.
- Inventory schema: `2026_08_17_000003_create_inventory_tables.php:51` stock unique item/warehouse, `:96` PO, `:131` GRN, `:153` GRN lines; no GRN-to-supplier-bill FK. Discount/VAT additions: `2026_09_07_000001_add_commercial_fields_to_purchase_orders.php`.
- Master commercial links: `2026_09_09_000001_add_project_classifications_payment_terms_and_linked_accounts.php`; payment metadata: `2026_09_18_000001_add_september_batch_master_and_hr_fields.php`; Customer channel: `2026_09_23_000001_add_customer_channels_and_type_catalog.php`.
- Workflow definition schema: `2026_07_02_000005_create_workflow_and_log_tables.php:11,25`; user actor extension `2026_07_02_000007_add_builder_fields_to_workflow_steps.php:12`. These are definitions, not per-request approval instances/decisions.
- Routes: `routes/web.php:102` auth/active/password/permission/scope group; accounting `:195-238`, payroll `:186-188`, PR `:266-268`. Permission catalogue/role form Task C does not itself fix any accounting runtime.
- Numbering: `DocumentNumberService:9` transaction-based sequence; accounting tables have unique journal/invoice numbers. Sequence safety is separate from payment idempotency and full business-operation retries.
- Existing tests to rerun after approved Finance fixes: AccountingTest, InventoryTest, DeploymentHardeningTest, ClientChangeRequestsTest/Round2/Round3, ClientFeedbackSeptember22Test, HrPayrollTest and ClientFeedbackSeptember21Test where integration touches payroll. Add targeted negative/state/replay/race/reconciliation tests listed above rather than treating present smoke tests as exhaustive.

## 10. Audit handoff / implementation boundary

This document is the Task B deliverable. No Finance application change, new migration, seeder, deployment command or commit is prescribed as already completed. Task A and Task C test/build/commit evidence belongs to their separate sprint handoff. Review this Finance scope with owner/accountant before authorizing a Finance patch or connected workspace; retain existing transaction histories, scopes and accepted screens while repairing the specific gaps.

## 11. Verification addendum (25 September, implementation engineer)

Re-checked against source before adopting this audit as the Task B deliverable:

- `PurchaseRequestController::approve` writes `approved`/`approved_by` directly; `PayrollRunController::approve` changes status only; `ZatcaInvoiceController::retry` only updates the local record; `ApprovalWorkflow` models are referenced by their CRUD controller, the dashboard and the role form, never by an approve handler. F08 and F12 stand.
- `VatController::recalculate` refuses only `submitted` periods and `finalize` sets the status without sealing later entries; `PostingService::periodFor` selects by date alone. F03 stands.
- `PostingService::postGoodsReceipt` credits the supplier's payable account and `postSupplierBill` credits it again for a manually entered bill; no GRN-to-bill link exists. F04 stands.
- `PostingService::createEntry` returns a draft, possibly unbalanced, entry when no auto-post rule matches. F01 stands.

Test evidence for this checkpoint (SQLite in-memory, full suite): 215 tests / 2,030 assertions at `2d0f9d5` with Codex's uncommitted Task A patch; 225 tests / 2,160 assertions after the Task A tests were added. No Finance behaviour was changed in this sprint; the audit's findings remain open items for the 26–30 September correctness sprint once the owner and accountant confirm the FIN scenario list.

## 12. Finance correctness sprint outcome (26 September, implementation engineer)

| Finding | Status after sprint | Commit | Evidence |
|---------|--------------------|--------|----------|
| F01 atomic posting | Resolved: posting refuses unbalanced, missing or inactive accounts as a whole; document and journal succeed or fail together; manual journals refuse inactive accounts | `b850190`, `c90391f` | `FinancePostingIntegrityTest` (7), FIN-01/02 |
| F02 idempotency | Resolved: hidden per-form `idempotency_key`, unique column, lookup under the document lock; replay answers with the existing settlement | `5fb4cae` | `SettlementIdempotencyTest` (4), FIN-03/04 |
| F03 VAT period lock | Resolved: any VAT-bearing posting, manual VAT-account line, reopen or recalculate into a finalized/submitted period is refused; finalize locks and rechecks | `fd7ea4a` | `VatPeriodLockTest` (7), FIN-06 |
| F04 GRN vs bill | **Open, decision needed** | — | FIN-08 BLOCKED |
| F05 PO/GRN input integrity | Open (out of this sprint) | — | — |
| F06 GL / report balances | Resolved: ledger opening = account opening + prior movement, running balance continues across pages, balance sheet/trial balance cumulative, P&L period-only, project cost net of reversals, CSV = screen | `1b95db7` | `FinanceReportBalancesTest` (3), FIN-05 |
| F07 scope and export | Resolved: journal headers scoped through lines; company-only openings and VAT screens; CSV export needs the export right; manual journal lines confined to the user's projects/sites | `d1f77b5` | `FinanceScopeAuthorizationTest` (4), FIN-09 (permission half) |
| F08 approval runtime | **Open, configuration decision needed** | — | FIN-09 BLOCKED |
| F09 zero-rate / rounding policy | Open (policy) | — | FIN-06 note |
| F10 mapping / correction lifecycle | Partly: reopen reversal verified; payment reversal still absent | — | FIN-10 |
| F11 stale edit / delete locks | Resolved: edit, delete and cancel reload under lock and refuse a changed status | `c4fc7e0` | `FinanceStateTransitionTest` (3) |
| F12 payroll / ZATCA | Open (separate integration scopes) | — | — |

The FIN-01 … FIN-10 execution record is `docs/finance-acceptance-2026-09-26.md`.
