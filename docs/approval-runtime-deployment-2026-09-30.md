# F08 release instructions — not deployed by this sprint

Branch: `feature/seera-connected-workspaces-2026-09-23`. Start: `9f8a045`.
Use the final pushed commit from the sprint handoff and Git history; measured gates
are in `approval-runtime-verification-2026-09-30.md`. Do not switch to main or discard
host-specific `.htaccess` changes.

## Before the owner deploys

- Back up the database, `.env`, `public/.htaccess`, deployed build and current commit
  **outside the public web root**. Verify the backup is usable. Do not publish credentials
  or client recordings. Existing approved data and all previous runtime audit must survive.
- Confirm clean/reviewed Git state and the correct branch. Existing modified build.zip
  may block pull: preserve it outside the repository before explicitly resolving that
  single tracked-file conflict. Do not run reset/clean against unrelated local files.
- Build is prepared locally with `npm run build:release`: Vite production assets plus
  verified `public/build.zip`, containing `manifest.json` and `assets/` at archive root.
  It is not a ZIP of the whole application. No Node build on cPanel is needed when using it.
- New additive migration: `2026_09_30_000001_create_approval_runtime_tables`. It adds
  two runtime tables, PR `approval_mode` with historical default `legacy`, and the
  Approval History/view permission granted to existing PR-view roles. It does not
  manufacture decisions, change business document statuses or post accounting.
- **No production seeder is required. Do not run DatabaseSeeder, migrate:fresh,
  migrate:refresh or a demo reset.** No Composer dependency change is introduced.

## cPanel commands (owner execution only)

Run one command at a time, stop if any command fails:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
git status --short
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
git log -1 --oneline
$PHP artisan migrate --force
$PHP artisan migrate:status
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
$PHP artisan optimize:clear
$PHP artisan up
```

This intentionally clears caches instead of assuming `artisan optimize` is safe on
the host (the earlier deployment's 500 resolved after cache clearing). Optimize cache
generation separately only after verifying environment/configuration on that server.
If migration or cache clearing fails, keep maintenance mode and diagnose from private logs.

## After deployment

1. Confirm existing Employee, Supplier, Customer, Project and P2P pages still open.
2. Roles → Approval Workflows: configure a valid PR workflow deliberately. Seed examples
   with amount limits are not executable by F08. Use blank limits, No Auto Posting,
   matching department/scope and eligible assigned role holders. Do not remove thresholds
   from a real policy without owner approval; keep new PR submission blocked until agreed.
3. Verify required approvers' PR view/approve (and reject where intended) and scope.
   Approval History/view is independently configurable. No Super Admin bypass.
4. Use a clearly designated test PR: save → submit → first approve (still pending) →
   final required approve (approved). Check My Approvals changes with current step.
   Test rejection/correction/resubmission and inspect preserved history.
5. No journal, VAT or stock posting should result from PR approval. Do not perform
   financial test transactions against live books without separate authorization.

## Rollback caution

Rolling source back alone after new runtime approvals exist would restore older
single-actor PR logic and can bypass the new control. Prefer a forward fix. If rollback
is necessary, maintenance mode + owner-approved matched code/database backup recovery
is required, accounting for any transactions created since backup. Do not blindly
run migrate:rollback: down removes the runtime tables/audit. It intentionally leaves
the permission catalogue entry in place to avoid deleting role grants on schema rollback.

F08 does not integrate PO, Supplier Bill, Customer Invoice, HR or Payroll with the
runtime, and does not implement Site Expense posting. No merge or deployment was
performed by the development sprint.
