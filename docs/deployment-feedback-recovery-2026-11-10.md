# November feedback deployment: MyISAM recovery

## Confirmed state from the owner's terminal output

The owner installed candidate `77b91bb` over `0aed8c7`. The first migration failed adding `customer_documents.customer_id` → `customers.id`.

- `users`, `employees`, `customers`, `suppliers` and `permissions`: **MyISAM**.
- Server default and the newly created `customer_documents`: **InnoDB**.
- Both `profile_photo_path` columns already exist. The partial document table has no foreign keys.
- Neither November migration is recorded as complete.
- `QUEUE_CONNECTION=sync`; the supplied process check found no matching queue/scheduler worker and no busy process for this account. This is a point-in-time observation; it does not measure shared-host load or establish the cause of earlier page slowness.
- The deployment stopped before `artisan up`. Maintenance remains expected unless the operator changed it afterwards.
- Backups were created under `/home/techbrit/seera-pre-77b91bb.Q4ERPJ` according to the supplied run output. File/archive checks passed before the migration attempt, but a successful database restore has **not** been evidenced.

Foreign keys cannot join InnoDB child tables to MyISAM parents. More broadly, MyISAM does not provide the transactions/rollback/row locks assumed by several ERP workflows. The supplied table list does not establish which engine the remaining accounting/stock/approval tables use. Their engines require a read-only audit before any wider production-readiness claim.

## What the corrective patch does

1. Checks required parent engines before either migration changes the schema. Refuses MyISAM with named tables and **does not convert them automatically**.
2. Preserves existing photo columns and any stored values.
3. Reuses correctly shaped partial document/exception tables, preserving existing rows and IDs.
4. Restores missing foreign keys, retaining the intended Restrict/Cascade/Set Null actions and unique personal-permission index.
5. Refuses orphaned records, unexpected table shapes or conflicting existing constraints without dropping data or silently changing the constraint.
6. Explicitly creates new MySQL tables as InnoDB, independently of the server default.

This changes two migrations that have not completed on the reported server. Environments where they already completed do not rerun them automatically. It does not rewrite historical business records or weaken foreign keys.

## Next read-only audit

On the feedback branch, fast-forward to the corrective patch, refresh the Composer classmap, then collect the engine inventory. These commands do not run migrations, convert tables or bring the site out of maintenance:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
test "$(git branch --show-current)" = feature/seera-client-feedback-fixes-2026-11-10
git status --short
git pull --ff-only
if [ -f composer.phar ]; then
    "$PHP" composer.phar dump-autoload --optimize --no-interaction
else
    PATH="$(dirname "$PHP"):$PATH" composer dump-autoload --optimize --no-interaction
fi
"$PHP" scripts/diagnose-feedback-storage.php
```

Inspect errors before continuing to the next command. This audit reports table metadata and estimated sizes/counts, not personal records, passwords or bank numbers. It does not change the database. Share its output for review before preparing a production conversion.

The previous shell guard used Bash process substitution (`< <(...)`), unavailable in this cPanel environment. Future guards must use a normal temporary file instead, check the producer command's exit code, and then read that file. The Git status shown in the supplied output contains only the permitted tracked `.htaccess` edit, plus the previously known untracked files. No reset/clean or deletion is needed.

## Path A: temporarily restore the previous portal

This is an operator recovery option while storage-engine remediation is planned. It restores the previously accepted code/assets; the new feedback features will not be available. It does **not** reverse migrations or delete the added columns/table.

Before running, confirm the actual server branch/HEAD and the saved backup files. Pause business writers and retain maintenance. The following guarded block applies only when HEAD is the failed candidate `77b91bb` or a descendant corrective patch on the same feature branch; it refuses other starting branches and unreviewed tracked edits.

```bash
(
set -euo pipefail
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
BACKUP=/home/techbrit/seera-pre-77b91bb.Q4ERPJ
OLD=0aed8c791e4cd214d23a916026860585983d1cca
test "$(git branch --show-current)" = feature/seera-client-feedback-fixes-2026-11-10
git merge-base --is-ancestor 77b91bb HEAD
test -z "$(git diff --cached --name-only)"
CHANGES="$(mktemp "$HOME/seera-recovery-changes.XXXXXX")"
git diff --name-only > "$CHANGES"
while IFS= read -r path; do
    case "$path" in
        public/.htaccess|'') ;;
        *) echo "STOP: review tracked edit: $path"; exit 1 ;;
    esac
