# Customer, Supplier and Employee connected-workspace closure — verification, 1 November 2026

Branch `feature/seera-master-workspace-ux-closure-2026-11-01` (base `707c5eb`, the CU-06 computed-cell branch). Verified HEAD before fixes: `eb21bae`. UI fixes: `b7294e2`; test-runner fix: `b215d53`. Codex resumed at `b215d53`, ahead of the originally expected HEAD, and preserved the unfinished report, register and harness. Nothing was merged, deployed, migrated or seeded on production; production ZATCA stays disabled.

Scope: the final usability pass on the Supplier read-only view and Edit / Manage workspace, the Customer detail screen and the Employee detail screen: translated titles, breadcrumbs, descriptions, header actions, identity labels, tabs and priority actions in Arabic, with user-entered data, amounts, permissions, record scope and routes unchanged.

## 1. Safe checkout and diff review

- `eb21bae` matched the expected HEAD and has `707c5eb` as ancestor.
- `eb21bae` changes 7 files in 7 commits (+148 / −47): the supplier view (title, breadcrumb, description, header actions, nav label, "Profile" tab, the 18 profile row labels), the supplier edit page (title, breadcrumb, header, actions, nav label, "Profile" tab), the shared supplier identity header (8 `dt` labels, accepted payment types through `__()`, the open-bill count), the customer and employee detail titles / breadcrumbs and the employee documents empty state, 27 Arabic entries and the new `MasterWorkspaceUxClosureTest` (3 tests). **No controller, route, permission, model, query or amount code is touched**; `database/` unchanged. The supplier view still renders no form and no save control; the edit page keeps its profile form with save actions and its related-panel navigation.
- Translator audit through the application translator in `en` and `ar` over every literal key of the touched views plus the dynamic values (`Both` / `Cash` / `Bank`, supplier panel titles, approval labels): all resolve to strings; after the fixes none falls back to English except the design-time `ui.` / `zatca.type_` prefixes. Dictionary after fixes: 1,233 keys, 0 duplicates, valid JSON; all touched views lint clean.

## 2. Findings and fixes

| ID | Finding | Fix |
| --- | --- | --- |
| MW-01 | The supplier identity header replaced the English plural ("1 open bill" / "3 open bills") with `':count open bill(s)'`, so English now read "3 open bill(s)": a wording regression against the base. | Fix commit: `trans_choice(':count open bill|:count open bills', …)`; English pluralises again (verified for 0 / 1 / 2 through the translator), Arabic uses one count-neutral entry; the superseded entry removed. |
| MW-02 | Priority actions still English in Arabic on the screens under verification: the shared supplier related-record panel partial (used by both View and Edit / Manage) rendered "View project", "View PO", "View GRN", "Create Supplier Bill", "View", "Edit draft", "Record payment", 27 plain column headings, the "Invoiced / Uninvoiced" badges, the "Direct / n GRN lines" matched cell and the four payment metric labels in English; the two panel tab titles "Supplier Bills" and "Payments & Balance" and 14 of the partial's existing `__()` keys had no Arabic entry; the supplier form's quick-create buttons "Add Category" / "Add Term" and their dialog titles had no entry; the customer invoice panel actions "Edit draft" / "Record receipt" and the employee attendance / leave / overtime "Edit" buttons, the "+ New Structure From Profile" action and its empty state were plain English. Pre-existing on the base but inside this sprint's acceptance ("no untranslated priority action"). | Fix commit: every action, heading, badge and metric label of the supplier panel partial, the customer invoice actions and the employee detail actions pass through `__()`; 40 Arabic entries added (short UI terms; the two supplier accounting notes "Approve, pay and reopen…" and "Figures come only from…" are new Arabic wording for the Arabic-speaking reviewer to confirm). Covered by a new test in `MasterWorkspaceUxClosureTest`. |
| TS-01 | Tooling: ParaTest workers compile Blade views into `storage/framework/views/test_<n>`; a worker that compiled a view within the same second its source changed kept the stale copy forever (same mtime, never expired), so the new closure test saw English "Edit" links in Arabic only under ParaTest. | Fix commit: the runner deletes the per-worker compiled view folders before every parallel run; the compiled copies were inspected to confirm the stale content. |
| — | The Project Manager gets 404 rather than 403 on an employee outside their scope: the employee route binding is scope-filtered before the HR permission check. Pre-existing ordering, still a refusal, no data exposed; recorded, not changed. | — |

