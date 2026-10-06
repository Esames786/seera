# Wave 2 Batch A — Users + Sites

Starting release `5013596`, branch `feature/seera-connected-workspaces-2026-09-23`, clean. Source audit 6 October 2026. No production access, merge or Batch B work.

## Source audit / before → after

1. Users has a paginated scoped list, static show tabs, and one large edit form. Replace edit with a connected workspace and keep View read-only; retain create and global register.
2. `employees.user_id` is the actual link; `users.employee_id` is a unique human-readable code. Existing creation locks an Employee. Employee user_id is not unique in schema, so detect ambiguous historical links and serialize link changes on User + Employee rather than infer links from codes. No automatic role/scope/mobile grant from linking.
3. CONFIRMED defect: UserController update uses `roles()->sync([$roleId...])`, deleting additional/temporary rows. Replace with explicit primary designation without deleting unrelated assignments; permanent removal is separate and explicit.
4. `user_roles` is unique(user_id,role_id), with primary/temporary flags and inclusive date boundaries. EffectiveRolesQuery already excludes inactive roles and expired/future temporary grants. User-level temporary_access/start/end fields are stored metadata, not this permission evaluator. Keep that distinction explicit. A role cannot simultaneously have permanent and temporary rows; refuse implicit conversion. Retain ended temporary rows and log date changes.
5. Single project/site/warehouse fields on User plus managed Projects determine scope. Branch is profile metadata, not a working branch scope. Reuse scope service; verify hierarchy membership even for company actors, and visible IDs for restricted actors.
6. Mobile Access is a flag, with an existing responsive online Site Expense entry route. No native/offline/GPS implementation.
7. User show currently loads activity without Activity Logs permission/visibleTo and calls it Security Alerts. Remove misleading metric and gate/paginate actual actor activity. Existing status/deactivation and password-change controls remain; no verified mail/2FA claims.
8. Reuse SaveAction, workspace.js, relatedPanels, workspace-host, PanelDefinition/PanelSet and unsaved-changes. No Save All/Save Next. Profile and related writes independent.
9. Sites currently has a paginated list, eager warehouse show, and basic master edit. Add lazy View/Manage workspace and persistent identity, scoped counts, shared save actions.
10. Site belongsTo Project and User supervisor; ProjectSiteController has authoritative nested parent and safe return path. Preserve it, prohibit reassignment of an existing site's project through this UX to avoid orphaning child context.
11. Employees have site_id/project_id. Filter both, preserve HR scope and HR permission.
12. Warehouses have site_id/project_id/incharge. Filter both; stock counts/values require Warehouse Stock view and scoped stocks.
13. PR/PO have site_id/project_id. GRNs lack site_id; use linked scoped PO with exact project/site (do not infer site from receipt warehouse when order names a different site). Reuse P2P links only.
14. SiteExpense has mandatory project_id/site_id. Add nested Site create/store routes; URL Site is authoritative, verify forged body parents before delegation. Existing approval/accounting unchanged.
15. Attendance has stored site_id/project_id, source/geofence_status metadata, manually entered via AttendanceController. No device GPS distance validation. Geofence/offline fields are configuration only. Existing map supports display/pin/radius; do not claim enforcement.
16. Reuse Project lazy panel architecture, scoped StockLedgerEntry issue movements restricted to posted StockIssue (not GRNs), map picker and safe return. Activity uses exact new Site tokens only, not matching arbitrary names or all Project history.

## Boundaries and plan

Users: Profile; Linked Employee; Roles & Permissions; Temporary Access; Access Scope; Mobile Access; Security/Account Status; Activity. Roles hierarchy is reporting, not inherited permissions. Existing Roles/process authorizes additional/temporary assignments; User edit authorizes profile/access fields as before. Read panels require Users/view plus their child permission; manage writes require Users/edit plus relevant child authority. No secrets in headers/activity.

