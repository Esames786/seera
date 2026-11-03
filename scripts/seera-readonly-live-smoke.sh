#!/usr/bin/env bash
# SEERA read-only production verification. No artisan writes, migrations, seeds, resets or deployments.
set -uo pipefail
cd "${SEERA_ROOT:-$HOME/seera}" || exit 2
PHP="${SEERA_PHP:-/opt/cpanel/ea-php83/root/usr/bin/php}"
EXPECTED="a3316be4209b13efdc54ce329fa21218e0a95165"
failed=0
check(){ if "$@" >/dev/null 2>&1; then printf 'PASS: %s\n' "$1"; else printf 'FAIL: %s\n' "$1"; failed=$((failed+1)); fi; }
printf '=== SEERA read-only smoke: %s ===\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
ACTUAL="$(git rev-parse HEAD 2>/dev/null || echo UNKNOWN)"
printf 'Actual HEAD: %s\nExpected HEAD: %s\n' "$ACTUAL" "$EXPECTED"
[ "$ACTUAL" = "$EXPECTED" ] && echo 'PASS: expected code SHA' || { echo 'FAIL: unexpected code SHA'; failed=$((failed+1)); }
printf 'Tracked working tree changes (review, do not reset):\n'
git diff --name-only
printf 'Git branch: '; git branch --show-current
"$PHP" -v | head -n 1
printf 'Required guide files:\n'
for file in docs/user-guide/index.html docs/user-guide/VISUAL-EXAMPLES.html docs/user-guide/VISUAL-EXAMPLES-AR.html; do
  if [ -s "$file" ]; then printf 'PASS: %s\n' "$file"; else printf 'FAIL: missing/empty %s\n' "$file"; failed=$((failed+1)); fi
done
printf 'PHP runtime checks (read-only Laravel bootstrap):\n'
"$PHP" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "Environment: ".config("app.env").PHP_EOL;
echo "ZATCA mode: ".config("zatca.environment").PHP_EOL;
$enabled = (bool) config("zatca.production_enabled");
echo "Production ZATCA enabled: ".($enabled ? "YES - STOP" : "NO").PHP_EOL;
$schema = Illuminate\Support\Facades\Schema::class;
$exists = $schema::hasTable("notifications");
echo "Notifications table: ".($exists ? "PRESENT" : "MISSING").PHP_EOL;
if ($exists) {
    $columns = ["id","type","notifiable_type","notifiable_id","data","read_at","created_at","updated_at"];
    foreach ($columns as $col) { if (! $schema::hasColumn("notifications", $col)) { echo "Missing notifications column: ".$col.PHP_EOL; exit(3); } }
}
if ($enabled || ! $exists) exit(4);
' || failed=$((failed+1))
printf 'Migration status for Notifications (read-only):\n'
"$PHP" artisan migrate:status --no-ansi | grep -E '2026_11_01_000001_create_notifications_table|Migration name' || echo 'NOT VERIFIED: check migration status manually'
printf 'Public endpoints (GET only; no credentials):\n'
for path in /login /user-guide; do
  code="$(curl --max-time 15 -sS -o /dev/null -w '%{http_code}' "https://seera.tech-brit.co.uk$path" || echo ERROR)"
  printf '%s -> HTTP %s\n' "$path" "$code"
done
echo 'NOTE: /user-guide may redirect unauthenticated users. Verify pages and bell manually with authorized test account.'
echo 'NOTE: client UAT, Finance reconciliation, Android/iPhone and restore evidence remain separate.'
printf 'Failures: %s\n' "$failed"
[ "$failed" -eq 0 ]
