# Cumulative release inventory — 26 October 2026

Start/end application baseline: `aca78ee`, feature branch `feature/seera-connected-workspaces-2026-09-23`. Existing untracked `docs/FABLE-HANDOFF-2026-10-08.md` was preserved, not treated as a new authored deliverable. This pass adds tests and review evidence only.

| Area | Verified source / release evidence | Outstanding operational evidence |
| --- | --- | --- |
| Production code | Owner terminal: fast-forward `4d77c77..aca78ee`, HEAD and origin match, application live | Current remote state after that transcript not independently inspected |
| Database | Four migrations in batch 11: offline_sync_receipts; add_zatca_phase2; add_customer_adjustment_notes; add_customer_credit_resolution | Actual database counts, foreign-key integrity and GL totals before/after not supplied; no production-copy rehearsal |
| Assets | `public/build.zip` 197,790 bytes, 18 files, SHA-256 `E51BFFBC4C02B5FDEA33AE0281B8DEC02CAD236B97BD59F6A1CE7E0D0E2AF228`; owner verified/extracted | CDN/browser caching and phone acceptance |
| Non-Vite assets | Git supplies `public/sw.js`, `manifest.webmanifest`, `icons/*`, vendored QR script | Real offline service worker and QR-scanner acceptance |
| Server/runtime | Owner Composer platform verification completed with PHP CLI path `/opt/cpanel/ea-php83/root/usr/bin/php`; local review PHP 8.3.16 | Actual cPanel PHP build/extensions, DB version, timezone and web/CLI parity not independently read |
| Permissions | Existing per-action catalogue, scope middleware, runtime subjects and workspace tests; no new permissions in `aca78ee` correction | Real role assignments, configured approvers and restricted-user acceptance |
| Files | Private employee/payroll/ZATCA data paths and guarded downloads exist; APP_KEY encrypts ZATCA credentials | Production ownership, quota, private backups and restore |
| Jobs/scheduler | `routes/console.php` declares only the inspire command; no ShouldQueue or Schedule registration found in this source search | Actual cPanel cron/supervisor/external automation unknown; source search is not server inspection |
| Financial identity | Stored document numbers, posting source IDs, settlement idempotency, immutable approved history | Production numbering history and historic inconsistencies not audited in this pass |
| External configuration | ZATCA env/production flag, company/unit credentials, maps/GPS/device permissions; telematics readiness fields | No new credentials or providers activated; production settings not changed |

Classification and priorities are in [the current-state audit](SEERA-CUMULATIVE-RELEASE-AUDIT-2026-10.md). R22-02, full Arabic coverage and device/client acceptance are not completed merely because migrations and tests passed.

The deployed ZATCA migration includes legacy local-status normalization, so it is not described as exclusively creating empty tables. No historical migration was edited by this review. Its prior MySQL checks are retained as evidence; a fresh sanitised upgrade and restore remains NOT RUN.
