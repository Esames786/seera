# cPanel deployment guide — core release, 1 November 2026

**Status: PENDING owner deployment authorization. Prepared, not executed.** Use this runbook after the signed preflight, backup/restore rehearsal and Finance acceptance. A request for this guide is not treated as permission to modify production.

- Owner-reported current server: `aca78eea1388c249012239ffe207c6b2454db951`, branch `feature/seera-connected-workspaces-2026-09-23`.
- Reviewed release branch: `release/seera-core-integration-review-2026-11-01`.
- Pinned candidate: **`b88eec7ac79cd12b09197f4bc55f02dc61e0282d`**. Its application matches tested `c19943f`.
- Build ZIP SHA-256: **`B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`**.
- **No new migrations or seeders in this range. Do not run a seeder, migrate:fresh, migrate:refresh or automatic rollback.** If the real database has pending migrations, stop and reconcile the discrepancy before deployment.
- The ZIP is committed to Git. **No new ZIP upload or cPanel Node build is needed.** Fetching this exact release supplies the package. It extracts directly into `public/build`, with `manifest.json` at that level.
- Composer lock changed for the ParaTest **development-only** dependency. Use `install --no-dev`, never `composer update`.

## 1. Read-only operator checks now

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
git rev-parse HEAD
git status --short
$PHP -v
$PHP artisan migrate:status
$PHP -r 'foreach (["openssl","dom","xmlwriter","bcmath","mbstring","pdo_mysql","fileinfo","zip"] as $e) { echo $e, ": ", extension_loaded($e) ? "OK" : "MISSING", PHP_EOL; }'
$PHP -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "ZATCA environment: ", config("zatca.environment"), PHP_EOL, "Production enabled: ", config("zatca.production_enabled") ? "YES - STOP" : "NO", PHP_EOL;'
```

Record these results privately with the operator/date. Do not paste `.env`, keys, CSIDs, database passwords or private XML. The supplied server log is evidence of its reported checkout, not proof of the current DB, effective flags or web assets. The closure review reached HTTPS login (200), but authenticated FTPS inspection was refused; server internals remain unverified.

## 2. Required preparation

Record owner approval of the **exact pinned SHA**, approved window, executor and rollback decision owner in [deployment preflight](release-readiness/SEERA-DEPLOYMENT-PREFLIGHT-2026-11-01.md). Finance/HR/Procurement sign the relevant [owner UAT](release-readiness/SEERA-CLIENT-UAT-HANDOVER-2026-11-01.md).

Before the write freeze: obtain an encrypted private DB backup and successful isolated restore evidence; back up `.env`/APP_KEY securely, uploads/private files, current assets, hosting `.htaccess`, revision and local diffs. Record GL/AP/AR/GRNI/VAT/stock/payroll control totals. Decide how to drain workers and manage unsynced mobile writes. Confirm the exact staging upgrade and rollback rehearsal first. No live DB restore is part of this guide.

## 3. Approved change-window commands

**Run only after the requirements above are met.** The block deliberately stops on a different baseline, unknown tracked changes, pending migrations or a changed remote target. A failure after `artisan down` leaves maintenance enabled; investigate before reopening. Existing hosting `.htaccess` is preserved. The old ZIP is backed up before its tracked content is restored for checkout.

```bash
(
set -euo pipefail
cd ~/seera
umask 077
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
RELEASE_BRANCH=release/seera-core-integration-review-2026-11-01
RELEASE_SHA=b88eec7ac79cd12b09197f4bc55f02dc61e0282d
BUILD_SHA=B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E

test "$(git rev-parse HEAD)" = aca78eea1388c249012239ffe207c6b2454db951
test -z "$(git diff --cached --name-only)"
while IFS= read -r path; do
    case "$path" in public/.htaccess|public/build.zip|'') ;; *) echo "STOP: unreviewed tracked change: $path"; exit 1 ;; esac
done < <(git diff --name-only)

git fetch origin "refs/heads/$RELEASE_BRANCH:refs/remotes/origin/$RELEASE_BRANCH"
test "$(git rev-parse "origin/$RELEASE_BRANCH")" = "$RELEASE_SHA"
git merge-base --is-ancestor HEAD "$RELEASE_SHA"
if git show-ref --verify --quiet "refs/heads/$RELEASE_BRANCH"; then
    git merge-base --is-ancestor "$RELEASE_BRANCH" "$RELEASE_SHA"
