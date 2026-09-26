# Finance review follow-up — 26 September 2026

Branch: `feature/seera-connected-workspaces-2026-09-23`.
Reviewed baseline: `564fcd9`. This patch addresses the four independently reviewed
defects, not the outstanding procurement/approval architecture decisions.
No production access, deployment, merge, historical-data repair or credential changes.

## Changes and regression evidence

| Finding | Before | After | Coverage |
|---|---|---|---|
| Mixed-project journal integrity | A project user could edit visible lines while hidden lines survived; a header of Dr 40 / Cr 40 could post actual Dr 40 / Cr 140. | Journal list/metrics, View, Edit, Update, Delete, Cancel and Post require access to **every** line. Mutation checks repeat under the journal lock. Post validates all saved lines, positive balanced totals and agreement with the header, not just the header's balance flag. | `FinanceScopeAuthorizationTest`: mixed-entry GET/write denials, unchanged hidden lines, company-level balanced cross-project posting, unbalanced lines and stale headers refused. |
| VAT sealing race | Finalize locked the period but other writers used non-locking reads; a stale draft model could recalculate finalized totals. | VAT insertion, withdrawal, recalculation, finalize and VAT-account journal writes coordinate through the period row lock. Recalculation reloads status under lock and reads current transaction rows with locking reads, including MySQL REPEATABLE READ. Manual journal store/update recheck inside their transaction. | Existing sealed-period tests plus stale-model and legacy unlinked-VAT-row regressions; five real, two-process MySQL lock scenarios below. |
| Settlement key ownership | A key recorded on one document could return “already recorded” on another, or after changing the submitted details. | Shared `SettlementReplay` checks the document, party, normalized amount/date, cash/bank account, method, purpose where applicable, reference and notes. Conflicting reuse returns a generic `idempotency_key` validation error without disclosing the other document. Exact retries remain successful no-ops. | `SettlementIdempotencyTest`: AP and AR cross-document reuse and changed fields rejected; equivalent decimal formatting accepted; existing retries and separate operations remain supported. |
| Period-only P&L | Nonzero revenue/expense account opening balances appeared even in an empty future reporting period. | Account openings are included only for cumulative/as-of reports, not period P&L. Balance sheet/trial-balance openings remain intact. | `FinanceReportBalancesTest`: nonzero openings, empty-period screen/CSV and unchanged as-of balances. |

Journal scope deliberately does **not** suppress the user's own posted lines from
the General Ledger or scoped reports. The stricter whole-entry rule applies to
journal header screens/actions; hiding mixed entries globally would drop legitimate
own-project ledger movement. No existing journal is silently repaired or reposted.

`Phase4AccountingSeeder` needed a fixture-order correction: newly created demo VAT
periods are calculated while draft and only then sealed. It does not reopen existing
returns. **Do not run this demo seeder on production.**

## Verification

- Focused SQLite in-memory suite: **25 tests, 235 assertions, all passed**.
- Full application SQLite suite (Claude, 26 September evening): **275 tests, 2,789 assertions, all passed**. Focused finance classes (F01/F02/F03/F06/F07/F11, FIN-01..10, AccountingTest): 72 tests, 727 assertions, all passed.
- MySQL 8.0.30, `REPEATABLE-READ`, two independent PHP processes: **5/5 passed**.
  The worker announces reaching `SELECT ... FOR UPDATE`; the parent verifies it
  remains blocked until the period lock is released, then checks rows and frozen totals:
  1. Finalize wins; subsequent VAT insertion is refused.
  2. Finalize wins; subsequent VAT withdrawal is refused.
  3. Finalize wins; stale-model recalculation is refused.
  4. VAT insertion wins; finalization waits and includes the committed insertion.
  5. VAT withdrawal wins; finalization waits and excludes the withdrawn row.
- Opt-in integration probe: `tests/manual/finance-vat-concurrency.php`. It is outside
  PHPUnit's normal test directories. It only accepts a freshly initialized temporary
  MySQL instance on `127.0.0.1:33479`, verifies the exact server `@@datadir` against a
  supplied `seera-finance-lock-test-*` directory, and creates a uniquely named synthetic
  database. It never reads application DB credentials or resets existing databases.
- The temporary MySQL server was shut down after verification. Neither the existing
  local Seera database nor the live database was used for testing.
- No frontend source changes; no new asset build/ZIP required for this follow-up.

These are automated regression/integration results, not client/accountant UAT or a
certification of all financial workflows. The MySQL probe tests the actual VAT
service/model lock paths; it is not a full production-load or HTTP concurrency test.

## Release and rollback impact

- No new migration in this follow-up. The preceding release's
  `2026_09_26_000001_add_idempotency_keys_to_settlements` migration is still required
  if the server has not installed that release. Check migration status rather than
  running a reset or demo seeder.
- No production seeder is required. Do not run `migrate:fresh`.
- Backend-only changes: deploy the eventual approved code revision and clear stale
  Laravel caches as part of the existing controlled deployment procedure. Reuse the
  matching existing frontend assets; do not delete/rebuild them for this patch alone.
- This patch does not change existing data or introduce schema changes; code rollback
  requires no migration rollback. Rolling back restores the reviewed defects.
- This work has not been pushed or deployed by this session. A local commit is not
  available to the server's `git pull` until explicitly pushed.

## Still outside this patch / release blockers

- **F04:** GRN versus supplier-bill accounting ownership and duplicate AP/input VAT.
- **F08:** execution of the required multi-approver workflow, not just configuration.
- F09 zero-rate base/rounding policy, F10 settlement reversals, and the previously
  documented F05/F12 scope remain as recorded in the Finance sprint report.
- Existing inconsistent journals need a separately authorized, read-only assessment
  and explicit correction plan; this patch only refuses unsafe future operations.
- No automatic reopening of finalized Q3 VAT, no Supplier/AP workspace redesign,
  and no claim that the complete Finance module is now production-ready.
