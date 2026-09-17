# Seera — September media batch (NR-01 to NR-34): implementation status

Companion to [client-requirements-2026-09-16.md](client-requirements-2026-09-16.md), which records what the client asked for in the 14 September voice notes and screenshots. This document records what was built in response, in what order, the default taken wherever the client left a gap, and what stays open for the client's decision.

Work ran on 18 September 2026 from commit `40d33b9` to `6bc7147` (stages A–F, five commits). Status values: **Done** (built and covered by a test), **Partly** (built with an item still open), **Decision needed** (not started until the client answers), **Deferred** (separate piece of work).

Test evidence: `tests/Feature/ClientChangeRequestsRound3Test.php` (12 scenarios, one per stage group, plus adjusted assertions in `AccountingTest`, `HrPayrollTest`, `DeploymentHardeningTest`, `ProductionBootstrapTest`). Full suite at `6bc7147`: 163 tests, 1,491 assertions, all passing. Client walkthrough: round 3 section of the review page (`public/client-review/`).

## Stage plan (as executed)

| Stage | Items | Commit |
|---|---|---|
| A. Access and quick fixes | NR-34, NR-32, NR-24, NR-22, NR-23, NR-19, NR-10 | `c7c4303` |
| B. Master data | NR-03, NR-04, NR-05, NR-01, NR-02, NR-06, NR-08, NR-09, NR-15, NR-25, NR-07 | `c7c4303` |
| C. Documents and leave | NR-12, NR-13, NR-11, NR-14, NR-17, NR-18 | `2a7b4a1` |
| D. Accounting | NR-30, NR-28, NR-29, NR-27, NR-31 | `cde7028` |
| E. Reports | NR-20, NR-21 | `6bc7147` |
| F. Marketing | NR-16 | `6bc7147` |
| Throughout | NR-33 | every stage |

## Item-by-item status

