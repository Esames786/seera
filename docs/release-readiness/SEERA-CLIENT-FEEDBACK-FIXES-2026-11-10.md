# SEERA — 10 November feedback implementation and QA

Branch: `feature/seera-client-feedback-fixes-2026-11-10`.

Verified application commit: **`2da7886b3e93fdf59a15198f8507c45d64e6ff91`**. The subsequent closure commit contains documentation/evidence only.

Starting commit: `0aed8c791e4cd214d23a916026860585983d1cca`, verified before edits. Existing untracked handoff/deployment documents were preserved. This is a feature-branch implementation, not a production verification or deployment.

Original review: [client requests](../requirements/2026-11-10/CLIENT-FEEDBACK-REVIEW.md). That document records the pre-fix audit at the starting commit; its NOT RUN statements describe that earlier review, not the verification below.

## Requirement disposition

| Requirement | Implementation / disposition | Status |
| --- | --- | --- |
| R10-01 IBAN | Normalize Arabic/Persian digits, Unicode/nonbreaking/zero-width whitespace, existing case/hyphen presentation; retain mod-97 rejection. Separate format and checksum feedback. Client's exact bank-certified value has not been supplied as text/verified. | IMPLEMENTED and tested for valid/invalid representative inputs; exact client case PENDING |
| R10-02 leave balance | Owner confirmed Annual Leave only, each calendar year separate. Make year/type explanation explicit; add read-only year navigation on Employee View and a year selector on My Leave; show current-year balance in Employee Manage. Missing ANNUAL lookup now yields zero annual usage rather than deducting every other type. No historical updates or carry-forward. | IMPLEMENTED and owner policy CONFIRMED |
| R10-03 employee photo | Personal section supports image preview, upload, replace and remove; private authorized photo URL; initials remain when no photo. | IMPLEMENTED |
| R10-04 user photo | Create and connected Manage support real multipart upload/preview/replace/remove. View/topbar display saved image. User and Employee photos are explicitly managed independently; no unapproved synchronization/backfill. | IMPLEMENTED |
| R10-05 individual permissions | Owner approved Inherit/Allow/Deny, Deny priority and Super Admin-only management. Added central server enforcement, a grouped English/Arabic matrix in User Manage, explicit reason/audit snapshots, optimistic stale-form protection and a discovery link from the shared Permission Matrix. Role defaults for other people remain untouched; scope and active-role dates still apply. | IMPLEMENTED and owner policy CONFIRMED |
| R10-06 old login cleanup | Added read-only `seera:entry-audit` inventory with exact account IDs/roles/employee links. No automatic demo detection and no delete/execute mode. Exact protected owner/admin IDs, deletion/deactivation decision and restorable backup remain necessary. | Inventory IMPLEMENTED; actual cleanup NOT RUN |
| R10-07 old master remnants | Same audit inventories master IDs/codes/counts and Cost Centers whose linked master no longer exists. Records are not automatically classified as demo or deleted. This cannot detect every reused-ID or legitimate historical setup case. | Inventory IMPLEMENTED; keep/remove classification and cleanup PENDING |
| R10-08 own leave | Authenticated active linked users have My Leave from topbar; own-only list, selected-year annual balance, request form and private supporting attachment. Identity comes from the unique HR user link, not submitted employee ID. Days computed server-side; status always pending. HR-wide permissions/approval rights are not granted. | IMPLEMENTED |
| R10-09 auto codes | Customer/Supplier Create display next-code preview and deliberate optional manual code, like Employee. Save & New refreshes preview. Allocation is recomputed under a database mutex inside the parent transaction; preview itself never reserves a code. Existing/custom codes remain unchanged. | IMPLEMENTED; real MySQL concurrency probe NOT RUN |
| R10-10 supplier documents | Supplier Create/Manage has private documents with type/reference/dates, View and Download; Supplier View stays read-only. Existing AP/GL controls remain in place. | IMPLEMENTED |
| R10-11 customer documents | Same parent-owned private document facility on Customer Create/Manage and read-only View. Text notes, contacts, AR and ZATCA data remain separate. Customers with saved documents cannot be hard-deleted through the old delete action; an actionable message directs to Inactive status. | IMPLEMENTED |
| R10-12 Equipment initial documents | Existing EquipmentDocument model/storage reused: attach papers during initial asset save or profile Edit, see saved rows and View/Download in the same form. Operational actions and financial posting remain explicit. Equipment create permission is still required for uploads through Edit. | IMPLEMENTED |
| R10-13 Equipment null meter | Blank/omitted initial meter is explicitly saved as zero, matching existing schema default. Supplied zero/positive readings preserved; negative input refused. Existing update/forward-meter/correction rules preserved. | IMPLEMENTED |

