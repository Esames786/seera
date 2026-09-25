# Finance acceptance pack FIN-01 … FIN-10 — execution record (26 September 2026)

Branch `feature/seera-connected-workspaces-2026-09-23`, executed on synthetic data
over the demo seed by `tests/Feature/FinanceAcceptanceScenariosTest.php` (10 tests).
Each scenario below is asserted by that test; the PASS/BLOCKED status is the
automated result on synthetic data. **This is not a client acceptance.** The client
accountant still has to run the same scenarios on staging with real chart codes
and sign each line.

Conventions: VAT 15 %; accounts are the seeded codes (1110 Cash, 1120 Bank,
1200 AR, 1300 Input VAT, 1400 Inventory, 2100 AP, 2210 Output VAT, 3100 Owner
Equity, 4100 Project Revenue, 5200 Material Expense). "Ledger impact" is the net
posted movement of the account on the document date. "Audit log" is the
`activity_logs` row written by the action.

| # | Scenario | Result |
|---|----------|--------|
| FIN-01 | COA / opening / manual journal | PASS |
| FIN-02 | Standalone AP service bill | PASS |
| FIN-03 | AP partial and full payment, replay, overpayment | PASS |
| FIN-04 | AR invoice, receipts, cash-only channel, ZATCA foundation | PASS |
| FIN-05 | Cash / trial balance / report tie-out | PASS |
| FIN-06 | VAT open / finalized / correction | PASS |
| FIN-07 | Receivable ageing buckets | PASS (bucket boundaries need accountant sign-off) |
| FIN-08 | Procurement reconciliation | BLOCKED (F04) — inventory half PASS |
| FIN-09 | Approval and permission | BLOCKED (F08) — permission half PASS |
| FIN-10 | Recovery / correction | PASS (payment reversal is a known gap) |

## FIN-01 — COA, opening balance, manual journal

- **Input:** manual journal dated today, Dr 1120 Bank 10,000 / Cr 3100 Owner Equity 10,000, saved as draft.
- **Action:** save; read ledger; post; read trial balance and balance sheet; then try an unbalanced journal (10,000 / 9,000) and a journal on an inactive equity account.
- **Expected status:** `draft` after save, `posted` after post; the two bad journals are refused and nothing is saved.
- **Debit / Credit:** 1120 Dr 10,000; 3100 Cr 10,000.
- **VAT impact:** none (no VAT control account touched).
- **Ledger / report impact:** bank and equity movement unchanged while draft; +10,000 / −10,000 after posting. Trial balance Dr = Cr. Balance sheet assets = liabilities + equity + result.
- **Audit log:** `Posted journal entry <number>`.
- **Result:** PASS. Note: the inactive-account refusal was missing for manual journals before this sprint; commit `c90391f` adds it (store, update and post).

## FIN-02 — Standalone AP service bill

- **Input:** supplier bill BILL-FIN02, one service line 1,000, VAT 15 %.
- **Action:** save as draft; approve.
- **Expected status:** `draft` (no journal, no VAT row) then `unpaid`, balance 1,150.
- **Debit / Credit:** 5200 Dr 1,000; 1300 Dr 150; 2100 Cr 1,150 (journal source "Supplier Bill", status posted).
- **VAT impact:** exactly one `input` row of 150 in the open (draft) quarter.
- **Ledger / report impact:** expense +1,000, input VAT +150, payable −1,150 on the bill date.
- **Audit log:** `Approved supplier bill BILL-FIN02`.
- **Result:** PASS.

## FIN-03 — AP partial and full payment

- **Input:** the approved FIN-02 bill; payments of 400 (cash) with key `fin03-first`, the same request again, 751, then 750 with key `fin03-second`.
- **Action:** pay, replay, overpay, pay remainder, then try 1 more.
- **Expected status:** `partially_paid` / balance 750 after 400; replay changes nothing (one payment row, flash "already recorded"); 751 refused with no payment row and no journal; `paid` / balance 0 after 750; a further payment refused.
- **Debit / Credit:** each payment Dr 2100 / Cr 1110 for its amount.
- **VAT impact:** none; the VAT row count is unchanged by settlements.
- **Ledger / report impact:** AP nets to zero for the bill; cash −1,150.
- **Audit log:** `Recorded supplier payment` mentioning BILL-FIN03.
- **Result:** PASS.

## FIN-04 — AR invoice and receipts

- **Input:** customer invoice net 2,000, VAT 300; receipts 500 (bank, key `fin04-first`, replayed) and 1,800 (bank). A second customer with `allowed_payment_types = Cash` and an invoice of 115.
- **Action:** approve; receive; replay; receive; for the cash-only customer try a bank receipt then a cash receipt.
- **Expected status:** `unpaid` / 2,300 → `partially_paid` / 1,800 → `paid` / 0. Bank receipt on the cash-only customer refused (`receipt_account_id`); cash receipt accepted.
- **Debit / Credit:** invoice 1200 Dr 2,300 / 4100 Cr 2,000 / 2210 Cr 300; each receipt Dr 1120 / Cr 1200.
- **VAT impact:** one `output` row of 300; receipts add none.
- **Ledger / report impact:** AR nets to zero; bank +2,300; revenue −2,000 (credit); output VAT −300 (credit).
- **ZATCA:** one local record exists per approved invoice and is not `cleared` (foundation only, no live gateway).
- **Audit log:** `Approved customer invoice`, `Recorded customer receipt`.
- **Result:** PASS.