No permission, scope, route or financial-figure regression was found.

## 3. Tests

| Run | Result |
| --- | --- |
| `MasterWorkspaceUxClosureTest`, `CustomerWorkspaceTest`, `SupplierWorkspaceTest`, `EmployeeWorkspaceTest` on `eb21bae` (runner, 8 workers, OPcache) | **26 passed / 373 assertions, 0 failures** (90 s) |
| Same four classes on the fixed sources (4 closure tests) | **27 passed / 396 assertions, 0 failures** (42 s). Two earlier parallel runs showed 26 / 27 with the new test failing only under ParaTest while it passed alone and serially; the cause was a stale per-worker compiled Blade view (TS-01), not the application. |
| HR, finance, permission, scope, sidebar and Arabic regressions (runner, 8 workers, OPcache) | **321 passed / 3,974 assertions, 0 failures** (255 s) |
| Full Laravel suite on the final application sources (runner, 8 workers, OPcache) | **833 passed / 10,163 assertions, 0 failures**, exit 0; 585.72 s. Claude's completed run recovered from the handoff scratchpad; preserved as `evidence/master-workspace-ux-closure-2026-11-01/full-laravel.txt`. |
| Fresh resumed focused run, same four classes, CLI OPcache | **27 passed / 396 assertions, 0 failures**, exit 0; runner wall time 151.1 s. See `focused-resume.txt`. |
| Fresh resumed JavaScript run | **12 passed / 0 failed / 0 skipped**, exit 0; 152.589 ms. See `javascript-resume.txt`. |
| `npm run test:js` | **12 passed / 0 failed / 0 skipped**, exit 0; 513.4 ms. Completed handoff run preserved as `javascript.txt`. |

## 4. Browser verification (headless Edge via Playwright, isolated synthetic fixture; original run on 127.0.0.1:18769, resumed run on 127.0.0.1:18770)

Harness `tests/manual/master-workspace-browser.cjs` (Arabic expectations read from `lang/ar.json`; read-only). Results: `evidence/master-workspace-ux-closure-2026-11-01/master-workspace-browser-results.json`. Four 390px Arabic screenshots are included alongside the JSON; the remaining width/locale screenshots stay in the temporary `seera-master-workspace-resume-20261101` directory outside Git.

