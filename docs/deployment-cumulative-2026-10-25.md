# Cumulative cPanel deployment guide - 25 October 2026

**26 October correction status:** see [fixes and verification](deployment-fixes-2026-10-26.md). Use the exact published correction commit from the release handoff. The generic template below deliberately requires that commit; do not copy its placeholders unchanged. Do not use the rejected base commit as the fixed release.

## Release identity - reject the unfixed base

Candidate `d66a6ea` is **not approved**. See [the review and three reproduced blockers](deployment-review-2026-10-25.md) and the correction report above. This guide targets the corrected, tested release. The release placeholder below deliberately stops the block until the published correction hash and checksum are supplied. Automated verification does not replace staging/browser acceptance.

Last evidenced live revision is `4d77c77` (October 14 terminal output); inspect the current server instead of assuming it is unchanged. Branch stays `feature/seera-connected-workspaces-2026-09-23`. No main merge is required.

## Cumulative contents

Four migrations since that last release:

1. `2026_10_15_000001_create_offline_sync_receipts`
2. `2026_10_17_000001_add_zatca_phase2`
3. `2026_10_22_000001_add_customer_adjustment_notes`
4. `2026_10_23_000001_add_customer_credit_resolution`

No seeder is needed. The ZATCA migration also normalizes old local status labels to `not_generated`; it is not solely new empty tables. Review any existing ZATCA artefacts before migration if the server has previously integrated outside this release path. The final five readiness commits alone have no migrations, but deploying the cumulative range DOES.

The corrected local build ZIP (rebuilt 26 October) is 197790 bytes / 18 files, SHA-256 `E51BFFBC4C02B5FDEA33AE0281B8DEC02CAD236B97BD59F6A1CE7E0D0E2AF228`. Its manifest, fonts and assets are at the ZIP root. Confirm this checksum against the eventual pushed release before deployment; rebuilding can change the archive checksum. `public/sw.js`, `public/manifest.webmanifest`, `public/icons/*` and `public/vendor/qrcode-generator/qrcode.js` arrive through Git outside the ZIP.

