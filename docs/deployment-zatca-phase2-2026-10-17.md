# Deployment — ZATCA e-invoicing Phase 2, sprint 1 (compliant XML, stamp, sandbox clearance / reporting), 17 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md), [deployment-offline-sync-2026-10-15.md](deployment-offline-sync-2026-10-15.md) and the official-source record [zatca-phase2-source-baseline-2026-10-17.md](zatca-phase2-source-baseline-2026-10-17.md).

Brings the server from the Offline Sync HEAD (`a2b8d4d`) to the ZATCA Phase 2 sprint-1 HEAD. **One additive migration, a rebuilt asset bundle (the generated stylesheet changed with the new screens; no JavaScript change), no seeder, no new permission rows.** Deploying this release does **not** connect the company to ZATCA: the environment stays `sandbox`, production is disabled, and nothing is sent until the owner completes onboarding in ZATCA Settings.

| Item | Value |
| --- | --- |
| Migration | `2026_10_17_000001_add_zatca_phase2` — adds invoice type / issue time / supply date / snapshots / lock to `customer_invoices`; the state machine, environment, unit, ICV / PIH / hash, XML paths, QR, validation and response columns to `zatca_invoice_records` (legacy `clearance_status` kept as history; every existing row becomes `not_generated` because no XML ever existed); creates `zatca_egs_units` and `zatca_submissions`; adds national-address and identification columns to `customers` and `company_profiles`. Guarded, re-runnable, verified on MySQL 8.0.30 by `tests/manual/zatca-concurrency.php`. |
| Seeder | None. |
| Permissions | None added: the existing **ZATCA Invoicing** module actions are reused — view (register, record), edit (Settings and onboarding incl. credentials), post (generate / stamp / submit / regenerate), retry (resubmit), export (XML downloads). Grant `edit` only to the owner / Finance administrator. |
| Asset bundle | **Rebuilt**: `public/build.zip` 197,777 bytes, 18 files, SHA-256 `436847840B7FC77904572B45ED3388752BA76CBC0A12290EFBE821A31BD18B7C`. Changed asset `app-DiaSRgCb.css` (Tailwind output for the new ZATCA screens); `app-CorUbhvA.js`, `erp-B80nZ4NT.css`, `fonts-DkuEHybc.css` and the 12 font files are unchanged. |
| Configuration | `config/zatca.php`. Defaults: `ZATCA_ENVIRONMENT=sandbox`, `ZATCA_PRODUCTION_ENABLED=false`. Optional: `ZATCA_SANDBOX_BASE_URL` (confirm from the Developer Portal Swagger), `ZATCA_SIMULATION_BASE_URL`, `ZATCA_PRODUCTION_BASE_URL`, `ZATCA_HTTP_TIMEOUT`, `ZATCA_OPENSSL_CONFIG` (only when PHP has no default `openssl.cnf`; cPanel ea-php83 has one), `ZATCA_SOLUTION_PROVIDER`, `ZATCA_SOLUTION_VERSION`. Nothing needs to be added to `.env` for the defaults. **Never put a CSID, secret, OTP or private key in `.env`**: credentials are entered in ZATCA Settings and stored encrypted with `APP_KEY`. |
| PHP requirements | `openssl` (ECDSA secp256k1 — verify with `$PHP -r 'var_dump(in_array("secp256k1", openssl_get_curve_names()));'`), `dom`, `xmlwriter`, `bcmath`, `mbstring`. |
| New routes / screens | `/admin/accounting/zatca` (FIN-ZAT-001 register), `/admin/accounting/zatca/{id}` (FIN-ZAT-002 record with actions and attempts), `/admin/accounting/zatca/settings` (FIN-ZAT-003 Settings & Onboarding), XML downloads `/admin/accounting/zatca/{id}/xml/{unsigned|signed|cleared}`. Customer Invoice workspace gains the ZATCA E-Invoice section. |
| Private files | XML artifacts under `storage/app/private/zatca/{environment}/{uuid}/` (unsigned, signed, cleared). Make sure the local disk is writable as for receipts. |
| Accounting impact | None. ZATCA transmission never posts a journal or VAT row. |
| Behaviour change | Approving a customer invoice now also records the issue time and freezes the seller / buyer snapshot; a stamped or submitted invoice can no longer be reopened (credit / debit notes are not available yet). The invoice form has an Invoice type (standard / simplified) and Supply date; the Customer and Company Profile forms have national-address and identification fields. |

