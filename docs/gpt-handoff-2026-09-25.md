# Seera — status handoff for the backend architect, 25 September 2026

Paste this into the existing GPT planning conversation. It updates the 24 September handoff (`docs/backend-handoff-august-september-2026.md`) with what was built on 24–25 September and where the server actually stands.

## Where the code is

| Place | State |
|---|---|
| `origin/feature/seera-connected-workspaces-2026-09-23` | `8bb9065` — four new commits on top of `2d0f9d5` (below). Full suite 230 tests / 2,236 assertions passing. |
| `main` | `9e295b7` (21 September release). Nothing from the feature branch is merged. |
| Production server (`~/seera`, owner's terminal 25 Sep) | Code at **`2d0f9d5`** on the feature branch; the four new commits are **not pulled yet**. Migrations through `2026_09_23_000001` ran on 24 September. The live asset manifest serves `app-D3f81AIn.js`, a bundle built at 02:11 on 24 September that is **older than the branch's JavaScript** (it lacks the unsaved-navigation guard and the linked-account rendering); the new `public/build.zip` in `8bb9065` replaces it. |
| Production database | Not queried from the development machine (no remote DB access is used from there). The owner runs the three read-only commands at the end of this note to report row counts and recent activity. |

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

## Deployment of the four commits (owner decides; from the guide §12)

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan down
git fetch origin
git checkout feature/seera-connected-workspaces-2026-09-23
git pull --ff-only          # 2d0f9d5 -> 8bb9065
$PHP artisan migrate --force  # expected: Nothing to migrate
rm -rf public/build && mkdir -p public/build
unzip -oq public/build.zip -d public/build
$PHP artisan optimize
$PHP artisan up
```

Rollback: `git checkout 2d0f9d5`, extract that commit's `public/build.zip` the same way, `optimize`. No schema change.

## Database facts: run these on the server and paste the output

```bash
cd ~/seera; PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan migrate:status | tail -n 6
$PHP artisan tinker --execute="echo 'users=',App\Models\User::count(),' employees=',App\Models\Employee::count(),' linked=',App\Models\Employee::whereNotNull('user_id')->count(),' no_structure=',App\Models\Employee::whereDoesntHave('salaryStructures')->count(),' suppliers=',App\Models\Supplier::count(),' customers=',App\Models\Customer::count(),' bills=',App\Models\SupplierBill::count(),' invoices=',App\Models\CustomerInvoice::count(),' journals=',App\Models\JournalEntry::count(),' coa=',App\Models\ChartOfAccount::count(),' leads=',App\Models\MarketingLead::count(),' logs=',App\Models\ActivityLog::count(),PHP_EOL;"
$PHP artisan tinker --execute="App\Models\ActivityLog::latest('id')->limit(10)->get(['created_at','user_id','module','action','description'])->each(fn(\$l)=>print(\$l->created_at.' u'.\$l->user_id.' '.\$l->module.' '.\$l->action.' '.\$l->description.PHP_EOL));"
```

## Security note for the owner (not for GPT)

The database password is identical to the database user name and has now been pasted into chat, as were the `APP_KEY` and password on 18 September. Change the MySQL password in cPanel, update `.env`, run `$PHP artisan optimize`, and make sure cPanel "Remote MySQL" allows no external hosts. Rotate `APP_KEY` only with a planned session reset, since it invalidates encrypted data and logins.