Sites: Overview; Project; Geofence/Location; Staff; Warehouses; PR; PO; GRN; Material; Site Expenses; Attendance; Activity. Each child tab/endpoint requires its own permission; lazy paginate ten. Global registers retained. All source queries remain scoped, parent URL is authoritative. No new business module, valuation, RBAC engine, approval subjects or production seeder planned.

Implementation and focused verification are complete. Full-suite/release/push gates are recorded below only when measured. Stop after Batch A.

## Implementation review

- Users use the existing workspace kit, not a new RBAC engine. Profile is a native save; related forms are independent AJAX saves with native fallback. User-role updates hold the User row lock. Setting primary retains the old primary as additional; removal is separate. Temporary roles cannot silently become permanent, expired/future grants are ineffective, and End retains the row with before/after ActivityLog.
- New Users lookup/link does not copy scope/mobile/roles; unlink keeps the readable code, and scoped re-link lookup permits that user's own code. Actual FK changes lock User and Employee and reject duplicates, inactive employees, foreign codes and out-of-scope IDs. Historical ambiguous links are not auto-repaired; no uniqueness migration or data rewrite is introduced.
- Employment metadata stays editable. Classification changes additionally require HR view/edit and retain linked Employee synchronization. User-level temporary/2FA metadata is not advertised as implemented authentication enforcement. Existing HR System Account provisioning and global role bulk assignment are separate legacy paths, not silently redesigned.
- Access Scope validates visible Project/Site/Warehouse and hierarchy even for company administrators. Parent profile saves ignore submitted access/role fields. Security resets require confirmed eight-character password and force change on next sign-in. No hashes/secrets appear in UI/audit. Empty legacy passwords no longer overwrite the stored/default hash.
- AJAX User saves refresh the identity header (role, status, mobile, linked Employee) from the saved record, not just the panel. Employee search initializes again after lazy loading/refresh. User panels carry safe return context. Save/Save & Close/Cancel are consistent; Save & New is for the parent profile, not role grants. No Save All or Save & Next.
- Sites reuse existing master fields, map and Project-nested routes. Existing Project is fixed to prevent silently orphaning operational children. A current supervisor outside the editor's User dataset remains selected and can be preserved; NEW supervisor assignment still requires visibility. Global registers are retained.
- Site panels use exact Site/Project constraints and existing global scopes. GRNs follow the visible PO; Material follows posted issue ledger, never receipts. Warehouse stock counts/value require Warehouse Stock view. Site activity uses immutable Site tokens + existing ActivityLog visibility, omitting ambiguous legacy name-only descriptions.
- Nested Site Expense create/store locks URL context, rejects forged parents, delegates existing expense logic, and keeps return context through draft Save/Close, Submit and missing-workflow failure. Approval/posting/payment business engines are unchanged.
- No initial eager load of all employees/warehouses/stock/procurement/expenses/activity. Related lists paginate ten. Parent and child permissions are separately enforced; View panels contain no related write forms. No permission inheritance is inferred from reporting parents.
- English/Arabic workspace translation keys match and Arabic entries are checked for Arabic script; RTL rendering is covered. Older underlying form/map/document strings retain partial translation coverage. No Urdu.

## Measured gates (development only)