| ID | Client request (short) | Status | What was delivered / decision |
|---|---|---|---|
| NR-01 | Supplier city and projects | Done | `suppliers.city`; `supplier_projects` pivot with checkboxes on the supplier form; project page lists its suppliers. A supplier may serve several projects. |
| NR-02 | + New on Supplier Category and Nationality | Done | `lookup_values` table (types `supplier_category`, `nationality`) seeded with the values already in use; quick-create on both dropdowns; document subtypes are free text with suggestions. |
| NR-03 | Automatic employee codes per classification | Done | Blank code → `SP-001…` for Sponsorship, `FL-001…` for Freelancer (prefixes in `config/seera.php`, independent sequences). A typed code is still accepted. Renumbering on classification change is not done (kept stable for history). |
| NR-04 | Supplier rating red/amber/green | Done | `suppliers.rating` Green/Amber/Red, badge on list and page, list filter. Manual choice; no automatic formula. |
| NR-05 | Supplier bank details and allowed payment types | Done | Bank name, account name, IBAN, `allowed_payment_types` Cash/Bank/Both. Payment method recorded per payment (NR-28). Customers: rating only; customer bank fields were not requested explicitly. |
| NR-06 | Customer rating, overdue days, alert | Partly | Rating; overdue days/count/amount from unpaid invoices past due date; red alert on the customer page and Overdue column on the list. **Open:** alert recipients, channel and threshold. |
| NR-07 | Sites renamed to Locations | Done | Menu, titles and buttons say Locations / + Add Location; the underlying `sites` table and relations are unchanged, so attendance and stock are unaffected. |
| NR-08 | Office and site contacts on the customer | Done | `customer_contacts` (office or site, linked to a location) managed on the customer page. |
| NR-09 | Shared customer notes | Done | `customer_notes` with author and time, visible to anyone who may open the customer; delete by author or Super Admin. |
| NR-10 | Field-specific date rules | Done | No future date for joining date, document issue dates, attendance, bill/invoice dates, payment/receipt dates. Contract end, document expiry, due dates stay open. |
| NR-11 | Document subtypes (profession, licence class) | Done | `employee_documents.document_subtype` with suggestion list. |
| NR-12 | One document entry source, consistent expiry | Done | Section C removed from the employee form; `Employee::syncDocumentSummary()` fills the summary fields from the newest document per type after every save. |
| NR-13 | Edit and renew existing documents | Done | Saved documents are editable rows (number, subtype, dates) with Replace file; old file deleted after commit. |
| NR-14 | Document filters on the employee list | Done | `doc_type` and `doc_status` (expired / expiring / valid) filters on Employees. |
| NR-15 | Assigned employees on the project page | Done | Assigned Staff panel (name, designation, location, status). |
| NR-16 | Marketing leads and visit reports | Partly | New Marketing module: `marketing_leads`, `marketing_visits`, permission module `Marketing`, sidebar group, lead → assign → visit → follow-up → won/lost, convert to customer, manager visit report with quick ranges and CSV. Managers (Marketing approve) see all; staff see their own. **Open:** status names, who may close, visit attachments, reminders. |
| NR-17 | Leave types available, attachments | Done | `ProductionHrDefaultsSeeder` installs Annual 21 / Sick 30 / Urgent 5 / Unpaid 30; leave requests take a private attachment. Evidence rules per type not enforced. |
| NR-18 | Leave entitlement and balance | Partly | `employees.annual_leave_entitlement` (default 21); Leave Data panel with entitlement, used, pending, remaining for the year. **Open:** accrual, carry-forward, per-type balances. |
| NR-19 | Starred field saved empty (EOSB) | Done | Only Employee, Last Working Day and Last Basic Salary (> 0) are required and starred; other amounts default to 0. |
| NR-20 | Excel export | Done | CSV (UTF-8 with BOM, opens in Excel) for all six financial reports and the marketing visit report, same filters as the screen. XLSX not needed for the client's use; can be added later. |
| NR-21 | Report date presets | Done | `ReportPeriod` presets (this/last month, this/last quarter, year to date, this/last year, custom) resolved server-side and shown with their dates; VAT report gains date and status filters. Financial year start configurable (`SEERA_FINANCIAL_YEAR_START_MONTH`). |
| NR-22 | Separate Cash and Bank balances | Done | Cash in Hand and Bank Balance cards from their account groups. |
| NR-23 | Explain ZATCA Failed Invoices | Done | Card text explains: e-invoice records whose clearance attempt failed; links to the ZATCA screen with reason and retry. |
| NR-24 | Escaped label, ageing wording | Done | "Profit & Expense Trend"; "Payable Ageing" / "Receivable Ageing". |
| NR-25 | Inline edit of project classification | Done | Edit button beside + New opens the same dialog pre-filled and saves in place (quick-create component gained an edit mode). |
| NR-26 | Project and Site duplication | Decision needed | See open decisions. Locations renaming (NR-07) and + Add Location from the project page reduce the double entry meanwhile. |
| NR-27 | Expense account on bills | Done | Line selector shows "Default: 5200 - Material Expense" (and "Default: 4100 - Project Revenue" on invoices) with explanation; category-to-account mapping intentionally not automatic. |
| NR-28 | Payment account choices, payment method | Done | Payment form offers only the supplier's accepted channels, explains an empty list, records Payment Method (Cash / Bank Transfer / Cheque); receipts record the method too. |
| NR-29 | Payment purpose, advances, back-charges | Partly | Purpose list on supplier payments (bill payment, advance, salary on behalf, repair/back-charge, other) shown on the bill. **Open:** ledger treatment of advances and on-behalf payments. |
| NR-30 | Invoice / VAT / dashboard consistency | Done | Approval refuses (with guidance) when the posting cannot be made instead of leaving an approved document without journal and VAT; VAT screen shows draft output/input VAT separately. |
| NR-31 | Corrections after finalization | Done | Super Admin "Reopen for Correction" on approved bills/invoices without payments: reversing journal, VAT rows withdrawn from the open period, ZATCA record cancelled, document back to draft with the reason kept. Refused for finalized VAT periods or cleared invoices. |
| NR-32 | Activity visibility by hierarchy | Done | Dashboard and Activity Logs scoped to the user and the roles below theirs; Super Admin sees all. |
| NR-33 | Integrated testing | Done | Feature tests per item; walkthrough frames render the real code on demo data. |
| NR-34 | Assigned manager cannot see the project | Done | Project scope = user's project ∪ projects where the user is `manager_id`, for reading and writing. |

## Open decisions (not built until answered)

1. **NR-26 / NR-07 — are Project and Site the same thing?** Today a project has one or more locations and attendance, warehouses and stock are tied to a location. Merging them removes the ability to have two locations under one job. Proposed: keep both, and let a project be created together with its first location in one step. Confirm before any schema change.
2. **NR-29 — advances and on-behalf payments.** Recording the purpose is delivered; treating an advance as a receivable from the supplier, or an operator salary paid for the supplier as a back-charge, changes the ledger and needs the accountant's rule.
3. **NR-06 — customer alert channel.** Overdue days are shown; who receives an alert, by which channel, and after how many days.
4. **NR-18 — leave entitlement rule.** Delivered as a per-employee annual entitlement (default 21 days) counted in calendar days against approved Annual leave in the current year. Accrual, carry-forward and per-type balances need the HR rule.
5. **NR-16 — marketing statuses and roles.** Delivered as Lead → Visit → Follow-up with a manager report; confirm statuses, who may close a lead, attachments on visits and reminders.
6. **CR-08 (from round 1) — staged approval with two reporting lines** remains open from the earlier round.

## Deployment notes

- Migrations: `2026_09_18_000001_add_september_batch_master_and_hr_fields` and `2026_09_18_000002_create_marketing_tables` (additive; `php artisan migrate --force`).
- Seeders on an existing installation: `ProductionHrDefaultsSeeder` (leave types) and `MarketingModuleSeeder` (Marketing permissions for Super Admin and Marketing Manager). Both are idempotent and are also part of `ProductionBootstrapSeeder`.
- No asset rebuild needed for this round (no CSS/JS changes). Leave attachments are stored privately under `storage/app/private/leave-attachments`.
