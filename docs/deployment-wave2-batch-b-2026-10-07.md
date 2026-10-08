# Deploy Wave 2 Batch B — Items + Warehouses

Owner-run procedure only; no deployment has been performed by this sprint.

Release verified 8 October 2026. ZIP SHA-256: `B73DAD25B9372E90603DB5A0ABCDF2FD77C3D1AE7E56E393879B1601C2666AC0`. Full suite: 520 tests / 5,656 assertions, zero failures. The guide filename retains the 7 October implementation-start date.

Branch: `feature/seera-connected-workspaces-2026-09-23`. Expected prior baseline: `0de38951273b090dd5d8e89600f6c632fdf5cded` (Batch A). Use the final pushed HEAD and ZIP SHA-256 from the Batch B delivery report/verification ledger. No new migration or production seeder is required for Batch B.

## Before downtime

Take and verify a cPanel database backup and application/private-upload backup. Preserve `.env`, server `public/.htaccess`, and uploaded receipts/documents. Keep backups outside `public`. Run commands individually and stop on any error.

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
test "$(git branch --show-current)" = "feature/seera-connected-workspaces-2026-09-23" || { echo "STOP: wrong branch"; exit 1; }
git status --short
git log -1 --oneline
$PHP artisan migrate:status
BACKUP_DIR=$(mktemp -d /home/techbrit/seera-batch-b.XXXXXX)
chmod 700 "$BACKUP_DIR"
git rev-parse HEAD > "$BACKUP_DIR/previous-head.txt"
git diff -- public/.htaccess > "$BACKUP_DIR/htaccess.patch"
cp -p public/.htaccess "$BACKUP_DIR/htaccess"
cp -p .env "$BACKUP_DIR/env"
cp -a public/build "$BACKUP_DIR/build"
echo "$BACKUP_DIR"
```

The existing server customization of `public/.htaccess` is not changed by this release. Do not reset/clean/stage server modifications, logs, Composer or private backups. If any other tracked source is modified, or Git reports a conflict, stop for review. If older migrations are pending, follow the prior release guide first; do not assume Batch B can repair an older database.

## Apply release

```bash
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
git log -1 --oneline
git status --short
```

Compare HEAD with the delivered release before continuing. If the remote has advanced beyond it, review those changes first.

```bash
$PHP composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
$PHP artisan optimize:clear
unzip -t public/build.zip
sha256sum public/build.zip
```

Compare checksum with the Batch B verification ledger before extraction. The developer supplies the built ZIP; **no Node/npm build is needed on cPanel**. If `composer.phar` is unavailable, use your verified cPanel Composer executable with PHP 8.3; do not fetch arbitrary installers during downtime.

```bash
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
$PHP artisan view:cache
$PHP artisan migrate:status
$PHP artisan up
```

ZIP root contains `manifest.json` and `assets/`; extract to `public/build`, not `public` or `public/build/build`. Do not run `up` after a failed step. This sprint has no migration to execute and **no seeder**. Never run `migrate:fresh`, `db:wipe`, demo seeders or a blanket database reset. Do not add an untested blanket `artisan optimize`; use the clear/view-cache sequence above. No queue/service change is introduced.

## Owner acceptance, initially on staging/training records

1. Dashboard, `/user-guide`, Item/Warehouse lists, read-only View and Manage load. Hard refresh and check browser asset errors/private application logs; keep public `APP_DEBUG=false`.
2. Item Overview edits only master data; Save stays, Close returns to safe origin, New opens a new form, Cancel does not save. Verify tab/Back departure protection for unsaved input.
3. Stock and ledger lazy panels paginate; Back restores the previous panel/page. Document View opens the existing workflow. Item/Warehouse master saves do not post stock/journals or approve documents.
4. With a restricted Project/Site/Warehouse training login, verify visible rows match totals and foreign records/direct panel URLs are denied. Test missing child permissions, not only Super Admin.
5. Cost/value requires Warehouse Stock view; accounting mappings require Chart of Accounts view and Items create/edit to change. AP view gates F04 invoiced/uninvoiced context. Warehouse quantity summaries stay separated by unit.
6. Warehouse Project/Site ownership is fixed after creation. New Site must match Project. Deactivation keeps history and is not a new posting freeze. Item history in another scope must not be erased by Delete.
7. Transfers remain the existing two-stage document workflow. Seeded standard roles still lack Receive; do not interpret a visible transfer panel as authorization. Project/Site source-only visibility is unchanged.
8. Check English and Arabic/RTL with real screen sizes. Underlying older forms still have partial translation coverage. Local mocked-browser checks are not live ERP acceptance.

## Recovery

Keep maintenance on if verification fails; capture sanitized logs and prefer a reviewed forward fix. There is no Batch B schema rollback. Do not blindly roll back to Batch A while permitting Item/Warehouse deletion: older code has the documented history-loss paths. Retain the integrity fixes or temporarily prohibit deletion under a reviewed rollback plan. Also retain Batch A's role-preservation fix. Never roll back Supplier Bill/Site Expense migrations or replace a database with an old backup after new transactions without reviewing intervening data.

No merge, production connection/deployment, or Batch C work is authorized by this guide.
