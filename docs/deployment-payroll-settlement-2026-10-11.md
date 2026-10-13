# Deployment — Payroll Phase 2: salary settlement + bank transfer export, 11 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md) and [deployment-payroll-gl-2026-10-10.md](deployment-payroll-gl-2026-10-10.md).

Brings the server from the Payroll → GL HEAD (`2aa453c`) to the Payroll Phase 2 HEAD. This release has
**one additive migration** and **no asset change** (the committed `public/build.zip` is still the GPS release bundle).

| Item | Value |
| --- | --- |
| Migration | `2026_10_11_000001_add_payroll_settlement` — creates `payroll_settlements` and `payroll_bank_exports`; adds `payment_status` (default `not_paid`), `paid_amount`, `settled_at` to `payroll_runs`. Re-runnable (guards on `hasTable` / `hasColumn`); existing rows untouched and read *Not paid*; verified on MySQL 8 (fresh, additive and rerun) and SQLite. |
| Seeder | None. Uses the existing Cash in Hand (1110) / Bank Account (1120) accounts and the Payroll posting rule from Phase 1. |
| Asset bundle | Unchanged: `public/build.zip`, 189,897 bytes, 18 files, SHA-256 `25B77A8DEAC96BDB4BFE5646AA6BE800B30F12FA984CCC93DB01FF2901850A4E`. `npm run build:release` reproduced the same assets (`app-CS691W_a.js`, `app-R27YIm39.css`, `erp-BneCgvYl.css`); no re-extraction needed if the GPS bundle is already in place. |
| New routes | `POST /admin/hr/payroll/{id}/settle` (Record Salary Payment; Payroll → post **and** Accounts Payable → process, whole run in scope), `POST /admin/hr/payroll/{id}/settlement/reverse` (plus Payroll → approve), `POST /admin/hr/payroll/{id}/bank-export` and `GET /admin/hr/payroll/{id}/bank-export/{export}` (Payroll → export, whole run in scope). |
| Behaviour change | Post / Retry / Reverse Posting now also require the acting user to see every employee of the run (company scope or a project that covers all of them); Reverse Posting is refused while a salary payment is recorded; the employee form refuses an invalid IBAN (existing stored IBANs are not touched, but an invalid one blocks the bank export until corrected); Employee View and payslip show the IBAN masked. |

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php

$PHP artisan down
git fetch origin
git checkout feature/seera-connected-workspaces-2026-09-23
git pull --ff-only
$PHP artisan migrate --force          # expects exactly: 2026_10_11_000001_add_payroll_settlement
sha256sum public/build.zip            # see Asset bundle above
test -f public/build/manifest.json && echo "assets ok"
test -f docs/user-guide/index.html && echo "user guide ok"
$PHP artisan optimize
$PHP artisan up
```

Role configuration before use (Roles & Permissions, no seeder): the Finance role that records salary payments needs Payroll → post **and** Accounts Payable → process (Finance Manager already has Accounts Payable process; give it Payroll post if it should pay); Reverse Salary Payment additionally needs Payroll → approve; the role that generates the bank file needs Payroll → export (Finance Manager and HR Manager already have it).

Post-release checks (read-only unless stated):

1. Open an approved, posted payroll run: the header shows the third badge *Not paid*; the Salary Payment section shows payable / paid / remaining and, for the Finance role, the Record Salary Payment form with 1110 / 1120 only.
2. Bank Export section: readiness counts and, if any, the employees to fix (bank name / IBAN). A run approved before this release can still be exported once its employees' bank details are complete.
3. A Payroll-view-only user sees the payment state but no account, reference, forms or export; a project-scoped user on a company-wide run sees the "outside your scope" note and gets 403 on the actions.
4. Employee View → Payroll Information shows the IBAN masked (SA•• •••• 1234); the edit form shows the full value.
5. `https://seera.tech-brit.co.uk/user-guide/` shows version 1.12; WF-022 is present.
6. (Writes data; agreed acceptance window only.) On a training run: Generate bank transfer file → download → Record Salary Payment from 1120 → the journal under Finance → Journal Entries shows Dr 2300 / Cr 1120 for the net; the Project Cost Report is unchanged; Reverse Posting is refused until Reverse Salary Payment is done.

Rollback requires a verified private backup and review of the actual deployed HEAD. Keep the additive settlement/export tables and columns; they preserve payment, reversal and bank-file audit history. Do not routinely run `migrate:rollback --step=1`; it can orphan journals or target a later migration. Code rollback to `2aa453c` is only a candidate after reviewing subsequent changes and existing paid/reversed runs and obtaining approval. Preserve server configuration/uploads, use matching assets, clear/rebuild caches and smoke-test before bringing the application up.
