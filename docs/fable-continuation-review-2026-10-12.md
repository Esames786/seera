# Fable continuation review — 12 October 2026

## Review boundary and result

Reviewed the continuation from the last handoff baseline `8103bdb` to current HEAD `1b5d883`, **40 commits**, on `feature/seera-connected-workspaces-2026-09-23`. The existing untracked `FABLE-HANDOFF-2026-10-08.md` was preserved as a historical handoff, not overwritten.

Fable resumed at the expected point. Implemented scope now includes:

| Dates | Work | Delivery endpoint |
| --- | --- | --- |
| October 8 | Customer Invoice connected workspace (Batch C) | `f7d036d` |
| October 9 | Employee-centred HR context (Batch D) | `bd3090f` |
| October 9 | Online mobile GPS/geofence attendance | `1670772` |
| October 10 | Payroll accounting, controlled reversals and printable payslips | `2aa453c` |
| October 11 | Salary settlement, reversal and frozen generic bank-transfer CSV | `2dd9f8d` |
| October 11 | Project Budget/BOQ versions and Budget vs Actual | `d6bab55` |
| October 12 | Equipment/Vehicle assets, assignments, fuel, maintenance, private files and project cost | `1b5d883` |

This review read the changed controllers/services/models, permissions/scoping, views, migrations and tests, with deeper attention to new financial writes and the latest Equipment/Budget changes. It does not claim exhaustive verification of every changed line, a fresh full-suite result, production acceptance or new MySQL race certification.

## Confirmed defects and local fixes

Ten behavior defects were reproduced by new failing regression tests before their fixes:

| ID | Impact | Before | Fix |
| --- | --- | --- | --- |
| R01 | High: payroll access control | A user seeing only some employees could approve a whole run and trigger accounting for all employees, although settlement/export correctly rejected partial scope. | Whole-run check inside the approval lock; also enforce it on process/update/delete and align action visibility. |
| R02 | High: immutable payroll history | Process checked a stale route-bound model outside the run lock. A request holding the earlier processed snapshot could replace employee rows after approval/posting. Update/delete had the same stale-state pattern. | Process/update/delete now acquire the same run lock and re-read approved/financially locked state before mutation. |
| R03 | High: wrong liability selection | Salary settlement first consulted the current posting rule. Changing that rule to the deductions account made an old run settle against the wrong credit line or refuse a valid payment. | Identify the payable from the original journal's generated payable-line identity; legacy amount fallback is allowed only when unique. Never use today's mutable rule. |
| R04 | Medium: partial profile save | Equipment profile fields were committed before a lower meter reading failed validation. The screen reported failure but the name/ownership had changed. | Profile state, meter validation/update and audit now run atomically under the asset lock shared with assignment actions. |
| R05 | Medium: asset released too early | Completing one maintenance job reset the asset to Assigned/Available even with another open repair. | Keep Under Maintenance until no open repair remains, consistent with cancellation behavior. |
| R06 | Medium: maintenance posting stuck | A repair recorded initially at zero/unknown cost silently discarded its payment account. Completing it with a positive final cost could leave an unpostable record. | Preserve and validate supplied mapping even at zero cost; require valid mapping when completing positive cost; allow missing mapping to be entered in expandable Payment details. |
| R07 | High: misleading cost breakdown | An account could map to the same cost code in two categories, while reports grouped by category + code. Actual/commitment amounts were then repeated in multiple rows/categories. | Validate the full category/code group. Historical ambiguous mappings are flagged and their costs displayed once as unallocated, without rewriting finalized versions. |
| R08 | Medium: duplicate draft race | The one-draft/current-baseline checks ran only before the service acquired the project lock. Concurrent requests could both pass them. | Repeat both checks inside the project mutex, with validation errors handled by the controller. |
| R09 | Low: untranslated attendance confirmation | JSON responses returned `attendance.checked_in` / `attendance.checked_out` instead of a human-readable message. | Use the existing `mobile_attendance` translation namespace, for English and Arabic. |
| R10 | High: stale equipment scope | A stale asset instance could be reassigned after the asset had moved outside the actor's project; the unscoped asset lock bypassed the new ownership boundary. | Recheck asset scope while taking the lock for assign/release, fuel/maintenance creation and maintenance completion/cancellation. The regression preserves the new project's ownership and single active assignment. |

Additional regression coverage checks stale update/delete, partial-scope payroll mutations, deferred maintenance mapping and legacy ambiguous-budget reporting. No financial totals/formulas, approval workflow policy or stock-valuation rules were replaced.

### Documentation corrections

- The roadmap's top “current” section still described October 8 and said Batch C was not started. Added a current continuation/review summary; older checkpoints remain historical.
- User-guide role summaries still called BOQ, Equipment and mobile GPS unavailable. Updated them to match delivered source while keeping offline/live telematics out of scope.
- Removed routine `migrate:rollback --step=1` advice from the five new migration release guides. Unposted equipment/BOQ data and draft-journal links are still valuable history; a later migration can also become the target of a copied rollback command. Existing rows were not deleted or repaired.

