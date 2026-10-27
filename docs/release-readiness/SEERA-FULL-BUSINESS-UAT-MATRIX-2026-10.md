# Owner business UAT pack — focused first pass

26 October 2026; target baseline `aca78ee`. This is the first priority slice of full business UAT, not a claim that all scenarios have been performed. Use an independently authorised staging/training environment with synthetic data. Never create sample payments or payroll in real customer balances.

For EVERY row below the initial **actual result / status / evidence / tester / date / owner approval** is **not recorded / NOT RUN / none / unassigned / not run / PENDING**. Developer tests are supporting evidence, not client sign-off. After testing, copy the result record below for each ID. A failed prerequisite means BLOCKED, not PASS.

| ID | Role / prerequisite | Steps | Expected result |
| --- | --- | --- | --- |
| UAT-01 | Superadmin; training accounts | Log in, open dashboard, log out; try a bookmarked protected page | Protected page requests login; normal session navigation works |
| UAT-02 | Restricted Project A user; A and B records | View A; attempt B's direct URL and forged child ID | B's records and financial/private fields are refused server-side |
| UAT-03 | HR; synthetic employee | Open View, Edit, employment/pay/documents/leave/shifts; add authorised master | View does not edit; related data stays employee-bound; master selected without losing draft |
| UAT-04 | HR; employee draft | Change a field; Back/Next/Close; choose Keep editing, then Discard; test save intents | Warning preserves or discards as chosen; save succeeds before navigation; unrelated drafts not silently lost |
| UAT-05 | Account administrator; unlinked employee | Search employee, create account, set role explicitly; search again | Details copied once; linked employee/account shown; no duplicate or automatic permission grant |
| UAT-06 | HR; linked test employee | Deactivate employee, inspect history/account | Employee history remains; separate account state follows explicit existing policy |
| UAT-07 | Customer editor | Edit profile, type + New, contacts, notes, rating and Cash-only channel; return to View | Saved details persist; View read-only; forbidden bank receipt refused |
| UAT-08 | Supplier editor | Edit profile, Back and Save & Close; inspect bills/payment panels | Navigation stays in context; profile save never pays or approves a bill |
| UAT-09 | Requester + configured approvers | Create PR, submit, attempt later-step/self approval, reject/correct/resubmit, complete required steps | No skipped required approval; preserved decision history; no financial posting just from profile save |
| UAT-10 | Procurement/store; approved request/PO | Order 10 units at 100; receive 6; try receiving more than remaining | Six units received; four remain; over-receipt refused; GRNI accrual, no VAT/payable at receipt |
| UAT-11 | Two bill approvers + Finance; UAT-10 | Match 6 received units at 100 + 15% VAT; first approval, final approval | Before final approval no bill journal/consumption; after posting GRNI clears 600, Input VAT 90, AP 690 |
| UAT-12 | Finance; posted UAT-11 bill | Pay 200 then 490; repeat exact request/reference; inspect supplier balance | AP 490 then 0; payments not duplicated; no extra VAT/inventory expense |
| UAT-13 | Store; stocked item, two warehouses | Issue, transfer/receive and attempt excessive issue; inspect ledgers | Stock/valuation agree; transit state clear; no duplicate or forbidden negative stock |
| UAT-14 | Finance; training customer | Invoice net 1,000 + VAT 150; approve; receive 300 then 850 | AR 1,150 then 850 then 0; revenue/VAT once; receipts affect bank/AR only |
| UAT-15 | Finance; paid invoice | Credit note gross 230; apply 100 to another open invoice; refund 130; reverse refund | Credit 230 → 130 → 0 → 130; AR reconciliation holds; note/XML unchanged by settlement |
| UAT-16 | Finance; review-mode refund posting rule | Attempt refund, then inspect journal/credit | Refund refused atomically; no new draft refund journal or consumed credit; no silent rule override |
| UAT-17 | Finance; unposted source journal / closed VAT period | Try credit resolution; try posting into sealed period | Source/period controls refuse without partial writes |
| UAT-18 | HR + Finance; agreed salaries/period | Process payroll, review/approve/post, pay, retry, reverse settlement | Draft has no posted GL; expense once; payable clears/restores; history and salary visibility protected |
| UAT-19 | Site supervisor + approvers | Cash/bank, reimbursement and supplier-credit expenses with photos | Each path follows configured workflow; reimbursement distinct; one supplier bill, no duplicate expense/payable |
| UAT-20 | Project manager + Finance | Finalise BOQ, revise; inspect material/labour/expense/equipment actuals and PO commitments | Frozen version preserved; actuals tie to GL; commitment not double-counted as actual |
| UAT-21 | Equipment operator + Finance | Assign asset, fuel, two open repairs; complete one then the other; inspect costs | Scope/meter rules hold; under-maintenance until last repair closes; cost posted once |
| UAT-22 | English/Arabic users | Open key forms in both languages; Back/save/errors; inspect desktop/mobile RTL | Labels understandable; no clipped controls or unexpected English-only core workflow |
| UAT-23 | Finance; test invoice/note | Print English/Arabic A4, scan QR, test longer document | No clipped totals; stored amounts match; QR works; long documents paginate acceptably |
| UAT-24 | Android Chrome test user/site | Allow/deny GPS, inside/outside fence, offline check-in/photo expense, reconnect and retry | Correct refusal/evidence; capture time retained; one server record per operation |
| UAT-25 | iPhone Safari/PWA test user | Repeat UAT-24; suspend/resume, switch user with queued records, storage failure | No lost/foreign queue submission; visible recoverable errors; no native-app claim |
| UAT-26 | ZATCA administrator; nonproduction credentials only | Inspect readiness, signed identity, unknown/retry state, access to private XML | No production calls; no HTTP-only false acceptance; identity retained on retry; external gates explicit |
| UAT-27 | Finance | Reconcile journal, AR/AP, inventory/GRNI, VAT, payroll and project totals after scenarios | Every variance explained and approved; no hidden balancing entry |
| UAT-28 | Ordinary purchasing user and Super Admin; agreed R22-02 policy | Start ordinary-role PO at 15%; attempt crafted non-default header/line rate; compare authorised admin override | Ordinary change refused; authorised exception audited. **Known development gap at this baseline:** current diagnostic allows 5%; do not mark this accepted |

Result record (one per scenario):

```text
UAT ID:
Environment and commit:
Role and test records:
Preconditions satisfied:
Actual result:
Status: PASS / FAIL / BLOCKED / NOT RUN
Screenshot or evidence reference:
Tester:
Test date:
Defect ID if any:
Business owner approval: PENDING / SIGNED (name, date)
```

For the first owner session, prioritise UAT-03/04, 10–12, 14–18 and 27. Schedule physical-device and Arabic/print review separately; do not mark them passed from desktop emulation.
