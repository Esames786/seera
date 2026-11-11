# November clean start and feature activation — 11 November 2026

This replaces the full historical-data preservation procedure **only for the owner's revised clean-start request**. It does not change the older copy command's backup requirements. The source database is never deleted or rewritten by the new preparation command; no separate SQL backup is required by this path.

## Exact scope and candidate

- Branch: `feature/seera-clean-start-two-accounts-2026-11-11`.
- Application/guide pin: `48ea709e926d50858c4daf2cde0a5bdf7b914da5` (includes the complete November fixes and updated visual guide). Full application verification was at `02f6da4`; subsequent changes are the client guide, its read-only validator and the independent test probe. The updated guide passed its focused checks.
- Old serving DB: `techbrit_seera`; destination: **`techbrit_seera_clean`**.
- Retain login **1**, the existing active Super Admin; retain login **15**, the client's existing Finance Manager. Password hashes, role assignments, scope and individual permissions are retained. This does **not** promote the client to Super Admin.
- All other logins are excluded from the **new** database. They still exist in the untouched old database, which is no longer used after cutover. Do not claim they have been physically purged from the old DB.
- Preserve all existing setup and entry masters: company, organisation, roles/permissions, chart/posting rules, lookup values, leave types, shifts, workflows, customers/suppliers and their files, Employee records/photos/documents/salary structures/shifts, projects/sites, items/warehouses, equipment assets/documents and Supplier/Project links.
- No old business documents, GL/VAT entries, payroll/attendance/leave requests, stock balances/movements, equipment usage, approval instances, notifications, sessions, queued jobs or logs are copied. Existing uploads remain on disk; no file deletion is performed.
- Master opening balances, IDs, codes, document sequences and auto-increment positions are retained. Empty transaction history does not imply zero master opening balances. Review the report's nonzero opening-balance counts before activation; do not fabricate opening journals or silently zero master amounts.
- Nullable master references to excluded users are detached **in the destination only** and counted in the private report. An explicit workflow approver being excluded, an active equipment assignment, unknown tables/fields, invalid master references, source drift, production ZATCA history or a nonempty target stops preparation. No automatic replacement Admin, role escalation or silent repair.

The owner's screenshot proves DB creation, but shows the user-creation wizard. Finish **Create User**, then **Add User to Database → ALL PRIVILEGES**. The example assumes destination DB username `techbrit_seera_clean`. Its password is entered at a hidden prompt; never paste it into chat, command arguments or documentation. Source credentials remain in the original `.env`; they may be different.

## A. Prepare code and inspect — portal stays up

Run this first. It creates a private candidate outside the web root and inspects the source/empty destination. It does not change the live branch, DB, configuration or maintenance state.

The same block is also shipped as `scripts/prepare-clean-start-preflight.sh`. Fetch its exact reviewed documentation commit into the old checkout and extract it to a private temporary file with `git show`; run that saved file with Bash. Do not switch the live branch simply to obtain this script.

```bash
(
set -euo pipefail
umask 077
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
BRANCH=feature/seera-clean-start-two-accounts-2026-11-11
RELEASE_SHA=48ea709e926d50858c4daf2cde0a5bdf7b914da5
APP_DIR="$HOME/seera"

test "$(git rev-parse HEAD)" = 9cc5ea0179c6e104750fcb1249ec3f684194ad38
test -z "$(git diff --cached --name-only)"
RUN_DIR="$(mktemp -d "$HOME/seera-clean-start.XXXXXX")"
git diff --name-only > "$RUN_DIR/local-paths.txt"
while IFS= read -r path; do
    case "$path" in public/.htaccess|public/build.zip|'') ;; *) printf 'STOP: review local change: %s\n' "$path"; exit 1 ;; esac
done < "$RUN_DIR/local-paths.txt"
git fetch origin "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
git merge-base --is-ancestor "$RELEASE_SHA" "origin/$BRANCH"
git diff --quiet HEAD "$RELEASE_SHA" -- public/.htaccess
git rev-parse HEAD > "$RUN_DIR/previous-head.txt"
git branch --show-current > "$RUN_DIR/previous-branch.txt"
printf '%s\n' "$RELEASE_SHA" > "$RUN_DIR/release-sha.txt"
git diff --binary > "$RUN_DIR/local-changes.patch"
cp -p .env "$RUN_DIR/env.private"
cp -p public/.htaccess "$RUN_DIR/public.htaccess"
cp -p public/build.zip "$RUN_DIR/previous-build.zip"
if [ -d public/build ]; then cp -a public/build "$RUN_DIR/previous-build"; fi
git worktree add --detach "$RUN_DIR/candidate" "$RELEASE_SHA"
cp -p .env "$RUN_DIR/candidate/.env"
chmod 600 "$RUN_DIR/candidate/.env"
cd "$RUN_DIR/candidate"
if [ -f "$APP_DIR/composer.phar" ]; then
    "$PHP" "$APP_DIR/composer.phar" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
else
    PATH="$(dirname "$PHP"):$PATH" composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts
fi
"$PHP" artisan package:discover
"$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
if ($db->getDatabaseName() !== 'techbrit_seera' || config('zatca.production_enabled')) {
    throw new RuntimeException('STOP: original source DB/ZATCA guard.');
}
foreach ([1 => 'admin@seera.com', 15 => 'm.ashfaq@seerarental.com'] as $id => $email) {
    $user = $db->table('users')->where('id', $id)->first(['email','status']);
    if (!$user || strcasecmp($user->email, $email) !== 0 || $user->status !== 'active') {
        throw new RuntimeException('STOP: retained login identity/status changed: '.$id);
    }
}
echo "Original DB and retained login identities: OK\n";
PHP
printf 'Record this private RUN_DIR for subsequent steps: %s\n' "$RUN_DIR"
"$PHP" artisan seera:prepare-clean-start --target=techbrit_seera_clean \
    --target-user=techbrit_seera_clean --keep-users=1,15 --admin-id=1
)
```