**First run on the delivered sources: 92 / 104** (the failures were MW-02's English actions on the supplier edit, customer and employee pages, one harness selector and the scoped 404 noted above). **Claude final run: 104 checks, 104 passed, 0 page errors** (preserved as `browser-claude-104.json`). **Resumed run with the additional Customer amount assertion: 105 checks, 105 passed, 0 failed, 0 page errors**, exit 0. See `master-workspace-browser-results.json` and `browser-resume.txt`.

Covered:

1. **Supplier View stays read-only** (Super Admin, SUP-001): document title, breadcrumb, description and name; header actions "Edit / Manage" → edit route and "Back to Suppliers"; no save control, no profile form, no related-save form; the 18 profile row labels and the 8 identity labels exact; Profile + 7 panel tabs with the inline read-only panels; the open-bill count pluralised and never "bill(s)"; every `/admin/` link answers 200 / 302.
2. **Supplier Edit / Manage keeps its form and panels**: title, breadcrumb, description, "View (read-only)" → view route and "Back to Suppliers"; profile form with save action and fields; navigation with the 7 related panels, each fragment loading with its title; the projects panel keeps its link form on edit while the view has none.
3. **Customer and Employee detail**: translated titles and breadcrumbs in both languages, customer code and employee code present, names identical, customer view without save control.
4. **Arabic exactness and unchanged data**: supplier view and edit titles, breadcrumbs, descriptions, header actions, nav labels, tabs, the 18 profile labels (values identical to English apart from the translated "Both" and the status badge) and the 8 identity labels with identical amounts and counts; all 7 inline and all 7 AJAX panels with the Arabic title, no Latin heading or button, the same row counts, identical amounts in every row and identical metric values; payment metric labels exact; customer and employee pages with no untranslated priority action and identical decimal amounts.
5. **Sweeps** at 320 / 390 / 768 / 1440 on supplier view, supplier edit, customer view and employee view in both locales: no horizontal overflow, no untranslated priority action in Arabic; remaining Latin headings recorded.
6. **Roles**: Marketing Manager (Customers) opens the customer view and gets 403 on supplier view / edit and employee view; HR Manager opens the employee view (no untranslated action) and gets 403 on supplier and customer; Finance Manager (Accounts Payable without Suppliers view) gets 403 on supplier view, edit, the bills fragment, customer and employee; Project Manager gets 403 on supplier and customer and the scoped 404 on the employee.

## 5. Recommendation

The resumed review inspected all seven original files (`707c5eb..eb21bae`) and the additional changes through `b215d53`. The completed full-suite log was recovered rather than unnecessarily repeating it: no application source has changed since those successful runs. The four focused classes were run again on resume. Source hashes and the verified build hash are recorded in `evidence/master-workspace-ux-closure-2026-11-01/source-manifest.json`.

The resumed browser harness now refuses remote targets, exits unsuccessfully on JavaScript page errors, accepts an explicit Playwright module path, and compares Customer decimal amounts across locales. Its original comment was corrected: business records are read-only, while login/session and locale preferences do change inside the synthetic fixture. No new application regression was found during the resumed code review.

Changed application/test/tool files relative to `707c5eb`:

- `lang/ar.json`
- `resources/views/admin/hr/employees/show.blade.php`
- `resources/views/admin/master/customers/show.blade.php`
- `resources/views/admin/master/customers/_workspace-panel.blade.php`
- `resources/views/admin/master/suppliers/show.blade.php`
- `resources/views/admin/master/suppliers/edit.blade.php`
- `resources/views/admin/master/suppliers/_workspace-header.blade.php`
- `resources/views/admin/master/suppliers/_workspace-panel.blade.php`
- `tests/Feature/MasterWorkspaceUxClosureTest.php`
- `scripts/test-speed.ps1`
- `tests/manual/master-workspace-browser.cjs`

The QA report, defect register and evidence directory accompany these files. The pre-existing untracked `docs/FABLE-HANDOFF-2026-10-08.md` is excluded from this delivery. No controller, model, route, migration, permission configuration, financial calculation, Equipment implementation or bundled asset source was changed by this closure branch.

The existing `public/build.zip` was verified by decompressing and hashing all **18 entries** against `public/build`. SHA-256: `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`. No rebuild is needed for these Blade/dictionary changes.

Remaining language review: an Arabic-speaking business reviewer should approve the two new supplier accounting explanations. Latin record names and codes are intentional user data, not untranslated actions. Broader modules, physical devices and the existing production/ZATCA acceptance gates remain outside this local workspace verification.

**MW-03 — pre-existing localization gap, outside the priority-action closure:** visual review of Supplier Edit at 390px still shows English profile field labels (for example Supplier Name, Supplier Code, City / Location), the Payment & Banking section title, some option labels and help paragraphs in the unchanged `suppliers/_form.blade.php`. They are not business data. The priority-button scan does not assert complete form-label translation, so its pass must not be read as full Arabic completion. No new regression against `707c5eb`; a subsequent form-localization pass should cover these strings. Customer/Employee record names, ledger descriptions and activity text remain original stored data.

**GO for integration approval of `feature/seera-master-workspace-ux-closure-2026-11-01`** at the final HEAD: the Supplier view stays read-only, Edit / Manage keeps its form and related panels, the Customer and Employee screens carry translated titles and breadcrumbs, user-entered data and amounts are identical in both locales, permissions, record scope and routes are unchanged and re-verified for five roles, the English plural regression and the untranslated priority actions are fixed with tests, and the focused classes, regressions, full suite, JavaScript tests and browser verification are green. Not a production go: GATE-01 / GATE-02 remain as recorded. No merge, deployment, production migration or seeding, and production ZATCA stays disabled.