## 1. Safe inspection now (read-only on the application)

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
git status --short
git log -1 --oneline
$PHP -v
$PHP -r 'foreach (["openssl","dom","xmlwriter","bcmath","mbstring","pdo_mysql"] as $e) { echo $e, ": ", extension_loaded($e) ? "OK" : "MISSING", PHP_EOL; }'
$PHP -r 'echo extension_loaded("openssl") && in_array("secp256k1", openssl_get_curve_names(), true) ? "secp256k1 OK\n" : "secp256k1 MISSING\n";'
$PHP artisan migrate:status
```

Do not share `.env`, CSIDs, private keys, OTPs, database passwords or raw private XML. Check nonsecret ZATCA config locally. Preserve the hosting change to `public/.htaccess` and unknown server files. Never `git reset --hard` to resolve deployment conflicts.

## 2. Backups and gates before the approved deployment

- Fix and verify G01-G03; obtain the exact approved 40-character release commit, ZIP checksum and a successful staging acceptance result.
- Verify a private database backup and file backup outside the public web root. Include `.env`, the existing APP_KEY, `public/.htaccess`, `storage/app` (receipts/XML/private files), current assets and the current commit. Test restore access. Suspend writes/active workers for a consistent final backup.
- Do not rotate APP_KEY: encrypted EGS keys/credentials depend on it. Protect it with the backup; never put credentials into Git.
- For this code-only rollout, keep `ZATCA_ENVIRONMENT=sandbox` and `ZATCA_PRODUCTION_ENABLED=false` in the server configuration. Verify environment-variable overrides as well as `.env`. Do not onboard a production unit or send real invoices to the sandbox.
- Restrict onboarding permissions to the owner/Finance administrator. Schedule any financial acceptance actions on staging or explicitly approved training records; no test refunds in real customer balances.
- Confirm PHP requirements above, writable private storage and normal web-server file ownership. The release range changes neither composer.json nor composer.lock; locked `composer install` is retained below as an integrity step, never `composer update`.

## 3. Approved-release deployment block (NOT runnable until release gate passes)

Replace both placeholders with values from the corrected release review. The current rejected commit is explicitly blocked. Run as a single block; a failure after maintenance starts keeps the app down so you can investigate. No `artisan optimize` or config/route cache change is required here.

```bash
(
set -e
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
RELEASE_COMMIT='REPLACE_WITH_APPROVED_FIX_COMMIT'
RELEASE_ZIP_SHA='REPLACE_WITH_APPROVED_ZIP_SHA256'
[[ "$RELEASE_COMMIT" =~ ^[0-9a-f]{40}$ ]]
[[ "$RELEASE_ZIP_SHA" =~ ^[0-9a-fA-F]{64}$ ]]
test "$RELEASE_COMMIT" != d66a6ea07cee0eca22a875279b3ed68382c46714
test "$(git branch --show-current)" = feature/seera-connected-workspaces-2026-09-23
git fetch origin refs/heads/feature/seera-connected-workspaces-2026-09-23:refs/remotes/origin/feature/seera-connected-workspaces-2026-09-23
test "$(git rev-parse origin/feature/seera-connected-workspaces-2026-09-23)" = "$RELEASE_COMMIT"
git merge-base --is-ancestor HEAD "$RELEASE_COMMIT"
$PHP artisan down
git merge --ff-only "$RELEASE_COMMIT"
test "$(git rev-parse HEAD)" = "$RELEASE_COMMIT"
$PHP artisan optimize:clear
if [ -f composer.phar ]; then
    $PHP composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
    $PHP "$(command -v composer)" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi
echo "$RELEASE_ZIP_SHA  public/build.zip" | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
test -f public/sw.js
test -f public/manifest.webmanifest
test -f public/icons/seera-192.png
test -f public/icons/seera-512.png
test -f public/vendor/qrcode-generator/qrcode.js
$PHP artisan migrate:status
$PHP artisan migrate --force
$PHP artisan view:cache
$PHP artisan migrate:status
git log -1 --oneline
$PHP artisan up
)
```

The exact fast-forward merge is equivalent to pulling the reviewed remote commit but refuses to deploy an unreviewed newer tip. No Node/npm build or separate ZIP upload is needed on cPanel. Do not extract to `public/build/build`. If this server has active supervised workers, restart them under the established operations procedure after deployment.

## 4. Acceptance after deployment

- All expected migrations show Ran; existing users, employees, invoices and receipts remain available. Check recent application logs with secrets redacted.
- Dashboard and employee/customer/supplier workspaces open; invoice and note print pages show their stored totals, expected seller/buyer information and QR where available. Phone QR scanning and Arabic print layout are owner UAT steps, not proven by the deployment commands.
- ZATCA Settings confirms sandbox and production disabled. No production CSID or submission is created automatically.
- Recheck corrected refund behavior under automatic and review posting rules on staging; verify available credit and posted GL reconcile. Check note/credit registers, pagination and scoped roles.
- Recheck corrected ZATCA malformed response and in-flight submission tests before company simulation. Do not trigger duplicate production submissions as a smoke test.
- On approved mobile test accounts, check queued attendance and draft expense sync, offline logout/account switching, and retry idempotency. Do not clear browser storage while unsynced work remains. Ensure `/sw.js` is revalidated, not given long-lived immutable caching; hashed `/build/assets/` may be cached long.

## Recovery

If any command fails, retain the error, keep maintenance mode on and stop. Preserve configuration, uploads, keys, offline receipt IDs and financial/compliance history. Never run `db:seed`, `migrate:fresh`, `migrate:refresh`, a blind `migrate:rollback`, or restore a database over newer transactions. A code rollback needs review against the newly stored notes/refunds/offline events and matching assets; leaving additive tables in place alone does not prove old code can interpret new financial history. Obtain an approved recovery plan first.

## Live ZATCA activation is a separate project gate

After software fixes: obtain Finance's rounding decision, confirmed company legal/tax/address and EGS identity, company simulation results for all six invoice/note types, SDK validation, required owner onboarding/production credentials, and operator acceptance. The onboarding enable-flag sequencing has been corrected in the October 24 checklist. This code deployment guide never instructs setting `ZATCA_PRODUCTION_ENABLED=true`.