No claim that all 13 items are closed: policy decisions, exact-input diagnosis and production cleanup are explicitly outstanding.

## Upload and authorization boundaries

- New photos are JPG/PNG/WebP only, max 4 MB and 6000×6000; no SVG. Photos are private, not public storage URLs. Storage paths are hidden from serialized model data and never accepted from submitted fields.
- New document rows accept PDF/JPG/PNG/WebP only, max 10 MB each, up to 10 files per parent save. Categories are optional examples, not newly imposed mandatory legal-document rules. File selection must be repeated after validation errors (normal browser behavior).
- Parent profile, document metadata and photo path changes share a transaction. Newly written files are removed if that save fails; replaced photo files are retired after commit. Existing document uploads remain available.
- Files are served through authenticated routes, with parent permission and ownership checks, private/no-store headers and nosniff/sandbox headers. Photos use existing HR project/site scope or linked-user navigation checks. Customer/Supplier master documents follow the existing master visibility, which is shared rather than a newly invented invoice/project visibility rule.
- My Leave deliberately bypasses HR-wide listing scope only after establishing one unique linked employee for the logged-in user. All list/file queries are constrained to that ID; posted/forged identity, total and approval fields are refused. Duplicate active requests for the exact same type/dates are refused under an employee lock. No leave auto-approval or payroll posting added.
- Per-user exceptions preserve existing project/site scope, active-role dates, required approval roles and privileged business checks. Deny affects direct routes, sidebar checks, notification visibility and runtime approval eligibility through `User::hasPermission()`. Allow never changes `isSuperAdmin()` or record scope. Changes require Super Admin plus the existing User edit/scope authorization; no role defaults are rewritten.
- A related privilege-escalation path was found during this work: ordinary User/Role permissions previously allowed assigning Super Admin or changing that account/role through older management paths. Server guards now protect Super Admin assignment, account writes and its role/matrix, including user-create, legacy edit, workspace and bulk role assignment paths. Regression tests exercise the forged requests. This is security hardening needed to uphold the new Super Admin-only management rule.
- Exceptions persist until cleared; no automatic probation-expiry policy was approved or implemented. My Leave remains a separate own-record self-service action for active linked users, not an HR register permission grant.

## Accounting and historical impact

No change to VAT rates/treatments/rounding, PurchaseVatPolicy or Sales VAT authorization, journal posting, customer credits/refunds, stock movements, payroll arithmetic, ZATCA signing/onboarding or historical posted documents. Master uploads and photos create private file metadata/audit entries, not GL/VAT transactions. The new tests assert master document entry does not create journal entries.

The original July–September log entries are historical; no production migration/seeder or broad reset was triggered in response. The supplied log and original 29 client files remain untouched.

## Migration and assets

Two additive migrations: `2026_11_10_000001_add_profile_photos_and_party_documents.php` and `2026_11_10_000002_create_user_permission_overrides.php`.

- Adds nullable `profile_photo_path` columns to existing `users` and `employees`; no backfill.
- Adds `customer_documents` and `supplier_documents`, with explicit parent foreign keys and uploader references.
- Adds initially empty `user_permission_overrides`, with unique user/permission ownership, effect/reason/editor and timestamps. No existing user, permission or role assignment is backfilled.
- Existing guarded reset classifies the new document tables within their corresponding Customer/Supplier keep groups. The reset's actual execution was NOT run against production.
- User permission exceptions are protected setup alongside the always-retained login/role tables.
- No Composer/npm dependency changes and no application seeder needed.
- Rebuilt `public/build.zip`, 18 entries; SHA-256 `F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7`. Package script verified every ZIP entry against build files.

