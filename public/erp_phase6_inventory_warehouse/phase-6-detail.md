# Phase 6 Detail - Inventory & Warehouse

## Purpose
Phase 6 creates the inventory control layer for construction operations. It connects warehouses, projects, suppliers, purchase requests, purchase orders, stock receiving, stock issuing, transfers, adjustments, stock ledger, inventory reports, and accounting posting.

## Main Screens
1. Inventory Dashboard
2. Materials / Items
3. Item Categories & Units
4. Warehouses / Warehouse Stock Summary
5. Stock On Hand
6. Purchase Requests
7. Purchase Orders
8. Goods Receipt Notes
9. Stock Issues to Project/Site
10. Stock Transfers
11. Stock Adjustments
12. Stock Ledger
13. Inventory Reports
14. Accounting Posting
15. Sidebar Grouping Improvement

## Core Business Rules
- GRN increases warehouse stock.
- Stock issue decreases warehouse stock.
- Transfer decreases stock from source warehouse and increases stock in destination warehouse.
- Adjustment changes stock after approval.
- Every stock movement creates stock ledger entries.
- Negative stock should be blocked by default.
- Posted stock documents should become read-only.
- Average cost is default valuation in this phase.
- FIFO fields should exist, but complex FIFO costing is not overbuilt yet.

## Accounting Integration
GRN / purchase receipt:
Debit Inventory Asset
Debit Input VAT
Credit Accounts Payable

Stock issue to project:
Debit Project Material Expense
Credit Inventory Asset

Stock adjustment loss:
Debit Inventory Adjustment Expense
Credit Inventory Asset

## What Not To Build Yet
- Full mobile warehouse app
- Barcode scanning
- Advanced FIFO batch costing
- Vendor portal
- Automated purchase forecasting
- Equipment spare-part maintenance automation
