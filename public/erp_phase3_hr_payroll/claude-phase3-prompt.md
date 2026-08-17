# Claude Prompt - Build Phase 3 HR & Payroll Foundation

We are now starting Phase 3 of the Construction ERP system.

Current status:
Phase 1 and Phase 2 are completed and stable:
- Laravel 13.18
- MySQL
- Blade admin panel
- User management
- Role management
- Permission matrix
- Role hierarchy
- Approval workflow builder
- Activity logs
- Master setup: Company, branches, departments, designations, projects, sites, warehouses, expense categories, suppliers, customers

Now build Phase 3: HR & Payroll.

Do not start Accounting, Inventory, Equipment, ZATCA, Mobile App, or Site Expense module yet.
Only build HR & Payroll admin web screens.
Use the existing admin layout, sidebar, components, CSS, tables, forms, badges, filters, and route naming style.
Do not break Phase 1 or Phase 2.
Keep all existing tests green.

Build:
1. HR Dashboard
2. Employees CRUD
3. Employee Documents CRUD
4. Shifts CRUD
5. Attendance CRUD
6. Leaves CRUD
7. Overtime CRUD
8. Salary Structures CRUD with items
9. Payroll Runs create/process/approve
10. End of Service Benefits CRUD

Create migrations:
employees, employee_documents, shifts, employee_shift_assignments, attendance_records, leave_types, leave_requests, overtime_records, salary_structures, salary_structure_items, payroll_runs, payroll_run_items, end_of_service_records.

Seed:
20 employees, documents, 3 shifts, shift assignments, current week attendance, leave types, leave requests, overtime records, salary structures, one payroll run with items, one EOSB draft.

Add tests:
HR dashboard loads, employee CRUD, document create, shift create, attendance create, leave create, overtime create, salary structure create, payroll create/process/approve, EOSB create, and all Phase 1/2 tests still pass.

Commit message:
Build Phase 3 HR and Payroll foundation

Deliver:
commit hash, summary, files changed, routes, migrations, seed data, tests, known issues, screens to review.
