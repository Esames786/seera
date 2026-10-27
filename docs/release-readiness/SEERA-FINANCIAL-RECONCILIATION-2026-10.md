# Financial workflow verification — 26 October 2026

All new transactions in this review are synthetic, isolated tests. There is no claim of reconciliation of the live company's opening balances or transactions.

## Evidence selection

Fresh critical selection: `FinanceAcceptanceScenariosTest|GrnBillMatchingTest|SupplierBillApprovalRuntimeTest|PayrollAccountingTest|PayrollSettlementTest|FinanceScopeAuthorizationTest|VatPeriodLockTest|SiteExpenseTest|InventoryTest|ProjectBudgetReportTest|EquipmentCostTest`.

Environment: PHP 8.3.16; SQLite `:memory:` per test; explicit empty DB_URL, testing environment and production ZATCA disabled; no cached application config. One suite at a time against these fixtures. Existing AR/notes/refund evidence at `aca78ee` is reused rather than rerun without a change.

**Result:** 183 tests / 2,049 assertions passed (357,092 ms); [JUnit evidence](evidence/critical-183-tests.xml). The newly added connected procurement scenario then passed separately: 1 test / 36 assertions (4,652 ms). It was added after the broad run loaded its tests. No financial application code changed. The new fixture initially selected All Projects for a non-project bill and was correctly refused; changing the synthetic workflow to All Company corrected the fixture, not the application.

| Area | Checked invariant / source | Evidence boundary |
| --- | --- | --- |
| Journal/period/report | FIN-01–10: posted debits equal credits, draft excluded, trial balance and VAT reports, sealed-period refusal | Synthetic HTTP/service tests; legacy AP approval in older FIN fixtures |
| GRNI/runtime AP/payment | New `test_uat_received_goods_runtime_approval_and_partial_full_payment_reconcile` in `GrnBillMatchingTest` | Actual GRN posting, two required runtime approvers and HTTP payments on the same synthetic chain; starts with an approved PO fixture, not a new PR/browser journey |
| Stock | `InventoryTest`, GRN matching: receipts/issues/transfers, insufficient-stock refusal and duplicate controls | SQLite functional checks; not a fresh concurrent MySQL inventory certification |
| Payroll | `PayrollAccountingTest`, `PayrollSettlementTest`: expense once, payable clears, no VAT, immutable run, restricted scope, reversal | Synthetic approved-run accounting and settlement |
| Site expenses | `SiteExpenseTest`: required approvals, cash/bank/reimbursement/supplier-credit paths, generated bill and explicit accounting | Runtime integrated paths; not every possible workflow configuration |
| Project costs | `ProjectBudgetReportTest`, `EquipmentCostTest`: GL-based costs, account groups, one cost event, posted/draft distinction | Representative fixtures; not live BOQ/account mapping acceptance |
| Customer credit/AR | `CustomerCreditResolutionTest` and October 26 correction evidence | Prior passing checks on unchanged source: GL AR = open balances minus available customer credit, posted refund requirement, reversals, no duplicate VAT |

## New procurement scenario and expected figures

Approved PO: 10 units × SAR 100. Receive 6 units, then match all 6 to a supplier bill with 15% VAT.

| Stage | Inventory Dr | Input VAT Dr | GRNI net credit | AP net credit | Bank credit |
| --- | ---: | ---: | ---: | ---: | ---: |
| Receipt posted | 600 | 0 | 600 | 0 | 0 |
| First bill approver only | 600 | 0 | 600 | 0 | 0 |
| Both required approvers; bill posted | 600 | 90 | 0 | 690 | 0 |
| Pay 200 | 600 | 90 | 0 | 490 | 200 |
| Pay remaining 490 | 600 | 90 | 0 | 0 | 690 |

Exact payment retries must leave four new journals total (GRN, bill, two payments), one stock receipt movement and one bill VAT event. No second material expense. Each journal must balance individually. The test uses independent reviewer identities and real application routes; it does not force approval_mode to legacy.

## Live Finance reconciliation still required

Finance should record opening control balances, permitted documents, cutoff date, account mappings, ending balances and differences on an authorised copy. Reconcile AR, AP, stock valuation, GRNI, VAT, payroll payable, project actuals and equipment costs. Classify differences as bug/data/configuration/timing/expected policy; never conceal them with balancing journals.

Legacy-refund audit and immutable-history precautions remain in [the correction report](../deployment-fixes-2026-10-26.md). No live data repair is authorised by these tests.
