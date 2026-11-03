#!/usr/bin/env bash
# SEERA production smoke: read-only local metadata/DB schema and unauthenticated HTTP GETs.
# No migration, seeding, deployment, accounting write or ZATCA submission.
set -uo pipefail

EXPECTED_SHA='a3316be4209b13efdc54ce329fa21218e0a95165'
SITE='https://seera.tech-brit.co.uk'
PHP="${SEERA_PHP:-/opt/cpanel/ea-php83/root/usr/bin/php}"
ROOT="${SEERA_ROOT:-$HOME/seera}"
failed=0
pass() { printf 'PASS | %s\n' "$1"; }
fail() { printf 'FAIL | %s\n' "$1"; failed=$((failed + 1)); }
warn() { printf 'WARN | %s\n' "$1"; }

printf 'SEERA READ-ONLY LIVE PREFLIGHT | UTC %s\n' "$(date -u '+%Y-%m-%d %H:%M:%S')"
if ! cd "$ROOT"; then fail 'Application root is not accessible'; exit 2; fi
if ! command -v git >/dev/null 2>&1; then fail 'git executable not found'; exit 2; fi
if [ ! -x "$PHP" ]; then fail 'PHP binary is not executable (set SEERA_PHP)'; exit 2; fi
if ! command -v curl >/dev/null 2>&1; then fail 'curl executable not found'; exit 2; fi

head="$(git rev-parse HEAD 2>/dev/null)" || head='UNKNOWN'
printf 'Actual checkout SHA: %s\n' "$head"
if [ "$head" = "$EXPECTED_SHA" ]; then pass 'Approved Git commit matches'; else fail 'Checkout SHA differs from approved a3316be; STOP any rollout'; fi
tracked="$(git status --porcelain --untracked-files=no 2>/dev/null)" || tracked='STATUS_UNAVAILABLE'
if [ -n "$tracked" ]; then
  warn 'Tracked server overrides exist; preserve and review without git reset. Filenames:'
  git diff --name-only 2>/dev/null | sed 's/^/    /' || true
else
  pass 'Tracked Git checkout has no uncommitted changes'
fi

for page in docs/user-guide/index.html docs/user-guide/VISUAL-EXAMPLES.html docs/user-guide/VISUAL-EXAMPLES-AR.html; do
  if [ -s "$page" ]; then pass "Guide file present: $page"; else fail "Guide file missing or empty: $page"; fi
done

# Read-only Laravel boot and database metadata inspection. Do not echo secrets or .env.
runtime="$($PHP -r '
try {
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $environment = (string) config("zatca.environment");
    $enabled = (bool) config("zatca.production_enabled");
    $schema = Illuminate\Support\Facades\Schema::class;
    $exists = $schema::hasTable("notifications");
    $columns = ["id", "type", "notifiable_type", "notifiable_id", "data", "read_at", "created_at", "updated_at"];
    $complete = $exists;
    if ($exists) foreach ($columns as $name) if (! $schema::hasColumn("notifications", $name)) $complete = false;
    echo "ZATCA_MODE=".$environment.PHP_EOL;
    echo "ZATCA_PRODUCTION_ENABLED=".($enabled ? "YES" : "NO").PHP_EOL;
    echo "NOTIFICATIONS_SCHEMA=".($complete ? "READY" : "MISSING_OR_INCOMPLETE").PHP_EOL;
    if ($enabled || ! $complete) exit(8);
} catch (Throwable $e) {
    fwrite(STDERR, "READ_ONLY_BOOTSTRAP_ERROR=".get_class($e).PHP_EOL);
    exit(9);
}
' 2>&1)" && runtime_rc=0 || runtime_rc=$?
printf '%s\n' "$runtime"
if [ "$runtime_rc" -eq 0 ]; then pass 'Laravel schema and production ZATCA gate'; else fail "Laravel/ZATCA/notifications preflight (exit $runtime_rc)"; fi

migrations="$($PHP artisan migrate:status --no-ansi 2>&1)" && migration_rc=0 || migration_rc=$?
if [ "$migration_rc" -ne 0 ]; then
  fail "Unable to read migration status (exit $migration_rc)"
else
  notification_line="$(printf '%s\n' "$migrations" | grep '2026_11_01_000001_create_notifications_table' || true)"
  printf 'Notification migration: %s\n' "${notification_line:-NOT_FOUND}"
  if printf '%s\n' "$notification_line" | grep -Eq 'Ran[[:space:]]*$'; then
    pass 'Notification migration marked Ran'
  else
    fail 'Notification migration missing or not Ran'
  fi
  if printf '%s\n' "$migrations" | grep -Eq '[[:space:]]Pending[[:space:]]*$'; then
    fail 'Pending database migrations exist; manual review required'
  else
    pass 'No pending migrations reported'
  fi
fi

# Verify the CSS bundle: this catches Laravel working while layouts are unstyled.
css="$($PHP -r '
$m = json_decode(@file_get_contents("public/build/manifest.json") ?: "", true);
$c = $m["resources/css/erp.css"]["file"] ?? "";
if (is_string($c) && preg_match("~^assets/[A-Za-z0-9_.-]+\\.css$~", $c)) echo $c;
' 2>/dev/null)" || css=''
if [ -n "$css" ] && [ -s "public/build/$css" ]; then
  pass 'Vite ERP CSS listed in manifest and exists on disk'
  css_status="$(curl -sS --connect-timeout 6 --max-time 18 -o /dev/null -w '%{http_code}|%{content_type}' "$SITE/build/$css" 2>/dev/null)" && css_rc=0 || css_rc=$?
  if [ "$css_rc" -eq 0 ] && [[ "$css_status" =~ ^200\|text/css ]]; then
    pass "Public CSS served correctly ($css_status)"
  else
    fail "Public CSS failed check ($css_status, curl exit $css_rc)"
  fi
else
  fail 'Vite CSS manifest entry/file missing'
fi

for path in login user-guide; do
  status="$(curl -sS --connect-timeout 6 --max-time 18 -o /dev/null -w '%{http_code}' "$SITE/$path" 2>/dev/null)" && request_rc=0 || request_rc=$?
  printf 'GET /%s -> HTTP %s\n' "$path" "${status:-NO_RESPONSE}"
  if [ "$request_rc" -ne 0 ]; then
    fail "GET /$path could not complete (curl exit $request_rc)"
  elif [ "$path" = login ] && [ "$status" = 200 ]; then
    pass 'Unauthenticated Login GET returns HTTP 200'
  elif [ "$path" = user-guide ] && [[ "$status" =~ ^30[12378]$ ]]; then
    pass 'User Guide rejects guest with redirect (authentication still needs browser test)'
  else
    fail "Unexpected guest HTTP status for /$path: $status"
  fi
done

printf '\nNot verified by this script: authenticated bell/inbox, English/Arabic images,\n'
printf 'client UAT, live GL/AP/AR/VAT reconciliation, backup restore, Android/iPhone,\n'
printf 'client sign-off, and ZATCA company onboarding.\n'
printf 'READ-ONLY PREFLIGHT FAILURES: %s\n' "$failed"
if [ "$failed" -eq 0 ]; then
  printf 'RESULT: PASS for limited technical checks; NOT a client or deployment sign-off.\n'
else
  printf 'RESULT: INVESTIGATE failures; do not reset or migrate automatically.\n'
fi
[ "$failed" -eq 0 ]
