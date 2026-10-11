# Deployment — Project BOQ / Detailed Budget Engine, Phase 1 (baseline budget + Budget vs Actual), 11 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md) and [deployment-payroll-settlement-2026-10-11.md](deployment-payroll-settlement-2026-10-11.md).

Brings the server from the Payroll Phase 2 HEAD (`2dd9f8d`) to the Budget Engine HEAD. This release has
**one additive migration** and **no asset change** (the committed `public/build.zip` is still the GPS release bundle).

| Item | Value |
| --- | --- |
| Migration | `2026_10_11_000002_create_project_budgets` — creates `project_budgets` (versions) and `project_budget_lines`. Re-runnable (guards on `hasTable`); `projects.budget` (the master budget) and every existing row are untouched; verified fresh, additive and rerun on MySQL 8 and SQLite. |
| Seeder | None. No permission rows are added: the page uses the existing Projects module (view / edit / approve / delete / export) and Financial Reports → view. |
| Asset bundle | Unchanged: `public/build.zip`, 189,897 bytes, 18 files, SHA-256 `25B77A8DEAC96BDB4BFE5646AA6BE800B30F12FA984CCC93DB01FF2901850A4E`. `npm run build:release` reproduced the same assets; no re-extraction needed if the GPS bundle is already in place. |
| New routes / screens | `GET /admin/master/projects/{id}/budget` (MST-PRJ-006) with its POST / PUT / DELETE actions for the draft, its lines, Set as Current, Create Revision, Discard Draft and the CSV export; `GET …/budget/{budget}/lines/{line}/edit` (MST-PRJ-007). Project View gains the Budget / BOQ tab and the detailed-budget figures in the header and Financial / Cost Summary. |
| Accounting impact | None. Budget actions never write journals, bills, invoices, stock or payroll. Budget vs Actual only reads posted journal lines (Project Cost Report semantics) and open purchase orders. |

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php

$PHP artisan down
git fetch origin
git checkout feature/seera-connected-workspaces-2026-09-23
git pull --ff-only
$PHP artisan migrate --force          # expects exactly: 2026_10_11_000002_create_project_budgets
sha256sum public/build.zip            # 25B77A8D…850A4E (unchanged since the GPS release)
test -f public/build/manifest.json && echo "assets ok"
test -f docs/user-guide/index.html && echo "user guide ok"
$PHP artisan optimize
$PHP artisan up
```

Role configuration before use (Roles & Permissions, no seeder): the planner role needs Projects → edit (draft and lines) and Projects → approve (Set as Current / Create Revision); Projects → delete allows Discard Draft; Finance needs Financial Reports → view to see Actual / Committed and Projects → export for the CSV. Seeded roles: Project Manager already holds Projects view / create / edit / approve / export; Finance Manager holds Financial Reports view.

Post-release checks (read-only unless stated):

1. Open a project (Master Setup → Projects → View): the Budget / BOQ tab appears; the header shows "Master budget" and "Detailed current budget: none yet".
2. Open Budget / BOQ: the empty state offers Create Detailed Budget to a Projects → edit user; a Projects → view user sees the explanation only.
3. (Writes planning data only; no accounting.) On a training project: Create Detailed Budget → add two lines (one quantity × rate, one lump sum) → Set as Current Budget. The Project View header now shows the detailed current budget; Financial / Cost Summary shows master budget, detailed budget, committed, actual and remaining; Journal Entries unchanged.
4. Budget vs Actual for a project with posted stock issues / site expenses / payroll: the total actual equals the Project Cost Report posted cost for that project; cost on unmapped accounts is listed as "Other posted cost".
5. `https://seera.tech-brit.co.uk/user-guide/` shows version 1.13; MST-PRJ-006 and WF-023 are present.

Rollback: `git checkout 2dd9f8d` and `$PHP artisan optimize`. The migration can stay (the two new tables are unreferenced) or be reversed with `php artisan migrate:rollback --step=1`, which drops only the budget tables.
