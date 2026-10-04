# Supplier Bill approval runtime — deployment instructions

4 October 2026. Feature branch: `feature/seera-connected-workspaces-2026-09-23`.
Baseline: `eedc786`. This is a deployment guide, NOT evidence of production deployment.
Release verification and commit identifiers: see `supplier-bill-runtime-2026-10-03.md` and the delivery report.

## Before maintenance

- Take a verified cPanel database backup and private filesystem backup (including `.env`, `storage/app`, `public/.htaccess`, existing `public/build` and the current commit ID). Keep secrets/backups outside `public`. Do not paste credentials into tickets or commit them.
- Confirm the server is on the named feature branch and inspect `git status --short`. Preserve the server-specific `public/.htaccess`; stop and review if an incoming change conflicts with it. Do not reset the whole worktree or stash secrets indiscriminately.
- Ensure the complete release has been pushed and review `git log HEAD..origin/feature/seera-connected-workspaces-2026-09-23`. `Already up to date` is not proof that an unpushed local release exists on the server.
- PHP 8.3 with the existing project extensions and a trusted `composer.phar` is required. This sprint adds no Composer dependency, but the locked install below regenerates optimized autoload metadata for the new PHP classes (it does not run dependency update). If your Composer executable is elsewhere, resolve its trusted path before maintenance.
- `public/build.zip` is the release-built Vite package. No Node build on cPanel is necessary. Archive root is `manifest.json` plus `assets/`; compare its SHA-256 with the delivery report.
- Never run `migrate:fresh`, `db:wipe`, demo seeders or broad reset/reseed commands on production. No production seeder is required for this release.

Read-only preflight:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
git status --short
git fetch origin refs/heads/feature/seera-connected-workspaces-2026-09-23:refs/remotes/origin/feature/seera-connected-workspaces-2026-09-23
git log --oneline HEAD..origin/feature/seera-connected-workspaces-2026-09-23
git diff --name-only HEAD..origin/feature/seera-connected-workspaces-2026-09-23
$PHP artisan migrate:status
```

## Release commands

Only after backups and preflight are satisfactory. Run the block as a subshell; a failed command stops it and leaves maintenance on for investigation. Do not continue blindly after an error. If tracked `public/build.zip` has a server upload modification, preserve that exact file in the private backup before resolving that one conflict; do not discard other files.

```bash
(
set -e
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
test "$(git branch --show-current)" = "feature/seera-connected-workspaces-2026-09-23"
test -f composer.phar
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
$PHP composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
unzip -t public/build.zip
sha256sum public/build.zip
mkdir -p public/build
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
$PHP artisan optimize:clear
$PHP artisan migrate --force
$PHP artisan migrate:status
$PHP artisan view:cache
$PHP artisan up
)
```

Do not run blanket `artisan optimize` as a requirement: the earlier server 500 was resolved by `optimize:clear`. This guide leaves configuration/routes uncached unless separately verified on this hosting environment. If `view:cache` fails, remain in maintenance and inspect the error/log without enabling public debug output. Existing queues/cron, private receipt storage and storage links retain their previous configuration; no new worker or service is introduced.

New migration: `2026_10_03_000001_add_supplier_bill_approval_state`. It adds nullable approval metadata, default legacy mode, a match reservation marker and missing Accounts Payable permission catalogue rows. Existing bill amounts, payments, journals and approval history are not rebuilt. It is additive and rerun-aware; its down path intentionally refuses destructive rollback.

## Administrator configuration — required before new bill approvals

1. Administration → Roles / Permission Matrix: review Accounts Payable **view**, **create**, **edit**, **approve**, **reject**, **post**, **retry**, **process**. Migration adds missing catalogue rows but grants no new authority automatically. Post + retry are both needed for Retry Posting; process remains payment authority. Journal Entries view + post are separately required to post a review journal.
2. Administration → Approval Workflows: create/review an active **Supplier Bill** workflow, trigger **Bill Submitted**, posting **Create Accounting Entry**. Use All Company, All Projects or Assigned Project/Site as appropriate; the latter two require a project. Configure every required approval as a separate ordered step. Leave amount limits blank. Branch Level, parallel groups and thresholds are unsupported and must not be treated as active routing.
3. Each required role/user must be active, have Accounts Payable view + approve and access to the bill's project/site. Reject additionally requires reject. The creator/requester, submitting editor and last bill editor cannot approve. At least one eligible independent reviewer is needed for each required slot; workflow configuration permission is not approval authority.
4. Review the existing **Supplier Bill / Bill Approved** Automatic Posting Rule. Auto Post on posts the journal; off/missing active rule creates a draft review journal. Runtime approval does not bypass that rule. Payment is blocked until the journal is actually posted.
5. Existing posted/paid bills retain history without new runtime rows. Existing legacy drafts enrol only through explicit Submit. Newly created bills and new Site Expense credit bills require runtime; missing workflow blocks rather than falls back to legacy approval.

## Smoke checks (authorized training/staging records first)

- `/user-guide`, dashboard, Supplier Bill register/details and My Approvals load with the new assets; no 500 in private logs.
- Bill shows independent financial and approval states. Save creates a draft, not an approval or posting. Submit freezes lines/matches; only the current eligible required step appears in My Approvals.
- All required steps complete before accounting. Failure preserves approved history; retry creates no duplicate journal/AP/VAT/GRN consumption. Review journal cannot be paid before Journal Post.
- Supplier/Project/PO context displays approval status. Profile Save never submits or approves a bill.
- Site Expense credit links to one bill, requires its separate approval and has no second AP journal. Existing Cash/Bank/Reimbursement expense paths remain available.
- An unpaid posted runtime bill with no payments can be reopened only under existing Super Admin/reversal/VAT rules; original approval remains and correction needs a new attempt.

## If release fails

Keep maintenance on, capture the failing command and sanitized log, and use a forward fix. Do not run migration rollback: approval history and reservations are financial control data. After runtime records exist, checking out old code alone can bypass the new controls. Any full restoration needs a coordinated, authorized database + files/code backup recovery plan with explicit review of transactions recorded since the backup. Do not automatically erase those transactions.

No merge, production database connection or deployment is performed by the development sprint. Wave 2 is a recommendation only, not part of these commands.
