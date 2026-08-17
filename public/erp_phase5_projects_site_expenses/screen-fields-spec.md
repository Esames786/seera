# Phase 5 Screen-wise Field Specification

## Project Dashboard
Cards:
- Active Projects
- Total Budget
- Actual Cost
- Budget Variance
- Pending Expenses
- Approved Expenses
- Unposted Expenses
- Daily Reports

Sections:
- Project Cost Trend
- Project Action Queue
- Budget vs Actual
- Pending Approvals
- Delayed Milestones

## Projects Listing
Filters:
- Search
- Customer
- Project Manager
- Status
- Branch

Columns:
- Project Code
- Project Name
- Customer
- Project Manager
- Start Date
- End Date
- Budget
- Actual Cost
- Progress
- Status
- Actions

## Add/Edit Project
Project Information:
- Project Code
- Project Name
- Customer
- Branch
- Project Manager
- Status

Timeline & Budget:
- Start Date
- Expected End Date
- Contract Value
- Approved Budget
- Contingency Budget
- Cost Center

Location/Site:
- Main Site
- City
- Address
- Geo-Fence Required
- Latitude
- Longitude

## Project Details
Sections/Tabs:
- Overview
- Budget / BOQ
- Milestones
- Sites
- Expenses
- Labor Cost
- Reports
- Accounting

## Project Budget / BOQ
Fields:
- Project
- Budget Code
- Category: Material / Labor / Equipment / Subcontract / Other
- Description
- Unit
- Quantity
- Estimated Cost
- Committed Cost
- Actual Cost
- Cost Center
- Status

## Project Milestones
Fields:
- Project
- Milestone Name
- Description
- Target Date
- Completion Date
- Progress %
- Status: Pending / In Progress / Completed / Delayed
- Delay Reason

## Project Cost Tracking
Sources:
- Site Expenses
- Payroll
- Supplier Bills
- Inventory later
- Equipment later

Columns:
- Date
- Source
- Reference
- Project
- Site
- Category
- Amount
- VAT
- Total
- Posted

## Site Expenses Listing
Filters:
- Search
- Project
- Site
- Status
- Posting Status
- Date Range
- Category
- Supplier

Columns:
- Expense No
- Date
- Project/Site
- Category
- Supplier
- Payment Method
- Taxable Amount
- VAT Amount
- Total Amount
- Approval Status
- Posting Status
- Actions

## Add Site Expense
Expense Context:
- Project
- Site
- Expense Date
- Expense Category
- Budget Line
- Cost Center

Supplier & Invoice:
- Supplier
- Invoice Number
- Payment Method
- Invoice Photo
- Geo Location
- Offline Sync ID

Amount & VAT:
- Taxable Amount
- VAT Rate
- VAT Amount
- Total Amount
- Paid By
- Status
- Description / Remarks

## Expense Details
Sections:
- Summary cards
- Invoice photo preview
- Approval trail
- Accounting posting preview
- Activity log

## Expense Approvals
Approval flow:
1. Site Supervisor submits
2. Project Manager approves/rejects/sends back
3. Finance approves/rejects
4. Accounting posting runs after approval

## Expense Payments / Settlements
Fields:
- Payment No
- Expense
- Paid To
- Payment Date
- Method
- Account
- Amount
- Reference
- Status

## Daily Site Reports
Fields:
- Report No
- Date
- Project
- Site
- Supervisor
- Manpower Count
- Work Progress
- Materials Used
- Issues
- Photos
- Status

## Accounting Posting Preview
Posting example:
- Debit Expense Category Account
- Debit Input VAT
- Credit Cash / Bank / Petty Cash / Accounts Payable
