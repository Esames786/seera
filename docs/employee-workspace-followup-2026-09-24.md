# Employee workspace follow-up: masters and consistent saves

Source: owner's September 24 screenshots of Shift Assignments, EOSB, Leaves and Employment. This is a correction to the September 23 workspace, not acceptance of its remaining UX gaps. Work remains on `feature/seera-connected-workspaces-2026-09-23`; production is not modified by this work.

## Confirmed observations and decisions

- Shift dropdown is empty in the supplied screenshot. Inline creation is required. The owner's subsequent request also authorizes an optional missing-master seeder; see the production-safe defaults below.
- Leave types are present (Annual, Sick, Unpaid, Urgent), but there is no way to add another type from the merged form. This is a missing action, not proof of a failed seeder.
- Related forms say Save here while the profile says Save & stay / Save & next / Save & close. The inconsistency is confusing; all editable workspace sections must use the same save-intent vocabulary.
- Generated labels expose database implementation names: Shift Id, Leave Type Id, Role Id and Last Basic Salary. Use human labels; EOSB's existing input is final wage, not basic-only pay. Do not alter its accepted calculator.

## Audit matrix

| Field / section | Existing behavior | Correction / boundary |
|---|---|---|
| Nationality, department, designation, branch, document type | Existing + New dialogs | Preserve and regression-test |
| Shift in Attendance and Shift Assignments | Select existing only | Shared inline + New Shift; select only in the initiating field; other loaded shift selectors gain the option without losing their selection |
| Leave type in Leaves | Select active types only, no master creation endpoint | Authorized + New Leave Type; unique code, allowance days, paid flag and validation; save master separately from leave request |
| Project / Site in Employment | Separate setup forms only | Small authorized inline creation using existing store validation, with correct project/site dependency and scope checks |
| Role in System Account | Existing roles only | Inline role creation with explicit access scope / permission template; creating a role must not create an account or automatically grant permissions |
| Manager / linked account | Actual user records, not free-text lookup masters | Keep explicit account/security workflow; do not manufacture privileged users from a name |
| Status, classification, contract/payment enums, termination reasons | Fixed business choices | Not arbitrary master catalogues; preserve server rules |
| Bank name, salary additional item name | Existing free-text entry | No master-table creation needed |

## Save contract

1. **Save & stay** saves the current related record and shows that saved record in the same section (history-only records remain read-only). Explicit New entry starts another record.
2. **Save & next** saves, then opens the next available visible workspace section. It does not save other sections. Failure stays in place with data/files intact.
3. **Save & close** saves the current section and goes to Employees. If any other form has a draft, offer the existing unsaved-change choices before leaving. No implicit Save All, approval, posting or draft deletion.
4. Master dialog **Save & select** saves a master, inserts its option, and returns to the unfinished employee section. This is not a save of the employee/leave/attendance itself.
5. Quick-create 422/network failures retain dialog values, show errors, and do not alter parent drafts. Cancel/Escape/overlay close respects the dirty-form guard. No nested forms or script execution from AJAX HTML.
6. Profile multipart save/renewal behavior stays intact. Save & next from Access must continue to the first permitted related section, not get stuck on Access.

## Verification gate

- [x] Permission-positive and permission-negative tests for masters; duplicate/invalid data is rejected.
- [x] Shift can be created, selected and assigned with an initially empty master list.
- [x] Leave type can be created and used without navigating away.
- [x] Project/site dependency and role-security choices remain explicit.
- [x] Same three save intents on every editable related panel; failed save never navigates.
- [x] Save & close preserves unrelated drafts unless explicitly discarded.
- [x] Master selection updates only its intended field, including cached AJAX panels.
- [x] Human labels / names instead of raw foreign keys; empty states explain missing masters and permission limits.
- [x] Existing tests, new feature tests, actual-script browser fixtures, build and format checks pass.
- [x] Build/deployment/rollback instructions recorded; old user-modified `public/build.zip` preserved. Feature-branch commit contains this document.

