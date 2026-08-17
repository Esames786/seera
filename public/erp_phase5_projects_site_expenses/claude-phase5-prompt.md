# Claude Prompt - Build Phase 5 Projects + Site Expenses Foundation

We are now starting Phase 5 of the Construction ERP system.

Current status:
Phase 1: User, role, permission, hierarchy, approval workflow, activity logs.
Phase 2: Master setup: company, branches, departments, designations, projects, sites, warehouses, suppliers, customers, expense categories.
Phase 3: HR & Payroll foundation.
Phase 4: Accounting + VAT + ZATCA foundation.

Now build Phase 5:
Projects + Site Expenses.

IMPORTANT:
Do not start Inventory, Equipment, Vehicle Tracking, or full Mobile App yet.
Only build admin web screens, CRUD, seed data, approval workflow connection, and accounting posting preview/foundation.
Use existing layouts, components, CSS, route naming style, permissions, and activity logging.
Do not break previous phases.
Keep all existing tests green.

Main goal:
Build project execution and site expense management foundation for a Saudi construction ERP.

Build:
1. Project Dashboard
2. Projects listing and enhanced project details
3. Project Budget / BOQ
4. Project Milestones
5. Project Cost Tracking
6. Site Expenses CRUD
7. Expense Attachments / invoice photo foundation
8. Expense Approval Trail
9. Expense Payments / Settlements
10. Daily Site Reports
11. Accounting Posting Preview / create journal from approved expense
12. Project Reports

Suggested controllers:
app/Http/Controllers/Admin/Projects/
- ProjectDashboardController.php
- ProjectController.php
- ProjectBudgetController.php
- ProjectMilestoneController.php
- ProjectCostTrackingController.php
- SiteExpenseController.php
- SiteExpenseApprovalController.php
- SiteExpensePaymentController.php
- DailySiteReportController.php
- ProjectReportController.php

Suggested migrations:
- project_budgets
- project_budget_lines
- project_milestones
- project_cost_entries
- site_expenses
- site_expense_attachments
- site_expense_approvals
- site_expense_payments
- daily_site_reports
- daily_site_report_photos

Use existing projects and sites from Phase 2; do not duplicate project master if already exists.
Extend project details only if needed through additive migrations.

Site expense statuses:
- draft
- submitted
- pm_approved
- finance_approved
- approved
- rejected
- sent_back
- posted
- cancelled

Posting statuses:
- not_required
- not_posted
- ready_to_post
- posted
- failed

Approval flow:
1. Site Supervisor submits expense.
2. Project Manager approves/rejects/sends back.
3. Finance approves/rejects.
4. Approved expense becomes ready to post into accounting.
5. Posting creates journal entry using Phase 4 accounts:
   Debit: expense category linked account
   Debit: input VAT
   Credit: cash/bank/petty cash/accounts payable

Seed:
- 3 projects with budgets
- BOQ/budget lines
- Milestones
- Project cost entries
- 20 site expenses with different statuses
- Attachments placeholders
- Approval trails
- Payments/settlements
- Daily site reports
- Posting preview data

Permissions:
Modules:
- Project Dashboard
- Projects
- Project Budgets
- Project Milestones
- Project Cost Tracking
- Site Expenses
- Expense Approvals
- Expense Payments
- Daily Site Reports
- Project Reports

Actions:
view, create, edit, delete, approve, reject, send_back, export, post, process

Assign:
Super Admin all.
Project Manager full project and approval permissions.
Finance Manager expense finance approval, payment, and posting permissions.
Site Supervisor create/view own site expenses and daily reports.
HR Manager project labor cost view only if needed.
Mechanic no admin access for this phase.

Tests:
- Project dashboard loads
- Project listing loads
- Project budget line create/update works
- Project milestone create/update works
- Site expense create works
- Site expense attachment record create works
- PM approval changes status
- Finance approval changes status
- Reject and send back work
- Approved expense becomes ready to post
- Posting creates accounting journal entry
- Daily site report create works
- Project cost tracking screen loads
- Project reports screen loads
- All previous tests remain passing

Commit message:
Build Phase 5 Projects and Site Expenses foundation

Deliver:
1. Commit hash
2. Summary of completed work
3. Files created/modified
4. Routes added
5. Migrations added
6. Seed data added
7. Tests result
8. Known issues
9. Screens to manually review first
