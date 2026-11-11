#!/usr/bin/env bash
# Owner-authorized activation AFTER successful clean-start preparation; no migration/seeder.
set -euo pipefail
umask 077

PHP=/opt/cpanel/ea-php83/root/usr/bin/php
APP_DIR="$HOME/seera"
RUN_DIR="${1:?Pass the exact private preparation directory}"
BRANCH=feature/seera-clean-start-two-accounts-2026-11-11
RELEASE_SHA=48ea709e926d50858c4daf2cde0a5bdf7b914da5
export RUN_DIR

test -d "$RUN_DIR/candidate"
test "$(cat "$RUN_DIR/release-sha.txt")" = "$RELEASE_SHA"
test "$(git -C "$RUN_DIR/candidate" rev-parse HEAD)" = "$RELEASE_SHA"
cd "$APP_DIR"
test "$(git rev-parse HEAD)" = "$(cat "$RUN_DIR/previous-head.txt")"
test -f storage/framework/down
test -z "$(git diff --cached --name-only)"
cmp -s .env "$RUN_DIR/env.private"
git diff --name-only > "$RUN_DIR/activation-local-paths.txt"
while IFS= read -r path; do
    case "$path" in public/.htaccess|public/build.zip|'') ;; *) printf 'STOP: review local change: %s\n' "$path"; exit 1 ;; esac
done < "$RUN_DIR/activation-local-paths.txt"
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    test "$(git rev-parse "$BRANCH")" = "$RELEASE_SHA"
fi
git merge-base --is-ancestor "$RELEASE_SHA" "origin/$BRANCH"
git diff --quiet HEAD "$RELEASE_SHA" -- public/.htaccess

# Success is not inferred from a screenshot or an earlier dry run.
"$PHP" <<'PHP'
<?php
$files = glob(getenv('RUN_DIR').'/candidate/storage/app/private/operations/clean-start-*.json');
if (count($files) !== 1) { throw new RuntimeException('STOP: expected exactly one preparation report; review its exact path.'); }
$report = json_decode(file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);
foreach (['source_unchanged','ready_for_operator_review','all_destination_tables_innodb','business_tables_empty','master_sequences_preserved'] as $flag) {
    if (($report[$flag] ?? null) !== true) { throw new RuntimeException('STOP: preparation flag '.$flag); }
}
if (($report['dry_run'] ?? true) !== false || ($report['source_writes'] ?? true) !== false
    || ($report['automatic_promotion'] ?? true) !== false
    || ($report['source_database'] ?? '') !== 'techbrit_seera'
    || ($report['target_database'] ?? '') !== 'techbrit_seera_clean'
    || ($report['retained_user_ids'] ?? []) !== [1,15] || ($report['admin_user_id'] ?? 0) !== 1
    || empty($report['verified_master_hashes']) || ($report['verified_foreign_keys'] ?? 0) < 1) {
    throw new RuntimeException('STOP: preparation identity/verification mismatch.');
}
echo 'Preparation report verified; nullable removed-user links detached: ',
    array_sum($report['nullable_removed_login_references_detached'] ?? []), PHP_EOL;
echo 'Master opening balances are preserved; no balance is silently zeroed.', PHP_EOL;
PHP

read -r -s -p 'New database user password (hidden): ' SEERA_CLEAN_ACTIVATION_PASSWORD
printf '\n'
test -n "$SEERA_CLEAN_ACTIVATION_PASSWORD"
export SEERA_CLEAN_ACTIVATION_PASSWORD

# Check credentials and the retained set before changing the serving checkout/config.
cd "$RUN_DIR/candidate"
"$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$source = $app->make('db')->connection();
if ($source->getDatabaseName() !== 'techbrit_seera' || config('zatca.production_enabled')) {
    throw new RuntimeException('STOP: original DB/ZATCA guard.');
}
$config = $source->getConfig();
unset($config['url'], $config['name'], $config['read'], $config['write'], $config['sticky']);
$config['database'] = 'techbrit_seera_clean';
$config['username'] = 'techbrit_seera_clean';
$config['password'] = getenv('SEERA_CLEAN_ACTIVATION_PASSWORD');
config(['database.connections.seera_activation_check' => $config]);
$target = $app->make('db')->connection('seera_activation_check');
if ($target->selectOne('SELECT DATABASE() AS name')->name !== 'techbrit_seera_clean'
    || $target->table('users')->orderBy('id')->pluck('id')->map(fn($id) => (int)$id)->all() !== [1,15]) {
    throw new RuntimeException('STOP: destination identity/login set.');
}
echo "Destination credentials and retained users: OK\n";
PHP

cd "$APP_DIR"
git restore --source=HEAD -- public/build.zip
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    git switch "$BRANCH"
else
    git switch --create "$BRANCH" "$RELEASE_SHA"
