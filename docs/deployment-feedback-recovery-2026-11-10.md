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

## Path C (recommended): rebuild the database as InnoDB, then install the release

The owner's engine inventory of 10 November shows the **entire** live database on MyISAM (every table except `customer_documents` and `notifications`). That means no foreign key from any earlier migration was ever created on live and no accounting write ever ran inside a real transaction. `ALTER TABLE … ENGINE=InnoDB` would fix the engine but leave all those historical foreign keys missing. Because the live database holds only setup data after the go-live reset (14 accounts, roles, permissions, chart of accounts, masters: about 1,900 rows), the clean fix is to rebuild the schema from the migrations and reload exactly those rows.

Procedure, rehearsed on 10 November on a disposable local MySQL 8 database built from the pre-November migrations plus `ProductionBootstrapSeeder` (1,904 setup rows): data-only dump of the 26 setup tables → `migrate:fresh` on the corrective candidate → truncate the setup tables (several migrations pre-seed permissions, accounts, lookup values and payment terms, so a plain reload hits duplicate keys) → reload with foreign-key checks off → identical row counts, 105 InnoDB tables, 292 foreign keys, zero dangling references, Super Admin logs in with their permissions.

Preconditions: server HEAD `0aed8c7` on `feature/seera-go-live-reset-2026-11-04` (the Path A state), `mysql` and `mysqldump` clients available, the `techbrit_seera` database password at hand, no real business records entered since the reset (if any were, stop and list them first: this path keeps only the setup tables). The script keeps a full dump and a `storage/app` archive in a private folder before touching anything; if a verification step fails it stops **before** `artisan up`, and the operator restores `full-before-rebuild.sql` with `mysql --defaults-extra-file=<cnf> techbrit_seera < full-before-rebuild.sql` and returns to the previous code as in Path A.