Next modules remain behind this Employee correction gate. Preparing the optional seeder is authorized; no production seed/reset/deploy is performed by this task.

## Implementation notes

- Shift creation reuses `ShiftController` and its original validation; it now also returns a JSON option for inline callers. Leave Type gets a bounded HR-create endpoint using the existing table. Master creation does not create an attendance/leave/assignment record.
- Project/Site stores retain normal redirects for existing pages and additionally support JSON for inline callers. Project creation stays company-scoped; inline Site creation permits company/project scope, not site/warehouse scope. A Site returned by the dialog updates the parent Project selection before the dependent selector adds the new site.
- A single static dialog per master is rendered with the full Employee page. AJAX panels render only triggers with an explicit originating control ID. Do not push inline scripts/modals from AJAX fragments: those stacks are not rendered by JSON responses.
- The shared dialog's form previously defaulted to GET, which meant the universal dirty guard skipped it. It now declares POST, intercepts AJAX submission in capture phase, protects Escape/close and freezes its controls during submission. All cached selectors gain new options, but only the trigger's control is selected.
- Server responses identify the canonical saved record URL. This prevents Save & stay from silently opening another blank record. The Save & close navigation goes through the shared dirty-form guard after the selected record saves successfully.
- Payroll History remains read-only. Last available editable section shows Save & next disabled. Read-only intermediate panels have a Next section action, not a fake Save button.

## Deployment boundary

No database schema change is needed. The owner subsequently requested a seeder when master data is absent: `ProductionEmployeeMastersSeeder` is a new, opt-in master-only seeder. It calls the existing missing-only leave defaults and adds missing DAY / NIGHT / SPLIT shift codes using the repository's existing Phase 3 baseline. It never assigns employees, inserts transactions, overwrites custom values or reactivates an existing inactive record. Repeated runs preserve existing records. It is not added to the demo DatabaseSeeder.

Defaults must be reviewed against actual company policy before assigning shifts or using leave limits. These are configurable application defaults, not a claim about legal entitlements. Day: 08:00–17:00, 60-minute break, 10-minute grace; Night: 20:00–05:00, 60-minute break, 15-minute grace; Split: 07:00–19:00, 180-minute break, 10-minute grace. All use the existing 540-minute overtime threshold. Leave defaults: ANNUAL 21 paid days, SICK 30 paid days, URGENT 5 paid days, UNPAID 30 unpaid days. Existing same-code records are kept exactly as configured, even if inactive. Different-code custom records are not renamed or merged.

Project/site/branch names and security roles are company-specific: this seeder does not invent them or grant permissions. Use their authorized + New workflows for real master records.

After backing up and deploying this code, run only the bounded seeder if required:

```sh
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan db:seed --class=ProductionEmployeeMastersSeeder --force
```

Do **not** run unrestricted `db:seed`, `DatabaseSeeder`, `Phase3HrSeeder`, `migrate:fresh`, or `migrate:refresh` in production. No seeder was executed against production during this work.

Deploy the matching code commit and freshly generated Vite build together. On the already-tracking feature branch: back up, enter maintenance, pull the reviewed checkpoint, extract the **new** ZIP (its top-level folder is `build`, so extract into `public`), clear/rebuild Laravel caches, and smoke-test before reopening. Preserve `.env`, `APP_KEY`, `.htaccess`, uploads and the database. Old `public/build.zip` is user-modified and remains untouched. Neither raw client media nor that old archive belongs in this implementation commit.

Staging UAT must cover empty Shift creation, new Leave Type, close with another tab's draft, permission-limited users, Arabic/mobile layout, accepted employee document renewal and project/site setup. Automated fixtures are not live browser acceptance or MySQL concurrency proof.

## Additional screenshot: employee search on Add User

The screenshot shows EMP-002 returning the old generic “No eligible employees” message. Source inspection confirms the lookup intentionally excludes already-linked employees, inactive employees and employee codes already used by a user. The screenshot alone does not establish which condition applies to the live EMP-002; no live database diagnosis is claimed.

