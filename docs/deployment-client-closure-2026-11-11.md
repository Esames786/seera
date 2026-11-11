# November client closure — cPanel deployment guide

Updated: 11 November 2026 (Asia/Karachi). This runbook is for the owner/operator; the client-facing November pages contain the issue/fix/screen table instead of deployment instructions.

## Status and approved source

- Branch: `feature/seera-client-closure-2026-11-11`.
- QA anchor: `aa0a73b093fd3b4ba77254d21153f47313c9fe00`; application code: `510d88a914545b839ba393323b195e4844b0b85d`.
- Evidence: 936 Laravel tests / 10,855 assertions; 12 JavaScript tests; 687 browser checks; 7 MySQL concurrency/accounting checks; 28 storage-copy checks across four runs. These are saved local results, not a fresh production check.
- Latest **owner-reported** live HEAD: `9cc5ea0179c6e104750fcb1249ec3f684194ad38`, branch `fix/seera-client-guide-backport-2026-11-10`. Actual live HEAD must be checked again.
- Reported live storage: mostly MyISAM with partial November DDL. The feature code requires its new tables. Serving it against the unprepared old database can cause errors.
- No seeder, reset, historical backfill or production ZATCA activation is part of this rollout.

Visual-guide follow-up verification: 5 User Guide tests / 99 assertions and 125 browser checks passed. The guide contains 21 real training screenshots with screen URLs. Deployment Bash blocks and embedded PHP guards were syntax-checked without execution. Application code and the verified ZIP remain unchanged from the QA anchor; the full suite was not needlessly repeated for this documentation update.

The two branches diverge after their stable ancestor. This is a reviewed **branch switch**, not a fast-forward merge from the guide-only branch. Preserve hosting `.htaccess`, `.env`, current assets, uploads and database history.

## 1. Run now: read-only preflight, no downtime

This block fetches Git objects and reads configuration/schema metadata. It does not switch code, run migrations or put the portal down. It prints no passwords or application key.

```bash
(
set -euo pipefail
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
BRANCH=feature/seera-client-closure-2026-11-11
QA_HEAD=aa0a73b093fd3b4ba77254d21153f47313c9fe00

git branch --show-current
git log -1 --oneline
git status --short
git fetch origin "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
git merge-base --is-ancestor "$QA_HEAD" "origin/$BRANCH"
printf 'Candidate: %s\n' "$(git rev-parse "origin/$BRANCH")"

"$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
if (!in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) { throw new RuntimeException('Unexpected database driver.'); }
$engines = $db->select('SELECT ENGINE AS engine, COUNT(*) AS tables_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? GROUP BY ENGINE', ['BASE TABLE']);
$objects = [];
foreach (['views' => ['VIEWS','TABLE_SCHEMA'], 'triggers' => ['TRIGGERS','TRIGGER_SCHEMA'], 'routines' => ['ROUTINES','ROUTINE_SCHEMA'], 'events' => ['EVENTS','EVENT_SCHEMA']] as $label => [$table,$column]) {
    $objects[$label] = (int)$db->selectOne("SELECT COUNT(*) AS n FROM information_schema.$table WHERE $column = DATABASE()")->n;
}
echo json_encode(['read_only' => true, 'database' => $db->getDatabaseName(), 'app_env' => app()->environment(), 'maintenance_driver' => config('app.maintenance.driver'), 'timezone' => config('app.timezone'), 'queue' => config('queue.default'), 'production_zatca_enabled' => (bool)config('zatca.production_enabled'), 'engines' => $engines, 'additional_schema_objects' => $objects], JSON_PRETTY_PRINT), PHP_EOL;
if (config('zatca.production_enabled')) { throw new RuntimeException('STOP: production ZATCA must remain disabled.'); }
if (config('app.maintenance.driver') !== 'file') { throw new RuntimeException('STOP: review the different maintenance driver before using this runbook.'); }
if (array_sum($objects) > 0) { throw new RuntimeException('STOP: additional SQL objects need an explicit export/preservation plan before copying.'); }
PHP
)
```

Review the output before scheduling a maintenance window. If HEAD, database name, unreviewed changes or storage state differ, revise the plan. MyISAM in this output means the copy/rehearsal steps below are required; it is not permission to run blanket ALTER or `migrate:fresh`.

## 2. Prerequisites before any maintenance

