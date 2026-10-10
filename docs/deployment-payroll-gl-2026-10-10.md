# Deployment — Payroll Completion + Payroll → GL, Phase 1 (accounting posting + payslip), 10 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md).

Brings the server from the GPS attendance HEAD (`1670772`) to the Payroll → GL HEAD. This release has
**one additive migration** and **no asset change** (the committed `public/build.zip` is the GPS release bundle).

| Item | Value |
| --- | --- |
| Migration | `2026_10_10_000001_add_payroll_accounting_state` — adds to `payroll_runs`: `accounting_status` (default `not_posted`), `journal_entry_id`, `posted_at`, `posting_error`, `reversal_journal_id`, `reversed_at`, `reversal_reason`; adds `deduction_account_id` to `automatic_posting_rules`. Re-runnable (guards on `hasColumn`); existing rows untouched; verified on MySQL 8 (fresh, additive and rerun) and SQLite. |
| Seeder | None. The existing *Payroll / Payroll Approved* posting rule (debit 5100 Salary Expense, credit 2300 Salary Payable) is reused as the mapping; the new **Deduction Liability Account** on that rule must be chosen by Finance before a run with deductions can post. |
| Asset bundle | Unchanged: `public/build.zip`, 189,897 bytes, 18 files, SHA-256 `25B77A8DEAC96BDB4BFE5646AA6BE800B30F12FA984CCC93DB01FF2901850A4E`. `npm run build:release` reproduced the same assets (`app-CS691W_a.js`, `app-R27YIm39.css`, `erp-BneCgvYl.css`); no re-extraction needed if the GPS bundle is already in place. |
| New routes / screens | `POST /admin/hr/payroll/{id}/post` (Post to Accounting / Retry; Payroll → post), `POST /admin/hr/payroll/{id}/reverse` (Reverse Posting; Payroll → post + approve), `GET /admin/hr/payroll/{id}/items/{item}/payslip` (HR-PAY-005; Payroll → view). |
| Historical runs | Runs approved before this release keep `accounting_status = not_posted` and show *Approved — not posted*; Finance may post them with **Post to Accounting** once the rule is complete (one journal per run; a run that already has a Payroll journal from earlier data is linked, not duplicated). |

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php

$PHP artisan down
git fetch origin
git checkout feature/seera-connected-workspaces-2026-09-23
git pull --ff-only
$PHP artisan migrate --force          # expects exactly: 2026_10_10_000001_add_payroll_accounting_state
sha256sum public/build.zip            # 25B77A8D…850A4E (unchanged since the GPS release)
test -f public/build/manifest.json && echo "assets ok"
test -f docs/user-guide/index.html && echo "user guide ok"
$PHP artisan optimize
$PHP artisan up
```

Finance configuration before the first posting (FIN-PR-004, rule *Payroll / Payroll Approved*):

1. Debit Account = the salary expense account (active, type expense).
2. Credit Account = the payroll payable account (active, type liability). Never Bank / Cash.
3. Deduction Liability Account = the account that holds employee deductions (active, type liability). Required as soon as any employee has a deduction.
4. Auto Post: on = journal posted at approval; off = draft journal for Finance review (the run then reads *Approved — accounting review required*).
5. Every employee's project needs exactly one active project cost center (5100 Salary Expense is marked *Cost center required* in the standard chart); otherwise posting is refused with the employee named.

Post-release checks (read-only unless stated):

1. Open HR Registers → Payroll → an approved run: the header shows the accounting state; the Accounting section shows the state, journal (if any), posting date and totals.
2. Open an employee row → **Payslip**: the stored amounts print; **Print / Save as PDF** opens the browser print dialog.
3. A user with HR → view only cannot open the payslip (403); a user without Payroll → post does not see Post to Accounting.
4. `https://seera.tech-brit.co.uk/user-guide/` shows version 1.11; HR-PAY-005 and WF-021 are present.
5. (Writes data; agreed acceptance window only.) Approve a processed training run: the journal appears under Finance → Journal Entries with one salary expense line per employee, one payroll payable line and one deductions line; the Project Cost Report shows the gross on the employees' project.

Rollback: `git checkout 1670772` and `$PHP artisan optimize`. The migration can stay (nullable / defaulted columns are harmless) or be reversed with `php artisan migrate:rollback --step=1` only if no payroll run has been posted yet (the rollback drops the journal links, not the journals).
