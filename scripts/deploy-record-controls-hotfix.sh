#!/usr/bin/env bash
# Existing clean-start portal only: update UI/assets, never migrate/reset/change DB settings.
set -euo pipefail
umask 077
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
BRANCH=feature/seera-clean-start-two-accounts-2026-11-11
RELEASE_SHA="${1:?Pass the exact verified UI hotfix commit}"
[[ "$RELEASE_SHA" =~ ^[0-9a-f]{40}$ ]]
cd "$HOME/seera"
test "$(git branch --show-current)" = "$BRANCH"
test -z "$(git diff --cached --name-only)"
test ! -L public/build
test -z "$(find public/build -type l -print -quit)"
git fetch origin "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
git merge-base --is-ancestor HEAD "$RELEASE_SHA"
git merge-base --is-ancestor "$RELEASE_SHA" "origin/$BRANCH"
git diff --quiet HEAD "$RELEASE_SHA" -- app database routes composer.json composer.lock public/.htaccess
HOTFIX_DIR="$(mktemp -d "$HOME/seera-record-controls.XXXXXX")"
git diff --name-only > "$HOTFIX_DIR/local-paths.txt"
while IFS= read -r path; do
    case "$path" in public/.htaccess|public/build.zip|'') ;; *) printf 'STOP: review local change: %s\n' "$path"; exit 1 ;; esac
done < "$HOTFIX_DIR/local-paths.txt"
git rev-parse HEAD > "$HOTFIX_DIR/previous-head.txt"
git diff --binary > "$HOTFIX_DIR/local-changes.patch"
cp -p .env "$HOTFIX_DIR/env.private"
cp -p public/.htaccess "$HOTFIX_DIR/public.htaccess"
cp -p public/build.zip "$HOTFIX_DIR/previous-build.zip"
if [ -d public/build ]; then cp -a public/build "$HOTFIX_DIR/previous-build"; fi
"$PHP" <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if ($app->make('db')->connection()->getDatabaseName() !== 'techbrit_seera_clean' || config('zatca.production_enabled')) {
    throw new RuntimeException('STOP: current clean DB/ZATCA guard. No migration/reset is part of this UI update.');
}
echo "Current clean database confirmed; existing entries will be retained.\n";
PHP
printf 'Private rollback files: %s\n' "$HOTFIX_DIR"
"$PHP" artisan down
git restore --source=HEAD -- public/build.zip
git merge --ff-only "$RELEASE_SHA"
cmp -s .env "$HOTFIX_DIR/env.private"
cmp -s public/.htaccess "$HOTFIX_DIR/public.htaccess"
echo 'A0B6A8840C633152048487712FF8EEF78B190CCF559A29927240715FD0CD9115  public/build.zip' | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
find public/build -type d -exec chmod 755 {} +
find public/build -type f -exec chmod 644 {} +
"$PHP" artisan view:clear
"$PHP" artisan view:cache
ASSET_JS="$("$PHP" -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true,512,JSON_THROW_ON_ERROR);echo $m["resources/js/app.js"]["file"];')"
ASSET_CSS="$("$PHP" -r '$m=json_decode(file_get_contents("public/build/manifest.json"),true,512,JSON_THROW_ON_ERROR);echo $m["resources/css/erp.css"]["file"];')"
curl -fsS -o /dev/null -w 'JavaScript HTTP %{http_code}\n' "https://seera.tech-brit.co.uk/build/$ASSET_JS"
curl -fsS -o /dev/null -w 'Stylesheet HTTP %{http_code}\n' "https://seera.tech-brit.co.uk/build/$ASSET_CSS"
git log -1 --oneline
"$PHP" artisan up
printf 'RECORD CONTROLS UPDATED: %s; DB/config and existing records unchanged.\n' "$RELEASE_SHA"
