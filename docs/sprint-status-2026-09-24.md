# Seera — sprint status, 24–25 September 2026

Report for the owner and the backend architect (GPT) on the "next development sprint" brief. Branch `feature/seera-connected-workspaces-2026-09-23`. Nothing in this sprint was merged to `main` or deployed; the owner decides both.

## 1. Repository state

| Item | Value |
|---|---|
| Branch | `feature/seera-connected-workspaces-2026-09-23` (tracks `origin/…`); `main` stays at `9e295b7` |
| HEAD when the sprint started | `2d0f9d5` plus Codex's **uncommitted** Task A patch (16 modified files, `app/Support/LinkedIdentityNavigation.php` and the `linked-identity` component new) |
| Commits added by this sprint | `f12e228` Task A · `a028b00` Task C · `200d178` Task B audit · release commit (build, guide, this report) |
| Working tree after the sprint | Clean apart from untracked private research/media (`public/24_09_2026`, `public/seera_*`, `docs/*.html`, `docs/review-tools/`) and the root `seera-build-2026-09-24-employee-fixes.zip`; these are now listed in `.gitignore` so they cannot be staged by accident |
| Server (owner's terminal, 24 Sep) | Code at `2d0f9d5`; migrations through `2026_09_23` ran. The live asset manifest served `app-D3f81AIn.js`, which is Codex's 02:11 bundle: it predates the branch's current JavaScript (no `seera:before-navigation` guard, no linked-account rendering). This release's bundle replaces it. |

## 2. Implemented versus documented

**Implemented and covered by passing tests (branch commits `b6c4d86` … `2d0f9d5`, verified 215/215 at sprint start):** R22 customer corrections (coloured ratings, Customer Type + New, Cash/Bank/Both enforced on receipts, contacts/notes on Create/Edit, read-only Customer View), EN/AR preference with RTL shell, shared unsaved-change guard, Employee → User conversion, the Employee edit workspace with related panels, inline masters and consistent save actions, `ProductionEmployeeMastersSeeder`.

**Implemented in this sprint:** R24-01, R24-02 (Task A), R24-06, R24-07 (Task C). Details below.

**Documented only, not built:** multi-parent / all-required approval runtime (R22-01), VAT 15% default with Super Admin override (R22-02), full Arabic coverage, the remaining-module connected workspaces (R24-05), the client walkthrough video (R24-04), GRN-to-bill matching, payroll-to-GL, live ZATCA. Phase 5 and Phase 7 remain reference packages.

## 3. Task A — Employee View / Edit / Deactivate and linked identity

Codex's patch was reviewed, kept, completed with the missing Arabic labels (`View`, `Edit` in `lang/ar.json`) and covered by `tests/Feature/EmployeeViewEditDeactivateTest.php` (10 tests). Committed as `f12e228`.

| Behaviour | Where |
|---|---|
| List shows **View** (always), **Edit** (HR edit) and **Deactivate** (HR delete); the name links to View; "Open" is gone | `employees/index.blade.php` |
| View page hides Edit / Attach Document / New Structure for non-editors and carries no HR form; repeated visits write nothing | `employees/show.blade.php`, test `test_view_is_read_only_…` |
| Deactivate uses the shared modal with its own title and help text, keeps salary/document history, leaves the linked login active | `delete-modal.blade.php`, `layouts/admin.blade.php`, `EmployeeController::destroy` |
| Linked **User** card on the employee View/Edit pages and linked **Employee** card on the user View/Edit pages, from `employees.user_id` only; View/Edit destinations appear only with Users/HR permission and inside the actor's scope; "unlinked", "unavailable" (out of permission or scope) and "inconsistent" (two employees on one user) are distinct states and never disclose a name or URL | `app/Support/LinkedIdentityNavigation.php`, `components/admin/linked-identity.blade.php` |
| Account-creation search names the existing account for a linked employee with authorized destinations; a code conflict without a real link stays a diagnostic; payload carries only state, name, status and the two URLs | `UserController::employeeSearch`, `employee-user-search.js` |
| Users list, show, edit, update and destroy are now filtered by the actor's access scope (company / project / site / warehouse) | `UserController`, `LinkedIdentityNavigation::users()` — a side effect of the patch worth knowing: a site-level user administrator no longer sees users of other sites |

Not done, on purpose: no automatic relinking, no duplicate account creation, no password or permission change, no hard delete.

## 4. Test and build results

| Run | Result |
|---|---|
| Full suite at `2d0f9d5` + Codex patch (before this sprint's tests) | 215 tests, 2,030 assertions, all passing |
| Full suite after Task A (`f12e228`) | 225 tests, 2,160 assertions, all passing |
| Task C file + role/matrix/admin/workspace suites | 41 tests: one failure in the new counting assertion (the matrix script mentions the field name in a selector); assertion corrected, then 5/5 for `PermissionCatalogueTest` |
| Full suite on the final tree (`a028b00`) | 230 tests, 2,236 assertions, all passing (SQLite in memory, 22.7 min on this workstation) |
| Vite production build | `app-B6M69JbN.js`, `app-BS5RzxR6.css`, `erp-Bd96n4JM.css`; `public/build.zip` repacked with `assets/` and `manifest.json` at its root (18 files) |
| Browser fixture checks (`tests/browser/*.cjs`) | Not run in this sprint; the PHP suites cover the new behaviour, the fixtures remain the owner's pre-UAT step |

All PHP runs use SQLite in memory. They do not prove MySQL concurrency, Arabic acceptance or production behaviour.

## 5. Finance readiness

The audit is `docs/finance-readiness-audit-2026-09-24.md` (commit `200d178`), re-verified against source on 25 September (addendum §11). Summary of the per-area verdict:

| Area | Implemented | Tested | Client-accepted | Main gap |
|---|---|---|---|---|
| COA / cost centers / manual journals / GL | Yes | Yes (smoke and posting) | No | posted-history fields editable; GL running balance across pages; no period lock |
| AP bills / payments | Yes | Yes | Individual fields only | approval can leave a draft, possibly unbalanced, journal beside an "unpaid" bill (F01); no payment idempotency (F02); no payment reversal route; payable account resolved at payment time (F10) |
| AR invoices / receipts / ageing | Yes | Yes | Customer View and channel rules accepted | same as AP; no credit note; ageing is current, not as-of |
| Cash / Bank | Yes (1110 / 1120 only) | Indirect | No | one cash and one bank control; no reconciliation |
| VAT | Yes | Partly | No | finalised period not sealed (F03); zero-rate base dropped (F09); no coordinated 15% default / override (R22-02) |
| Inventory → accounting | Yes | Yes | No | GRN and a manual bill both credit AP and input VAT (F04); PO receipt bounds not locked (F05) |
| Reports / trial balance | Yes | Smoke + CSV | No | range openings, project-cost nets, scope of exports (F06/F07) |
| Approval runtime | Definitions only | Denial tests only | Rule confirmed, runtime absent | one actor with `approve` finalises everything (F08) |
| Payroll → GL | No | n/a | No | no journal from payroll approval (F12) |
| ZATCA | Local records only | State tests | No | no signed XML, no transmission; retry is a DB flag (F12) |

**30 September:** treat it as a bounded acceptance demonstration of FIN-01 … FIN-10 (audit §6) on a synthetic staging company, conditional on the owner and accountant confirming the scenario list by 25 September and on F01/F02/F03/F06/F07/F11 fixes landing in a Finance correctness sprint 26–30 September. If those do not fit, 30 September is the evidence review with named blockers, not operational acceptance. Live ZATCA, payroll-to-GL, bank reconciliation, advances/on-behalf clearing and credit notes are deferred unless the owner re-plans.

## 6. Task C — permission catalogue and matrix (`a028b00`)

- `RoleController::FORM_ACTIONS` now equals `Permission::ACTIONS`; both screens render the fifteen actions with shared labels from `Permission::ACTION_LABELS`.
- A save can only revoke what it displayed: the `visible_permission_ids` contract is unchanged for the form and the matrix, and a request that omits the visible list can now add but never remove (previously it replaced the whole set).
- Both matrices keep the module column and the action header in view while scrolling (`.matrix-wrap`, sticky, RTL-aware). Existing grouping, "Show all modules", horizontal overflow and visible-only bulk selection were left as they are.
- `tests/Feature/PermissionCatalogueTest.php`: catalogue parity on create/edit/matrix, one visible marker per rendered checkbox, extended actions round-trip, displayed-only revocation on both save paths, view-only actor denied on both endpoints.

## 7. Next three batches (dates are planning targets)

1. **26–30 September — Finance correctness sprint and evidence gate.** Needs: owner/accountant sign-off of the FIN-01…10 list and expected figures by 25 September. Work: F01 (reject unbalanced/missing-account postings atomically, truthful draft/posted status), F02 (payment/receipt idempotency key + unique constraint, additive migration), F03 (closed-period guard on every VAT path), F06/F07 (GL running balance, report openings, journal header scope, CSV export permission), F11 (locked re-checks on edit/delete). Present the patch plan before code; run the ten scenarios on staging with screenshots and exports.
2. **1–9 October — Supplier/AP connected workspace.** Depends on batch 1 and on the GRN-versus-bill liability decision (F04); until that decision the workspace shows bills and payments only and does not hide the missing GRN link. View stays read-only; Approve, Pay and Reopen remain explicit commands.
3. **10–16 October — Customer/AR connected workspace**, then the all-required approval runtime as its own schema/runtime patch once the actor, order, rejection and legacy-request rules are fixed. 17–31 October: remaining agreed Finance gaps, payroll-to-GL if commissioned, Arabic/RTL pass, release freeze for the 31 October development target.

## 8. Decisions still needed from the owner or client

1. Which FIN scenarios are the 30 September minimum, who signs each, and is it a demonstration or authority to transact.
2. All required approvers must approve is settled; still open: who the approvers are per request type, parallel or sequential, thresholds, self-approval, delegation, rejection/resubmission, and what happens to requests already pending.
3. GRN versus supplier invoice: when the liability and input VAT are recognised, and how the two are matched, so the same purchase is not counted twice.
4. VAT: default rate, who may override, categories/zero-rate handling, rounding, period close and late entries, credit notes.
5. Whether financially approved documents post immediately or sit as review-mode journals with an explicit unposted state.
6. Payroll-to-GL: included in this year's scope or not; ZATCA: which invoice flows need live clearance and who provides sandbox credentials.
7. R24-04 walkthrough: video or written, language, audience, finance-only or whole product.
8. Remaining-module workspaces: confirm Supplier before Customer, and that Customer View stays read-only.

## 9. Commits and deployment

| Commit | Content |
|---|---|
| `f12e228` | Task A: View / Edit / Deactivate, linked identity navigation, Arabic labels, 10 tests |
| `a028b00` | Task C: shared action catalogue, revoke-only-displayed rule, sticky matrix, 5 tests |
| `200d178` | Task B: finance readiness audit with verification addendum |
| release commit | rebuilt `public/build.zip`, deployment guide §12, feedback register statuses, this report |

Deploy only with owner approval. Steps are in `docs/production-deployment-guide.md` §12: fetch, stay on the feature branch, pull, `migrate --force` (no new migration), extract `public/build.zip` into `public/build`, `optimize`, `up`. Rollback is `git checkout 2d0f9d5` plus that commit's bundle; no database change is involved.
