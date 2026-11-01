# cPanel deployment guide — core release, 1 November 2026

**Owner deployment update:** the supplied terminal transcript records successful deployment of `b88eec7`, verified ZIP, cached views and `Application is now live`. **Do not rerun section 3 on the already updated server.** Use the post-deployment checks below. This runbook was prepared by the agent; the owner executed it. Business UAT, live reconciliation and restore evidence are still separate pending items.

The first execution reported `/dev/fd/62: No such file or directory` in the tracked-change guard. This hosting shell cannot use that process-substitution path; the failed redirection bypassed that one guard while later steps continued. The corrected guard below captures Git output first and uses a here-document, so a Git failure stops the block and `/dev/fd` is not required. Do not repeat the deployment to fix this documentation issue.

**Post-deployment asset incident:** live CSS, JS, manifest and a font returned 403 while login, service worker and icon returned 200. The original block retained backup-only `umask 077` for checkout/extraction; restrictive build permissions are the likely cause (server modes still require operator confirmation). The corrected block restores `umask 022` before checkout and explicitly sets public build directory/file modes after extraction. Use the scoped repair in section 4 on an already deployed server.

- Original server baseline: `aca78eea1388c249012239ffe207c6b2454db951`, branch `feature/seera-connected-workspaces-2026-09-23`. Owner-reported deployed revision is now `b88eec7` on the release branch.
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
TRACKED_CHANGES="$(git diff --name-only)"
while IFS= read -r path; do
    case "$path" in public/.htaccess|public/build.zip|'') ;; *) echo "STOP: unreviewed tracked change: $path"; exit 1 ;; esac
done <<TRACKED_PATHS
$TRACKED_CHANGES
TRACKED_PATHS

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
# Private backups retain their protected directory/files. Public assets must be readable.
umask 022
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
find public/build -type d -exec chmod 755 {} +
find public/build -type f -exec chmod 644 {} +
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

For the reported unstyled login / build-asset 403, repair **only public build permissions**, without redeploying, changing `.env`, running migrations or clearing application data:

```bash
cd ~/seera
ls -ld public/build public/build/assets
ls -l public/build/assets/erp-BUNRXPfo.css
find public/build -type d -exec chmod 755 {} +
find public/build -type f -exec chmod 644 {} +
curl --fail --silent --show-error --output /dev/null --write-out 'CSS HTTP %{http_code}\n' https://seera.tech-brit.co.uk/build/assets/erp-BUNRXPfo.css
```

Expect CSS HTTP 200, then hard-refresh the browser. These commands do not change private backups, application configuration or database content. Do not use recursive 777 permissions. If 403 remains, retain the directory/file listing and inspect the server's build-path access rules/ownership; permissions are a likely cause, not independently inspected server evidence.

The owner's execution reached the pinned HEAD, Composer completed, the expected ZIP hash and archive integrity passed, assets were extracted, Blade views cached, every listed migration was Ran, and maintenance mode was lifted. No migration or seeder command ran. The script recorded a private file/asset backup at `/home/techbrit/seera-pre-b88eec7.kgZvaS`. The typed `BACKUP-VERIFIED` is an operator confirmation; it is not independently inspected DB-dump or restore evidence.

Read-only post-deployment checks (safe to run now; no redeployment):

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git rev-parse HEAD
git status --short
sha256sum public/build.zip
$PHP -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "ZATCA environment: ", config("zatca.environment"), PHP_EOL, "Production enabled: ", config("zatca.production_enabled") ? "YES - STOP" : "NO", PHP_EOL;'
```

Expect HEAD `b88eec7ac79cd12b09197f4bc55f02dc61e0282d`, the documented ZIP hash, ZATCA sandbox/NO, and only reviewed server overrides such as `public/.htaccess`. Inspect the web application and have Finance complete reconciliation next. Do not run deployment steps or migrations again merely because the initial guard emitted `/dev/fd`.

Check actual web revision/assets and PHP/FPM code freshness, login/roles, EN/AR navigation, Employee/User links, Customer/Supplier/Project/Site workspaces, HR/Project Reports and printable invoices. Confirm private uploads still open. Verify migration status, ZATCA remains disabled, logs/queues are healthy and pending mobile operations were not discarded.

Finance compares post-change GL/AP/AR/GRNI/VAT, stock, payroll and project/Equipment totals to the signed pre-change record. Run posting/payment examples only on approved staging/training records. Do not create demonstration money movements on real customer balances.

If a check fails: freeze writes, preserve evidence and involve the owner. The backup is not permission to overwrite a live DB. Code rollback must be reviewed against any transactions written after the update, paired with matching assets; there is no automatic DB downgrade or restore step.