## Validation ledger

- Pre-fix reproduction: seven new regression failures in the first run; two additional payroll authorization/stale-process regressions and the stale equipment reassignment regression also failed as expected.
- First post-fix targeted run: **12 tests / 82 assertions, all passing** (includes three pre-existing tests matching the review filter).
- New-module suite: **120 tests / 1,946 assertions, all passing**, covering Customer Invoice, Employee HR context, GPS attendance, payroll GL/payslips/settlement/export, BOQ/report and Equipment. Duration 605,602 ms. This run preceded the final Equipment scope guard and Arabic-message test; those final changes are covered by the next run.
- Final Equipment plus review-regression run: **30 tests / 461 assertions, all passing**; includes stale equipment scope, payroll protections, mapping/reconciliation, deferred maintenance details and English/Arabic messages. Duration 88,651 ms. Runs overlap; counts must not be added as unique tests.
- Wider financial/HR/guide regressions: **250 tests / 2,267 assertions, all passing**, covering finance integrity/acceptance/state transitions/scope/reports, VAT-period locks, settlement idempotency, GRN matching, Supplier Bill runtime, Site Expenses, Approval Runtime, accounting forms, HR/payroll and user-guide checks. Duration 856,180 ms. This selection can overlap other runs; do not sum counts as distinct tests.
- Local mocked browser regressions: **77 checks passed** (31 Back/previous, 26 User/Site guard/search, 20 Item/Warehouse). These use real shared JavaScript with mocked HTTP, not live ERP acceptance and not visual acceptance of the new Equipment/Budget screens. Initial sandbox access to the Playwright cache was denied; the approved local retry passed.
- Release build and ZIP verification passed: **18 files / 189,897 bytes**, root manifest/assets, actual `unzip -t` CRC check passed. SHA-256: `96740A018AD23262D6EF4213232E5885EA19A3E9ECECF0E3FCED7EAF6EF219D1`. JS/CSS filenames remain the same as the GPS bundle; ZIP metadata can change the archive hash. This patch's main UI changes are server-rendered Blade.
- Guide HTML regenerated after the final text corrections; **425 internal links/anchors** validated, with existing screen/workflow IDs preserved. Changed PHP plus new translation files passed Pint; `git diff --check` and local review/roadmap document links passed.
- All PHPUnit runs use explicit `APP_ENV=testing`, SQLite `:memory:`, empty `DB_URL` and no cached application config. No production DB connection was used.
- Historical Fable release notes report **627 tests / 7,647 assertions** and disposable MySQL probes. Those are prior reported results, not a fresh full-suite/MySQL claim by this review.

## Data and release safety

At the October 12 review checkpoint these fixes were local only. On October 14 the owner authorized commit and push; see [the review-fix deployment guide](deployment-review-fixes-2026-10-14.md). Deployment is not performed by this review. No new migration or seeder is introduced by this patch. The continuation itself added five migrations (GPS, payroll GL, salary settlement, project budgets, equipment); a server still on `8103bdb` needs those existing migrations for a cumulative release. Do not assume the October 8 deployment proves the later features are already live.

No bulk production cleanup/correction was run. If affected data already exists, inspect it read-only first: maintenance records with positive cost and incomplete mapping, approved payroll rows versus their posted journal, or conflicting account groups in frozen budgets. Resolve business history with Finance/owner approval, not a reset or silent backfill. A historically completed maintenance record with missing mapping is not automatically rewritten by this patch.

Existing English-only areas in newer Budget/Equipment/payroll screens remain a localization gap; the new review UI labels have English/Arabic translations, but this review does not claim a complete Arabic conversion. No native/offline attendance, official WPS/Mudad/GOSI, live telematics or construction scheduling is added here.

## Main changed areas

- `app/Http/Controllers/Admin/Hr/PayrollRunController.php`
- `app/Services/Accounting/PostingService.php`
- `app/Http/Controllers/Admin/Equipment/EquipmentAssetController.php`
- `app/Http/Controllers/Admin/Equipment/EquipmentOperationsController.php`
- `app/Services/Equipment/EquipmentCostService.php`
- `app/Services/Projects/ProjectBudgetService.php`
- `app/Services/Projects/ProjectBudgetReport.php`
- `app/Http/Controllers/Admin/Master/ProjectBudgetController.php`
- `app/Http/Controllers/Admin/Hr/MobileAttendanceController.php`
- Equipment completion/Budget views, review translations, regression tests, roadmap/user guide and safer release notes.

## Next decision

The validation runs above are finished; commit and push were authorized on October 14. Before production use, perform real ERP browser acceptance for maintenance completion, payroll role restrictions, budget warnings and English/Arabic attendance messages. A fresh complete-suite run and real concurrent MySQL probes were **not run in this review**; prior reported results are not substituted for them. Continue new feature development only after the owner supplies the next scope.
