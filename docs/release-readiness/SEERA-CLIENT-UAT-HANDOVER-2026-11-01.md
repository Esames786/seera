# SEERA — core ERP client UAT and handover pack
**Prepared:** 1 November 2026  
**Reviewed integration branch:** `release/seera-core-integration-review-2026-11-01`  
**Reviewed HEAD:** `e1c03474104df480369805e32b2ca4eab22c8dd2`  
**Application tree:** `c19943f9cb8df2dfe3dae30eee6031b736f3a777` (the final commit contains QA evidence)  
**Decision today:** GO for controlled staging / client UAT; **NO-GO for unconditional production deployment**.

## Verified development evidence — not client acceptance

The 1 November integration review records **839/839 Laravel tests, 10,227 assertions**, **12/12 JavaScript tests**, **20 focused Supplier tests / 267 assertions**, and **117/117 Supplier browser checks**. Critical browser navigation/mobile/financial scenarios initially had **115/116 passes**, with the failed receipt case caused by an incorrect cash-account/bank-transfer pair in the *browser harness*. The corrected targeted transaction/refusal/reconciliation checks then passed **4/4**; separate read-only GL/AR/VAT reconciliation passed **11/11**. These results use isolated synthetic/local data, not an approved staging upgrade, live balances or client UAT.

All **28** owner scenarios in `SEERA-FULL-BUSINESS-UAT-MATRIX-2026-10.md` began as **NOT RUN** and no signed client results for them have been supplied. Never relabel developer automation as UAT PASS.

## Short owner demonstration — 15 to 25 minutes

| Segment | Connected, user-friendly journey | Acceptance to record |
| --- | --- | --- |
| Login, roles, Arabic | Login, navigation, role restriction, English↔Arabic, 390px layout | Core actions clear; unauthorized role blocked |
| Employee | Employee View (read-only) → Edit/Manage → account link → leave/attendance/payroll history | No silent record updates; HR/Payroll data role-gated |
| Customer / AR | Customer View/Edit → linked projects → invoice → Approve/Post → receipt → journal/VAT | Stored amounts match; posted edit refused |
| Supplier / procurement | Supplier View/Edit → quick-create category/terms/account → PR approvals → PO → GRN → two-stage supplier bill → partial/full payment | Submit values stable; GRNI/AP/VAT and partial balances reconcile |
| Project and site | Project View → team/sites → expense → BOQ/Budget → Project Reports/Cost Report | Project-level scope; totals from posted accounting, no duplicate costs |
| Equipment | Asset → assignment → fuel/maintenance/docs → posted costs; show GPS readiness notice | Real GPS provider integration not claimed |
| Reports and handover | HR Reports/Project Reports and bilingual screens, hand over open-decision register | Scope and acceptance owner recorded |

For every demo record, capture the role, environment, selected record IDs (non-secret), screenshots, actual results, any defect ID, and the business approver.

## Two-day closure sequence

**Day 1 — staging and owner walkthrough:** freeze reviewed source; confirm actual server/staging revision; test backups + restore on isolated environment; run quick smoke (auth, roles, Arabic, Employee/Customer/Supplier/Project/Equipment/HR Reports/Project Reports, invoice posting/receipt). Client HR/Finance/Procurement execute highest-priority journeys from the 28-case UAT matrix. Gather precise defects with screenshots, not a new scope wishlist.

**Day 2 — reconcile and handover:** close any P0/P1 defects in a *separate* reviewed fix branch, repeat targeted checks plus integrated suite as appropriate, reconcile opening/ending control balances with Finance, test physical Android Chrome and iPhone Safari geofence/offline/reconnect, review critical Arabic financial wording and invoice/QR/print. Record owner sign-off or a documented conditional handover with named pending items and target owners.

No deployment is implied by a two-day development-closure goal. Where testing cannot be performed, record **BLOCKED/NOT RUN**, not PASS.

## UAT priority mapping

| Priority | Cases from original 28-case matrix | Acceptance owner |
| --- | --- | --- |
| P0 — identity and permissions | UAT-01, 02, 03, 04, 05, 06 | Owner / HR |
| P0 — finance/procurement | UAT-09, 10, 11, 12, 14, 15, 16, 17, 18, 27, 28 | Finance / procurement / HR |
| P1 — operational and UX | UAT-07, 08, 13, 19, 20, 21, 22, 23 | Department owners |
| Device-dependent | UAT-24, 25 | Android + iOS device owners |
| ZATCA nonproduction only | UAT-26 | Finance/ZATCA owner |

Use the full matrix for detailed steps and expected amounts. Preserve signed results by scenario; do not invent acceptance results.

## Known deferred or owner-dependent matters

- **Production ZATCA stays disabled** until the official SDK, company-specific simulation, seller identity/credentials and VAT-rounding decision are signed. Sandbox dummy-identity successes are not production certification.
- Finance must choose **VAT-rounding Option A/B**. Sales zero-rated/exempt/out-of-scope classifications need a company supply/exception decision; current controlled 15%/Super Admin override is not a substitute for classifications.
- Real vehicle/equipment telematics GPS remains provider-dependent; mobile attendance GPS/offline must be validated on real Android/iOS devices.
- GOSI/WPS/Mudad statutory integration needs separate Finance/HR policy and registrations; do not represent a generic bank export as certified.
- Arabic-speaking business reviewer must sign off critical wording. Some controller messages, payment-term suffixes outside Supplier forms and other lower-priority copy may remain.
- Any undecided client requirements/approval routing/HR calendar/advances/alerts remain in the existing change register and require explicit scope disposition.

## Acceptance record — copy for each scenario

```text
UAT ID:
Test date/time:
Environment + deployed revision:
User role + anonymized test records:
Preconditions / test steps:
Expected result:
Observed result + finance figures:
Status: PASS / FAIL / BLOCKED / NOT RUN
Screenshot / ledger evidence:
Defect ID / severity / fix revision (if any):
Tester:
Department approval: PENDING / SIGNED (name/date)
Owner sign-off: PENDING / SIGNED (name/date)
```

## Handover sign-off

**Core ERP operational acceptance:** PENDING.  
**Arabic business-language acceptance:** PENDING.  
**Physical Android/iOS acceptance:** NOT RUN.  
**Staging restore rehearsal:** NOT RUN.  
**Real Finance balance reconciliation:** NOT RUN.  
**Production deployment authorization:** NOT GIVEN.  
**Production ZATCA enablement:** NOT AUTHORIZED.

Delivery package to provide after sign-off: exact release SHA, QA and UAT evidence, non-secret deployment runbook/checklist, known-issues list, rollback decision tree, asset checksum, user guide, role contacts and signed owner disposition of exclusions. Never attach real credentials, CSIDs, private XML, passwords or live personal data to the repository.