1. Obtain a full consistent database backup and demonstrate a successful isolated restore. With MyISAM, `--single-transaction` alone is not a consistency guarantee. Stop all web/cron/queue/external writers for the final snapshot. The earlier backup folder's existence does not prove restoration.
2. Rehearse this exact release on that restored data in an isolated environment: prepare a separate InnoDB destination, verify row/field hashes and foreign keys, reconcile GL/AP/AR/VAT/payroll/stock, then test the November entry and Finance workflows.
3. Keep rehearsal transactions out of the eventual production destination. A database used to post training payments is not promoted as the final live copy. Make a **fresh final copy** under the final write freeze.
4. Have cPanel/hosting create a separately named **empty** final destination and grant the application user access. The preparation tool needs schema/table privileges there. Do not use the live DB or another application's DB as the destination.
5. Review actual duplicate Employee codes, missing references, unique-key conflicts and any schema objects from preflight. A refused copy needs review; do not delete/conflict-ignore rows to force it through.
6. Approve the exact release, destination and maintenance window. Account/master cleanup is separate; no automatic deactivation/reset belongs to this deployment.

The remaining blocks are conditional operator steps after these prerequisites. They have not been run on production by the developer.

## 3. Pin the release and prepare a private candidate checkout

### If the private candidate was already prepared at 063c42f

The owner reported `/home/techbrit/seera-november.mAuOQH/candidate`. To include the screenshot walkthrough, this block updates **that private checkout only** to its tested visual-guide commit. It does not switch `~/seera`, run migrations or activate the website. Use it only for that exact unchanged candidate; otherwise follow the fresh preparation below. Do not run both preparation paths.

```bash
(
set -euo pipefail
APP_DIR="$HOME/seera"
RUN_DIR="$HOME/seera-november.mAuOQH"
CANDIDATE_DIR="$RUN_DIR/candidate"
BRANCH=feature/seera-client-closure-2026-11-11
VISUAL_SHA=aa0a73b093fd3b4ba77254d21153f47313c9fe00
test "$(git -C "$APP_DIR" rev-parse HEAD)" = 9cc5ea0179c6e104750fcb1249ec3f684194ad38
test "$(git -C "$CANDIDATE_DIR" rev-parse HEAD)" = 063c42f695ad2bd929cce79c03725223e1273184
git -C "$CANDIDATE_DIR" diff --quiet
git -C "$CANDIDATE_DIR" diff --cached --quiet
git -C "$APP_DIR" fetch origin "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
git -C "$APP_DIR" merge-base --is-ancestor "$VISUAL_SHA" "origin/$BRANCH"
git -C "$CANDIDATE_DIR" merge --ff-only "$VISUAL_SHA"
printf '%s\n' "$VISUAL_SHA" > "$RUN_DIR/release-sha.txt"
printf 'Private candidate contains screenshots. Live HEAD: %s\n' "$(git -C "$APP_DIR" rev-parse --short HEAD)"
)
```

For later sections, set `APP_DIR="$HOME/seera"`, `PHP=/opt/cpanel/ea-php83/root/usr/bin/php`, `RUN_DIR="$HOME/seera-november.mAuOQH"` and `CANDIDATE_DIR="$RUN_DIR/candidate"` again; the block keeps its variables local. Backup/restore, rehearsal and destination checks remain required before step 4.

### Fresh private candidate

Use the **same terminal** through the later steps. `RUN_DIR`, `CANDIDATE_DIR` and `RELEASE_SHA` are shell variables. Record the printed private directory; if the terminal is reopened, restore those exact values from its files before continuing.

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
APP_DIR="$HOME/seera"
BRANCH=feature/seera-client-closure-2026-11-11
QA_HEAD=aa0a73b093fd3b4ba77254d21153f47313c9fe00
RUN_DIR="$(mktemp -d "$HOME/seera-november.XXXXXX")"
CANDIDATE_DIR="$RUN_DIR/candidate"

(
set -euo pipefail
umask 077
cd "$APP_DIR"
test "$(git rev-parse HEAD)" = 9cc5ea0179c6e104750fcb1249ec3f684194ad38
test -z "$(git diff --cached --name-only)"
git diff --name-only > "$RUN_DIR/local-tracked-paths.txt"
while IFS= read -r path; do
    case "$path" in public/.htaccess|public/build.zip|'') ;; *) printf 'STOP: review local change: %s\n' "$path"; exit 1 ;; esac
