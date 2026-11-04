# Go-live reset command — controlled deployment and reset guide, 4 November 2026

Target: `feature/seera-go-live-reset-2026-11-04`, code commit **`30346bc`** followed only by documentation commits (base `a3316be`, the approved production commit). The candidate is **code only**: one artisan command, its test class and two guides; the deploy block below verifies that content rather than a fixed branch head, so later documentation commits on the branch do not change what is deployed. No migration, no Composer change, no new asset build, no `public/build.zip` change, no seeder, no ZATCA change. Fast-forward from `a3316be`.

**This guide does not authorize production changes.** The reset deletes data permanently. It runs only after the client has confirmed in writing which registers they keep (see §3), a database backup has been taken and its restore verified, and the owner approves. Rehearse on staging first if a staging copy of the live database exists.

Companion: [go-live-reset-2026-11-04.md](go-live-reset-2026-11-04.md) (what the command keeps and removes, safeguards, options).

## 1. Read-only preflight (run now, changes nothing)

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current
git rev-parse HEAD                      # expected a3316be4209b13efdc54ce329fa21218e0a95165
git status --short --untracked-files=no # only public/.htaccess / public/build.zip overrides are expected
$PHP artisan migrate:status | grep -Ec 'Pending'   # expected 0
bash scripts/seera-readonly-live-smoke.sh 2>/dev/null || true   # if the ops smoke script is present
```

Stop if HEAD is not `a3316be`, if migrations are pending, or if tracked changes other than `public/.htaccess` / `public/build.zip` exist: those are different rollouts and need their own review.

## 2. Deploy the command (code only, no data change)

```bash
(
set -euo pipefail
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
TARGET_BRANCH=feature/seera-go-live-reset-2026-11-04
CODE_SHA=30346bc
PREVIOUS_SHA=a3316be4209b13efdc54ce329fa21218e0a95165

test "$(git rev-parse HEAD)" = "$PREVIOUS_SHA"
test -z "$(git diff --cached --name-only)"
TRACKED_CHANGES="$(git diff --name-only)"
while IFS= read -r path; do
    case "$path" in public/.htaccess|public/build.zip|'') ;; *) printf 'STOP: review tracked change: %s\n' "$path"; exit 1 ;; esac
done <<< "$TRACKED_CHANGES"
git fetch origin "refs/heads/$TARGET_BRANCH:refs/remotes/origin/$TARGET_BRANCH"
RELEASE_FULL="$(git rev-parse "origin/$TARGET_BRANCH")"
git merge-base --is-ancestor HEAD "$RELEASE_FULL"
git merge-base --is-ancestor "$CODE_SHA" "$RELEASE_FULL"
# Code-only candidate: between the production commit and the branch head only the command, its test and Markdown guides may differ.
if git diff --name-only "$PREVIOUS_SHA" "$RELEASE_FULL" | grep -vE '^(app/Console/Commands/GoLiveReset\.php|tests/Feature/GoLiveResetCommandTest\.php|docs/[^/]+\.md)$'; then printf 'STOP: unexpected file in the candidate
'; exit 1; fi
git diff --quiet "$PREVIOUS_SHA" "$RELEASE_FULL" -- composer.json composer.lock public/build.zip database/migrations database/seeders

umask 077
BACKUP_DIR="$(mktemp -d "$HOME/seera-pre-golive-reset.XXXXXX")"
git rev-parse HEAD > "$BACKUP_DIR/previous-head.txt"
git branch --show-current > "$BACKUP_DIR/previous-branch.txt"
git diff --binary > "$BACKUP_DIR/local-changes.patch"
cp -p .env "$BACKUP_DIR/env.private"

if git show-ref --verify --quiet "refs/heads/$TARGET_BRANCH"; then
    git switch "$TARGET_BRANCH"
    git merge --ff-only "$RELEASE_FULL"
else
    git switch --create "$TARGET_BRANCH" --track "origin/$TARGET_BRANCH"