done < "$CHANGES"
test "$(cat "$BACKUP/previous-head.txt")" = "$OLD"
test -s "$BACKUP/build/manifest.json"
test "$(git rev-parse feature/seera-go-live-reset-2026-11-04)" = "$OLD"
cmp -s .env "$BACKUP/env.private"
cmp -s public/.htaccess "$BACKUP/public.htaccess"
"$PHP" artisan down
git switch feature/seera-go-live-reset-2026-11-04
test "$(git rev-parse HEAD)" = "$OLD"
cmp -s public/.htaccess "$BACKUP/public.htaccess"
cp -a "$BACKUP/build/." public/build/
"$PHP" artisan optimize:clear
if [ -f composer.phar ]; then
    "$PHP" composer.phar dump-autoload --optimize --no-interaction
else
    PATH="$(dirname "$PHP"):$PATH" composer dump-autoload --optimize --no-interaction
fi
"$PHP" artisan view:cache
git log -1 --oneline
"$PHP" artisan up
)
```

Then verify Login/Dashboard and a read-only accepted workspace. Resume paused writers only after successful checks. Existing MyISAM limitations predate this patch and remain unresolved by a code rollback.

## Path B: prepare a controlled engine conversion, then resume

*Superseded for this server by Path C below: the inventory showed the whole database on MyISAM, so converting engines would still leave every historical foreign key missing. Keep Path B only for a database where just a few parent tables are MyISAM.*

**PENDING owner approval and production schema/backup review.** Conversion of existing live tables is a separate production schema operation, beyond the original two additive migrations. The migrations do not perform it.

The minimum known parent set for this candidate is `users`, `employees`, `customers`, `suppliers`, `permissions`. The exact reviewed changes would be `ALTER TABLE <each reviewed table> ENGINE=InnoDB`; they rebuild/lock tables, require adequate disk space and an uninterrupted maintenance window. Do not run a blanket ALTER on every table or disable foreign-key checks to bypass the error.

Before approval/execution:

1. Review the full read-only engine inventory, actual table definitions, existing indexes/constraints and sizes. In particular, inspect GL/AP/AR/payroll/stock/approval tables for nontransactional engines.
2. Restore the exact database backup to an isolated staging database. Preserve/verify all retained users, permissions, financial balances, document IDs and private files. `--single-transaction` alone is not a consistency guarantee for MyISAM: a writer freeze or appropriately locked backup is required.
3. Rehearse the precise conversion set on that restored database, checking charset, indexes, unique constraints, counts and transaction behaviour. Do not infer production safety solely from the synthetic five-table probe.
4. Obtain explicit owner approval for the reviewed live ALTER list and maintenance window. Take the final consistent database/private-file snapshot with all writers stopped.
5. Apply only the approved conversions, preserving all data. Verify engines and relevant counts/balances before retrying the two migration paths below.

After those prerequisites, on the corrective feedback candidate:

```bash
"$PHP" artisan migrate --force \
    --path=database/migrations/2026_11_10_000001_add_profile_photos_and_party_documents.php \
    --path=database/migrations/2026_11_10_000002_create_user_permission_overrides.php
