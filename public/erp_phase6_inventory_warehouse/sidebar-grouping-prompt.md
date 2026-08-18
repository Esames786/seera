# Claude Prompt - Improve Sidebar Grouping

The current sidebar is becoming too long after HR, Accounting, Projects, Site Expenses, and Inventory.

Improve sidebar UX.

Requirements:
1. Convert sidebar into clean grouped sections.
2. Groups should be visually separated.
3. Groups should be collapsible using lightweight vanilla JavaScript.
4. Active route group should stay open automatically.
5. Store collapsed/open state in localStorage.
6. Keep current dark sidebar theme.
7. Do not break route names.
8. Do not remove current links.
9. Coming Soon modules stay visible but less prominent.

Recommended groups:
- Main: Dashboard
- Administration: Users, Roles, Permission Matrix, Role Hierarchy, Approval Workflows, Activity Logs
- Master Setup: Company, Branches, Departments, Designations, Projects, Sites, Warehouses, Suppliers, Customers, Expense Categories
- Operations: HR & Payroll, Projects & Site Expenses, Inventory & Warehouse, Equipment & Vehicles
- Finance: Accounting Dashboard, COA, Journal Entries, Ledger, AP, AR, VAT, ZATCA, Reports
- Reports: HR Reports, Accounting Reports, Project Reports, Inventory Reports
- Settings: Company Settings, System Settings, Audit Logs

Visual improvements:
- Slightly lighter group header background.
- Chevron on right side.
- Active blue highlight only on current child link.
- Pending count badges where needed.
- Less long scrolling.
- Responsive mobile behavior.

After finishing:
php artisan test
npm run build
Confirm sidebar active states for Phase 1 to Phase 6.
