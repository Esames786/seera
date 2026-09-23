# Connected workspaces: implementation status

Branch: `feature/seera-connected-workspaces-2026-09-23`

Starting commit: `9e295b7`

Scope: owner UX requests plus the reviewed September 22 client package. English and Arabic only. No production changes.

## Confirmed owner decisions

- All required reporting approvers must approve. Do not hardcode an unconfirmed Site-before-Purchase order; workflow sequencing must remain explicit.
- Customer Cash/Bank/Both is enforced on receipts, not merely a label. Existing customers default to Both.
- Keep a separate branch for review and easy code rollback.

## First implementation batch

| Item | Implemented behavior | Boundary |
|---|---|---|
| R22-03 | Accessible coloured rating radios for Suppliers and Customers | Existing values unchanged |
| R22-04 | Customer Type catalogue and authorized inline + New | Existing types preserved; no demo seeder required |
| R22-05 | Customer Cash/Bank/Both; receipt account filtering and server checks under transaction | Historical receipts untouched; no journal reversal |
| R22-06 | Optional Office/Site contacts and notes on Customer Create/Edit | Customer/site ownership checked; parent and new children saved atomically |
| R22-07 | Customer View is read-only; editing entry remains on Edit | Old contact/note endpoints retained for compatibility and authorization |
| Language foundation | Laravel PHP/JSON translations, saved EN/AR preference, RTL shell and translated navigation/new controls | Full translation of every existing screen is NOT complete |
| Unsaved changes | Shared form snapshots; in-app Save/Discard/Keep editing; native browser unload guard | Browser native warning text is controlled by browser; no implicit posting or approval |
| Employee to User | Bounded employee search; relevant identity/employment prefill; explicit role/security; atomic link on save | Requires Users create + HR view/edit and existing record scope; no salary/bank copying; manual users remain supported |
| Save & stay | Customers and Users | Other module save intents remain pending |

First rollback checkpoint: `b6c4d86` (Customer feedback, shared form/locale foundation and employee-account conversion).

## Employee pilot (second batch)

- Existing employee fields, accepted document uploads and original store/update transaction remain in place.
- Edit gains Personal / Employment / Salary & payment / Documents / Access navigation. Show all restores the full form. Without JavaScript, the full form remains available.
- Create initially shows every required field; Save & next enters the saved employee's next section. Save & stay/next only navigate after successful server validation. The original Save/Update still returns to the list.
- Tab switching retains unsaved fields and files. Browser/server validation reveals affected sections; changing a section is not itself considered a data change.
- Payroll-authorized users see the existing salary structure and mismatch notice within the Payroll section. Creating a new effective-dated structure still uses its accepted specialized form, prefilled from the saved profile, and then returns to the same employee. This is contextual navigation, NOT a claim that all payroll actions are embedded.
- Existing salary structures and past payroll are not rewritten by profile saves. Additional Attendance/Leaves/Overtime/payroll-history workspace panels remain pending.
- Pilot verification: **36 HR/payroll feature tests passed, 284 assertions**, including the September 21 accepted-feedback tests. **33 isolated browser checks passed**, including edit-section selection, unchanged navigation staying clean, unsaved input retention across sections, hidden invalid fields becoming visible and Show all restoring the full form. Vite build and PHP formatting checks passed.

The employee conversion retains the existing account password policy (including forced change when the existing default is used); changing the entire onboarding policy is not silently bundled into this UX change. Explicit passwords in the new employee-conversion path must be at least eight characters. Search does not create a user, grant permissions, or modify the employee. Linking uses a row lock; SQLite feature tests do not prove MySQL concurrency behavior.

### Source and media review

The September 22 package contains 6 audio files and 6 screenshots. All were reviewed and linked to the requirements; 18 local transcription passes and all 12 file hashes are recorded in the private evidence package. Machine transcription is not claimed as verbatim certainty. Do not publish the embedded-media HTML in `public` or upload client recordings without permission.

## Still pending — not represented as completed