fi
git branch --set-upstream-to="origin/$BRANCH" "$BRANCH"
test "$(git rev-parse HEAD)" = "$RELEASE_SHA"
cmp -s public/.htaccess "$RUN_DIR/public.htaccess"

# Atomically replace only the DB selection/credentials/URL and disable production ZATCA.
"$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
try {
    $path = '.env';
    if (is_link($path)) { throw new RuntimeException('Symlinked environment requires operator review.'); }
    $before = file_get_contents($path);
    if ($before === false || $before !== file_get_contents(getenv('RUN_DIR').'/env.private')) {
        throw new RuntimeException('Environment changed since preparation.');
    }
    $values = ['DB_DATABASE' => 'techbrit_seera_clean', 'DB_USERNAME' => 'techbrit_seera_clean',
        'DB_PASSWORD' => getenv('SEERA_CLEAN_ACTIVATION_PASSWORD'), 'DB_URL' => '', 'ZATCA_PRODUCTION_ENABLED' => 'false'];
    $after = $before;
    foreach ($values as $key => $value) {
        if (!is_string($value) || preg_match('/[\r\n\x00]/', $value)) { throw new RuntimeException('Invalid private environment value.'); }
        $line = $key.'="'.str_replace(['\\','"','$'], ['\\\\','\\"','\\$'], $value).'"';
        $pattern = '/^(?:export[ \t]+)?'.preg_quote($key,'/').'[ \t]*=.*$/m';
        if (preg_match_all($pattern, $after) > 1) { throw new RuntimeException('Duplicate environment key.'); }
        if (preg_match($pattern, $after)) {
            $after = preg_replace_callback($pattern, fn() => $line, $after);
        } else {
            $after = rtrim($after, "\r\n")."\n".$line."\n";
        }
    }
    $parsed = Dotenv\Dotenv::parse($after);
    foreach ($values as $key => $value) {
        if (($parsed[$key] ?? null) !== $value) { throw new RuntimeException('Environment round-trip failed.'); }
    }
    if (($parsed['APP_KEY'] ?? null) !== (Dotenv\Dotenv::parse($before)['APP_KEY'] ?? null)) {
        throw new RuntimeException('Application key changed.');
    }
    $temporary = tempnam(getcwd(), '.seera-env-');
    if ($temporary === false || file_put_contents($temporary, $after) !== strlen($after)
        || !chmod($temporary, 0600) || !rename($temporary, $path)) { throw new RuntimeException('Environment replacement failed.'); }
    echo "Private DB settings updated; APP_KEY and unrelated settings preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "STOP: private environment validation/write failed. No secret values printed. Keep maintenance enabled.\n");
    exit(1);
}
PHP
unset SEERA_CLEAN_ACTIVATION_PASSWORD

"$PHP" artisan optimize:clear
if [ -f composer.phar ]; then
    "$PHP" composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
    PATH="$(dirname "$PHP"):$PATH" composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi
echo 'F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7  public/build.zip' | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json

"$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
if ($db->getDatabaseName() !== 'techbrit_seera_clean' || $db->getConfig('username') !== 'techbrit_seera_clean'
    || config('zatca.production_enabled')) { throw new RuntimeException('STOP: resolved DB/ZATCA mismatch.'); }
$ids = $db->table('users')->orderBy('id')->pluck('id')->map(fn($id) => (int)$id)->all();
if ($ids !== [1,15] || !App\Models\User::findOrFail(1)->isSuperAdmin()) { throw new RuntimeException('STOP: retained logins/Admin access.'); }
foreach ($db->select('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=?', ['BASE TABLE']) as $table) {
    if (strcasecmp($table->engine,'InnoDB') !== 0) { throw new RuntimeException('STOP: non-InnoDB destination.'); }
}
foreach (array_diff(App\Console\Commands\GoLiveReset::TRANSACTIONS, ['supplier_projects','equipment_documents']) as $table) {
    if ($db->table($table)->exists()) { throw new RuntimeException('STOP: unexpected business data: '.$table); }
}
$m = $app->make('migrator');
if (!$m->repositoryExists() || array_diff(array_keys($m->getMigrationFiles(database_path('migrations'))), $m->getRepository()->getRan())) {
    throw new RuntimeException('STOP: missing/pending destination migrations.');
}
echo "New DB, two logins, InnoDB, empty business tables, migration ledger and disabled production ZATCA: OK\n";
PHP
"$PHP" artisan view:cache
git log -1 --oneline
"$PHP" artisan up
printf 'ACTIVATED: %s on techbrit_seera_clean. Verify both logins and November screens now.\n' "$RELEASE_SHA"