Enter the **new DB user's password** at the hidden prompt. No SQL backup or maintenance is needed for A. On access denial, finish the cPanel user/database privilege assignment; do not change the original DB user or paste passwords. On any other refusal, inspect the reason before proceeding. Save the printed `RUN_DIR` privately. Do not create/empty another database by guessing.

## B. Final preparation — stopped writers

Set `RUN_DIR` to the exact directory printed by A. No placeholder below is a valid operator value. Keep `~/seera/.env` on the **old** DB throughout B.

```bash
read -r -p 'Exact private RUN_DIR printed by step A: ' RUN_DIR
export RUN_DIR
(
set -euo pipefail
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
test -d "$RUN_DIR/candidate"
test -f "$RUN_DIR/previous-head.txt"
test "$(git -C "$HOME/seera" rev-parse HEAD)" = "$(cat "$RUN_DIR/previous-head.txt")"
cd "$HOME/seera"
"$PHP" artisan down
# Pause/drain actual hosting cron, workers and external writers, if any.
read -r -p 'When all source writers are stopped, type WRITERS-STOPPED: ' CONFIRM
test "$CONFIRM" = WRITERS-STOPPED
cd "$RUN_DIR/candidate"
test "$(git rev-parse HEAD)" = "$(cat "$RUN_DIR/release-sha.txt")"
"$PHP" artisan down
"$PHP" artisan seera:prepare-clean-start --target=techbrit_seera_clean \
    --target-user=techbrit_seera_clean --keep-users=1,15 --admin-id=1 \
    --execute --writers-stopped --force
)
```

The new password is prompted privately again. At the destination confirmation enter exactly `techbrit_seera_clean`. This migrates **only the separate empty destination**, runs no seeder and changes no source table/engine/record. It prints a private verification report path. Review `ready_for_operator_review`, `source_unchanged`, exact retained IDs, master hashes/counts, detached references, empty business tables and all-InnoDB/FK results.

If preparation fails, do **not** activate the partial target. It stays available for inspection; no automatic deletion/retry occurs. While the code/config are still old, verify old HEAD and resolved source DB and reopen the old portal if needed. The existing database is intact. Do not use `migrate:fresh`, reset or fake backup attestations to force a refusal through.

## C. Activate verified code/database/assets

Only after B succeeds and its report is reviewed. Keep both old writers stopped and the live portal down. Do not write training transactions into the final copy before this switch.

### Single-script alternative (recommended after the owner-reported successful preparation)

`scripts/activate-clean-start.sh` performs the following manual C steps, including private `.env` replacement, in one guarded run. Use **either this script or the manual procedure below**, not both. It expects the source code/config to remain unchanged since A, maintenance enabled, the same pinned candidate, and exactly one successful preparation report matching source/target and retained IDs. It validates new credentials before changing the serving checkout. It privately prompts for the new DB password once, preserves APP_KEY/unrelated settings, installs locked dependencies, verifies/extracts the existing ZIP, checks resolved target/users/schema/empty transactions, then runs `artisan up`. It performs no migration/seeder and does not remove the old source database.

Fetch the exact reviewed tools commit supplied in the operator reply, save `git show COMMIT:scripts/activate-clean-start.sh` to a private temporary file, then execute `bash FILE /home/techbrit/seera-clean-start.ZgB77G` for the owner's confirmed run. No manual `.env` edit is needed for this option. On failure maintenance stays enabled; inspect the output before any retry or reopening. Do not invent a success report or copy a dry-run JSON into the report folder.

### Manual alternative

Using cPanel File Manager, edit **`~/seera/.env` privately**:

