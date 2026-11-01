# SEERA — production deployment preflight and GO/NO-GO checklist

**Prepared:** 1 November 2026. **Reference (not approved production target):** `release/seera-core-integration-review-2026-11-01` at `e1c03474104df480369805e32b2ca4eab22c8dd2`. Application source at `c19943f9cb8df2dfe3dae30eee6031b736f3a777`.

## Current decision

**GO: staging rehearsal / client UAT only.**  
**NO-GO: production rollout until the owner signs the gates below.**

The last production commit `aca78ee` was **reported by the owner**, not independently observed via server access. Repository comparison indicates the release code is ahead of that reported baseline, but GitHub cannot establish live server revision, DB status, env flags, web assets, private uploads, background jobs, or financial correctness. Do not run the older 25 October deployment script unmodified: it contains a historical branch-specific template and is not a signed deployment authorization.

## Mandatory preflight: read-only first

| Gate | Owner and required evidence | Status now |
| --- | --- | --- |
| Actual server revision | Operations records `git rev-parse HEAD`, current branch, working-tree/server overrides and release SHA | NOT VERIFIED |
| Business UAT | Finance/HR/Procurement/Owner sign relevant cases from `SEERA-FULL-BUSINESS-UAT-MATRIX-2026-10.md` | PENDING |
| Arabic reviewer | Sign off critical HR, sales/procurement, VAT, print and project terminology | PENDING |
| Device tests | Physical Android Chrome and iPhone Safari: GPS, permission denial, geofence, offline queue/retry and account switch | NOT RUN |
| Staging upgrade/restore | Isolated sanitized rehearsal of *actual current schema*, backups/uploads/APP_KEY-safe restoration, smoke and evidence | NOT RUN |
| Financial integrity | Finance records reconciled GL, AP/AR, GRNI, stock valuation, VAT, payroll, project and Equipment balances from authorized source | NOT RUN |
| Rollout permission | Named owner gives explicit authorized target SHA, change window, on-call and rollback decision authority | PENDING |
| Compliance | `ZATCA_PRODUCTION_ENABLED=false` verified on actual server; official SDK/company simulation/rounding pending | SERVER NOT VERIFIED |

## Source and deployment integrity

- Reviewed source includes ParaTest as **dev-only** dependency. Production dependency installation must use the reviewed lockfile with `--no-dev`; it must **not** install test runners on production. Do not run `composer update` during deployment.
- No changed `database/migrations/` or `database/seeders/` in the reviewed range compared to owner-reported `aca78ee`. This is *not* proof that the live migration table or files match; compare `artisan migrate:status` first and never seed production.
- Reviewed asset archive checksum: `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`. Confirm actual server archive against this expected SHA-256 if using this exact release artifact. Confirm no unsynced build assets.
- Preserve original `.env`, APP_KEY, `public/.htaccess`, private storage/receipt uploads, audit history, invoice/ZATCA artifacts, workers and queues. Never print or commit secrets.
- Verify actual runtime PHP extensions, TLS/HTTPS, write permissions, storage symlink and process/workers before a change window.

## Written backup/restore evidence must include

1. Private DB backup name, creation time, checksum, encryption and custody.
2. Private files/uploads, configuration and APP_KEY backup custody; preserve existing permissions.
3. Successful *isolated* restore with smoke checks and reconciled row/control totals; no restore over more recent production transactions.
4. Maintenance/write freeze and queue/worker drain procedure.
5. Recovery decision: rollback of code only **after checking compatibility with any new posted records**, not blind migration reversal or replacement of live DB.
6. Owner acknowledgement that any offline mobile queue may contain pending writes; manage these rather than deleting client data.

## Explicit live rollout contract — fill and sign, then execute via authorized operator

```text
Actual running server revision:
Approved source branch:
Approved exact release SHA:
Expected build ZIP SHA-256:
Approved change window / timezone:
Operations executor and backup operator:
Verified DB and uploads backup location (private):
Staging restore evidence link:
Pre-change GL/AP/AR/GRNI/VAT totals:
ZATCA production flag verified disabled:
Mobile/offline safety check:
Finance and HR sign-off:
Owner authorization (name + time):
Rollback trigger and decision owner:
Post-rollout smoke / reconciliation owner:
```

Do **not** populate this record with guessed SHA, production server values, approvals or balances.

## Post-rollout acceptance (after authorized deployment only)

Confirm protected login, English/Arabic RTL, Employee/Customer/Supplier/Project linked workspaces, HR/Project Reports, PR→PO→GRN→supplier bill approvals→payment, customer invoice→receipt→balanced journal/VAT, report scope and printed invoice. Confirm correct assets and service-worker freshness; do not clear unsynced queues. Finance signs post-release balances against pre-release control totals. Watch errors and queues; do not enable production ZATCA merely because the UI loads.

**Status at preparation:** delivery candidate evidence green; operational and business gates not met. This document neither executes nor authorizes production changes.