**Do not serve new code before both migrations:** existing Create/Manage/View pages query the new tables, and authorization now reads the exceptions table. Any future approved deployment needs a verified DB/private-file backup and maintenance window to install code, migrate the schema and extract the matching build together. Do not run `migrate:fresh`, a broad seeder or `seera:go-live-reset` to install this change. No production action is authorized by this report. See [deployment preflight](../deployment-client-feedback-2026-11-10.md).

## Verification evidence

Evidence directory: [client-feedback-2026-11-10](evidence/client-feedback-2026-11-10/).

| Check | Result |
| --- | --- |
| First affected existing workspace/Equipment run | 40 tests / 903 assertions PASS (early implementation snapshot) |
| New focused test class, before read-only-audit test was added | 14 tests / 128 assertions PASS |
| Combined focused HR/workspace/IBAN/reset/Arabic suite including final 15-test class | 52 tests / 582 assertions PASS |
| JavaScript `npm run test:js` | 12/12 PASS, 0 failures |
| First browser run, before individual-permission UI | 251/251 PASS |
| Expanded browser, disposable synthetic SQLite fixture | 363/363 PASS, no uncaught page errors |
| Browser sizes/locales | English and Arabic, 320/390/768/1440px; all 13 forms/workspaces HTTP 200, correct direction and no page-level overflow; permission matrix expanded at all widths |
| Interactive browser checks | Manual/automatic code toggle, clearing old manual value, add/remove document rows, working native photo chooser and blob preview; read-only Customer/Supplier/Employee View |
| Browser writes | Only local login and locale selection; router and browser block business writes. Real save/rollback/authorization behavior is covered by feature tests |
| Screenshots | 12 synthetic full-page 390px images: Equipment/Supplier Create, Employee Manage, User Create, My Leave and expanded individual permissions, English/Arabic |
| Permission/approval/notification/Employee View targeted suite | 138 tests / 1,006 assertions PASS |
| Additional User/Role privilege-guard/workspace targeted suite | 42 tests / 624 assertions PASS |
| Final filename/reference/date-boundary + Equipment Arabic tests | 20 tests / 176 assertions PASS |
| Final full accelerated Laravel suite | **894/894 PASS, 10,638 assertions, 0 failures**; 8 workers, 469.03s wall time |
| Final upload/Equipment regression after retry-wrapper hardening | 25 tests / 336 assertions PASS |
| MySQL concurrent Customer/Supplier creation | NOT RUN; mutex uses existing `document_sequences` row locking |
| Physical mobile devices / live data / client acceptance | NOT RUN |
| Production migration, seeder, reset, deployment, ZATCA activation | NOT RUN |

Initial new-test failures were fixture mistakes (omitted existing required asset status/employee payroll fields and assuming the seeder created zero leave rows), corrected before the passing focused run. They were not hidden production fixes. A synthetic setup attempt also failed closed before DB access when PowerShell could not generate a key using a newer .NET API; the corrected fixture setup used an empty isolated SQLite database.

The initial full run (879 tests, 877 passed) caught two Employee View contract regressions from adding a GET filter form on a strictly read-only page. That form was replaced with read-only year navigation links; existing read-only tests were preserved. The next full run passed **892/892 tests, 10,629 assertions**. Final MySQL-storage-boundary regression cases were then added; the final full run passed **894/894, 10,638 assertions**. The upload retry-wrapper refinement also passed its final 25-test targeted regression. These are separate runs, not additive totals.

Equipment's existing three-attempt deadlock retry was preserved through the upload transaction wrapper. A retried, rolled-back attempt cleans its new files before retrying; committed files from earlier successful calls are not treated as failed uploads. Oversized Equipment references are validated against its existing 80-character column, stored original filename labels fit the 191-character schema, and impossible own-leave day ranges return validation errors instead of a MySQL decimal overflow. File contents and historic readings are not altered.

## Operator follow-up for cleanup — read-only first