```dotenv
DB_DATABASE=techbrit_seera_clean
DB_USERNAME=techbrit_seera_clean
```

Set `DB_PASSWORD` to the actual new database-user password. Keep `APP_KEY`, domain/session settings, uploads and all unrelated configuration unchanged. Set any conflicting `DB_URL` to empty. Keep `ZATCA_PRODUCTION_ENABLED=false`. Do not paste the file or password into chat. The private candidate `.env` stays on the original DB for source comparison.

With the exact `RUN_DIR` still exported, run:

```bash
(
set -euo pipefail
umask 077
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
cd ~/seera
BRANCH=feature/seera-clean-start-two-accounts-2026-11-11
RELEASE_SHA="$(cat "$RUN_DIR/release-sha.txt")"
test "$(git rev-parse HEAD)" = "$(cat "$RUN_DIR/previous-head.txt")"
test -f storage/framework/down
test -z "$(git diff --cached --name-only)"
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
echo 'F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7  public/build.zip' | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test ! -L public/build
test -z "$(find public/build -type l -print -quit)"
find public/build -type d -exec chmod 755 {} +
find public/build -type f -exec chmod 644 {} +
test -f public/build/manifest.json
"$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
if ($db->getDatabaseName() !== 'techbrit_seera_clean' || config('zatca.production_enabled')) {
    throw new RuntimeException('STOP: destination DB/ZATCA mismatch.');
}
$ids = $db->table('users')->orderBy('id')->pluck('id')->map(fn($id) => (int)$id)->all();
if ($ids !== [1,15]) { throw new RuntimeException('STOP: unexpected retained user set.'); }
if (!App\Models\User::findOrFail(1)->isSuperAdmin()) { throw new RuntimeException('STOP: retained Admin access.'); }
foreach ($db->select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=?', ['BASE TABLE']) as $table) {
    if (strcasecmp($table->engine,'InnoDB') !== 0) { throw new RuntimeException('STOP: non-InnoDB destination table.'); }
}
foreach (['customer_invoices','supplier_bills','journal_entries','vat_transactions','payroll_runs','stock_ledger_entries','warehouse_stocks','notifications'] as $table) {
    if ($db->table($table)->exists()) { throw new RuntimeException('STOP: unexpected business data in '.$table); }
}
$m = $app->make('migrator');
if (!$m->repositoryExists() || array_diff(array_keys($m->getMigrationFiles(database_path('migrations'))), $m->getRepository()->getRan())) {
    throw new RuntimeException('STOP: missing/pending destination migrations.');
}
echo "New DB, retained users, empty transactions, InnoDB, migration ledger and ZATCA guards: OK\n";
PHP
"$PHP" artisan view:cache
"$PHP" artisan migrate:status
git log -1 --oneline
"$PHP" artisan up
)
```

No `artisan migrate --force` or seeder is needed on the serving checkout: B has already migrated the destination. Do not add `artisan optimize` blindly or an unconditional `artisan up` trap. On failure the portal remains down until the correct code/config/DB pair is restored or repaired.

## D. Live acceptance and recovery boundary

- Admin and client can sign in using existing passwords; Users shows exactly two logins. Client remains Finance Manager. New users can be created normally later; setup seeders must not recreate demo accounts.
- Employee Personal has the photo chooser. Customer/Supplier Create has readonly next-code preview and document rows; saved records/files still open. Verify permissions with the actual intended login, not just Admin.
- Login/Dashboard assets and English/Arabic render correctly. The November guide URL remains `/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html` and is accessible from User Guide/topbar.
- Master data, saved attachments, salary/leave setup, project/site links and chart/posting rules match the report. Business ledgers/history are empty, with any retained nonzero master opening amounts separately reviewed.
- Role-based approval workflows remain configured. Removing old logins can leave some approver roles unstaffed until the client creates replacement users. Do not grant new roles or redirect approvals to Admin automatically; assign the approved users before testing those workflows end to end.
- Overdue reminders remain default off until their documented setting/hosting scheduler is explicitly activated. No new background queue worker is launched by this deployment. Supplier advance posting still requires its dedicated Finance account mapping; no seeder fabricates one. Production ZATCA remains disabled.
- Resume only previously approved writers after these checks. The old DB and original uploads are not deleted by this procedure.
- Before any new business writes, rollback means maintenance, restore the recorded old branch/HEAD, old `.env`, `.htaccess` and build files from `RUN_DIR`, reinstall its locked Composer dependencies, clear compiled config/views, verify resolved `techbrit_seera`, and reopen. Once the new DB accepts real writes, **do not switch back to stale old data**: stop and reconcile those writes first. Do not run schema rollback against either database to undo this activation.

These are operator instructions, not evidence that production migration, cleanup or activation has occurred. Record actual outputs and smoke results after execution.
