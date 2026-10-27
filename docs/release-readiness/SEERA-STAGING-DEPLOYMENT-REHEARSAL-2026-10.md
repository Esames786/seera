# Local rehearsal and browser evidence — 26 October 2026

**Remote staging: NOT RUN.** No separately authorised remote staging environment was supplied. This review created an isolated local synthetic fixture; no production database was copied or modified.

## Isolation and setup

- Exact app baseline `aca78ee`; PHP 8.3.16 local development server on **127.0.0.1:18769 only**.
- New temporary root `seera-uat-20261026-f4b174e2450e4af9a3a47f9bf40c31b5` under the local user's Temp directory; separate SQLite `uat.sqlite`, generated APP_KEY, storage, file sessions, compiled views and cache paths.
- `APP_ENV=testing`, `APP_DEBUG=false`, empty DB_URL, array cache/mail, sync queue, null broadcaster, sandbox ZATCA and production flag false. No external submission action was invoked. Browser interception refused every non-local origin; service workers were deliberately blocked for this smoke test.
- Fresh `artisan migrate --seed --force` ran **only on this new disposable synthetic database**. All 39 migrations and the existing training seed completed. This is not an instruction to seed the live installation.
- Initial local setup failed before migration because Laravel treated a Windows drive-letter cache path as relative. The fixture switched only its cache environment paths to an isolated ignored workspace directory, then setup passed. Application source/configuration was not changed.
- This is a clean local installation, not a production-copy upgrade, MySQL rehearsal, HTTPS/session deployment test or backup restore.

## Browser result

Harness: `tests/manual/uat-browser-smoke.cjs`, installed Playwright with local headless Microsoft Edge, viewport 1440×1000 then 390×844. Actual Laravel HTTP and compiled production assets were used, not mocked HTML.

**33 checks: 30 PASS, 3 FAIL.** [Machine-readable results](evidence/browser-results.json).

Passed: synthetic admin login; 21 register/dashboard/guide navigation checks; Employee/Customer/Supplier/Project edit pages with Save & Close visible; Customer unsaved-change Keep/Discard and unchanged value after reload; persisted Arabic locale and RTL shell; no browser JavaScript exceptions or failed same-origin responses.

Failed: mobile Arabic page overflow on Dashboard, Employee Edit and Customer Edit. See [defect register](SEERA-RELEASE-DEFECTS-2026-10.md), UI-01. Screenshots also show partial English text in the Arabic shell (GAP-02).

Not exercised by this browser run: save-to-database across every workspace, all restricted-role browser journeys, financial posting in the UI, QR scanning, print pagination, real GPS, device suspension, offline queue/service worker, iOS or client participation. Automated financial tests complement this smoke run; they do not fill these manual/device gaps.

The local fixture has synthetic records only. Its generated key/process-env.json is outside Git; only selected screenshots and sanitised results are retained in `evidence/`. Do not publish an environment file or copy the temporary fixture onto production.
