# Deployment: Fable continuation review fixes - 14 October 2026

Branch: `feature/seera-connected-workspaces-2026-09-23`. Review base: `1b5d883`.
Commit/push authorized by the owner; this guide does not claim production deployment.
See [review findings and test ledger](fable-continuation-review-2026-10-12.md).

## What ships

Ten reproduced corrections covering payroll authorization/state locks/original payable, equipment atomic saves and scoped locks, maintenance status/payment mapping, BOQ grouping/draft guards and attendance translations. Updated user guide, regressions and freshly packaged `public/build.zip` are included.

- No new migration or seeder for this corrective patch.
- If the server is behind Fable's latest work, `migrate --force` will apply pending continuation migrations. Inspect `migrate:status` first; do not assume only one migration is pending.
- Do not run `db:seed`, `migrate:fresh`, `migrate:refresh` or routine migration rollback.
- ZIP: 18 files, 189897 bytes; SHA-256 `96740A018AD23262D6EF4213232E5885EA19A3E9ECECF0E3FCED7EAF6EF219D1`.
- Previously verified targeted/module/wider regression runs and 77 mocked browser checks passed. Full-suite, fresh MySQL concurrency and live browser acceptance remain unperformed. The PHP/Blade source has not changed since those recorded runs.

## 1. Back up and inspect

Take and verify a private database backup through cPanel before proceeding. Back up `.env`, `public/.htaccess`, `storage/app`, deployed assets and the current commit outside the web root. For a consistent application snapshot, suspend writes/background workers for the backup and deployment window. Never commit environment files or backups. Preserve the hosting-specific `.htaccess` change shown in earlier server output.

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
git status --short
git log -1 --oneline
git fetch origin
```

Confirm the branch above and compare the remote tip with the release hash supplied in chat. If unexpected server edits or a newer remote release appear, stop and review them. Do not reset or discard them. The deployment block deliberately refuses to run on a different branch. If local `.htaccess` changes conflict with the pull, retain the file and resolve deliberately; do not use `git reset --hard`.

## 2. Deploy (only after verified backups)

Run this as one block. Its subshell stops on failure and leaves maintenance mode enabled if deployment fails after `artisan down`; do not bring the app up until the error is resolved. No global `artisan optimize` is used because this server previously needed `optimize:clear` after a cached deployment.

```bash
(
set -e
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
test "$(git branch --show-current)" = feature/seera-connected-workspaces-2026-09-23
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
if [ -f composer.phar ]; then
    $PHP composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
    $PHP "$(command -v composer)" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi
echo '96740A018AD23262D6EF4213232E5885EA19A3E9ECECF0E3FCED7EAF6EF219D1  public/build.zip' | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
$PHP artisan optimize:clear
$PHP artisan migrate:status
$PHP artisan migrate --force
$PHP artisan view:cache
$PHP artisan migrate:status
git log -1 --oneline
$PHP artisan up
)
```

The committed ZIP is built locally: no Node/npm build or separate ZIP upload is needed on cPanel. Do not extract it into `public/build/build`. If deploying a later release, use that release's checksum instead of bypassing the checksum failure. For servers with active supervised queue workers, restart them using the established operations procedure after a successful deployment.

## 3. Verify

- Log in, open dashboard, employee/customer/supplier workspaces and Equipment/Budget screens. Check browser assets and application logs without sharing secrets.
- Confirm all expected migrations show Ran. "Nothing to migrate" is correct if the continuation migrations were already deployed.
- In staging or an explicitly approved training-data window, check maintenance completion with multiple repairs and deferred payment mapping, whole-run payroll restrictions, legacy budget warnings, and English/Arabic attendance messages. Do not create production payments just to smoke-test.
- Confirm user guide opens. No seeder or automatic historical-data cleanup is needed.

## Recovery

Keep maintenance mode on when a deployment step fails; retain the error output with sensitive values removed. Preserve the database, private uploads, `.env` and hosting `.htaccess`. A code rollback must target the recorded pre-release commit and its matching assets after compatibility review; this patch introduces no schema change, but a cumulative deployment may have applied earlier additive migrations. Do not roll those back blindly or restore a database over transactions created since its backup. Obtain approval before any data restore or corrective accounting action.
