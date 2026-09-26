# SEERA ERP — Workflow Index (Current System)

Index of the end-to-end workflows documented in chapter 17 of the [User Guide](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#17-end-to-end-workflows). Screens are indexed in [SCREEN-INDEX.md](SCREEN-INDEX.md).

Version 1.0 · Prepared 27 September 2026 · Status: current implemented system only.

| Workflow ID | Workflow | Roles involved | Main screens | Status | Guide |
|---|---|---|---|---|---|
| WF-001 | Create Employee and User Access | HR Manager, Super Admin | HR-EMP-002, HR-EMP-004, USR-002 | AVAILABLE | [WF-001](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-001-create-employee-and-user-access) |
| WF-002 | Supplier → Purchase Order → Goods Receipt → Supplier Bill → Payment | Purchase Manager, Warehouse Incharge, Finance Manager | SUP-002, INV-PO-002, INV-GRN-002, FIN-AP-002, FIN-AP-005 | AVAILABLE | [WF-002](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-002-supplier--purchase-order--goods-receipt--supplier-bill--payment) |
| WF-003 | Customer → Invoice → Receipt | Marketing Manager, Finance Manager | CUS-002, FIN-AR-002, FIN-AR-003, FIN-AR-005 | AVAILABLE | [WF-003](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-003-customer--invoice--receipt) |
| WF-004 | Manual Journal → General Ledger | Finance Manager | FIN-JE-002, FIN-JE-003, FIN-GL-001 | AVAILABLE | [WF-004](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-004-manual-journal--general-ledger) |
| WF-005 | VAT Period Review and Finalize | Finance Manager | FIN-VAT-001, FIN-VAT-002, FIN-REP-006 | AVAILABLE | [WF-005](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-005-vat-period-review-and-finalize) |
| WF-006 | Inventory Transfer between Warehouses | Warehouse Incharge | INV-TRF-002, INV-TRF-003 | AVAILABLE (Receive needs the "receive" right) | [WF-006](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-006-inventory-transfer-between-warehouses) |
| WF-007 | Stock Issue to a Project | Warehouse Incharge | INV-ISS-002, INV-ISS-003, FIN-REP-007 | AVAILABLE | [WF-007](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-007-stock-issue-to-a-project) |
| WF-008 | Payroll Run | HR Manager, Finance Manager | HR-PAY-002, HR-PAY-003 | PARTIAL (no payslip, no accounting posting) | [WF-008](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-008-payroll-run) |
| WF-009 | Leave Request lifecycle | HR Manager | HR-LV-002, HR-LV-003, HR-EMP-004 | AVAILABLE | [WF-009](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-009-leave-request-lifecycle) |
| WF-010 | Supplier F04 GRNI accounting flow | Warehouse Incharge, Finance Manager | INV-GRN-003, FIN-AP-002, FIN-AP-003 | AVAILABLE | [WF-010](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-010-supplier-f04-grni-accounting-flow) |
| WF-011 | Purchase Request → Purchase Order | Site In-Charge, Purchase Manager | INV-PR-002, INV-PR-003, INV-PO-002 | AVAILABLE | [WF-011](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-011-purchase-request--purchase-order) |
| WF-012 | Correct an approved bill or invoice (Reopen) | Super Admin, Finance Manager | FIN-AP-003, FIN-AR-003 | AVAILABLE | [WF-012](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-012-correct-an-approved-bill-or-invoice-reopen) |
| WF-013 | Stock Adjustment after a physical count | Warehouse Incharge, Inventory Manager | INV-ADJ-002, INV-ADJ-003 | AVAILABLE | [WF-013](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-013-stock-adjustment-after-a-physical-count) |
| WF-014 | Marketing Lead → Visit → Customer | Marketing Manager | MKT-002, MKT-003, CUS-004 | AVAILABLE | [WF-014](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-014-marketing-lead--visit--customer) |
| WF-015 | End of Service settlement | HR Manager | HR-EOS-002, HR-EOS-003 | AVAILABLE (calculation and approval; no accounting posting) | [WF-015](SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md#wf-015-end-of-service-settlement) |

Total: 15 workflows.
