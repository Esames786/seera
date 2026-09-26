# F04 — GRN / Supplier Bill accounting integrity (GRNI model), 27 September 2026

Branch `feature/seera-connected-workspaces-2026-09-23`, continued from `d81948c`.
Scope: F04 plus the goods-receipt over-receipt guard only. Nothing deployed, nothing merged.

## 1. What was wrong (source findings at d81948c)

- `PostingService::postGoodsReceipt` posted Dr Inventory / Dr Input VAT / Cr Accounts Payable and wrote an input VAT row.
- `PostingService::postSupplierBill` posted Dr Expense / Dr Input VAT / Cr Accounts Payable and wrote a second input VAT row for the same delivery.
- `supplier_bills` had no `purchase_order_id` / `goods_receipt_id`; nothing linked a bill to received goods.
- `GoodsReceiptController` matched PO lines by `item_id` with `first()`, never checked the outstanding quantity, the PO state or the PO supplier, and re-checked nothing under lock at posting.
- Result: one purchase could carry two payables and two input VAT amounts, and the GRN liability could not be settled because payments require a bill.

## 2. Target model (adopted)

| Event | Debit | Credit | VAT row |
|---|---|---|---|
| Goods receipt posted | 1400 Inventory (per line, item inventory account) | **2150 Goods Received Not Invoiced** | none |
| Supplier bill line matched to a receipt line | 2150 GRNI (receipt cost × matched qty); price variance to the line's expense account | 2100 Accounts Payable (bill total, once) | one `input` row on the bill |
| Supplier bill direct / service line | 5200 or chosen expense account | 2100 Accounts Payable | same single row |
| Input VAT | 1300 Input VAT (bill VAT amount) | | |

GRNI account: code **2150**, "Goods Received Not Invoiced", liability, credit-normal, parent 2000. Created idempotently by the migration and present in both chart seeders. `PostingService::GRNI` constant.

## 3. Schema (additive)

`2026_09_27_000001_add_grni_and_supplier_bill_grn_matching`:
- `goods_receipt_lines.purchase_order_line_id` (nullable FK) and `goods_receipt_lines.invoiced_quantity` (decimal 15,3, default 0).
- `supplier_bill_grn_matches`: supplier_bill_id, supplier_bill_line_id, goods_receipt_id, goods_receipt_line_id, matched_quantity, matched_taxable_amount, committed_at, timestamps; unique (bill line, receipt line); index (receipt line, committed_at).
- Inserts account 2150 when missing and repoints the "Inventory Purchase" rule credit to it.

## 4. Matching rules

- A bill line may name one posted receipt line of the same supplier and a matched quantity. Several bill lines can point to several receipts (one invoice, many GRNs). Two lines may point to the same receipt line; their sum is checked.
- Save (draft): the receipt line must be visible to the user (access scope), posted, same supplier, quantity > 0 and ≤ uninvoiced. Draft matches do not consume anything.
- Approve: the bill row is locked, then each matched receipt line is locked and re-checked (posted, supplier, uninvoiced ≥ requested). `invoiced_quantity` is incremented and the match stamped `committed_at`; the journal then clears GRNI. Any refusal rolls back the whole approval (no bill status change, no journal, no VAT row).
- Reopen (Super Admin): reversal journal as before, matches released (`invoiced_quantity` decremented), bill back to draft; re-approval consumes again.
- Description, quantity and unit price default from the receipt line when left blank; a different unit price posts a variance line, the inventory value is never restated.
- UI: bill form gains "Received Goods (GRN)" and "Invoiced Qty" columns (options filtered by supplier, inline script, no asset build); posted receipts show an "Invoiced" column and a "Create Supplier Bill" button that pre-fills the bill from the receipt.

## 5. Receipt guards (GoodsReceiptController)

- Save against an order: order must be `approved` or `partially_received`; order supplier must equal receipt supplier; every line must be on the order (by `purchase_order_line_id` from the form, else by item when unambiguous, refused when ambiguous); received quantity per order line ≤ outstanding.
- Post: order and order lines locked `FOR UPDATE`; accepted per line re-checked against outstanding; the specific order line is incremented; `purchase_order_line_id` is stored on the receipt line. A second draft receipt for the same outstanding quantity is refused at posting.

## 6. Historical data

Not rewritten. `php artisan finance:grn-bill-overlap` (read-only) lists posted receipts whose journal credits accounts payable, with same-supplier bills of equal taxable amount or matching invoice number within ±45 days. Locally the demo database shows 4 seeded receipts (GRN-2026-0001…0004) with no duplicate bills. On staging the command must be run after migrating and the list handed to the accountant; corrections need an approved reversing-entry plan.

## 7. Tests

- New `tests/Feature/GrnBillMatchingTest.php` (14 tests): PO→GRN accrual without VAT/AP; matched bill clears GRNI with VAT and AP once; partial invoice, several receipts on one bill, no double invoicing (also split across lines of one bill); over-receipt, supplier mismatch, draft/received order, foreign item; two draft receipts for one outstanding quantity (only the first posts); direct/service bill; mixed bill; price variance; stale duplicate match refused at approval with full rollback; unposted or other-supplier receipt refused; project-scoped user cannot match another project's receipt but can match their own; reopen releases and reversal credits GRNI; receipt in a sealed VAT period posts while its bill dated there is refused; bill form filtering and prefill.
- Updated: `InventoryTest::test_goods_receipt_posting_creates_accounting_journal` (credit to 2150, no 2100/1300, no VAT row), `VatPeriodLockTest` receipt test (a receipt is no longer a VAT event), FIN-08 in `FinanceAcceptanceScenariosTest` (now asserts the full reconciliation and the duplicate refusal).

## 8. Out of scope / still open

F08 approval runtime, Save & Close retrofit, site expenses, mobile attendance, equipment, ZATCA, payroll→GL, payment reversal, credit notes. Accountant to confirm: GRNI code 2150, variance treatment, and the correction plan for legacy receipts.
