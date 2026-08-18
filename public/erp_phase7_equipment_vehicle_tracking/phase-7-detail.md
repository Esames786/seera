# Phase 7 Detail - Equipment & Vehicle Tracking

## Purpose
Phase 7 creates the equipment, fleet, and vehicle control layer for construction operations.

It manages heavy machinery, construction equipment, company vehicles, rented/leased equipment, project/site assignments, operators/drivers, GPS status, fuel logs, maintenance jobs, documents and expiry alerts, utilization and downtime, project equipment costing, and accounting posting foundation.

## Why this phase matters
Construction companies have expensive machinery and vehicles. Without tracking, fuel cost, maintenance cost, idle time, misuse, downtime, and project allocation become unclear.

Phase 7 helps answer:
- Which equipment is assigned to which project?
- Who is operating it?
- Is GPS online?
- Is it inside the site geofence?
- How much fuel was consumed?
- What maintenance is due?
- What is the project-wise equipment cost?
- Which documents are expiring?

## Main Screens
1. Equipment Dashboard
2. Equipment & Vehicles
3. Add/Edit Equipment or Vehicle
4. Project / Site Assignments
5. GPS Tracking Overview
6. Maintenance Jobs
7. Fuel Logs
8. Equipment / Vehicle Documents
9. Operators / Drivers
10. Utilization & Downtime
11. Project Equipment Costing
12. Accounting Posting
13. Equipment Reports

## Core Business Rules
- One asset can be assigned to one active project/site at a time unless sharing is enabled.
- Operator/driver should come from Phase 3 employees.
- Project/site should come from Phase 2 and Phase 5.
- Fuel and maintenance should be linked to asset and project/site.
- Maintenance due should be based on date, odometer, or working hours.
- GPS offline assets should show warning.
- Documents expiring soon should show alerts.
- Approved equipment costs should update project cost tracking.
- Accounting posting should use Phase 4 accounts.
- Inventory spare parts usage should later connect with Phase 6 inventory.

## Accounting Posting Examples
Fuel: Debit Fuel Expense, Credit Cash / Bank / Accounts Payable.
Maintenance: Debit Maintenance Expense, Credit Cash / Bank / Accounts Payable / Inventory Asset.
Rental: Debit Equipment Rental Expense, Credit Accounts Payable.
Depreciation: Debit Depreciation Expense, Credit Accumulated Depreciation.

## What Not To Overbuild Yet
Do not build live GPS provider integration, mobile driver app, advanced route replay, IoT engine, automated fuel card integration, or final depreciation engine yet.