"$PHP" scripts/diagnose-feedback-storage.php
"$PHP" artisan view:cache
"$PHP" artisan migrate:status
```

Expect both migrations complete, both photo columns and seven foreign keys across the three new tables, plus the unique `(user_id, permission_id)` index. Keep ZATCA production disabled. Verify those facts and the release smoke checks before the operator brings the portal live. No seeder, reset, table drop, blind `migrate:rollback`, manual migration-record insert or data cleanup belongs to this recovery.

## Local evidence and production boundary

The corrective patch was tested on SQLite and a dedicated disposable MySQL 8 instance. The MySQL probe reproduces the original mixed-engine failure and partial DDL, demonstrates both new guards, then converts **synthetic local tables only** and verifies retry/data preservation, all seven foreign keys, uniqueness, Restrict deletion, transaction rollback and explicit InnoDB creation even with a MyISAM server default. See the dated `migration-recovery` evidence folder.

No production engine conversion, migration rerun, rollback, deployment, data deletion or ZATCA activation has been performed by this development run. Production recovery and earlier performance diagnosis remain pending operator evidence.

## Path C: prepare a complete InnoDB copy without resetting the source

**The previous setup-only rebuild recipe is withdrawn. Do not run `migrate:fresh` on the live database.** The owner has since shown newly entered accounts and may have real business records. A historic setup-only snapshot is not permission to discard other rows.

The current storage inventory also includes financial/stock/approval tables using MyISAM. This prevents dependable transaction rollback and row locks. Missing historical constraints must be reconstructed and checked, not merely assumed to appear when an engine changes.

The corrective branch adds `seera:prepare-storage-copy`. It prepares a DIFFERENT, existing empty database on the same MySQL/MariaDB server. It never changes the live database engine, drops its tables, rewrites its rows or edits `.env`.

### Preparation and read-only inspection

1. Arrange a full consistent backup with all writers stopped and verify restoration separately. A single-transaction dump alone is not a consistency guarantee for MyISAM.
2. Have the hosting operator create a separately named EMPTY database and grant the application's DB user access to it. Do not reuse another application's database or the live database.
3. Install the reviewed corrective code in the controlled environment. The full November feature branch still requires its new schema; do not serve it prematurely against the old live DB.
4. Run `php artisan seera:prepare-storage-copy --target=YOUR_EMPTY_DATABASE` for a read-only report. The command refuses the live database as a destination, populated destinations, table prefixes and split read/write connections.

### Approved preparation (not automatic live activation)

Execution requires `--execute`, a private full backup file and matching `--backup-sha256`, the operator's `--restore-verified` and `--writers-stopped` attestations, and typing the exact destination name. Production additionally requires maintenance mode and `--force`.

The command migrates the empty destination with an explicit InnoDB connection, then copies **every source table and every source column**, including business transactions, user passwords, permissions, new real accounts, financial rows and document paths. It retains the destination's fresh migration ledger; the source ledger is unchanged. Migration-created seed rows are replaced only inside this new destination for tables being copied, so existing source settings/IDs remain authoritative. It does not guess new permissions or run a bootstrap/demo seeder.

Target foreign-key checks are temporarily suspended only on the target connection to load self/cross references. Unique checks remain enabled. The command then verifies every generated foreign key, every copied table's row count/content hash, source unchanged before/after, restored auto-increment counters and InnoDB engines. Unknown source fields, duplicate target keys, orphan references or concurrent source changes cause refusal. A failed target is not reused or promoted automatically; the source stays available for recovery.

A private verification report records counts/hashes and the operator's backup attestations. These attestations are not claimed as an automated backup-restore test.

### Before owner-approved activation

- Review the report and target schema/permissions, and independently reconcile GL/AP/AR/VAT/stock/payroll.
- Check new schema/setup needs, configured posting accounts and user/project scope; the source's existing configuration is preserved, not replaced with demo data.
- Test the exact new release against this database in a controlled environment, including login, photos/documents, My Leave, permissions and the customer/supplier flows.
- Obtain owner approval for the destination database, release and maintenance window. Recheck stopped writers and the source snapshot before changing the application's database configuration.
- Preserve the source database, previous configuration/assets and private uploaded files. The command does not promote the target or delete the source.
- Keep production ZATCA disabled. No data cleanup, account deactivation, full reset or unreviewed engine conversion is part of this storage preparation.