1. Extend the Employee pilot with permission-aware Attendance/Leaves/Overtime/payroll-history panels and consider in-place salary entry only after its specialized form is safely reusable.
2. R22-01: multiple reporting parents, cycle checks, approver snapshots/decisions and an all-required approval runtime. Preserve existing behavior until the complete path is tested. Approval ordering/configuration and legacy pending-request treatment must be explicit.
3. R22-02: coordinated VAT default and Super Admin override policy across purchase/sales entry paths. No historical-rate rewrite; the separate 20% comment is not VAT scope.
4. Complete English/Arabic coverage, human Arabic review, RTL screen/PDF verification.
5. Remaining Suppliers/Projects/purchasing/inventory/accounting contextual navigation rollout. Bulk payroll, approval queues and ledger posting remain distinct actions.
6. Real application browser UAT for accepted screens, low-privilege roles, mobile RTL, uploads and slow/failed saves. Browser fixture checks are not a replacement for this.

## Verification and release gate

- First Customer/locale batch: 10 feature tests, 78 assertions, passed.
- Isolated browser checks using the actual application JavaScript: **25 checks passed** (dirty forms, text/checkbox/file tracking, GET exclusions, logout, modal close, multi-form safety, AJAX success/failure, native validation, employee prefill and role/password preservation).
- Production Vite asset build passed. PHP formatting applied to changed files; `git diff --check` passed.
- First complete regression run: **189 tests, 188 passed, 1 failed, 1,730 assertions**. The new out-of-scope test expected 404, while existing write middleware correctly returned 403. After correcting that expectation and checking that no account/link was created, the 15 new feature tests passed (114 assertions).
- Follow-up scope review found that Users' human-readable `employee_id` was treated as a numeric HR foreign key by shared middleware. It now checks the explicit `source_employee_id` for account conversion, leaving numeric employee checks on other modules intact. Null site assignments now fail closed rather than comparing null IDs as zero. A positive in-scope site-admin test was added alongside the out-of-scope denial.
- After that correction: **32 focused feature/security tests passed, 308 assertions** (September 22 feedback, employee conversion, deployment hardening and organization access). The earlier 189-test run is not relabelled as passing; see the fresh combined result below.
- Private report browser validation passed: 6 audio, 6 images, 12 source cards, 7 requirements, valid anchors/unique IDs, no mobile overflow and zero external requests.
- A focused run also caught SQLite-incompatible full-name concatenation; the search now uses portable bound name-part predicates.
- An earlier suite overlapped code edits and produced a route-registration mismatch; it is not a clean baseline or a release result.
- Do not deploy this partial batch as though the whole programme is finished.

### Final combined result (September 23)

**PASS: 195/195 PHPUnit tests, 1,772 assertions, exit 0.** This was a fresh complete run after the customer/user foundation, scope correction and Employee pilot, with application code frozen throughout. The test environment was SQLite `:memory:`. Also passed: 33 browser-fixture checks using the actual JavaScript, Vite production build, PHP formatting and whitespace checks.

These results verify the delivered batches, not the pending approval engine, VAT changes, all-screen Arabic translation or real production/MySQL concurrency behavior. Staging UAT, Arabic review and the remaining business scope still gate the overall rollout.

## Deployment / rollback notes for this batch

No production migration, seed, reset or deployment was run. Only an in-memory SQLite test database is used in automated feature checks.

New migration: `2026_09_23_000001_add_customer_channels_and_type_catalog.php`. It adds the customer payment-channel default and seeds the customer-type catalogue from existing data. No `DatabaseSeeder`, `migrate:fresh`, demo seeder or data wipe is required.

After staging acceptance: back up database and uploaded files, deploy reviewed code, install locked dependencies, build assets, run `php artisan migrate --force`, then rebuild the application's normal caches. Confirm the server PHP path and migration status before executing commands; this is not a complete replacement for the existing production runbook.

For code rollback, return to the recorded previous release and rebuild its assets/caches. Prefer retaining the additive database column and catalogue entries: old code can ignore them. Migration rollback removes saved channel preferences, so do not run it merely to roll back UI. Saved contacts, notes and valid receipt postings must not be deleted or reversed automatically. This feature branch does not include unrelated private media or archives in its implementation commits.
