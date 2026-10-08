# Wave 2 Batch B — source audit and verification ledger

Branch: `feature/seera-connected-workspaces-2026-09-23`. Starting HEAD: `0de38951273b090dd5d8e89600f6c632fdf5cded`. Scope: Items + Warehouses only. No merge, deployment, production data access or Batch C.

## Before → after source audit (before implementation)

| # | Source finding | Bounded change |
|---|---|---|
| 1 | ItemController has ordinary CRUD; show eagerly loads all stock and 15 movements | Read-only View / connected Manage, independent master save actions |
| 2 | Item is a shared master; WarehouseStock has global scope | Explicit visible-warehouse intersection on panels and summaries; lazy ten-row lists |
| 3 | StockService uses warehouse weighted average; Item.average_cost is company-wide; FIFO is metadata only | Use stored warehouse average_cost/total_value, never company Item cost for scoped users; no new costing |
| 4 | PR/PO lines have item_id and scoped parent relations; preferred_supplier_id is real | Separate authorized PR/PO line panels, supplier context by permission |
| 5 | Existing consumption uses issue ledger; issues have posted status | Posted Stock Issue lines/context only; GRN is not consumption |
| 6 | Inventory/expense accounts and VAT are existing Item fields; old show/edit exposes mappings to Items-only users | Chart of Accounts view additionally gates mappings; Items create/edit still needed to change them; no posting |
| 7 | Reorder > 0 and quantity <= reorder drives low stock; min/max are metadata | Same rule, explicitly scoped totals/row state |
| 8 | Item delete guard only sees scoped stock/history; list exposes global Item average_cost; item/warehouse FKs cascade | Hide values without Warehouse Stock view; preserve cross-scope history on delete, no data rewrite |
| 9 | Warehouse is master CRUD with branch/project/site/incharge | Connected View / Manage with existing generic workspace kit |
| 10 | WarehouseStock holds balance, reserved qty and average cost | Read-only scoped balances; no stock writes |
| 11 | Stored total_value owns current valuation | Sum same visible rows; warehouse quantities grouped by unit, not mixed-unit grand totals |
| 12 | GoodsReceipt.warehouse_id is direct; lines hold accepted/rejected/invoiced amounts | Read-only GRN context; F04 quantities only with AP view |
| 13 | StockIssue.warehouse_id is direct, line quantities/costs | Posted issues context; existing issue screens own writes |
| 14 | Transfer has from/to warehouses, draft/dispatched/received states and dates | Display authorized inbound/outbound records without changing global scope |
| 15 | StockAdjustment is single-item, not header/lines | Existing adjustment quantity delta/reason/state links |
| 16 | Warehouse project/site exists rules do not validate matching parents for company users | Validate visible parent consistency; freeze existing project/site ownership to prevent relocating historical stock via profile save |
| 17 | Dispatch/Receive controller and permission mapping exist; standard seeds omit Stock Transfers receive. Project/site transfer scope is source-only; warehouse scope is either endpoint | Document exact limitation; no receive grant, scope expansion or engine rewrite |

Inspected models, controllers, middleware, inventory migrations/FKs, seed permission definitions, stock service/report queries, master/workspace views, InventoryTest and the authoritative guide. The existing request-scope middleware already guards most non-company write IDs; it is not replaced. New read queries additionally intersect authorized warehouses; related parent scope remains enforced. Warehouse master deletion is changed to deactivation to avoid cascading stock/document loss. Item deletion retains history through a cross-scope existence-only integrity check (never exposes hidden records).

## Implementation / verification

Implemented: shared `InventoryWorkspace` read-only query/presentation service, `InventoryWorkspaceController` guarded lazy GET routes, shared Item/Warehouse Blade workspace/panels, existing master save controls, identity, scoped summaries, read-only document links, account/value gates, Warehouse deactivation/fixed ownership, Item cross-scope history preservation, English/Arabic workspace labels. No StockService, accounting/GRNI/runtime, global scope, schema or seeder change.

Verification ledger:

- Dedicated Item/Warehouse tests: **20 tests / 369 assertions / zero failures** (11 Item methods, 9 Warehouse methods; each method covers multiple acceptance points).
- Earlier A+B focused pass before two additional tests: **37 tests / 771 assertions**. Development failures were corrected before final runs: a test-loop syntax error; the global Stock Ledger route is `admin.inventory.stock-ledger`, not `.index`; two assertions needed exact attribute / decoded JSON matching. The shared lazy-panel `data-panel-status` contract was added and regression-covered.
- Requested broad regressions: `artisan test --compact --filter='Workspace|ApprovalRuntime|SiteExpense|GrnBillMatching|InventoryTest|Finance|Accounting'` — **338 tests / 3,815 assertions / zero failures**, 350,216 ms. Covers existing User/Site/Employee/Supplier/Customer/Project/P2P workspaces, inventory, F04, expenses/runtime and Finance/Accounting.
- Browser checks: **77 passed**: inventory-workspace 20, wave2-guarded-workspace 26, workspace-previous 31. Installed Chrome/Playwright runs real shared JS against local mocked HTTP. This is not actual ERP or owner business acceptance.
- All changed PHP passes Pint; `git diff --check` passes.
- Authoritative guide v1.7 regenerated as four dark HTML pages. **408 internal links/anchors pass**. Compared with starting HEAD: **188 Screen IDs / 19 Workflow IDs unchanged**.
- Full suite: `php artisan test --compact` — **520 tests / 5,656 assertions / zero failures**, 3,065,716 ms. Result checked at the 8 October 2026 handoff before packaging. The first launch attempt was not executed because approval-service usage review was unavailable; the resumed launch completed successfully.
- `npm run build:release` passed after the full-suite result: Vite 10 modules, 2m 58s, plugin timing notices only. Packaging compared every archived byte with the current built files and validated manifest references. `unzip -t public/build.zip` passed; **18 files**, root `manifest.json`, `fonts-manifest.json`, `assets/`. SHA-256: **`B73DAD25B9372E90603DB5A0ABCDF2FD77C3D1AE7E56E393879B1601C2666AC0`**.
- Implementation commit `2cfce05`; tests commit `4428908`; the subsequent documentation/build commit completes the release. Final commit SHA and remote-ref equality are reported in the delivery message after push. No merge or deployment.

Testing uses explicit `APP_ENV=testing`, SQLite `:memory:`, empty DB_URL and no cached config. No production database access or change. No migration or production seeder is required. No merge/deployment/Batch C is performed. Stock-value/performance coverage is development evidence, not production load or MySQL concurrency certification.