fi

# Read-only DB/config guard: refuses drift; does not run migrations.
$PHP -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (config("zatca.production_enabled")) { fwrite(STDERR,"STOP: production ZATCA enabled\n"); exit(1); } $m = $app->make("migrator"); if (!$m->repositoryExists()) { fwrite(STDERR,"STOP: no migration repository\n"); exit(1); } $pending = array_diff(array_keys($m->getMigrationFiles(database_path("migrations"))), $m->getRepository()->getRan()); if ($pending) { fwrite(STDERR,"STOP: pending migrations need review\n".implode("\n", $pending)."\n"); exit(1); }'

$PHP artisan down
# Workers/writers must now be drained under the approved operations procedure.
# Take/confirm the final consistent DB snapshot under this write freeze.
# Pause here until its private location/checksum and completion are confirmed.
read -r -p "Final consistent DB snapshot confirmed by backup operator? Type BACKUP-VERIFIED: " BACKUP_CONFIRMATION
test "$BACKUP_CONFIRMATION" = BACKUP-VERIFIED

BACKUP_DIR="$(mktemp -d "$HOME/seera-pre-b88eec7.XXXXXX")"
git rev-parse HEAD > "$BACKUP_DIR/previous-head.txt"
git diff --binary > "$BACKUP_DIR/local-changes.patch"
cp -p .env "$BACKUP_DIR/env.private"
cp -p public/.htaccess "$BACKUP_DIR/public.htaccess"
cp -p public/build.zip "$BACKUP_DIR/previous-build.zip"
if [ -d public/build ]; then cp -a public/build "$BACKUP_DIR/build"; fi
tar -czf "$BACKUP_DIR/private-files.tgz" storage/app
test -s "$BACKUP_DIR/private-files.tgz"
cmp -s public/build.zip "$BACKUP_DIR/previous-build.zip"
git restore --source=HEAD -- public/build.zip

if git show-ref --verify --quiet "refs/heads/$RELEASE_BRANCH"; then
    git switch "$RELEASE_BRANCH"
    git merge --ff-only "$RELEASE_SHA"
else
    git switch --create "$RELEASE_BRANCH" --track "origin/$RELEASE_BRANCH"
fi
test "$(git rev-parse HEAD)" = "$RELEASE_SHA"
cmp -s public/.htaccess "$BACKUP_DIR/public.htaccess"

$PHP artisan optimize:clear
if [ -f composer.phar ]; then
    $PHP composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
    PATH="$(dirname "$PHP"):$PATH" composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

echo "$BUILD_SHA  public/build.zip" | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
test -f public/sw.js
test -f public/manifest.webmanifest
test -f public/vendor/qrcode-generator/qrcode.js
$PHP artisan view:cache
$PHP artisan migrate:status
# Restart active queue workers through the approved supervisor/cPanel procedure;
# with Laravel queue workers, use: $PHP artisan queue:restart
git log -1 --oneline
$PHP artisan up
printf 'Code/assets updated to %s; private file backup: %s\n' "$RELEASE_SHA" "$BACKUP_DIR"
)
```

Do not add `artisan optimize`, `config:cache` or `route:cache` during this closure rollout without a tested environment-specific cache plan. `optimize:clear` avoids the stale-cache issue previously reported. The script neither changes `.env` nor enables ZATCA. Inspect and preserve unrelated untracked files; a checkout collision must be reviewed, not deleted blindly.

## 4. Acceptance after the approved update

Check actual web revision/assets and PHP/FPM code freshness, login/roles, EN/AR navigation, Employee/User links, Customer/Supplier/Project/Site workspaces, HR/Project Reports and printable invoices. Confirm private uploads still open. Verify migration status, ZATCA remains disabled, logs/queues are healthy and pending mobile operations were not discarded.

Finance compares post-change GL/AP/AR/GRNI/VAT, stock, payroll and project/Equipment totals to the signed pre-change record. Run posting/payment examples only on approved staging/training records. Do not create demonstration money movements on real customer balances.

If a check fails: freeze writes, preserve evidence and involve the owner. The backup is not permission to overwrite a live DB. Code rollback must be reviewed against any transactions written after the update, paired with matching assets; there is no automatic DB downgrade or restore step.
