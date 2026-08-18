# Claude Prompt - Build Phase 6 Inventory & Warehouse Foundation

We are now starting Phase 6 of the Construction ERP system.

Current status:
Phase 1: Access Control
Phase 2: Master Setup
Phase 3: HR & Payroll
Phase 4: Accounting + VAT + ZATCA
Phase 5: Projects + Site Expenses reference package exists

Now build Phase 6: Inventory & Warehouse.

Important:
- Do not start Equipment, Vehicle Tracking, Mobile App, barcode scanning, or advanced procurement automation yet.
- Only build admin web screens, CRUD, seed data, approval flow, stock ledger, and accounting posting foundation.
- Use existing layout, components, route naming, permissions, and activity logs.
- Keep all existing tests green.

Build:
1. Inventory Dashboard
2. Items CRUD
3. Item Categories CRUD
4. Units CRUD
5. Warehouse Stock Summary
6. Stock On Hand
7. Purchase Requests CRUD + approval
8. Purchase Orders CRUD
9. Goods Receipt Notes CRUD + post stock
10. Stock Issues to project/site
11. Stock Transfers
12. Stock Adjustments
13. Stock Ledger
14. Inventory Reports
15. Accounting posting foundation

Migrations:
item_categories, units, items, warehouse_stocks, purchase_requests, purchase_request_lines, purchase_orders, purchase_order_lines, goods_receipts, goods_receipt_lines, stock_issues, stock_issue_lines, stock_transfers, stock_transfer_lines, stock_adjustments, stock_ledger_entries.

Stock movement rules:
- GRN increases stock.
- Stock issue decreases stock.
- Transfer moves stock between warehouses.
- Adjustment changes stock after approval.
- Stock ledger records every movement.
- Prevent negative stock by default.
- Posted stock documents become read-only.

Valuation:
- Average cost default for this phase.
- Keep FIFO fields ready but do not overbuild complex FIFO costing.

Accounting:
GRN: Debit Inventory Asset + Input VAT, Credit AP.
Stock Issue: Debit Project Material Expense, Credit Inventory Asset.
Adjustment Loss: Debit Inventory Adjustment Expense, Credit Inventory Asset.

Tests:
Inventory dashboard loads; item CRUD; category/unit create; stock screen; PR create/approve; PO create; GRN create/post stock; stock issue decreases stock; negative stock rejected; transfer moves stock; adjustment works; ledger records movement; reports load; accounting posting creates journal; previous tests pass.

Commit message:
Build Phase 6 Inventory and Warehouse foundation

Deliver:
commit hash, summary, files changed, routes, migrations, seed data, tests, known issues, screens to review.