| Gate | Result |
|---|---|
| Initial role-preservation regression, before role UI | PASS, 1 test / 4 assertions |
| Initial User/Project/Employee regression selection | PASS, 31 tests / 383 assertions |
| Broader workspace / approval / finance / inventory selection | 365 tests: 364 passed, one old map-help text expectation failed; map instruction restored and dedicated test subsequently passed |
| Final dedicated User + Site suites | PASS, 19 tests / 418 assertions (9 User, 10 Site methods) |
| Local mocked-browser new guarded workspace/search/header flows | PASS, 26 checks; no page errors |
| Local mocked-browser Employee / Customer / Supplier previous/save regressions | PASS, 31 checks; no page errors |
| Pint on changed PHP | PASS |
| HTML validation | PASS, 4 generated pages / 408 internal links and anchors |
| Stable ID comparison to 5013596 | PASS, 188 Screen IDs / 19 Workflow IDs; none added/removed/renumbered |
| First final-tree full suite | 500 tests: 499 passed; one assertion expected raw apostrophe text instead of safely escaped HTML. Test now uses a deterministic apostrophe fixture and asserts escaped text; production escaping unchanged |
| Corrected full suite on final implementation, 7 October | PASS, 500 tests / 5,287 assertions / zero failures; 674.519 seconds; testing environment with in-memory SQLite |
| npm run build:release / ZIP | PASS, 18 files; manifest.json and assets/ at root; every archived file byte-verified against build; unzip CRC test PASS |
| Same-branch commits/push and remote verification | Exact final release HEAD and remote verification are reported in the delivery response; no merge or deployment |

The new tests cover read-only GETs, independent save actions, identity, real Employee FK/re-link/duplicates, temporary lifecycle and expired permissions, role preservation, scope forgery, mobile/status, direct permission gates, activity visibility, Arabic RTL, Site ownership, supervisor preservation, scoped counts/stock values, exact procurement/receipt relationships, posted issue semantics, Site Expense context/submit, manual attendance wording, and safe return navigation. Initial fixture-comparison failures were corrected to compare database-refreshed rows; no business assertion was removed. The initial full run was superseded after final refinements. On 7 October the deterministic escaped-name correction passed both the dedicated 19-test / 418-assertion suites and the complete 500-test / 5,287-assertion release gate. Test execution was moved outside the slow filesystem sandbox with testing mode and in-memory SQLite explicitly forced; no production data was used.

Release ZIP SHA-256: `11E80CF2FFA328679DA219D310B55C0E2C0BC393306996715722945F65342FE9`. Build and HTML regeneration/validation completed after the green full suite. The remote feature tip was checked at the expected starting `5013596` before release preparation. Final publication identifiers accompany the delivery response to avoid a self-referencing commit hash.

Review commits: `0b5b749` (application/UI and role-integrity fix), `987e7d8` (dedicated feature/browser regressions), followed by this documentation and release ZIP commit. The tested application/test tree is unchanged by the documentation-only finalization.

## Limits and release boundary

- No independent live/browser business acceptance. Browser automation above uses local mock HTTP and real shared JS, not the production server. Owner staging acceptance remains required.
- No new migration, production seeder, permission grant, Composer dependency or new worker. No production connection, merge or deployment. Real MySQL races were not rerun for this schema-free UI batch; the previous Supplier Bill race report remains a separate historical gate.
- GPS/haversine attendance, offline sync, native app, mail/2FA verification, notification jobs, Equipment, BOQ, payroll-to-GL, PO/AR/Leave runtime and live ZATCA are NOT supplied.
- Branch profile metadata is not an access dimension. User-level temporary dates are not account-expiry enforcement. Role hierarchy is not permission inheritance. Existing global bulk role replacement is unchanged.
- Ending a scheduled temporary grant preserves its original start while setting end to yesterday; it is revoked, not a malformed new grant. Before/after values remain in ActivityLog.
- Historical ambiguous Employee/User links remain visible as inconsistent/unavailable and require reviewed cleanup. There is no new database uniqueness constraint.
- User/Site panels optimize context, not approval/financial logic. Global registers remain for searches, batches, reports and approvals. Underlying forms retain some English strings; complete application Arabic coverage is not claimed.
- Deployment instructions: [Batch A deployment](deployment-wave2-batch-a-2026-10-06.md). Do not blindly roll back to 5013596 and resume User editing: that reintroduces the role-sync wipe.
- Next candidate: **Wave 2 Batch B — Items + Warehouses**, requiring a new approved bounded sprint, stock-write scope audit, read-only balances/ledger and reuse of existing valuation/posting. **NOT STARTED.**
