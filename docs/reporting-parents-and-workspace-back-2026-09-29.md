# 29 September follow-up: previous section and reporting parents

Branch: `feature/seera-connected-workspaces-2026-09-23`. Local implementation; not a production deployment claim.

## Request and evidence

- Owner screenshots: Employee Employment footer has Save & next but no Back; Customer/Supplier have the same navigation gap.
- Role edit screenshot: only one Parent Role dropdown.
- Earlier source: `client-requirements-2026-09-22.md`, R22-01, audio A01/image I01, and the owner's explicit **all approved** answer. No need to ask again whether all required approvers must approve. This patch uses the reviewed interpretation; it does not claim a new audio transcription.

## Before / after

| Area | Before | After |
|---|---|---|
| Employee core sections | Forward save actions, no previous-section control | Back / previous section next to save actions; first section disabled |
| Employee related panels | Forward only | Back for editable and read-only panels, following the actual visible tab order |
| Customer / Supplier Edit panels | Next section only | Back alongside Next; first related panel returns to Profile |
| Unsaved input | Tab switching retains DOM | Back uses the same retained DOM, preserves text/files, sends no writes; leaving still prompts |
| Role form | One parent | Primary parent plus additional-parent checkboxes; clear all to remove extras |
| Role list/detail/hierarchy | One parent displayed | Primary and additional names displayed; primary tree has additional-parent cross-links |
| Invalid graphs | No cycle protection; traversal could loop | Server rejects self/duplicate/cyclic links across both types of edge; primary traversal terminates on legacy cycles |
| Delete role | Protect users and primary children | Also protect roles used as additional parents |

## Intentional security / compatibility boundary

`roles.parent_id` remains the primary hierarchy used by existing activity visibility. New `role_reporting_parents` stores **additional reporting links**, not permission inheritance or authority to approve. No automatic broadening of user scope, access to salary records, or activity visibility. Existing primary edges are not copied, cleared or rewritten. Legacy clients that omit `additional_parent_ids` preserve the extra links.

Graph updates lock roles inside a transaction; validation failure rolls back the role and permissions together. Module-level permission middleware still controls role create/edit/delete. Secondary links are not silently injected into payroll, purchasing or accounting approvals.

## R22-01 status: partially delivered, approval runtime still pending

The user-visible multi-parent reporting selection/persistence is implemented. **All-required-parent transactional approval is not implemented by this patch.** This limitation is also shown on Role Edit and Hierarchy, not hidden in this document.

Already decided: every required approver must approve. Remaining runtime design needs explicit module coverage, parallel/sequential stages, approver snapshots, actor scope/substitution, rejection/revision handling and legacy pending-document handling. Do not advertise one-parent direct approvals as satisfying the confirmed requirement; do not turn reporting links into implicit finance approval authority.

## Verification

- `ReportingParentsAndBackTest`: create/edit persistence and all role displays; remove/preserve semantics; self/duplicate/unknown/mixed cycles; transactional rollback; protected deletion; no permission/scope expansion; low-privilege write denial; Blade Back controls.
- `tests/browser/workspace-previous.cjs`: real application JS, mocked local HTTP only; Employee/Customer/Supplier previous navigation, retained draft and file input, no writes on Back, validation failure, Save & next regression, unsaved-page departure guard.
- Verified 29 September: focused feature regression suite **49 tests / 668 assertions**, plus the existing primary reporting-activity visibility regression **1 test / 15 assertions**. Total **50 tests / 683 assertions**, all passed (exit 0), isolated SQLite `:memory:`.
- Browser regression: **31** new previous-section checks plus **26** existing Employee quick-create/save/unsaved-input checks; **57 checks passed** in headless Chrome with local HTTP mocks. This is not a live production browser check.
- `npm run build` and `git diff --check` passed; user-guide HTML regenerated. No live database, production migration, deployment, or full-suite rerun was performed for this follow-up.

## Deployment when this change is committed and published

1. Back up the live database and deployed assets. Confirm the intended feature branch and revision; preserve the server's `.htaccess` changes.
2. Prepare the **matching** Vite build and ZIP locally on Windows with `npm run build:release`. This rebuilds assets, creates `public/build.zip`, and verifies its manifest/assets and every archived file against the build. Include this ZIP with the release commit. On cPanel, after pulling the matching code, run `unzip -oq public/build.zip -d public/build`. The ZIP contains `manifest.json` and `assets/` at its root, not a nested `build/` folder; Node is not needed on cPanel.
3. In maintenance mode, run `$PHP artisan migrate --force` for `2026_09_29_000001_create_role_reporting_parents_table`; **no seeder required**. Never run migrate:fresh or the demo DatabaseSeeder.
4. Run `$PHP artisan optimize:clear`, then `$PHP artisan up`. Verify role saves and workspace Back controls before rebuilding optional caches.
5. Code rollback to the preceding revision can leave the additive table in place. Do not drop it after users save reporting links unless those links are backed up and their loss is explicitly approved.

No live data, credentials or raw client media are included in this document.
