#!/usr/bin/env bash
# Read-only DB inspection and private checkout preparation; no live code/DB switch.
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
