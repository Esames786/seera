# Seera — September media batch (NR-01 to NR-34): implementation status

Companion to [client-requirements-2026-09-16.md](client-requirements-2026-09-16.md), which records what the client asked for in the 14 September voice notes and screenshots. This document records what is being built in response, in what order, the default taken wherever the client left a gap, and what stays open for the client's decision.

Work started 18 September 2026 against commit `40d33b9`. Status values: **Done** (built and covered by a test), **Partly** (built with an item still open), **Planned** (in the current sequence), **Decision needed** (not started until the client answers), **Deferred** (separate piece of work).

## Stage plan

| Stage | Items | Why this order |
|---|---|---|
| A. Access and quick fixes | NR-34, NR-32, NR-24, NR-22, NR-23, NR-19, NR-10 | Reported blockers and label defects; no schema decisions needed |
| B. Master data | NR-03, NR-04, NR-05, NR-01, NR-02, NR-06, NR-08, NR-09, NR-15, NR-25, NR-07 | Supplier/customer/project fields the client tests daily |
| C. Documents and leave | NR-12, NR-13, NR-11, NR-14, NR-17, NR-18 | One document source, renewals, leave setup and balances |
| D. Accounting | NR-30, NR-28, NR-29, NR-27, NR-31 | Consistency between invoices, VAT and dashboard; payment choices |
| E. Reports | NR-20, NR-21 | Exports and date presets |
| F. Marketing | NR-16 | New module, last because it depends on nothing above |
| Throughout | NR-33 | Every item ships with a feature test; this file is the acceptance log |

## Item-by-item status

| ID | Client request (short) | Status | What was delivered / decision |
|---|---|---|---|
| NR-01 | Supplier city and projects | Planned | |
| NR-02 | + New on Supplier Category and Nationality | Planned | |
| NR-03 | Automatic employee codes per classification | Planned | |
| NR-04 | Supplier rating red/amber/green | Planned | |
| NR-05 | Supplier bank details and allowed payment types | Planned | |
| NR-06 | Customer rating, overdue days, alert | Planned | |
| NR-07 | Sites renamed to Locations | Planned | |
| NR-08 | Office and site contacts on the customer | Planned | |
| NR-09 | Shared customer notes | Planned | |
| NR-10 | Field-specific date rules | Planned | |
| NR-11 | Document subtypes (profession, licence class) | Planned | |
| NR-12 | One document entry source, consistent expiry | Planned | |
| NR-13 | Edit and renew existing documents | Planned | |
| NR-14 | Document filters on the employee list | Planned | |
| NR-15 | Assigned employees on the project page | Planned | |
| NR-16 | Marketing leads and visit reports | Planned | |
| NR-17 | Leave types available, attachments | Planned | |
| NR-18 | Leave entitlement and balance | Planned | |
| NR-19 | Starred field saved empty (EOSB) | Planned | |
| NR-20 | Excel export | Planned | |
| NR-21 | Report date presets | Planned | |
| NR-22 | Separate Cash and Bank balances | Planned | |
| NR-23 | Explain ZATCA Failed Invoices | Planned | |
| NR-24 | Escaped label, ageing wording | Planned | |
| NR-25 | Inline edit of project classification | Planned | |
| NR-26 | Project and Site duplication | Decision needed | See open decisions |
| NR-27 | Expense account on bills | Planned | |
| NR-28 | Payment account choices, payment method | Planned | |
| NR-29 | Payment purpose, advances, back-charges | Partly planned | Purpose recorded; advance accounting stays open |
| NR-30 | Invoice / VAT / dashboard consistency | Planned | |
| NR-31 | Corrections after finalization | Planned | |
| NR-32 | Activity visibility by hierarchy | Planned | |
| NR-33 | Integrated testing | Throughout | |
| NR-34 | Assigned manager cannot see the project | Planned | |

## Open decisions (not built until answered)

1. **NR-26 / NR-07 — are Project and Site the same thing?** Today a project has one or more sites (locations) and attendance, warehouses and stock are tied to a site. Merging them removes the ability to have two locations under one job. Proposed: keep both, rename Sites to Locations, and let a project be created together with its first location in one step. Confirm before any schema change.
2. **NR-29 — advances and on-behalf payments.** Recording the purpose is delivered; treating an advance as a receivable from the supplier, or an operator salary paid for the supplier as a back-charge, changes the ledger and needs the accountant's rule.
3. **NR-06 — customer alert channel.** Overdue days are shown; who receives an alert, by which channel, and after how many days.
4. **NR-18 — leave entitlement rule.** Delivered as a per-employee annual entitlement (default 21 days) counted in calendar days against approved Annual leave in the current year. Accrual, carry-forward and per-type balances need the HR rule.
5. **NR-16 — marketing statuses and roles.** Delivered as Lead → Visit → Follow-up with a manager report; confirm statuses and who may close a lead.
