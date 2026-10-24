# Deployment addendum — ZATCA production-readiness gate (printable invoice, certificate expiry check), 24 October 2026

Feature branch `feature/seera-connected-workspaces-2026-09-23`. Extends [deployment-customer-credit-resolution-2026-10-23.md](deployment-customer-credit-resolution-2026-10-23.md). Readiness and activation plan: [zatca-production-readiness-2026-10-24.md](zatca-production-readiness-2026-10-24.md).

**Code-only release.** No migration, no seeder, no permission rows, no configuration change, asset bundle unchanged (`public/build.zip` 197,777 bytes, SHA-256 `436847840B7FC77904572B45ED3388752BA76CBC0A12290EFBE821A31BD18B7C`). One new static file outside the bundle: `public/vendor/qrcode-generator/qrcode.js` (MIT, vendored unchanged, served by the web server like any public file). Not deployed; production ZATCA stays disabled.

| Change | Effect |
| --- | --- |
| Customer Invoice print view (FIN-AR-011) and shared print layout for notes | New GET route `accounts-receivable/{id}/print` (Accounts Receivable — view, approved invoices only). |
| Certificate-expiry readiness rule | A unit whose `certificate_expires_at` is past fails readiness (`unit_certificate_expired`); nothing is signed with a lapsed certificate. |
| Company logo on prints | Read from `company_profiles.logo_path` (public disk or `public/`); optional. |

Deploy: `git pull --ff-only`, `optimize:clear`, `view:cache`; no `migrate`, no bundle step. Verify: open an approved invoice → Print Invoice; the page shows both languages, the totals and, for a stamped invoice, the QR square.