After an approved release containing the audit command is available, the owner can run:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
umask 077
$PHP artisan seera:entry-audit --json > "$HOME/seera-entry-audit-private.json"
```

The report is private and contains account identities/master records. Review exact protected login IDs and an itemized keep/remove list. Truncated inventories show a row limit; use `--limit=1000` if appropriate. No output is an approved deletion list. Existing user deactivation can retain authored records; hard deletion requires a separate reviewed dependency plan. Old bank/company/employee entry must not be guessed from a name or timestamp.

**Decision: GO for integration/client review of the implemented software batch; NOT a declaration that production cleanup/client acceptance is complete, and NOT production deployment approval.** Both policy answers are confirmed. The owner approved inventory first, then review of account deactivation; actual live inventory, exact protected IDs and backup/restore evidence remain pending. The client's exact IBAN input still needs bank-document comparison if normalization does not resolve it. No live deletion/deactivation is inferred from a screenshot or account name.


## Application files changed

The application commit contains the following 63 paths. Closure reports/evidence are additional documentation-only changes. No `.env`, `.htaccess`, Composer lockfile, npm lockfile, original client media or supplied log was changed.

- `app/Console/Commands/EntryAudit.php`
- `app/Console/Commands/GoLiveReset.php`
- `app/Http/Controllers/Admin/Equipment/EquipmentAssetController.php`
- `app/Http/Controllers/Admin/Hr/EmployeeController.php`
- `app/Http/Controllers/Admin/Master/CustomerController.php`
- `app/Http/Controllers/Admin/Master/SupplierController.php`
- `app/Http/Controllers/Admin/MyLeaveController.php`
- `app/Http/Controllers/Admin/PermissionMatrixController.php`
- `app/Http/Controllers/Admin/RecordFileController.php`
- `app/Http/Controllers/Admin/RoleController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/Admin/UserPermissionOverrideController.php`
- `app/Models/Customer.php`
- `app/Models/CustomerDocument.php`
- `app/Models/Employee.php`
- `app/Models/Supplier.php`
- `app/Models/SupplierDocument.php`
- `app/Models/User.php`
- `app/Models/UserPermissionOverride.php`
- `app/Services/RecordUploads.php`
- `app/Services/UserRoleAssignments.php`
- `app/Support/CodeGenerator.php`
- `app/Support/Iban.php`
- `app/Support/LinkedIdentityNavigation.php`
- `app/Support/SuperAdminProtection.php`
- `app/Support/Workspace/EmployeeHrContext.php`
- `database/migrations/2026_11_10_000001_add_profile_photos_and_party_documents.php`
- `database/migrations/2026_11_10_000002_create_user_permission_overrides.php`
- `docs/user-guide/CLIENT-ENTRY-UPDATES-2026-11-10.md`
- `lang/ar.json`
- `lang/ar/feedback.php`
- `lang/en/feedback.php`
- `public/build.zip`
- `resources/js/app.js`
- `resources/js/record-inputs.js`
- `resources/views/admin/equipment/_form.blade.php`
- `resources/views/admin/equipment/create.blade.php`
- `resources/views/admin/equipment/edit.blade.php`
- `resources/views/admin/hr/employees/_form.blade.php`
- `resources/views/admin/hr/employees/_workspace-panel.blade.php`
- `resources/views/admin/hr/employees/show.blade.php`
- `resources/views/admin/master/customers/_form.blade.php`
- `resources/views/admin/master/customers/show.blade.php`
- `resources/views/admin/master/suppliers/_form.blade.php`
- `resources/views/admin/master/suppliers/show.blade.php`
- `resources/views/admin/my-leave.blade.php`
- `resources/views/admin/roles/permission-matrix.blade.php`
- `resources/views/admin/users/_form.blade.php`
- `resources/views/admin/users/_identity.blade.php`
- `resources/views/admin/users/_individual-permissions.blade.php`
- `resources/views/admin/users/_workspace.blade.php`
- `resources/views/components/admin/master-code.blade.php`
- `resources/views/components/admin/profile-photo.blade.php`
- `resources/views/components/admin/record-documents.blade.php`
- `resources/views/components/admin/sidebar.blade.php`
- `resources/views/components/admin/topbar.blade.php`
- `routes/web.php`
- `tests/Feature/ApprovalRuntimeTest.php`
- `tests/Feature/ClientFeedbackClosureTest.php`
- `tests/Feature/NotificationCenterTest.php`
- `tests/Feature/UserPermissionOverrideTest.php`
- `tests/manual/client-feedback-browser.cjs`
- `tests/manual/client-feedback-fixture.php`
