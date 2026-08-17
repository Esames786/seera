# Phase 4 Screen-wise Field Specification

## Accounting Dashboard
Cards:
- Cash / Bank Balance
- Accounts Payable
- Accounts Receivable
- VAT Payable
- Unposted Journals
- ZATCA Failed
- Monthly Revenue
- Monthly Expenses

Sections:
- Profit & Expense Trend
- Finance Action Queue
- Payable Aging
- Receivable Aging
- VAT Summary
- Recent Journal Entries

## Chart of Accounts
Fields:
- Account Code
- Account Name
- Account Type
- Parent Account
- Opening Balance
- Normal Balance
- VAT Applicable
- Cost Center Required
- Status

Account types:
- Asset
- Liability
- Equity
- Revenue
- Expense

## Journal Entries
Header fields:
- Journal Date
- Reference No
- Source Module
- Cost Center
- Description
- Status

Line fields:
- Account
- Debit
- Credit
- Description
- Cost Center

## General Ledger
Filters:
- Date Range
- Account
- Cost Center
- Source
- Posted Only

Columns:
- Date
- Voucher
- Account
- Description
- Debit
- Credit
- Balance
- Source
- Cost Center

## Accounts Payable
Fields:
- Supplier
- Bill Number
- Bill Date
- Due Date
- Taxable Amount
- VAT Amount
- Total Amount
- Paid Amount
- Balance
- Status

## Accounts Receivable
Fields:
- Customer
- Invoice Number
- Invoice Date
- Due Date
- Taxable Amount
- VAT Amount
- Total Amount
- Received Amount
- Balance
- ZATCA Status
- Status

## VAT Management
Fields:
- VAT Period
- Sales Taxable Amount
- Output VAT
- Purchase Taxable Amount
- Input VAT
- VAT Payable
- Exceptions
- Status

## ZATCA E-Invoicing
Fields:
- Invoice Number
- UUID
- QR Status
- XML Status
- Digital Signature Status
- Clearance Status
- ZATCA Response
- Retry Count
- Tamper-Proof Storage Status

Note:
Final ZATCA compliance and production integration should be verified with the latest official Saudi requirements before live use.

## Financial Reports
Reports:
- Balance Sheet
- Profit & Loss
- Trial Balance
- Cash Flow
- VAT Report
- Project Cost Report

## Cost Centers
Fields:
- Cost Center Code
- Cost Center Name
- Type: Branch / Department / Project / Site / Warehouse
- Linked Record
- Manager
- Status

## Automatic Posting Rules
Fields:
- Source Module
- Trigger Event
- Debit Account
- Credit Account
- Cost Center Rule
- Auto Post
- Approval Required
- Status

Example rules:
- Payroll Approved: Debit Salary Expense, Credit Salary Payable
- Site Expense Approved: Debit Expense Account, Credit Cash/Bank
- Inventory Purchase: Debit Inventory Asset, Credit Accounts Payable
- Customer Invoice: Debit Accounts Receivable, Credit Revenue + VAT Payable
