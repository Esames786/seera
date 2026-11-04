# Go-live reset: removing test records before the client starts real entry

Command: `php artisan seera:go-live-reset` (dry run) / `php artisan seera:go-live-reset --execute --force` (production). Added on 4 November 2026 on `feature/seera-go-live-reset-2026-11-04` (base `a3316be`, the approved production commit). It does not migrate, seed, deploy or touch ZATCA.

## What the client asked for

Keep the setup they made (master data, chart of accounts, the Super Admin and every user account with its role and permissions) and remove all the test entries so that real entry can start from a clean system.

## What is kept, always

| Area | Tables |
| --- | --- |
| Login accounts, roles, permissions | `users`, `user_roles`, `roles`, `permissions`, `role_permissions`, `role_reporting_parents` |
| Organisation | `branches`, `departments`, `designations`, `company_profiles` |
| Accounting setup | `chart_of_accounts` (with opening balances as entered), `cost_centers`, `automatic_posting_rules`, `vat_periods`, `zatca_egs_units` |
| Masters and settings | `lookup_values`, `payment_terms`, `units`, `item_categories`, `expense_categories`, `leave_types`, `shifts`, `approval_workflows`, `approval_workflow_steps`, `project_classifications`, `document_sequences` (counters restarted, see below) |
| Framework | `migrations`, `cache`, `sessions`, `jobs`, `failed_jobs`, `job_batches`, `password_reset_tokens` |

## What is removed, always

Every business document and posting: journal entries and lines, customer invoices, receipts, adjustment notes, credit allocations and refunds, customer notes, supplier bills, payments and GRN matches, purchase requests, purchase orders and attachments, goods receipts, stock issues, transfers, adjustments, ledger entries and warehouse balances, site expenses and receipts, attendance, leave, overtime, payroll runs, items, settlements and bank exports, end-of-service records, project budgets, equipment assignments, documents, fuel and maintenance logs, marketing leads and visits, supplier–project links, approval instances, notifications, activity logs, VAT transactions and ZATCA invoice records / submissions.

## Entry groups: removed unless `--keep`

| Group | Tables | Note |
| --- | --- | --- |
| `customers` | `customer_contacts`, `customers` | |
| `suppliers` | `suppliers` | |
| `employees` | `employees`, `employee_documents`, `employee_shift_assignments`, `salary_structures`, `salary_structure_items` | Keeping employees keeps their documents, shifts and salary structures; payroll runs still go |
| `projects` | `projects`, `sites` | Users linked to a removed project / site are unlinked (their accounts stay) |
| `inventory` | `items`, `warehouses` | Requires `--keep=projects` (warehouses belong to projects and sites) |
| `equipment` | `equipment_assets` | Operator / project links are cleared when those groups are removed |

Example keeping the employee register and the project list: `php artisan seera:go-live-reset --execute --force --keep=employees,projects`.

## Safeguards

- Dry run by default: prints the plan (rows per table to empty, rows per table kept) and changes nothing.
- `--execute` asks the operator to type the database name; a wrong answer changes nothing. `--force` is required in production (Laravel's environment confirmation).
- Every table in the schema must be classified in the command as protected or removable; an unknown table stops the command before anything is deleted (this is how a new module is noticed).
- All deletes run in one transaction; afterwards the command scans every foreign key of the database for dangling references (SQLite `PRAGMA foreign_key_check`, MySQL `information_schema`) and rolls back if any is found.
- Document number sequences restart at 1 so the first real invoice / PO / voucher does not continue the test numbering; `--keep-sequences` leaves them alone.
- Uploaded files (employee documents, expense receipts, PO attachments, equipment documents, customer-note attachments) are **not** deleted; the command lists their storage folders for manual review.
- ZATCA device units keep their invoice counter and last hash; clear them only with the ZATCA onboarding owner (production ZATCA stays disabled).

## Production procedure (cPanel host)

```bash
cd ~/seera
# 1. Backup first and verify it restores (owner gate: no reset without a verified backup).
mysqldump --single-transaction "$DB_NAME" > ~/backups/seera-before-go-live-$(date +%F-%H%M).sql
# 2. Dry run: read the two tables carefully.
/opt/cpanel/ea-php83/root/usr/bin/php artisan seera:go-live-reset
# 3. Execute (type the database name when asked). Add --keep=... if the client wants to retain a group.
/opt/cpanel/ea-php83/root/usr/bin/php artisan seera:go-live-reset --execute --force
# 4. Log in as the Super Admin, open Dashboard, HR, Projects, Finance and Inventory lists: empty, no errors.
# 5. Remove the listed storage folders only if they hold nothing but test uploads.
```

Verification on 4 November 2026: `GoLiveResetCommandTest` (6 tests: every table classified, dry run changes nothing, wrong confirmation changes nothing, execute empties the business tables and keeps the protected ones with the Super Admin still able to open the dashboard, `--keep=employees,projects` retains those registers with their salary structures, `--keep=inventory` without projects is refused). The dry run was also printed against the isolated synthetic UAT fixture, and the execute path was exercised against a disposable MySQL copy of the local development database: 745 rows deleted, 13 login accounts, 25 accounts, 14 roles and 24 lookup values kept, sequences restarted, no user left linked to a removed project / site / warehouse, `information_schema` orphan scan clean. The same copy first contained tables of another application, which the command refused to touch until they were removed; on MySQL the schema listing is pinned to the current database for exactly that reason.
