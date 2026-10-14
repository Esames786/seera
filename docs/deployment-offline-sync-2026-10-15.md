# Deployment — Offline Mobile Sync Foundation, Phase 1 (GPS attendance + site expenses), 15 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md) and [deployment-review-fixes-2026-10-14.md](deployment-review-fixes-2026-10-14.md) (the cumulative release gate to `4d77c77`).

Brings the server from the reviewed continuation HEAD (`4d77c77`) to the Offline Sync HEAD. This release has **one additive migration**, **a rebuilt asset bundle** (the application JavaScript changed), and **three new static files** under `public/` that arrive with `git pull` (no build step). Deploy it only after the cumulative `4d77c77` release has been accepted; if the server is still at `8103bdb`, `migrate --force` applies the five continuation migrations and this one in order.

| Item | Value |
| --- | --- |
| Migration | `2026_10_15_000001_create_offline_sync_receipts` — creates `offline_sync_receipts` (one row per client UUID; hash + small response snapshot, no sensitive payload); adds nullable `check_in_captured_at`, `check_in_client_uuid`, `check_out_captured_at`, `check_out_client_uuid` to `attendance_records`; adds nullable `sync_client_uuid` (unique) and `captured_at_client` to `site_expenses`. Guarded (`hasTable` / `hasColumn`), re-runnable, data preserving; verified on MySQL 8.0.30 by `tests/manual/offline-sync-concurrency.php`. |
| Seeder | None. No permission rows are added: attendance sync uses Attendance → mobile, expense sync uses Site Expenses → create, the Mobile Sync page opens for either. |
| Asset bundle | **Rebuilt**: `public/build.zip`, 197,770 bytes, 18 files, SHA-256 `AB04040F83C8C0AB0A520ED710FA76D6F49536AA4D6D052C1CF3BFE59FEE1981`. New hashed assets `app-CorUbhvA.js` and `erp-B80nZ4NT.css`; `app-R27YIm39.css`, `fonts-DkuEHybc.css` and the 12 font files are unchanged. `manifest.json` and `fonts-manifest.json` at the ZIP root. |
| Static files (git) | `public/sw.js` (service worker, site scope `/`), `public/manifest.webmanifest`, `public/icons/seera-192.png`, `public/icons/seera-512.png`. They are plain files in the repository: no upload, no build. |
| New routes / screens | `GET /admin/mobile-sync` (MOB-001), `GET /admin/mobile-sync/session`, `GET /admin/mobile-sync/reference`, `POST /admin/mobile-sync/attendance`, `POST /admin/mobile-sync/site-expenses` (both throttled 30 / minute per user, batches of 20 attendance items or 5 expense items with files). Sidebar: Main → Mobile Sync for mobile-capable accounts. |
| Configuration | Conservative defaults in `config/seera.php` (`offline_sync`), all overridable by env without code change: `SEERA_OFFLINE_SYNC_BATCH_SIZE=20`, `SEERA_OFFLINE_SYNC_BATCH_SIZE_WITH_FILES=5`, `SEERA_OFFLINE_ATTENDANCE_MAX_AGE_HOURS=72`, `SEERA_OFFLINE_FUTURE_TOLERANCE_MINUTES=10`, `SEERA_OFFLINE_CLOCK_SKEW_ATTENTION_MINUTES=30`, `SEERA_OFFLINE_SITE_EXPENSE_MAX_AGE_DAYS=60`. Nothing needs to be added to `.env` for the defaults. |
| Accounting impact | None by sync. A synced expense is a draft; approval and accounting continue through the existing runtime. Offline attendance writes attendance rows only. |
| Private files | Receipts synced offline are stored on the same private `local` disk path as online receipts (`storage/app/private/site-expense-receipts`). |
| HTTPS | The service worker, geolocation and the web app manifest only work over HTTPS (already the case on `seera.tech-brit.co.uk`). |

## 1. Back up and inspect