## FIN-05 — Cash / trial balance / report tie-out

- **Input:** FIN-02 bill paid in full by bank, FIN-04 invoice received in full by bank, all dated today; report range = today.
- **Action:** read profit & loss, cash flow, trial balance, balance sheet, bank general ledger, P&L CSV.
- **Expected:** P&L delta revenue +2,000, expenses +1,000, net +1,000. Cash flow in +2,300, out +1,150, closing = opening + in − out. Trial balance Dr = Cr. Balance sheet identity holds. Bank ledger: opening + page movements = last running balance. CSV totals equal the screen.
- **VAT impact:** unchanged from FIN-02/04.
- **Result:** PASS. Openings: the seeded 850,000 bank/equity opening is included for company-scope users only (F07); scoped users see movement-only figures.

## FIN-06 — VAT open / finalized / correction

- **Input:** open quarter recalculated as baseline; FIN-02 bill and FIN-04 invoice; reopen of the unpaid bill (super admin, reason given); re-approval; a zero-rated bill of 100; finalize; a late bill; a late invoice correction.
- **Expected:** period deltas output +300, input +150, payable +150. Reopen keeps the original journal `posted`, posts a reversal (1300 Cr 150 / 2100 Dr 1,150 / 5200 Cr 1,000), withdraws the bill's VAT row, sets the bill to `draft`; re-approval records exactly one VAT row again. Zero-rated bill posts without a VAT row. After finalize: late bill approval refused (`vat`, stays draft, no journal); late reopen of the invoice refused; second finalize and recalculate refused; stored totals frozen.
- **Audit log:** `Finalized VAT period <name>`, `Reopened supplier bill BILL-FIN06`.
- **Result:** PASS. Open policy items: zero-rate base category and rounding sign-off (F09) are not decided by this test.

## FIN-07 — Ageing

- **Input:** open invoices of 1,150 due today, 31, 61 and 91 days ago (due dates aged after approval to simulate elapsed time), one settled invoice, one draft.
- **Expected buckets (delta on the accounting dashboard):** Current +1,150; 1–30 days 0; 31–60 days +1,150; 60+ days +2,300. Settled and draft excluded. A partial receipt of 150 leaves 1,000 in the 31–60 bucket.
- **Result:** PASS on the current rules: `days late = due date → today`, 0 → Current, 1–30, 31–60, 61+ → "60+". No-due-date invoices count as Current. The report is as-of today only (no historical as-of date). The accountant must confirm these boundaries.

## FIN-08 — Procurement reconciliation

- **Input:** fresh item, GRN 10 units at 100, VAT 15 %, posted to stock; stock issue of 3 units, posted.
- **Expected (inventory half):** stock 10 / 1,000 then 7 / 700; GRN journal 1400 Dr 1,000 / 1300 Dr 150 / 2100 Cr 1,150 with one `input` VAT row (source "Goods Receipt"); issue journal 5200 Dr 300 / 1400 Cr 300 with no VAT row.
- **Audit log:** `Posted goods receipt <GRN number>` (Inventory module).
- **Result:** inventory half PASS. **BLOCKED** for the reconciliation half: a supplier bill entered for the same delivery posts a second AP liability and a second input VAT row today (audit F04). No assertion is made about that until the GRN-vs-bill model is decided.

## FIN-09 — Approval and permission

- **Input:** draft bill; a company-level "AP clerk" role with view/create/edit on Accounts Payable only.
- **Expected:** clerk's approve and reopen return 403, bill stays `draft`, no journal; clerk can still open the bill. Admin approval posts once; a second approval is refused (F11).
- **Result:** permission half PASS. **BLOCKED** for "first of two required approvals leaves pending": there is no multi-approver runtime (audit F08); single-actor approval is the live behaviour and must not be presented as compliant with an all-required workflow.

## FIN-10 — Recovery / correction

- **Input:** draft journal 500; unpaid bill; paid bill.
- **Expected:** cancelling the draft has no ledger effect and the cancelled journal cannot be posted. Reopening the unpaid bill nets AP and expense back to zero, keeps the original journal `posted` and adds the reversal (both retained). The posted original refuses update, delete and cancel. The paid bill cannot be reopened. There is no payment reversal route.
- **Audit log:** `Reopened supplier bill BILL-FIN10`, `Cancelled journal entry <number>`.
- **Result:** PASS. Known gap: no payment/receipt reversal process exists; until one does, a wrong payment can only be corrected by a manual journal.

## What the client accountant still has to do

1. Run FIN-01 … FIN-07 and FIN-10 on staging with the production chart codes and sign each line.
2. Decide F04 (GRN vs supplier bill) so FIN-08 can be completed.
3. Decide the approval configuration (F08) so FIN-09 can be completed.
4. Confirm the ageing boundaries and the zero-rate / rounding policy (FIN-07, FIN-06).