The lookup now returns a separate, non-selectable explanation for in-scope matching employees in those situations. It does not expose the linked user's identity/security details or bypass employee scope. Eligible employees still support confirmed autofill; role/password/security choices stay explicit. An existing user must be managed from Users, not duplicated or silently relinked. Missing master seeders do not repair account links.

## Deployment and rollback checklist

1. This increment is local until its reviewed commit is explicitly pushed. Do not assume a server `git pull` can fetch an unpublished local commit.
2. Back up the production database, current code commit, `.env` and `public/build` outside the web root. Preserve the server's modified `public/.htaccess`. Do not commit secrets or backups.
3. Enter maintenance, pull the reviewed feature-branch commit with `--ff-only`, and extract the matching new archive into `public` (archive paths begin `build/`). Do not copy the whole local public directory or private client media.
4. There is no new migration in this increment. `migrate:status` can confirm earlier required migrations are applied. Run only the named `ProductionEmployeeMastersSeeder` above if the missing defaults are wanted. Review their values before use.
5. Run `$PHP artisan optimize:clear`. The owner reported this resolved the previous 500; its underlying exception was not established here. Do not immediately reproduce that problem with an unverified blanket optimize step. Confirm critical screens and application logs before separately reintroducing cache optimization.
6. Run `$PHP artisan up` and verify Employee create/edit, Shift + New, Leave Type + New, each save intent, unrelated unsaved drafts, employee search and Arabic layout. Keep `APP_DEBUG=false`. Authenticated production acceptance remains an operator task.
7. Code rollback checkpoint before this correction is `9df25b1`; restore its matching build and clear caches together. The bounded seeder does not need a schema rollback; newly created masters may already be referenced, so do not delete them blindly. Reverting code does not undo subsequent business transactions.

Never run `git reset --hard` or clean the server worktree to bypass `.htaccess` / archive differences. Inspect and preserve those local files first.

## Build artifact

- New standalone asset archive: `seera-build-2026-09-24-employee-fixes.zip` in the workspace root. It is generated locally, not committed and not uploaded.
- SHA-256: `859BB07547E9907D1D05FF75C43C1DAF29070BFDE9A94697BAF0C79D14865A25`.
- Linux-compatible ZIP paths start with `build/`; manifest points to `assets/app-BgdO7_Bb.js`.
- Original user-modified `public/build.zip` is unchanged: SHA-256 `F10E7B203F22E876A66179FBD1C78AD22B94C975D066E7CE17FAAECAC2947CFC`.
- Asset ZIP alone is not the release. The PHP/Blade/routes/translations/seeder changes must be deployed from the matching reviewed code commit.

## Final local verification — September 24

- Full PHPUnit suite after all application changes: **215 passed, 2,028 assertions**, exit 0 (200,149 ms). Explicit testing environment, SQLite `:memory:`, empty DB_URL; no production database.
- Browser fixtures against actual application scripts with mocked HTTP: **81 checks passed** (35 connected foundations, 26 Employee follow-up, 20 related workspace); no browser script errors. These are regression fixtures, not authenticated production UAT.
- Seeder tests cover empty catalogues, repeated runs, customized/inactive records unchanged and no employee/user/transaction creation.
- Account-search tests cover eligible autofill, linked/inactive/code-conflict explanations, permission denial, out-of-scope exclusion and no private payroll/security-field exposure.
- Production Vite build passed; Pint `--dirty --test` passed; `git diff --check` passed. All 18 ZIP entries were hash-compared to the current built assets, with portable forward-slash paths.
- A first targeted test run found a test-only comparison of an in-memory boolean against the database representation. The assertion was corrected to compare freshly read database snapshots before/after; the final full run above passed.
- No production deployment, seeding, migration, remote Git push or client-media modification performed. Human staging approval and review of defaults remain required before broad rollout.
