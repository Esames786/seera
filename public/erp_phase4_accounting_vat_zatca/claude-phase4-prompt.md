# Claude Prompt - Build Phase 4 Accounting + VAT + ZATCA Foundation

We are now starting Phase 4 of the Construction ERP system.

Current status:
Phase 1: User, role, permission, hierarchy, approval workflow, activity logs.
Phase 2: Master setup: company, branches, departments, designations, projects, sites, warehouses, expense categories, suppliers, customers.
Phase 3: HR & Payroll foundation.

Now build Phase 4:
Accounting + VAT + ZATCA.

IMPORTANT:
Do not start Projects + Site Expenses, Inventory, Equipment, Mobile App, or advanced ZATCA production API integration yet.
Only build the Accounting/VAT/ZATCA admin web foundation, CRUD, seed data, and basic posting structure.
Use existing layouts, components, CSS, route naming style, and activity logging.
Do not break Phase 1, 2, or 3 tests.

Build:
1. Accounting Dashboard
2. Chart of Accounts CRUD with tree
3. Journal Entries CRUD with line items and post action
4. General Ledger screen
5. Accounts Payable bills and payments foundation
6. Accounts Receivable invoices and receipts foundation
7. VAT Management
8. ZATCA E-Invoicing foundation: UUID, QR, XML, digital signature status, clearance status, retry
9. Financial Reports screens: Balance Sheet, Profit & Loss, Trial Balance, Cash Flow
10. Project-Based Cost Centers
11. Automatic Posting Rules

Suggested controllers:
app/Http/Controllers/Admin/Accounting/
- AccountingDashboardController.php
- ChartOfAccountController.php
- JournalEntryController.php
- GeneralLedgerController.php
- AccountsPayableController.php
- AccountsReceivableController.php
- VatController.php
- ZatcaInvoiceController.php
- FinancialReportController.php
- CostCenterController.php
- AutoPostingRuleController.php

Suggested migrations:
- chart_of_accounts
- journal_entries
- journal_entry_lines
- supplier_bills
- supplier_bill_lines
- supplier_payments
- customer_invoices
- customer_invoice_lines
- customer_receipts
- vat_periods
- vat_transactions
- zatca_invoice_records
- cost_centers
- automatic_posting_rules

Seed:
- Standard chart of accounts
- Cost centers from existing branches/projects/sites
- Sample journal entries
- Sample supplier bills
- Sample customer invoices
- VAT period with sample transactions
- ZATCA invoice records with cleared/failed/draft statuses
- Auto posting rules for payroll, site expenses, inventory, and customer invoices

Permissions:
Add accounting modules:
- Accounting Dashboard
- Chart of Accounts
- Journal Entries
- General Ledger
- Accounts Payable
- Accounts Receivable
- VAT Management
- ZATCA Invoicing
- Financial Reports
- Cost Centers
- Auto Posting Rules

Actions:
view, create, edit, delete, approve, export, post, process, retry

Assign:
Super Admin all.
Finance Manager full accounting.
HR Manager can view payroll-related finance summary only if needed.
Project Manager view project cost reports.
Site Supervisor no accounting admin access.

Tests:
- Accounting dashboard loads
- Chart account create/update/delete
- Journal entry create with balanced lines
- Unbalanced journal rejected
- Journal post changes status
- Ledger screen loads
- Supplier bill create
- Customer invoice create
- VAT period screen loads
- ZATCA invoice record create/retry
- Cost center create
- Auto posting rule create
- All previous tests still pass

Commit message:
Build Phase 4 Accounting VAT and ZATCA foundation

Deliver:
commit hash, summary, files changed, routes, migrations, seed data, tests, known issues, screens to review.
