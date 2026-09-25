# Finance correctness sprint — status (26 September 2026)

Branch `feature/seera-connected-workspaces-2026-09-23`. Nothing deployed, nothing
merged; the server is still at `7fc1749`. Prepared for architect review.

## 1. Commits (oldest first)

| Hash | Subject |
|------|---------|
| `b850190` | F01: harden atomic finance posting |
| `5fb4cae` | F02: add payment and receipt idempotency |
| `fd7ea4a` | F03: enforce VAT period locks |
| `1b95db7` | F06: correct GL and report balances |
| `d1f77b5` | F07: harden finance scope and export authorization |
| `c4fc7e0` | F11: recheck finance state under lock before edit, delete and cancel |
| `c90391f` | Add Finance acceptance scenarios FIN-01 to FIN-10 |
| (release) | Finance sprint records, rebuilt bundle, round-3 test key |

## 2. Migrations

`2026_09_26_000001_add_idempotency_keys_to_settlements` — nullable unique
`idempotency_key` (64) on `supplier_payments` and `customer_receipts`. Additive,
no data change, no seeder. Rollback = `migrate:rollback --step=1` while it is the
last batch.

## 3. What each finding now does

- **F01** `PostingService::createEntry` filters zero lines, refuses fewer than two lines, missing or inactive accounts and any imbalance ≥ 0.01, and creates entry plus lines in one transaction. Every `post*` method returns the entry (inventory postings return null only for zero value). Bill/invoice approval, payments, receipts, GRN, issue and adjustment posting run inside the document transaction so a refused posting rolls the document back. Error key is `posting` everywhere. Manual journals refuse inactive accounts on store, update and post.
- **F02** Payment and receipt forms carry a hidden UUID `idempotency_key`; the store looks it up under the document lock and answers a replay with the existing settlement ("already recorded, nothing was added"). Two independent equal amounts without a key are still two payments.
- **F03** `PostingService::assertVatPeriodOpen` refuses any VAT-bearing posting dated into a finalized or submitted period; manual journals on VAT control accounts are checked on store and post; reopen (withdraw VAT) is refused in a sealed period; `recalculate` refuses non-draft periods; `finalize` locks the row and rechecks the status.
- **F06** Ledger opening = account opening (company scope only) + posted movement before `from` with the same filters; running balance continues across pages; balance sheet and trial balance are cumulative as-of `to`, P&L is period-only, cash flow opening includes prior cash movement, project cost report nets reversals and date-filters bills/invoices; CSV exports carry the screen's figures.
- **F07** `JournalEntry` is now in the access-scope list (scoped through its lines), so scoped users cannot list, open or post other projects' journals; openings excluded for scoped users; VAT Management and the VAT report are company-scope only (403 otherwise); CSV export of financial and marketing reports requires the module's `export` permission; manual journal lines by scoped users must lie inside their projects/sites.
- **F11** AP/AR `update`/`destroy` and journal `update`/`destroy`/`cancel` reload the row with `lockForUpdate` inside the transaction and refuse if the status changed. Approve, post, pay, receive and finalize already did (F01/F02/F03).

## 4. Tests

| Run | Result |
|-----|--------|
| `FinancePostingIntegrityTest` (F01) | 7 / 7 |
| `SettlementIdempotencyTest` (F02) | 4 / 4 |
| `VatPeriodLockTest` (F03) | 7 / 7 |
| `FinanceReportBalancesTest` (F06) | 3 / 3 |
| `FinanceScopeAuthorizationTest` (F07) | 4 / 4 |
| `FinanceStateTransitionTest` (F11) | 3 / 3 |
| `FinanceAcceptanceScenariosTest` (FIN-01…10) | 10 / 10 |
| Full suite at `c90391f` | 268 tests, 2,699 assertions: 267 passed, 1 failed (`ClientChangeRequestsRound3Test` still asserted the old `invoice` error key after F01 unified it as `posting`) |
| After the one-line test fix | that class 17 / 17; no application code changed by the fix |

Browser fixtures (`tests/browser/*.cjs`) need Playwright, which is not installed on
this workstation; no JavaScript changed in this sprint, so they were not run.

## 5. Build

`npm run build` succeeded. `app-*.js` and `erp-*.css` hashes are unchanged from the
24 September bundle; `app-*.css` got a new hash because Tailwind's `@source` includes
the compiled Blade cache (`storage/framework/views`), which differs per machine.
`public/build.zip` was repacked from the fresh build (root-level `assets/` +
`manifest.json`, 18 files).

## 6. Blockers and decisions still open

1. **F04 GRN vs supplier bill** — a bill for a delivery already received posts AP and input VAT a second time. FIN-08 is BLOCKED until the model is decided (bill-against-GRN, or GRN accrual reversed by the bill).
2. **F08 approval runtime** — one actor approves and posts; `ApprovalWorkflow` configuration is not executed. FIN-09 is BLOCKED for the two-approver case.
3. **F09 zero-rate base / rounding policy** — accountant decision.
4. **F10 payment/receipt reversal** — no route; correction is a manual journal today.
5. **F05, F12** — out of this sprint (PO/GRN input integrity, payroll-to-GL, live ZATCA).
6. Staging has `Q3 2026` finalized since 21 September. Every VAT-bearing document dated in Q3 will now be refused on staging by design; the owner must either enter test data dated in an open period or ask for a controlled reopen (not done automatically).

## 7. Records

- `docs/finance-acceptance-2026-09-26.md` — FIN-01…10 execution record.
- `docs/finance-readiness-audit-2026-09-24.md` §12 — finding-by-finding outcome.
- `docs/production-deployment-guide.md` §13 — release and rollback commands.
