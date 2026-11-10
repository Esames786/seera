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
