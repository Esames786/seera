# November client feedback — deployment preflight

Preparation only. No production deployment, migration, seeder, cleanup, permission change or ZATCA activation was performed by the development run.

- Branch: `feature/seera-client-feedback-fixes-2026-11-10`.
- Reviewed application commit: `2da7886b3e93fdf59a15198f8507c45d64e6ff91`; later closure commit is documentation/evidence only.
- Expected base from local Git and owner-reported live state: `0aed8c791e4cd214d23a916026860585983d1cca`. Recheck actual server HEAD; do not infer deployment from GitHub.
- Build ZIP SHA-256: `F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7`, 18 verified entries.
- QA: [implementation and evidence report](release-readiness/SEERA-CLIENT-FEEDBACK-FIXES-2026-11-10.md).
- User instructions: [entry workflow supplement](user-guide/CLIENT-ENTRY-UPDATES-2026-11-10.md).

## First, read-only checks

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
git log -1 --oneline
git status --short
$PHP artisan migrate:status
```

Review local edits and preserve `.env`, `public/.htaccess`, any server build changes and untracked files. Fetching the feature branch for review is read-only for the working tree; it is not approval to switch/deploy. Do not use reset/clean, discard the server .htaccess, or run the broad go-live reset.

## Required installation boundary

Unlike the earlier code-only reset release, **this release needs schema changes**:

1. `2026_11_10_000001_add_profile_photos_and_party_documents`
2. `2026_11_10_000002_create_user_permission_overrides`

They add nullable photo paths, empty Customer/Supplier document tables and an empty user-exception table. No historical backfill, role re-grant, master seeder or test-data reset is required.

After explicit owner deployment approval, rehearse with a disposable restored database, then use the established controlled production procedure: verified restorable DB and private-file backups, pause/drain writers, maintenance mode, preserve rollback files, install the pinned code, refresh the optimized Composer classmap if production uses one, clear Laravel caches, apply the two reviewed pending migrations, extract the verified build, cache views, restart approved workers and verify before returning live. Keep the site in maintenance if any step fails.

Do not serve authenticated pages between switching code and applying both migrations: authorization reads `user_permission_overrides` and forms read the new document tables. No production seeder or npm build is needed; use the packaged build.

The asset verification/extraction commands for that approved procedure are:

```bash
echo 'F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7  public/build.zip' | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
```

This is a preflight, not a copy/paste authorization to execute production migrations. Confirm the exact pending migration list against the installed candidate before the owner executes the maintenance procedure. Production ZATCA must remain disabled; no signing-unit/credential change belongs to this release.

## Smoke checks after an approved rollout

- Login and Dashboard load with the new schema; verify the existing notification bell and guide still work.
- Equipment Create with blank/zero/positive meter and initial document: no 500; save/view/download and existing assignments/fuel/maintenance still work under their existing permissions.
- Customer/Supplier successive automatic codes and Save & New; deliberate custom code; initial documents and View/Manage. Read-only View has no upload or data-write form.
- Employee and User photo upload/replace/remove, unauthorized file read refused, correct language/layout.
- My Leave: a correctly linked ordinary account sees only its own requests, can submit Pending and cannot forge an employee/approval; Annual-only selected-year balance matches approved records.
- Super Admin changes one user's exceptions with a reason; Deny applies only to that user, inheritance restores defaults, a stale form is refused, direct non-admin requests cannot change exceptions or elevate to Super Admin.
- Confirm no historical GL/AP/AR/VAT/payroll/stock balances changed. Development reconciliation tests are not live Finance sign-off.

## Cleanup is a separate reviewed operation

The owner confirmed **inventory first, then review/deactivation**, not another reset. Once the approved code is installed:

```bash
umask 077
$PHP artisan seera:entry-audit --json > "$HOME/seera-entry-audit-private.json"
```

Review the exact client login and Super Admin IDs, recently entered real masters, transaction/approval/audit references and any missing Cost Center links. The command is strictly read-only. Do not infer demo status from a timestamp/name or deactivate every account except a guessed email. An approved itemized list and a verified backup are needed before the separate deactivation step; physical upload cleanup is not included.

## Recovery

Keep pre-release Git revision, private files, .env/.htaccess and matching assets. These schema changes are additive; an old application code rollback may leave the new tables/columns present, preserving newly saved data. Do not drop the new tables or blindly run `migrate:rollback` after real photos/documents/exceptions have been saved. Review data written since deployment before any database restore, and account for lost writes. Keep production ZATCA disabled during recovery as well.
