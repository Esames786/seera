# Phase 6 Screen-wise Field Specification

## Inventory Dashboard
Cards: Total Items, Stock Value, Low Stock, Pending PRs, Open POs, Pending GRNs, Transfers, Unposted Stock.

## Materials / Items
Fields: Item Code, Item Name, Category, Unit, Valuation Method, Reorder Level, Minimum Stock, Maximum Stock, Preferred Supplier, Linked Inventory Account, Linked Expense Account, VAT Applicable, Status.

## Categories & Units
Category fields: Code, Name, Parent Category, Linked Inventory Account, Linked Expense Account, Status.
Unit fields: Code, Name, Allows Decimal, Status.

## Stock On Hand
Columns: Item, Warehouse, Project/Site, On Hand, Reserved, Available, Reorder Level, Average Cost, Stock Value, Status.

## Purchase Requests
Fields: PR Number, Request Date, Requested By, Project, Site, Warehouse, Required Date, Priority, Reason, Status, Approval Status.
Lines: Item, Description, Quantity, Unit, Estimated Unit Cost, Estimated Total, Budget Line.

## Purchase Orders
Fields: PO Number, Supplier, PO Date, Expected Delivery Date, Project, Site, Warehouse, Taxable Amount, VAT Rate, VAT Amount, Total Amount, Status, Approval Status.
Lines: Item, Quantity, Unit Price, VAT, Total.

## Goods Receipt Notes
Fields: GRN Number, PO, Supplier, Warehouse, Received Date, Received By, Delivery Note Number, Invoice Number, Status, Stock Updated, Accounting Posted.
Lines: Item, Ordered Qty, Received Qty, Accepted Qty, Rejected Qty, Unit Cost, Total Cost.

## Stock Issues
Fields: Issue Number, Warehouse, Project, Site, Requested By, Approved By, Issue Date, Purpose, Status, Accounting Posted.
Lines: Item, Quantity, Unit Cost, Total Cost.

## Stock Transfers
Fields: Transfer Number, Transfer Date, From Warehouse, To Warehouse, Requested By, Approved By, Dispatched By, Received By, Dispatch Date, Receive Date, Status.
Lines: Item, Quantity, Unit Cost.

## Stock Adjustments
Fields: Adjustment Number, Warehouse, Item, Adjustment Date, Current Quantity, Adjusted Quantity, Reason, Approved By, Status, Accounting Posted.

## Stock Ledger
Filters: Date Range, Item, Warehouse, Movement Type, Project, Site.
Columns: Date, Reference, Movement Type, Item, Warehouse, In Qty, Out Qty, Balance Qty, Unit Cost, Value, Project/Site.

## Inventory Reports
Stock Valuation, Low Stock, Warehouse Stock, Project Material Consumption, Stock Movement, PO Status, GRN Pending, Inventory Aging.
