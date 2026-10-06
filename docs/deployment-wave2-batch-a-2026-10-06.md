# Deploy Wave 2 Batch A — Users + Sites

Target branch: `feature/seera-connected-workspaces-2026-09-23`. Baseline: `50135961bdacb4e9a9e40d835d2eb0816bf925b7` (Supplier Bill runtime release). This document is an owner-run procedure, NOT evidence of deployment. No merge required.

## Before maintenance

Confirm the release's pushed HEAD and green gates in `workspace-wave2-batch-a-2026-10-06.md` / delivery report. Take and verify a cPanel database backup plus the current application/files backup. Preserve private uploads, `.env`, and the server's customized `public/.htaccess`.

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
test "$(git branch --show-current)" = "feature/seera-connected-workspaces-2026-09-23" || { echo "STOP: wrong branch"; exit 1; }
git status --short
git log -1 --oneline
$PHP artisan migrate:status
BACKUP_DIR=$(mktemp -d /home/techbrit/seera-batch-a.XXXXXX)
chmod 700 "$BACKUP_DIR"
git rev-parse HEAD > "$BACKUP_DIR/previous-head.txt"
git diff -- public/.htaccess > "$BACKUP_DIR/htaccess.patch"
cp -p public/.htaccess "$BACKUP_DIR/htaccess"
cp -p .env "$BACKUP_DIR/env"
cp -a public/build "$BACKUP_DIR/build"
echo "$BACKUP_DIR"
```

Use these only on the named Seera checkout. Keep the backup path private and outside the web root. Existing untracked `composer.phar`, private environment backups or log files are not reasons to reset/clean the checkout. Do not stage production changes. If other tracked source files are changed, stop and review them first. This release does not change `public/.htaccess`, so that specific local customization should remain untouched by the fast-forward; stop if Git reports a conflict.

## Apply verified release

Run one command at a time; stop on ANY error. Do not run `up` after a failed deploy step.

```bash
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
git log -1 --oneline
git status --short
$PHP composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
$PHP artisan optimize:clear
unzip -t public/build.zip
sha256sum public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
$PHP artisan view:cache
$PHP artisan migrate:status
$PHP artisan up
```

Compare HEAD to the delivered/pushed release before Composer. Compare ZIP checksum to the verification ledger **before extraction**. The archive has `manifest.json` and `assets/` at its root; extract to `public/build`, not `public` or `public/build/build`. The developer supplies a rebuilt tracked ZIP, so **Node/npm is not required on cPanel**. If `composer.phar` is absent, use your verified cPanel Composer executable with PHP 8.3; do not download random installers during downtime.

**Migrations:** none added by Batch A. If the baseline's Site Expense / Supplier Bill migrations are all Ran, there is no new migration to execute. If `migrate:status` shows older pending migrations, stop and follow that prior release's deployment instructions/backups; do not silently treat an older database as ready.

**Seeders:** NO production seeder. Never use `migrate:fresh`, `db:wipe`, demo seeders or broad database reset. No new automatic role grants or workflow policies.

**Caches:** `optimize:clear` then `view:cache` is sufficient here. Do not use blanket `artisan optimize` as an untested extra step; it previously coincided with the owner's 500 incident. Configuration/route caching is a separate deployment choice, not required by this release. No queue/job/service was added, and the existing queue deployment procedure remains unchanged.

## Owner acceptance (staging/training records first)

1. Dashboard, `/user-guide`, User View/Manage and Site View/Manage load. Hard refresh once; check browser asset errors and private Laravel logs if anything fails. Keep `APP_DEBUG=false` publicly.
2. User profile Save stays; Save & Close returns; Save & New opens blank; related section saves refresh the header. Tab/Previous/page departure warns about unsaved input. Validation failure retains input.
3. User Roles: profile edit preserves temporary/additional assignments; changing primary retains the old role as additional until explicitly removed. Test access with a restricted training login, not only Super Admin.
4. Employee search/link uses the actual FK, supports unlink/re-link, and does not grant scope/mobile/roles. Check duplicate/foreign employee rejection.
5. Site View has no mutation controls, except links to separately authorized screens. Child tabs/URLs deny missing permissions; hidden Site/Project records are neither counted nor listed.
6. Site master Save & Close from Project returns to Project → Sites. Existing Project ownership cannot be changed in this UI. Map is configuration only; no GPS/offline attendance claim.
7. Site → Expenses → Add locks Site/Project. Use a training draft; verify Save/Close and Submit return links. Existing approvals/posting remain separate actions.
8. Verify English and Arabic/RTL with real screen sizes. Older underlying forms retain partial translation coverage. Browser automation in development uses local mock HTTP; it is not owner business acceptance or a live deployment test.

## Recovery

Keep maintenance on if validation fails; capture the sanitized error and use a reviewed forward fix. Batch A itself has no schema rollback. **Do not blindly switch back to 5013596:** its historical User edit can wipe additional/temporary role assignments. If a UI rollback is needed, retain the role-integrity fix or freeze User edits under a reviewed rollback plan. Do not roll back Supplier Bill/Site Expense migrations or replace the database with an old backup after new transactions. File/database restoration needs explicit owner review of intervening business data.

No production access/deployment, merge, or Wave 2 Batch B work is performed by this sprint.
