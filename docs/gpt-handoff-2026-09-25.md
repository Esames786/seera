# Seera — status handoff for the backend architect, 25 September 2026

Paste this into the existing GPT planning conversation. It updates the 24 September handoff (`docs/backend-handoff-august-september-2026.md`) with what was built on 24–25 September and where the server actually stands.

## Where the code is

| Place | State |
|---|---|
| `origin/feature/seera-connected-workspaces-2026-09-23` | `8bb9065` — four new commits on top of `2d0f9d5` (below). Full suite 230 tests / 2,236 assertions passing. |
| `main` | `9e295b7` (21 September release). Nothing from the feature branch is merged. |
| Production server (`~/seera`, owner's terminal 25 Sep) | **Deployed at `7b4c7f7`** on the feature branch on 25 September: fast-forward from `2d0f9d5`, `migrate --force` reported nothing to migrate, `public/build.zip` re-extracted, `optimize`, `up`. Verified from outside afterwards: the live manifest serves `app-B6M69JbN.js`, `app-BS5RzxR6.css` and `erp-Bd96n4JM.css` (the new bundle) and the login page renders. The server keeps a local modification to `public/.htaccess` that must not be overwritten. |
| Production database (owner's terminal, 25 Sep, read-only) | All migrations ran, latest batch 4 = `2026_09_23_000001`. Row counts: users 14, employees 1 (linked to a user 1, all have a salary structure), suppliers 1, customers 1, supplier bills 1, customer invoices 1, journal entries 2, chart of accounts 25, marketing leads 0, activity logs 84. Last data activity was 21 September by user 2 (Omar): supplier payment of SAR 30,000 on `Bill-001-2026` and **VAT period Q3 2026 finalized**; 22–24 September show logins only. This is a lightly used test dataset, not live books. |

## What changed on 24–25 September (all on the feature branch)

| Commit | Requirement | Change |
|---|---|---|
| `f12e228` | R24-01 | Employee list: **View / Edit / Deactivate** as separate actions by permission; the name opens the read-only page; "Open" removed. View carries no edit controls or HR forms and writes nothing. Deactivate keeps history and leaves the linked login untouched. |
| `f12e228` | R24-02 | Linked **User** card on the employee View/Edit pages and linked **Employee** card on the user View/Edit pages, resolved from `employees.user_id` only. View/Edit destinations appear only inside the actor's Users/HR permissions and access scope; unlinked, unavailable and inconsistent (two employees on one user) states are shown without disclosing a name or URL. The account-creation search now names the existing account for a linked employee. Side effect: the Users list and pages are scope-filtered for project, site and warehouse actors. Ten regression tests. |
| `a028b00` | R24-06, R24-07 | Role form and Permission Matrix share one catalogue: all 15 actions with shared labels (`Permission::ACTIONS`, `Permission::ACTION_LABELS`). A save can only revoke permissions it displayed; a request without the visible list can add but never remove. Sticky module column and header on both matrices, RTL-aware. Five tests. |
| `200d178` | R24-03 (Task B) | `docs/finance-readiness-audit-2026-09-24.md`: per-area readiness (Implemented / Tested / Client-accepted / Missing / Blocked by decision), findings F01–F12, the FIN-01…10 acceptance pack for 30 September, dates and decisions. Re-verified against source on 25 September (§11). |
| `8bb9065` | release | Rebuilt `public/build.zip` (root-level `assets/` + `manifest.json`), deployment guide §12, feedback register statuses, sprint report `docs/sprint-status-2026-09-24.md`, `.gitignore` rules so private recordings, review HTML and transcript ledgers cannot be committed. |

Nothing in Finance was changed. No migration was added. No production action was taken.

## Finance position in one paragraph

AP, AR, manual journals, VAT rows, cash/bank postings, stock-to-accounting hooks and the financial reports exist and have passing tests, but no end-to-end journey is client-accepted. Confirmed blockers: approval can leave a draft, possibly unbalanced, journal beside an "unpaid" document (F01); payments and receipts have no idempotency key (F02); a finalised VAT period is not sealed against late entries (F03); a GRN and a manually entered bill both credit AP and input VAT (F04); GL running balance and report openings are wrong across pages and ranges (F06); journal headers and CSV export are not scope/permission-checked (F07); approval is single-actor everywhere, the workflow definitions are never evaluated (F08); payroll approval writes no journal and ZATCA retry only flips a local flag (F12). Proposal: 26–30 September correctness sprint on F01/F02/F03/F06/F07/F11, then a bounded ten-scenario demonstration on a synthetic staging company on 30 September, or an evidence review with named blockers if the fixes do not land.

## Decisions requested from you (do not re-ask "must all approvers approve" — yes, settled)

1. Confirm the FIN-01…10 minimum for 30 September and whether it is a demonstration or authority to transact.
2. Approval runtime design: approvers per request type, parallel or sequential, thresholds, self-approval, delegation, rejection/resubmission, treatment of already-pending requests.
3. GRN versus supplier invoice: when liability and input VAT are recognised and how they are matched.
4. VAT: default 15% and override authority, categories/zero-rate, rounding, period close, credit notes.
5. Immediate posting versus review-mode journals with an explicit unposted state.
6. Payroll-to-GL and live ZATCA: in scope for go-live or not; who supplies ZATCA sandbox credentials.
7. Next connected workspace: Supplier/AP first, then Customer/AR (Customer View stays read-only).
8. Walkthrough (R24-04): video or written, language, audience.

## Next three batches (planning targets)

1. 26–30 September: Finance correctness sprint and evidence gate (F01, F02, F03, F06, F07, F11), patch plan presented before code.
2. 1–9 October: Supplier/AP connected workspace, after the F04 decision; Approve, Pay and Reopen stay explicit.
3. 10–16 October: Customer/AR workspace, then the all-required approval runtime as its own schema/runtime patch. 17–31 October: remaining Finance gaps, payroll-to-GL if commissioned, Arabic/RTL pass, freeze for the 31 October development target.

## Deployment record

Deployed on 25 September with the guide's §12 steps (`down`, fetch, pull `2d0f9d5 → 7b4c7f7`, `migrate --force` = nothing to migrate, re-extract `public/build.zip` into `public/build`, `optimize`, `up`). Rollback if ever needed: `git checkout 2d0f9d5`, extract that commit's bundle the same way, `optimize`. No schema change was involved.

## Two observations from the database that affect the Finance plan

1. **VAT period Q3 2026 is already finalized** in the test data (21 September). With finding F03 a finalized period does not stop new postings, and the reopen-for-correction path refuses to withdraw VAT from a finalized period, so further September test transactions will behave oddly. Either treat everything entered so far as disposable test data to be cleared before UAT, or add a Super Admin "reopen period" action in the Finance correctness sprint. There is no such action today.
2. The dataset is one employee, one supplier, one customer, one bill (paid 30,000), one invoice and two journals. The FIN-01…10 acceptance run needs a synthetic staging company with a documented chart of accounts and zero openings; it should not be run on top of this data.

## Security note for the owner (not for GPT)

The database password is identical to the database user name and has now been pasted into chat, as were the `APP_KEY` and password on 18 September. Change the MySQL password in cPanel, update `.env`, run `$PHP artisan optimize`, and make sure cPanel "Remote MySQL" allows no external hosts. Rotate `APP_KEY` only with a planned session reset, since it invalidates encrypted data and logins.