As in the cumulative guide: verified private database backup, `.env`, `public/.htaccess`, `storage/app`, current `public/build` and the current commit, outside the web root. Then:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current          # expect feature/seera-connected-workspaces-2026-09-23
git status --short                 # expect only the known hosting edit to public/.htaccess and old server artefacts
git log -1 --oneline               # expect 4d77c77 (or the accepted release in production)
git fetch origin
$PHP artisan migrate:status | grep -i pending   # expect nothing before the pull
```

## 2. Deploy (only after verified backups), one command at a time

```bash
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
git log -1 --oneline
git status --short
echo 'ab04040f83c8c0ab0a520ed710fa76d6f49536aa4d6d052c1cf3bfe59fee1981  public/build.zip' | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json && echo MANIFEST-OK
test -f public/sw.js && test -f public/manifest.webmanifest && test -f public/icons/seera-192.png && echo STATIC-OK
$PHP artisan optimize:clear
$PHP artisan migrate:status | grep -i pending   # expect exactly 2026_10_15_000001_create_offline_sync_receipts (plus the continuation migrations if still pending)
$PHP artisan migrate --force
$PHP artisan migrate:status | grep -i pending   # expect nothing
$PHP artisan view:cache
$PHP artisan up
```

`composer.lock` is unchanged; no Composer step. Never `composer update`, `db:seed`, `migrate:fresh`, `migrate:refresh` or a blind `migrate:rollback`.

Service worker caching: `public/sw.js` must not be served with a long `Cache-Control` max-age, or phones keep an old worker. The current hosting `.htaccess` sets no long cache for root `.js` files; if a caching rule is added later, exclude `/sw.js` (or send `Cache-Control: no-cache` for it). Hashed assets under `/build/` may be cached long.

## 3. Post-deploy configuration (owner / HR / Finance confirm; nothing is configured automatically)

1. Roles: the mobile accounts hold Attendance → mobile (attendance) and / or Site Expenses → create (expenses); the Permission Matrix shows no new rows.
2. Users: Mobile Access on the mobile accounts; employee linked to the login; current project and site assignment.
3. Sites: coordinates, radius, Geofence enabled and Attendance inside only, as for online GPS attendance; the policy in force at sync time is applied to offline records.
4. Expense categories: `mobile_visible` set for the categories site staff may use; payment accounts (Cash in Hand / Bank) active.
5. Time policy: confirm the default 72-hour attendance sync window, the 30-minute clock-drift threshold and the 60-day expense-date limit, or set the env values above.
6. Decide the HR procedure for "Needs attention" attendance (manual entry by HR after review) and tell site staff that offline records must be synced from the same account.

## 4. Verify (browser, after `artisan up`)

Hard refresh (Ctrl+Shift+R) once so the new `app-CorUbhvA.js` loads, then on a real phone over HTTPS with an approved test employee and site:

- Open Mobile Attendance online once; open Mobile Sync (Main → Mobile Sync): it shows "Online · Pending: 0".
- Enable airplane mode; reopen Mobile Attendance (served from the cache); Check in → location fix → "Save check-in on device"; Mobile Sync shows Pending: 1.
- Disable airplane mode; the item syncs (or tap Sync now); the Attendance register shows one row with source **Offline GPS / Synchronized**, the captured time and the server's received time.
- Repeat for Check out; still one row.
- Offline: Site Expenses → Add (mobile shortcut) → fill and attach a photo → "Save on device (offline)"; reconnect; one draft expense with its receipt appears; submit it normally.
- Retry a synced item from Mobile Sync: no duplicate. Sign out with a pending item: warning shown; sign in with another test account: "belong to another signed-in account"; sign back in with the original: syncs.
- Check `storage/logs/laravel.log` for errors without sharing secrets.

## Recovery

Keep maintenance mode on if a step fails. The migration is additive: to back the code out, return to the previous commit and its matching `public/build.zip`; leave the new tables and columns in place (nullable or defaulted; old code ignores them). Do not drop `offline_sync_receipts` while any device may still replay: the receipts are what keep retries from creating duplicates. Obtain approval before any data restore.