```bash
(
set -euo pipefail
cd ~/seera
umask 077
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
BRANCH=feature/seera-client-feedback-fixes-2026-11-10
CODE_SHA=0a1a01a
PREVIOUS_SHA=0aed8c791e4cd214d23a916026860585983d1cca
BUILD_SHA=F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7
DBNAME=techbrit_seera
DBUSER=techbrit_seera
SETUP="users user_roles roles permissions role_permissions role_reporting_parents branches departments designations company_profiles chart_of_accounts cost_centers automatic_posting_rules vat_periods zatca_egs_units lookup_values payment_terms units item_categories expense_categories leave_types shifts approval_workflows approval_workflow_steps project_classifications document_sequences"

test -x "$PHP"; command -v mysql >/dev/null; command -v mysqldump >/dev/null
test "$(git rev-parse HEAD)" = "$PREVIOUS_SHA"
test -z "$(git diff --cached --name-only)"
CHANGES="$(mktemp "$HOME/seera-changes.XXXXXX")"
git diff --name-only > "$CHANGES"
while IFS= read -r p; do case "$p" in public/.htaccess|public/build.zip|'') ;; *) echo "STOP: review local change: $p"; exit 1 ;; esac; done < "$CHANGES"
git fetch origin "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
RELEASE="$(git rev-parse "origin/$BRANCH")"
git merge-base --is-ancestor "$CODE_SHA" "$RELEASE"
git merge-base --is-ancestor HEAD "$RELEASE"
git diff --quiet "$PREVIOUS_SHA" "$RELEASE" -- composer.json composer.lock

CNF="$(mktemp "$HOME/seera-my.XXXXXX")"
read -r -s -p "MySQL password for $DBUSER: " DBPW; echo
printf '[client]\nhost=127.0.0.1\nport=3306\nuser=%s\npassword=%s\n' "$DBUSER" "$DBPW" > "$CNF"; unset DBPW
mysql --defaults-extra-file="$CNF" -N -e "select count(*) from information_schema.tables where table_schema='$DBNAME'" | sed 's/^/tables now: /'

$PHP artisan down
BACKUP_DIR="$(mktemp -d "$HOME/seera-rebuild-$(date +%Y%m%d-%H%M).XXXXXX")"
printf 'Private backup folder: %s\n' "$BACKUP_DIR"
git rev-parse HEAD > "$BACKUP_DIR/previous-head.txt"
cp -p .env "$BACKUP_DIR/env.private"
cp -p public/.htaccess "$BACKUP_DIR/public.htaccess"
cp -p public/build.zip "$BACKUP_DIR/previous-build.zip"
mysqldump --defaults-extra-file="$CNF" --quick --no-tablespaces --skip-lock-tables "$DBNAME" > "$BACKUP_DIR/full-before-rebuild.sql"
test -s "$BACKUP_DIR/full-before-rebuild.sql"
grep -c '^CREATE TABLE' "$BACKUP_DIR/full-before-rebuild.sql" | sed 's/^/tables in full backup: /'
tar -czf "$BACKUP_DIR/storage-app.tgz" storage/app
tar -tzf "$BACKUP_DIR/storage-app.tgz" >/dev/null
# shellcheck disable=SC2086
mysqldump --defaults-extra-file="$CNF" --no-create-info --skip-triggers --complete-insert --quick --no-tablespaces --skip-lock-tables "$DBNAME" $SETUP > "$BACKUP_DIR/setup-data.sql"
test -s "$BACKUP_DIR/setup-data.sql"
for t in $SETUP; do printf '%s %s\n' "$t" "$(mysql --defaults-extra-file="$CNF" -N -e "select count(*) from $DBNAME.$t")"; done > "$BACKUP_DIR/setup-counts-before.txt"
cat "$BACKUP_DIR/setup-counts-before.txt"

git restore --source=HEAD -- public/build.zip
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then git switch "$BRANCH"; git merge --ff-only "$RELEASE"; else git switch --create "$BRANCH" --track "origin/$BRANCH"; fi
test "$(git rev-parse HEAD)" = "$RELEASE"
cmp -s .env "$BACKUP_DIR/env.private"
cmp -s public/.htaccess "$BACKUP_DIR/public.htaccess"
$PHP artisan optimize:clear
if [ -f composer.phar ]; then $PHP composer.phar dump-autoload --optimize --no-interaction; else PATH="$(dirname "$PHP"):$PATH" composer dump-autoload --optimize --no-interaction; fi
echo "$BUILD_SHA  public/build.zip" | sha256sum -c -
unzip -t public/build.zip >/dev/null
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json
$PHP -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (config("database.connections.mysql.database") !== "techbrit_seera" || config("zatca.production_enabled")) { fwrite(STDERR, "STOP: database target or ZATCA configuration\n"); exit(1); } echo "Database target and ZATCA guard: OK\n";'

$PHP artisan migrate:fresh --force
{ echo "SET FOREIGN_KEY_CHECKS=0; SET UNIQUE_CHECKS=0;"; for t in $SETUP; do echo "TRUNCATE TABLE \`$t\`;"; done; cat "$BACKUP_DIR/setup-data.sql"; echo "SET FOREIGN_KEY_CHECKS=1; SET UNIQUE_CHECKS=1;"; } | mysql --defaults-extra-file="$CNF" "$DBNAME"
for t in $SETUP; do printf '%s %s\n' "$t" "$(mysql --defaults-extra-file="$CNF" -N -e "select count(*) from $DBNAME.$t")"; done > "$BACKUP_DIR/setup-counts-after.txt"
diff "$BACKUP_DIR/setup-counts-before.txt" "$BACKUP_DIR/setup-counts-after.txt" && echo "setup row counts identical"
mysql --defaults-extra-file="$CNF" -N -e "select engine, count(*) from information_schema.tables where table_schema='$DBNAME' group by engine"
mysql --defaults-extra-file="$CNF" -N -e "select count(*) from information_schema.referential_constraints where constraint_schema='$DBNAME'" | sed 's/^/foreign keys: /'
$PHP -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $p=0; foreach (DB::select("select TABLE_NAME c, COLUMN_NAME k, REFERENCED_TABLE_NAME p, REFERENCED_COLUMN_NAME pk from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA=database() and REFERENCED_TABLE_NAME is not null") as $f) { $n = DB::table($f->c." as c")->leftJoin($f->p." as p", "c.".$f->k, "=", "p.".$f->pk)->whereNotNull("c.".$f->k)->whereNull("p.".$f->pk)->count(); if ($n) { $p++; echo "ORPHANS ", $f->c, ".", $f->k, " -> ", $f->p, ": ", $n, PHP_EOL; } } echo "dangling references: ", $p, PHP_EOL; exit($p ? 1 : 0);'
$PHP -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $u=App\Models\User::whereHas("roles", fn($q)=>$q->where("code","SUPER_ADMIN"))->first(); if (!$u || !$u->hasPermission("Users","view")) { fwrite(STDERR, "STOP: Super Admin account or permissions missing\n"); exit(1); } echo "Super Admin: ", $u->email, " with Users view\n";'
$PHP artisan migrate:status | grep -c Pending | grep -qx 0
$PHP scripts/diagnose-feedback-storage.php > "$BACKUP_DIR/diagnose-after.json"
$PHP artisan view:cache
rm -f "$CNF" "$CHANGES"
git log -1 --oneline
$PHP artisan up
printf 'Rebuild complete. Keep the private folder: %s\n' "$BACKUP_DIR"
)
```

After it prints `Rebuild complete`: log in as the Super Admin and one ordinary user (sessions were cleared), check Dashboard, Users → Edit / Manage → Individual permissions, Employees → photo upload, Customers / Suppliers → automatic code and Documents, Equipment → save with a blank meter, My Leave, and the User Guide links including `/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html`. Rerun the read-only smoke script only after its expected SHA is updated to the installed release. ZATCA production stays disabled.
