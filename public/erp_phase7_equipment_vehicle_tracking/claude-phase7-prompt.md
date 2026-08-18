# Claude Prompt - Build Phase 7 Equipment & Vehicle Tracking Foundation

We are now starting Phase 7 of the Construction ERP system.

Current status:
Phase 1: Access Control
Phase 2: Master Setup
Phase 3: HR & Payroll
Phase 4: Accounting + VAT + ZATCA
Phase 5: Projects + Site Expenses
Phase 6: Inventory & Warehouse

Now build Phase 7: Equipment & Vehicle Tracking.

IMPORTANT:
Do not start Mobile App, live GPS provider integration, barcode/IoT engine, or advanced route replay yet.
Only build admin web screens, CRUD, seed data, assignment flow, GPS status foundation, maintenance/fuel tracking, costing, and accounting posting foundation.
Use existing layout, grouped sidebar, route naming, permissions, activity logging, and components.
Do not break previous phases. Keep all existing tests green.

Build:
1. Equipment Dashboard
2. Equipment & Vehicles CRUD
3. Equipment Details
4. Project / Site Assignments CRUD
5. GPS Tracking Overview
6. Maintenance Jobs CRUD
7. Fuel Logs CRUD
8. Equipment / Vehicle Documents CRUD
9. Operators / Drivers assignment
10. Utilization & Downtime
11. Project Equipment Costing
12. Accounting Posting foundation
13. Equipment Reports

Suggested controllers:
app/Http/Controllers/Admin/Equipment/
- EquipmentDashboardController.php
- EquipmentAssetController.php
- EquipmentAssignmentController.php
- GpsTrackingController.php
- MaintenanceJobController.php
- FuelLogController.php
- EquipmentDocumentController.php
- OperatorAssignmentController.php
- EquipmentUtilizationController.php
- EquipmentCostingController.php
- EquipmentReportController.php

Suggested migrations:
- equipment_categories
- equipment_assets
- equipment_assignments
- equipment_gps_locations
- maintenance_jobs
- maintenance_job_parts
- fuel_logs
- equipment_documents
- operator_assignments
- equipment_utilization_logs
- equipment_cost_entries

Use existing employees, projects, sites, inventory items, cost centers, chart of accounts, and posting rules where possible.

Statuses:
Equipment: active, assigned, idle, under_maintenance, retired, rented_out.
GPS: online, offline, inside_geofence, outside_geofence, unknown.
Maintenance: scheduled, in_progress, completed, cancelled.
Fuel: draft, submitted, approved, rejected, posted.
Assignment: active, completed, cancelled.

Business rules:
- One equipment asset should not have more than one active full-time assignment unless sharing is enabled.
- Operator must be an employee.
- Maintenance due can be based on date, odometer, or working hours.
- Fuel log requires asset, date, liters, rate, amount, and odometer/hours reading.
- GPS latest location should show asset status even without live provider integration.
- Approved equipment costs should update project equipment costing.
- Posting creates accounting journal entry using Phase 4.

Accounting posting:
Fuel: Debit Fuel Expense, Credit Cash / Bank / AP.
Maintenance: Debit Maintenance Expense, Credit Cash / Bank / AP / Inventory Asset.
Rental: Debit Equipment Rental Expense, Credit AP.
Depreciation: Debit Depreciation Expense, Credit Accumulated Depreciation.

Permissions:
Modules: Equipment Dashboard, Equipment Assets, Equipment Assignments, GPS Tracking, Maintenance Jobs, Fuel Logs, Equipment Documents, Operator Assignments, Equipment Utilization, Equipment Costing, Equipment Reports.
Actions: view, create, edit, delete, approve, reject, assign, complete, post, export.

Seed:
8 equipment categories, 40 assets, 20 assignments, GPS latest locations, 15 maintenance jobs, 25 fuel logs, document expiry examples, operator assignments, utilization logs, equipment cost entries, accounting posting examples.

Tests:
Dashboard loads; asset create/update; duplicate active full-time assignment rejected; assignment create; GPS loads; maintenance create/update/complete; fuel log create/approve; document create; operator assignment; utilization; costing; posting creates journal; reports load; previous tests pass.

Commit message:
Build Phase 7 Equipment and Vehicle Tracking foundation

Deliver commit hash, summary, files, routes, migrations, seed data, tests, known issues, screens to review.
