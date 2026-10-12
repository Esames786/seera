# Deployment — Equipment & Vehicles, Phase 1 (asset master, assignment, fuel, maintenance, project cost), 12 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md) and [deployment-project-budget-2026-10-11.md](deployment-project-budget-2026-10-11.md).

Brings the server from the Budget Engine HEAD (`d6bab55`) to the Equipment HEAD. This release has
**one additive migration** and **no asset change** (the committed `public/build.zip` is still the GPS release bundle).

| Item | Value |
| --- | --- |
| Migration | `2026_10_12_000001_create_equipment_module` — creates `equipment_assets`, `equipment_assignments`, `equipment_documents`, `equipment_fuel_logs`, `equipment_maintenance_records` and completes the `permissions` rows for module Equipment (every catalogue action, inserted only when missing; no role is granted anything). Re-runnable (guards on `hasTable` / row existence); existing tables untouched; verified fresh, additive and rerun on MySQL 8 and SQLite. |
| Seeder | None. Fuel / maintenance posting uses existing Expense Categories (e.g. Fuel → Fuel Expense, Equipment Maintenance → Maintenance Expense) and the existing Cash in Hand / Bank Account catalogue. |
| Asset bundle | Unchanged: `public/build.zip`, 189,897 bytes, 18 files, SHA-256 `25B77A8DEAC96BDB4BFE5646AA6BE800B30F12FA984CCC93DB01FF2901850A4E`. `npm run build:release` reproduced the same assets (`app-CS691W_a.js`, `app-R27YIm39.css`, `erp-BneCgvYl.css`); no re-extraction needed if the GPS bundle is already in place. |
| Menu | Operations → Equipment & Vehicles now opens the real register (`/admin/equipment`); the coming-soon page is no longer linked. |
| New routes / screens | `GET /admin/equipment` (EQP-001), `/create` (EQP-002), `/{id}` (EQP-003), `/{id}/edit` (EQP-004), `/export` (CSV), plus POST actions assign / release / fuel / fuel post / maintenance / complete / cancel / post / documents and the private `GET /{id}/documents/{document}`. Project View and Site View gain an Equipment panel. |
| Accounting impact | None until Finance acts: Post to accounting creates one posted journal (Dr expense category account + Input VAT, Cr Cash / Bank); Create supplier bill creates one draft bill for the AP runtime. Equipment journals and equipment-sourced bills are source-controlled. |
| Private files | Stored under `storage/app/equipment-documents` (local disk) and served only through the authorized route; make sure the local disk is writable as for site-expense receipts. |

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php

$PHP artisan down
git fetch origin
git checkout feature/seera-connected-workspaces-2026-09-23
git pull --ff-only
$PHP artisan migrate --force          # expects exactly: 2026_10_12_000001_create_equipment_module
sha256sum public/build.zip            # see Asset bundle above
test -f public/build/manifest.json && echo "assets ok"
test -f docs/user-guide/index.html && echo "user guide ok"
$PHP artisan optimize
$PHP artisan up
```

Role configuration before use (Roles & Permissions, no seeder): grant Equipment → view / create / edit / process to the fleet or site role, Equipment → post to Finance (plus Financial Reports → view for cost figures and Journal Entries / Accounts Payable → view for links), Equipment → export where the register CSV is needed. Check that the Expense Categories used for fuel and maintenance have an active expense account.

Post-release checks (read-only unless stated):

1. Operations → Equipment & Vehicles opens the register with the six cards and filters; Export CSV downloads for an Equipment → export user.
2. (Writes operational data only.) Add a training asset, assign it to a training project / site, record a fuel entry: the total equals quantity × rate on the server; the asset page shows *Not posted*; the Project Cost Report is unchanged; Project View → Equipment lists the asset.
3. Tracking / GPS section reads "Live GPS tracking is not connected to a telematics provider"; no map or position appears.
4. (Writes accounting; agreed acceptance window only.) Finance → Post to accounting on the training fuel entry: one journal under Finance → Journal Entries with the project / site; the Project Cost Report and Budget vs Actual (Equipment category) increase once.
5. `https://seera.tech-brit.co.uk/user-guide/` shows version 1.14; chapter 22, EQP-003 and WF-024 are present.

Rollback: `git checkout d6bab55` and `$PHP artisan optimize`. The migration can stay (new tables and permission rows are unreferenced) or be reversed with `php artisan migrate:rollback --step=1` only if no equipment cost has been posted (the rollback drops the equipment tables, not the journals or bills).