done < "$RUN_DIR/local-tracked-paths.txt"

git fetch origin "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
RELEASE_SHA="$(git rev-parse "origin/$BRANCH")"
git merge-base --is-ancestor "$QA_HEAD" "$RELEASE_SHA"
# Only the reviewed guide/runbook files may change after the QA anchor.
git diff --name-only "$QA_HEAD" "$RELEASE_SHA" > "$RUN_DIR/post-qa-paths.txt"
while IFS= read -r path; do
    case "$path" in
        docs/deployment-client-closure-2026-11-11.md|docs/client-closure-operations-2026-11-11.md|docs/user-guide/CLIENT-ENTRY-UPDATES-2026-11-10.md|docs/user-guide/CLIENT-ENTRY-UPDATES-2026-11-10.html|docs/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.md|docs/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html|'') ;;
        *) printf 'STOP: candidate changed after QA: %s\n' "$path"; exit 1 ;;
    esac
done < "$RUN_DIR/post-qa-paths.txt"
git diff --quiet HEAD "$RELEASE_SHA" -- public/.htaccess
git rev-parse HEAD > "$RUN_DIR/previous-head.txt"
git branch --show-current > "$RUN_DIR/previous-branch.txt"
printf '%s\n' "$RELEASE_SHA" > "$RUN_DIR/release-sha.txt"
git diff --binary > "$RUN_DIR/local-changes.patch"
cp -p .env "$RUN_DIR/env.private"
cp -p public/.htaccess "$RUN_DIR/public.htaccess"
cp -p public/build.zip "$RUN_DIR/previous-build.zip"
if [ -d public/build ]; then cp -a public/build "$RUN_DIR/previous-build"; fi
git worktree add --detach "$CANDIDATE_DIR" "$RELEASE_SHA"
cp -p .env "$CANDIDATE_DIR/.env"
chmod 600 "$CANDIDATE_DIR/.env"
cd "$CANDIDATE_DIR"
if [ -f "$APP_DIR/composer.phar" ]; then
    "$PHP" "$APP_DIR/composer.phar" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
else
    PATH="$(dirname "$PHP"):$PATH" composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
fi
"$PHP" artisan package:discover
printf 'Private preparation directory: %s\n' "$RUN_DIR"
)
```

If any block fails, stop and inspect it. Later steps are not automatically authorized or successful. The portal still serves its old checkout at this point. The candidate is outside the web document root and is not a second live portal.

## 4. Final freeze, consistent backup and empty destination inspection

Before this step, set `TARGET_DB` to the exact empty DB created in cPanel. Example naming is not proof it exists. Keep the full backup outside `~/seera` with private permissions. Use the hosting operator's verified backup process. If using `mysqldump`, supply the actual DB username at its password prompt; do not paste the password into the command or share `.env`.

```bash
# Set these to the actual reviewed values before running the next block:
read -r -p 'Exact empty final destination database name: ' TARGET_DB