fi
test "$(git rev-parse HEAD)" = "$RELEASE_FULL"
cmp -s .env "$BACKUP_DIR/env.private"
$PHP artisan optimize:clear
$PHP artisan view:cache
$PHP artisan list | grep -F 'seera:go-live-reset'
$PHP artisan migrate:status | grep -Ec 'Pending' | grep -qx 0
git log -1 --oneline
printf 'Deploy checks passed. Rollback files: %s\n' "$BACKUP_DIR"
)
```

No maintenance window is needed for this step: the deploy adds a command and changes no request path, table or asset. Users keep working. If `artisan list` does not show `seera:go-live-reset`, stop and report the output.

## 3. Decide what the client keeps (before the reset)

The command removes customers, suppliers, employees, projects (with sites), inventory (items and warehouses) and equipment assets unless they are kept. Confirm with the client and write the answer into the handover record:

| Client answer | Flag |
| --- | --- |
| Remove everything except setup, accounts and the chart of accounts | *(no flag)* |
| Keep the employee register (with documents, shifts, salary structures) | `--keep=employees` |
| Keep the project and site list | `--keep=projects` |
| Keep the item and warehouse masters | `--keep=projects,inventory` (warehouses belong to projects) |
| Keep customers / suppliers / equipment assets | `--keep=customers`, `--keep=suppliers`, `--keep=equipment` |

Flags combine: `--keep=employees,projects,inventory`. Login accounts, roles, permissions, organisation, chart of accounts, cost centres, posting rules, VAT periods, ZATCA units, lookup values, payment terms, units, categories, leave types, shifts, approval workflows and project classifications are kept in every case.

## 4. Dry run (changes nothing)

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan seera:go-live-reset --keep=...   # or without --keep
```

Read both tables: "Table to empty / Rows now" and "Table kept untouched / Rows". If a table you expected to keep is in the first table, or the command reports *Unclassified tables*, stop and send the output. Send the dry-run output to the owner before §5.

## 5. Execute the reset (data change, owner approval required)

```bash
(
set -euo pipefail
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
DB_NAME="$($PHP -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("database.connections.".config("database.default").".database");')"
$PHP artisan down
read -r -p 'Users logged out, scheduler/queue paused, consistent DB backup taken and its restore verified? Type BACKUP-VERIFIED: ' OK
test "$OK" = BACKUP-VERIFIED
umask 077
BACKUP_DIR="$(mktemp -d "$HOME/seera-pre-golive-reset-data.XXXXXX")"
mysqldump --single-transaction --quick "$DB_NAME" > "$BACKUP_DIR/before-reset.sql"
test -s "$BACKUP_DIR/before-reset.sql"
tar -czf "$BACKUP_DIR/storage-app.tgz" storage/app
printf 'Private backup files: %s\n' "$BACKUP_DIR"
# Type the database name when asked. Add the agreed --keep flags.
$PHP artisan seera:go-live-reset --execute --force --keep=...
$PHP artisan optimize:clear
$PHP artisan up
)
```

`mysqldump` needs the usual `~/.my.cnf` credentials or `-u/-p`; never put the password on the command line in a shared terminal. The command rolls back by itself if any dangling reference remains; a non-zero exit means nothing was deleted.

## 6. Immediate checks after the reset

- Log in as the Super Admin and one ordinary user: Dashboard, HR → Employees, Projects, Finance → Journal Entries, Inventory → Stock on Hand and Suppliers / Customers open without errors and show the expected empty (or kept) lists.
- Create one test-free real record the client needs first (for example the first customer): its document number must start from 1 unless `--keep-sequences` was used.
- Review the storage folders the command listed; delete them only if they contain nothing but test uploads, otherwise leave them.
- Record actual HEAD, dry-run output, flags used, "Reset complete" line with the row count, backup paths, operator and time in the handover record.

## 7. Recovery

If the reset result is wrong, restore the SQL backup taken in §5 (`mysql "$DB_NAME" < "$BACKUP_DIR/before-reset.sql"`) and `storage/app` from the archive, then `artisan optimize:clear`. To roll the code back, `git switch` to the branch recorded in `previous-branch.txt` at `previous-head.txt`, then `artisan optimize:clear` and `view:cache`. Production ZATCA stays disabled throughout; nothing in this guide enables it.
