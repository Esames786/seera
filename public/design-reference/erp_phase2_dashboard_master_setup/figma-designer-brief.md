# Figma Designer Brief - Phase 2

## Goal

Design the **Main ERP Dashboard + Master Setup** section for the Construction ERP system.

The client requirement is high-level and inspired by Saudi ERP systems. We should design standard ERP-style screens but keep them customized for construction operations, site staff, geo-fence attendance, site expenses, inventory, projects, suppliers, customers, VAT, and ZATCA.

## Style Direction

Use the same admin theme from Phase 1:

- Left sidebar navigation
- Top header with search and actions
- Clean cards
- Tables with filters
- Status badges
- Add/Edit/View/Delete actions
- Section-based forms
- Clear hierarchy and spacing

## Sidebar Structure

Dashboard

Administration
- Users
- Roles
- Permission Matrix
- Role Hierarchy
- Approval Workflows
- Activity Logs

Master Setup
- Company Profile
- Branches
- Departments
- Designations
- Projects
- Sites / Geo-Fence
- Warehouses
- Expense Categories
- Suppliers
- Customers

HR & Payroll
- Employees
- Attendance
- Shifts
- Leaves
- Payroll
- Documents / IQAMA

Accounting
- Chart of Accounts
- Journal Entries
- General Ledger
- Payables
- Receivables
- VAT Management
- Financial Reports

Projects
- Project Dashboard
- Project Budget
- Project Costing
- Site Expenses
- Budget vs Actual

Inventory
- Materials
- Stock In
- Stock Out
- Stock Transfers
- Low Stock Alerts

Equipment & Vehicles
- Equipment
- Vehicles
- GPS Tracking
- Maintenance
- Fuel Tracking

ZATCA E-Invoicing
- Invoices
- QR Code
- XML
- Clearance Status

Reports
Settings

## Important Notes

1. Master setup should not be treated as simple settings only. These are core ERP records.
2. Sites must include geo-fence fields because mobile attendance depends on them.
3. Expense Categories must include linked Chart of Account because approved site expenses will post automatically to accounting.
4. Suppliers and Customers must include VAT/CR fields because Saudi VAT/ZATCA invoicing depends on them.
5. Projects must include budget and project manager because cost tracking depends on them.
6. Warehouse can be branch-level or site/project-level.