(
set -euo pipefail
umask 077
test -n "$TARGET_DB"
test "$TARGET_DB" != techbrit_seera
cd "$APP_DIR"
test "$(git rev-parse HEAD)" = "$(cat "$RUN_DIR/previous-head.txt")"
"$PHP" artisan down
# Drain/pause actual cron, queue and external writers through the hosting procedure.
read -r -p 'After writers are stopped, type WRITERS-STOPPED: ' CONFIRM
test "$CONFIRM" = WRITERS-STOPPED
tar -czf "$RUN_DIR/storage-app.tgz" -C "$APP_DIR" storage/app
test -s "$RUN_DIR/storage-app.tgz"
tar -tzf "$RUN_DIR/storage-app.tgz" > "$RUN_DIR/storage-files.txt"

# Take the final full DB backup with the approved operator process now.
# If dump privileges fail, stop; do not skip this checkpoint.
read -r -p 'Absolute path of the final full private SQL backup: ' BACKUP_SQL
BACKUP_SQL="$("$PHP" -r 'echo realpath($argv[1]) ?: "";' -- "$BACKUP_SQL")"
case "$BACKUP_SQL" in "$APP_DIR"/*|"$CANDIDATE_DIR"/*|'') echo 'STOP: backup must be outside both application folders'; exit 1 ;; esac
test -s "$BACKUP_SQL"
chmod 600 "$BACKUP_SQL"
read -r -p 'After verifying restoration of this backup, type RESTORE-VERIFIED: ' CONFIRM
test "$CONFIRM" = RESTORE-VERIFIED
sha256sum "$BACKUP_SQL" > "$RUN_DIR/database-backup.sha256"
BACKUP_SHA="$(sha256sum "$BACKUP_SQL" | cut -d ' ' -f 1)"

cd "$CANDIDATE_DIR"
# Candidate uses a private copy of the original environment and the ORIGINAL DB.
# Confirm the resolved source DB is still the reviewed source; no credentials printed.
"$PHP" -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if ($app->make("db")->connection()->getDatabaseName() !== "techbrit_seera" || config("zatca.production_enabled")) { throw new RuntimeException("STOP: source DB/ZATCA guard"); }'
"$PHP" artisan down
"$PHP" artisan seera:prepare-storage-copy --target="$TARGET_DB"
"$PHP" artisan seera:prepare-storage-copy --target="$TARGET_DB" --execute \
    --backup-file="$BACKUP_SQL" --backup-sha256="$BACKUP_SHA" \
    --restore-verified --writers-stopped --force
printf '%s\n' "$TARGET_DB" > "$RUN_DIR/final-target-db.txt"
)
```

The execution asks you to type the destination DB name. It migrates/copies **only that independent empty destination**, not the old source. The source data/config are unchanged. It keeps the new destination migration ledger and verifies row/field digests, IDs, auto-increment positions, InnoDB engines and foreign-key orphans. It does not run demo/bootstrap seeders. Read its private report before proceeding.

**Stop on refusal.** Preserve the old source and failed destination for review. Do not run `migrate:fresh`, `go-live-reset`, `migrate:rollback`, manually mark migrations complete, ignore unique keys, or drop real rows. Missing/duplicate legacy identities need the approved review process.

## 5. Review the final copy before promotion

Record the tool's private report path under `RUN_DIR`. Independently verify source/copy user counts, actual Employee links, roles/permissions, company setup, opening/posted GL, AP, AR, VAT, payroll, stock and uploaded-file references. The successful earlier restored-copy rehearsal must cover this release.

No synthetic payments, test employees or test documents are entered into this final destination. If source writers resume, this copy is stale: make a new final backup/copy under a new freeze instead of switching to it later. Do not silently combine two databases that have both accepted business writes.

The final database switch and code activation require the owner's explicit approval. This runbook is not evidence that those checks or approval occurred.

## 6. Approved activation: code, database configuration and assets

Only after step 5 is signed off. Keep the portal in maintenance. In the private `~/seera/.env`, change `DB_DATABASE` to the approved final destination, and change DB credentials only if the hosting operator supplied different destination credentials. Preserve `APP_KEY`, uploads, domain/session settings and company configuration. Resolve/remove a conflicting DB URL if one exists. Keep `ZATCA_PRODUCTION_ENABLED=false` and the approved non-production ZATCA environment. Do not print `.env`.

Then run:

```bash
(
set -euo pipefail
umask 077
cd "$APP_DIR"
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
BRANCH=feature/seera-client-closure-2026-11-11
RELEASE_SHA="$(cat "$RUN_DIR/release-sha.txt")"
TARGET_DB="$(cat "$RUN_DIR/final-target-db.txt")"
test "$(git rev-parse HEAD)" = "$(cat "$RUN_DIR/previous-head.txt")"
test -z "$(git diff --cached --name-only)"
test -f storage/framework/down
# The previous ZIP is already backed up; remove only its local tracked edit.
git restore --source=HEAD -- public/build.zip
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    test "$(git rev-parse "$BRANCH")" = "$RELEASE_SHA"
    git switch "$BRANCH"
else
    git switch --create "$BRANCH" "$RELEASE_SHA"
fi
git branch --set-upstream-to="origin/$BRANCH" "$BRANCH"
test "$(git rev-parse HEAD)" = "$RELEASE_SHA"
cmp -s public/.htaccess "$RUN_DIR/public.htaccess"
"$PHP" artisan optimize:clear
if [ -f composer.phar ]; then
    "$PHP" composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
    PATH="$(dirname "$PHP"):$PATH" composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi
printf '%s  public/build.zip\n' F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7 | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json

SEERA_EXPECTED_DB="$TARGET_DB" "$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
if ($db->getDatabaseName() !== getenv('SEERA_EXPECTED_DB') || config('zatca.production_enabled') || config('app.debug')) { throw new RuntimeException('STOP: destination/config mismatch'); }
$bad = $db->select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? AND ENGINE <> ?', ['BASE TABLE', 'InnoDB']);
if ($bad) { throw new RuntimeException('STOP: destination has nontransactional tables'); }
foreach (['notifications','customer_documents','supplier_documents','user_permission_overrides','supplier_advances','supplier_advance_movements'] as $t) {
    if (!$db->getSchemaBuilder()->hasTable($t)) { throw new RuntimeException('STOP: missing '.$t); }
}
$m=$app->make('migrator');
if (!$m->repositoryExists() || array_diff(array_keys($m->getMigrationFiles(database_path('migrations'))), $m->getRepository()->getRan())) { throw new RuntimeException('STOP: pending/missing migration ledger'); }
echo "Resolved destination, InnoDB, schema and ZATCA guard: OK\n";
PHP
"$PHP" artisan view:cache
"$PHP" artisan migrate:status
git log -1 --oneline
# No seeder/migration is needed here: the final copy already has the complete current schema.
# Restart only previously active, approved workers using the hosting procedure.
"$PHP" artisan up
printf 'Activated release %s; retain private rollback files at %s\n' "$RELEASE_SHA" "$RUN_DIR"
)
```

On an error after maintenance begins, the portal stays down for inspection. Do not add an unconditional `artisan up` trap that would expose an incompatible code/database pair.

## 7. Smoke checks and separately activated setup

- Login/Dashboard: styling/assets load; English and Arabic; no new Laravel error.
- November guide: `/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html` and `/user-guide/CLIENT-ENTRY-UPDATES-2026-11-10.html`; both discoverable from User Guide/topbar.
- Existing real User/Employee links and saved records/files remain correct. View pages stay read-only. Check authorized photos, party documents, code preview, My Leave and personal Deny using approved examples.
- Supplier advances: Finance must map its dedicated asset via the posting rule before the first **authorized real** payment. Do not create a demo account by seeder. See [operator prerequisites](client-closure-operations-2026-11-11.md#supplier-recoverable-mapping).
- Overdue reminders: default off. Preview with `php artisan seera:overdue-reminders`; it creates no notices. At the separately approved activation, set `SEERA_OVERDUE_REMINDERS_ENABLED=true`, clear cached config, verify application timezone, then configure one `artisan schedule:run` hosting cron. Daily 08:00 uses the existing app timezone, not an assumed Pakistan/Saudi time. No queue worker is needed for these in-app reminders.
- Review runtime-generated errors and independently reconcile live GL/AP/AR/VAT/stock/payroll. Resume approved writers only after smoke checks pass. Keep ZATCA production disabled.

## Rollback boundary

Before activation, the old application still uses the original DB: stop preparation and recover the maintenance state through the operator procedure after confirming the old code/config pair. A failed new destination is never promoted.

For example, if destination preparation failed and code/config have not been switched, verify the saved old HEAD and resolved original database before reopening the existing portal:

```bash
(
set -euo pipefail
cd "$APP_DIR"
test "$(git rev-parse HEAD)" = "$(cat "$RUN_DIR/previous-head.txt")"
"$PHP" -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if ($app->make("db")->connection()->getDatabaseName() !== "techbrit_seera" || config("zatca.production_enabled")) { throw new RuntimeException("STOP: old code/config pair not confirmed"); }'
"$PHP" artisan up
)
```

This example applies only before the code/database switch. Resume approved old-portal writers after its smoke checks. Retain the failed destination privately for inspection; do not delete it in a blanket cleanup.

If activation failed **before any business write reached the new DB**, retain maintenance, restore the backed-up `.env`, previous branch/HEAD, previous ZIP/extracted assets and hosting `.htaccess`, refresh Composer autoload, clear caches and confirm the resolved original DB before `artisan up`. Keep both databases/files. Do not run schema rollback or reset.

If business writes reached the new DB, switching to the old DB would lose those new transactions. Keep maintenance and use the approved recovery/reconciliation procedure; a simple branch/DB switch is not a safe rollback in that case.

## Still pending after software installation

Exact IBAN confirmation; owner-approved old-user/master list and identity review; real-user Project/Location task; Finance mapping/taxable back-charge treatment; client/Arabic/device acceptance. Additional HR runtime approval/video scope and separate production ZATCA/external-integration gates remain as recorded in the closure report. Installing this release does not automatically close those items.