## 1. Back up and inspect

As in the previous guides (database, `.env`, `public/.htaccess`, `storage/app`, current commit), then:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
git branch --show-current          # expect feature/seera-connected-workspaces-2026-09-23
git status --short                 # expect only the known hosting edit and old server artefacts
git log -1 --oneline               # expect a2b8d4d (or the accepted release in production)
git fetch origin
$PHP artisan migrate:status | grep -i pending
```

## 2. Deploy, one command at a time

```bash
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
git log -1 --oneline
echo '436847840b7fc77904572b45ed3388752ba76cbc0a12290efbe821a31bd18b7c  public/build.zip' | sha256sum -c -
unzip -t public/build.zip
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json && echo MANIFEST-OK
$PHP artisan optimize:clear
$PHP artisan migrate:status | grep -i pending                # expect 2026_10_17_000001_add_zatca_phase2 (plus earlier pending ones)
$PHP artisan migrate --force
$PHP artisan migrate:status | grep -i pending                # expect nothing
$PHP artisan view:cache
$PHP artisan up
```

`composer.lock` unchanged; no Composer step. Never `composer update`, `db:seed`, `migrate:fresh`, `migrate:refresh` or a blind rollback.

## 3. Post-deploy configuration (owner / Finance confirm; nothing is configured automatically)

1. Company Profile: legal name (English and Arabic), VAT number (15 digits, 3…3), CR number, street, building number (4 digits), district, city, postal code (5 digits), country code SA. ZATCA Settings shows the seller readiness.
2. Customers used on standard tax invoices: street, city, country, and either the VAT number or a buyer identification (National ID, Iqama, CRN, …).
3. Roles: ZATCA Invoicing — view for AR users, post / retry / export for Finance, edit only for the owner / Finance administrator.
4. Decide the environment for the first tests: `sandbox` (Developer Portal, dummy VAT allowed) or `simulation` (FATOORA simulation portal with the company's own VAT and a real OTP). Production stays disabled until the owner's wave notification and production onboarding.
5. Onboarding in ZATCA Settings: create the unit (name, document types `1100` for standard + simplified, location, industry) → Generate key pair & CSR → obtain the OTP in the chosen portal (valid one hour) → Request compliance CSID → Run compliance checks → Request production CSID.
6. Invoice policy: confirm which customers receive simplified invoices (B2C) and that invoices are approved with the correct type and supply date; confirm the 24-hour reporting practice for simplified documents.
7. Rounding policy: decide whether invoice VAT should move to document-level rounding (ZATCA BR-S-09); until then multi-line invoices whose per-line VAT differs by rounding are refused locally and reported on the record.

## 4. Verify

- Register and record pages open; the Customer Invoice workspace shows "ZATCA E-Invoice" with status "Not generated" on existing approved invoices.
- Generate & validate XML on a test invoice (no network call): local validation result and the unsigned XML download.
- Only in the chosen test environment with a test CSID: Submit to ZATCA on a test invoice; expect Cleared (standard) or Reported (simplified), the attempt row, and the cleared XML download.
- `storage/logs/laravel.log` without secrets.

## Recovery

Additive migration: to back the code out, return to the previous commit; leave the new tables and columns in place. Do not delete `zatca_egs_units` rows after a stamp exists: the invoice counter and the last document hash are the chain ZATCA checks. Obtain approval before any data restore.
