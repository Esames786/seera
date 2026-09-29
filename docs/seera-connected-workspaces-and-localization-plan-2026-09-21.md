# Seera: connected workspaces, safe navigation and two-language plan

Review date: 21 September 2026. Language scope revised 22 September 2026: English and Arabic only; this supersedes the earlier three-language proposal. Source baseline: `9e295b7` on the local checkout.

**Status: research and implementation proposal, not implemented by this review.** Languages confirmed by the owner: **English and Arabic**. No application code, database records, migrations, production configuration or client media were changed for this document.

## 1. Recommendation in plain language

Keep the accepted business rules and simplify the journey around them. Build a **record workspace**: one place to open an employee, customer, supplier, project or purchasing document and work with its related information. Reuse the existing Laravel Blade forms and server operations rather than rebuilding the ERP as a new application.

For an employee, the user should be able to save the profile, check the salary structure, attach documents, review leave and manage the account link without repeatedly finding the same person on another listing.

Use **Save & stay**, **Save & next**, and **Save & close**. Do not ask whether to save immediately after a successful Save: that suggests the save did not happen. Ask **Save & continue / Discard & continue / Stay** only when someone tries to leave with unsaved changes. Payroll processing, posting, approval and destructive actions remain separate, deliberate operations.

Replace the duplicate **View / Edit** choices with **Open** in the pilot workspace. Open is editable only where the user's permissions and the record's status allow it. Do not delete the existing View routes or read-only screens during rollout.

**22 September client correction:** Customer View must remain a read-only dashboard. Put Office/Site Contacts and Shared Notes entry in Customer Create/Edit, not View. The Open/workspace idea above is the Employee pilot proposal, not a blanket instruction to remove Customer View. See [new feedback R22-06/R22-07](client-requirements-2026-09-22.md).

The recommended sequence is:

1. Freeze the accepted baseline and define shared save, permission and translation contracts.
2. Pilot Employee workspace and navigation protection, alongside a separately gated employee-to-user flow.
3. Extend protection to all inventoried editable forms; expand workspaces to customers, suppliers and projects.
4. Introduce purchasing, stock and accounting workspace improvements only after their higher-risk regression checks.
5. Complete English/Arabic coverage in approved module waves. New components support both languages from their first release.

“Without affecting anything” cannot be guaranteed merely by rearranging screens. The protection is small releases, unchanged business contracts, negative permission tests, browser tests, client acceptance and a working fallback.

## 2. What was reviewed and what remains unverified

### Evidence used

- The latest substantive Seera Claude handoff in project session `137b5bc7-77f9-4c94-b46e-54bc7c2cfa0e`, including the 21 September implementation and test report. This was a focused review of relevant assistant handoff text, not a claim to have audited every session entry.
- Current Git history and worktree; the latest implementation status registers, earlier requirements context and the four-round client walkthrough.
- Current routes, admin layout, sidebar, shared quick-create component, user/employee models and controllers, salary linkage, permission/scope middleware, and representative HR, master-data, marketing and purchasing flows.
- The owner's screenshots and four requested improvements. Earlier recordings were not retranscribed for this architecture review.
- Official framework, browser, accessibility and authorization references listed in section 12.

### Current evidence level

| Item | Finding | Confidence boundary |
|---|---|---|
| Latest local source | HEAD `9e295b7`; round-four implementation commit `663b0b1` | Verified locally; not proof of the server checkout |
| Architecture | PHP requirement `^8.3`, Laravel requirement `^13.8`, Blade views, Vite assets; no SPA framework required by the inspected package manifest | Source-backed; no framework migration recommended |
| Route/view inventory | 343 total routes, 332 admin routes, 58 distinct admin controllers, 219 admin Blade files and 36 `_form.blade.php` partials | Read-only inventory at this baseline; not a count of distinct user workflows |
| Tests | Claude reported **174 tests and 1,618 assertions** for the latest work | Historical session evidence; this review did not rerun the suite |
| Production | Claude's handoff reports deployment; the production guide's opening still references the older `8f32d728` baseline | Current live commit, migrations and behavior were not independently verified |
| Worktree | Existing untracked requirements reports, archives and media were present | Preserved; not treated as disposable generated files |
| Release readiness | Not assessed/certified by this document | Requires fresh test results and live deployment verification |

The old authorization audit is historical, not a current verdict. Current source contains permission and access-scope enforcement; any new workspace must preserve and test those controls.

### Where Claude's September 21 work stands

The [current implementation register](client-change-register-2026-09-21-status.md) is the implementation companion to the [client requirements](client-requirements-2026-09-21.md), not interchangeable with them.

| Feedback | Current source/status | Preserve during simplification |
|---|---|---|
| Contract Start | Rejects future dates; Contract End can be future; Joining Date remains separate | Do not merge dates or change the accepted rule |
| Document Type | Reusable lookup catalogue and inline `+ New` | Keep reuse, filters and authorization |
| Document preview | Authenticated View/Download for saved files; local preview before saving a new file | Keep private storage, file checks and preview access controls |
| Salary structure | Saving an employee with basic salary creates the first structure if none exists; carries the five allowances; later profile edits do not overwrite existing structures | Preserve effective dates, history and the mismatch/new-structure flow |
| Payroll fallback | Employee allowances are now included when no salary structure applies | Do not reintroduce the earlier basic-only fallback |
| Leave duration | Live inclusive calculation, server recalculation and explicit manual/half-day override | Do not silently introduce working-day counting |
| Employee code | Classification-specific preview; final allocation on save; optional manual code | Preview is not a reserved number |
| HR labels | Escaped ampersand labels corrected | Include in translated-view regression checks |

One qualification to the handoff: `CodeGenerator::sequential()` uses a maximum/existence lookup, and Employee allocation occurs before the create transaction. This inspection did not establish an atomic reservation or retry mechanism. The register's concurrency assurance should therefore be verified with simultaneous creates; a unique database constraint alone can reject a collision without providing a successful retry. This is a targeted verification gate, not evidence that existing employee records are duplicated.

Still-open business decisions must not be settled accidentally through UI work:

- Salary effective dates and whether later profile edits automatically create a new structure.
- Whether to backfill salary structures for existing employees in one approved operation.
- Calendar versus working leave days, holidays and manual override rights.
- Who may add, rename or retire document types.
- Whether Joining Date and Contract Start must be equal.
- Earlier unresolved items: project/location meaning, advance/on-behalf accounting, alert channels, leave accrual, marketing lifecycle details and staged approvals.

## 3. Requested scope and proposed interpretation

| ID | Owner request | Recommended scope | Explicit exclusion |
|---|---|---|---|
| UX-01 | Related modules on one screen; keep saving and proceeding | Record workspaces, contextual child forms, consistent save destinations, preserved list context | No single enormous form for the entire ERP; no automatic approval/posting |
| UX-02 | Universal save warning on leaving/changing pages | Shared dirty-form protection with a recorded coverage checklist for every editable flow | No warning on untouched forms; no promise of custom browser-close dialogs |
| UX-03 | Multilanguage | English `en`, Arabic `ar`; translated UI, validation and messages; RTL for Arabic | No automatic translation of stored business records or change to accounting/date rules |
| UX-04 | Search employee on Users and fetch details | Permission-scoped selection, safe prefill, explicit role/access setup, atomic user creation and employee linking | No employee deletion, automatic privilege grant or copying all confidential HR data |

These are proposed acceptance boundaries for development. Existing accepted behavior remains the default until its replacement is explicitly approved.

## 4. Which modules should become connected workspaces?

“Single screen” means a stable record context with relevant tabs or sections, not loading every dataset or giving everyone access to every related module.

| Area | Recommended simplification | Keep separate / important boundary | Priority |
|---|---|---|---|
| Employees | Profile, employment, documents, pay, leave/time summaries and system access in one workspace | Payroll batch processing and HR-wide registers | First pilot |
| Salary structures | Open/create the employee's structure inside the employee context, with effective-date/history visibility | Approval/status rules and historical structures; no silent salary replacement | First pilot |
| Documents / IQAMA | Attach, preview and maintain the selected employee's documents locally | Global expiry/compliance register and private-file authorization | First pilot |
| Leave and overtime | Employee-prefilled contextual forms and related history | Approval queues, balances and existing counting/calculation rules | After pilot core |
| Attendance / shifts | Employee attendance summary and relevant assignment actions | Bulk attendance entry and shared shift setup remain dedicated tools | Later HR wave |
| Payroll / end of service | Employee links and summaries; clear return to selected employee | Batch calculations, finalization, payment/posting and settlement lifecycle | High-risk wave |
| Users | Employee search/prefill plus account, role, scope and language setup in one user form | Roles, hierarchy and permission matrix remain specialist screens | First pilot, separate gate |
| Customers | Create/Edit workspace with details, Office/Site contacts and notes; separate read-only View | September 22 requires no inline mutation on View; retain receivables queue and posting/receipt controls | Second wave |
| Suppliers | Details, project relationships, bills/payment summaries | Payables queue, bill/payment posting, accounting controls | Second wave |
| Projects / locations | Project details, locations, assigned staff and related suppliers/warehouses together | Do not resolve the open project/location data-model question by merging entities | Second wave |
| Organization structure | Extend the existing branch/department/designation hub and `+ New` dialogs | Preserve section-specific permissions and independent masters | Small incremental work |
| Marketing leads / visits | Preserve and refine the existing lead detail + Record Visit pattern | Lead conversion remains explicit; unresolved lifecycle changes stay separate | Small incremental work |
| Materials / items | Item details, category/unit lookup, stock and ledger summaries | Actual stock-changing commands retain their own validation and permissions | Inventory wave |
| Warehouses | Warehouse details and stock context with issue/transfer entry points | Dispatch and receipt are separate lifecycle events, possibly different operators | Inventory wave |
| PR → PO → GRN | Linked-document progression with parent context and return location preserved | Approval, quantities, partial receipts, stock/accounting posting remain explicit | High-risk wave |
| Stock issues / transfers / adjustments | Contextual drawers/forms and linked history | No automatic posting merely because a form is saved | High-risk wave |
| AP / AR | Bill/invoice workspace with related transactions and outstanding balance | Bulk ageing, payment/receipt controls and posted-document read-only state | High-risk wave |
| Chart of accounts / cost centers | Easy related lookup and authorized contextual creation | Do not casually create accounting configuration from transactional screens | Controlled later wave |
| Journal entries / GL / VAT / ZATCA | Better context links and return paths, not forced consolidation | Posting, reconciliation, reporting and compliance workflows | Keep dedicated |
| Roles / permission matrix / approval workflows / activity logs | Consistent navigation and guard on editable configuration | Security administration and audit records remain distinct | Shared UX only |
| Reports and dashboards | Drill through to a workspace and return to retained filters | Reports are not editable forms; no blanket unsaved warning | Shared navigation |

The sidebar still labels some areas as coming soon, including Equipment & Vehicles and certain project/report/settings entries. A sidebar placeholder or reference package is not a completed module; do not include those as implemented functionality in a redesign estimate.

Existing foundations reduce risk: employee and project detail pages already expose related information; customer/marketing pages have contextual actions; shared quick-create dialogs can save without leaving their parent. Purchasing already accepts a source purchase request for PO creation and a source PO for GRN creation. Improve these existing paths instead of inventing parallel domain logic.

Do not assume a PR → PO → GRN → supplier-bill chain is already fully automated. Any additional GRN-to-bill integration needs separate accounting review to avoid duplicate inventory/liability postings.

### Employee pilot layout

```text
Employees / Employee name · code · status                 Previous | Next

Profile | Employment | Documents | Pay | Leave & Time | System Access | History

Selected section: existing fields, validation and related records
Related action: local panel or drawer, employee already selected

                           Save & stay | Save & next | Save & close
```

- Start with the existing employee form grouped into sections; preserve its complete validation/payload contract. Do not relax required fields just to make a wizard appear to work.
- Save a valid core employee before creating children that require an employee ID. Show clearly which information has already been saved.
- “Next” in **Save & next** means the next section in this employee's workflow. “Next employee” is a separate, guarded action based on the current filtered listing.
- Switching between mounted sections can preserve unsaved inputs without prompting. Leaving the record or unloading those inputs must be guarded.
- Child forms save through their own existing domain operation. No nested HTML forms and no all-or-nothing transaction spanning unrelated modules.
- The Pay section shows current/effective structures and the existing profile mismatch notice. It does not silently make employee profile salary the authoritative replacement for historical payroll.
- Preserve search/filter/page state when returning to the employee list. Load sensitive tabs only after their own authorization checks.
- Use one **Open** action for the pilot; preserve direct old URLs and read-only rendering for view-only users and immutable documents. Remove duplicate navigation only after acceptance, not the underlying access mode.

## 5. Universal save and navigation protection

### Current gap

The inspected shared admin layout has no universal dirty-state guard. It loads the CSS entry point, while much behavior is inline in Blade; `resources/js/app.js` is essentially empty. There are multiple forms on a page, including logout, delete confirmation and quick-create modals. A script that watches only the first form or treats every POST as editable data will be incorrect.

Introduce a shared form registry with stable form IDs and explicit categories: editable record, editable child dialog, GET filter, command/approval, upload and read-only view. Inventory all forms, not merely the 36 `_form` partials. Initially migrate one category at a time, then require registration for every new editable form.

### Required behavior

| Situation | Behavior |
|---|---|
| Untouched form; ordinary navigation | Navigate without a prompt |
| Edited then reverted to the original values | Clean again; no prompt |
| Dirty form; sidebar, breadcrumb, Cancel or same-tab link | Save & continue / Discard & continue / Stay |
| Save & continue | Validate and persist; navigate only after confirmed server success |
| Discard & continue | Discard only the affected unsaved edits; explain that previously saved child records remain saved |
| Stay / Escape | Remain on the form with inputs intact; return keyboard focus |
| Save & stay | Save once, show success, update the clean baseline, retain workspace |
| Save & next / close | Save once, then move to the agreed destination; do not show a second save warning |
| Validation error or permission failure | Stay dirty; reveal the section with the error and preserve input |
| Offline, timeout or uncertain response | Do not mark clean or automatically replay a payment/posting command; reconcile save status safely |
| Expired login / CSRF session | Preserve safe in-memory context, explain reauthentication; do not treat a login HTML response as a successful save |
| Browser refresh, close or external history navigation | Use native browser unsaved warning where supported; do not promise the custom three-button dialog |
| Language change while dirty | Guard before a reload; a language selection must not destroy edits |
| Logout while dirty | Guard before logout; Stay must not log the user out |
| Quick-create child dialog | Track child and parent separately; closing a dirty child prompts for that child |
| File preview in a new tab | Do not prompt solely for opening a preview when the editing page remains intact |
| Delete, approve, post, process or finalize | Keep explicit command confirmation and existing server checks; never invoke through generic autosave |

Browsers control `beforeunload` wording and may not fire it reliably, especially on mobile. Register the handler only while edits exist. A guaranteed custom “Save” button on browser close is not a feasible acceptance criterion. See [MDN: beforeunload](https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeunload_event).

### Technical contract proposed

- Track a normalized value snapshot, including checkboxes, multi-selects, dynamic rows, deletions and selected file metadata. Establish the initial snapshot after server defaults and widget initialization. Include programmatic changes such as quick-create selection and live leave totals.
- Keep parent and modal state independent. A saved `+ New` lookup remains saved even if the parent form is later discarded; make that visible.
- Preserve CSRF, method spoofing, multipart uploads and the actual submit-button intent. Use normal form submission where possible; explicit adapters are safer than globally replacing every form with `fetch`.
- Where a guard triggers submission, `requestSubmit()` preserves normal validation and submit-event behavior; calling `submit()` directly does not. See [MDN: requestSubmit](https://developer.mozilla.org/en-US/docs/Web/API/HTMLFormElement/requestSubmit).
- Standardize an allowlisted save intent and server-resolved return context. Existing requests without those fields retain existing redirects. Never accept arbitrary external return URLs.
- For an explicitly adopted JSON path, define success, record ID, destination and validation error contracts; only clear dirty state after a verified successful save. Laravel supplies structured validation errors with HTTP 422 for JSON requests: [validation documentation](https://laravel.com/framework/docs/13.x/validation#xhr-requests).
- Disable repeated submissions while pending. Financial commands additionally need their existing transaction/idempotency protections, not just a disabled button.
- Do not persist salary, identity documents, passwords or file content in browser local storage as an incidental feature of the guard. Server-side drafts would be a separate, permission-controlled and retention-defined feature.
- Test browser Back/Forward explicitly. Do not create a history trap or duplicate records by replaying navigation. A custom three-choice dialog is required for controlled in-app navigation; native browser navigation has the documented limitation above.
- Warn about concurrent edits rather than silently overwriting another user's changes. Before implementing conflict detection, verify the persistence strategy for each adopted form; a stale-record/version check must be enforced by the server, not only displayed by JavaScript.

Dialogs must have labels, contained focus and appropriate focus restoration. Tabs require keyboard behavior and associations; ordinary navigation links should not receive tab roles merely for their appearance. Follow the [W3C dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/) and [tabs pattern](https://www.w3.org/WAI/ARIA/apg/patterns/tabs/).

## 6. English and Arabic

### Current gap and target

Users currently offers English and Arabic labels, and the user controller accepts a generic language string. The inspected layout sets `lang` from the application locale but does not set `dir`. A language field alone does not establish translated UI, per-request locale selection or RTL support. Use Laravel’s built-in localization facilities for English and Arabic throughout the interface; Urdu is out of scope.

| Concern | Proposed behavior |
|---|---|
| Supported locales | `en`, `ar`; validate against this allowlist |
| Existing stored values | Compatibility map for `English` and `Arabic`; do not break existing users with an immediate destructive rewrite |
| Preference | Authenticated user's preference; temporary/session choice for unauthenticated pages; English fallback |
| Direction | English LTR; Arabic RTL at document level |
| Translation storage | Domain-grouped Laravel language files; shared escaped JS messages for dialog/widget text |
| What gets translated | Navigation, labels, help, buttons, validation, flash messages, empty states, visible status names and applicable report/print labels |
| What stays stable | Routes, permission keys, database enum values, IDs, stored names, accounting codes and business calculations |
| Missing strings | English fallback plus a release-time missing-key check; do not quietly accept mixed-language critical flows |
| Language switch | Visible in top bar and editable in user preferences; dirty-form protection applies |

Laravel supports translation files, request locale selection and fallback locale. Use that existing facility rather than a parallel translation engine: [Laravel localization](https://laravel.com/framework/docs/13.x/localization).

Set `lang` and `dir` on the HTML document, and use direction-aware CSS spacing/alignment. Isolate mixed-direction values such as email, IBAN, employee code, phone and reference numbers; avoid reversing their characters. Do not mirror logos or every icon indiscriminately. See [W3C HTML direction guidance](https://www.w3.org/International/questions/qa-html-dir).

Additional acceptance details:

- Arabic translations need review by speakers familiar with the client's HR/accounting vocabulary.
- Select fonts with verified Arabic shaping; test line height, table density, modals and printed output.
- Keep currency and stored numeric/date semantics unchanged. Localization does not imply a Hijri calendar or new payroll/leave rules. Keep the present business calendar unless separately approved.
- Test Arabic-Indic digit entry and parsing if accepted by inputs; normalize deliberately at the validation boundary. Never localize numeric database values by storing display-formatted strings.
- Business names and free text stay as entered. Separate multilingual business-name fields are an optional later requirement; do not translate names or documents automatically.
- Cover login/password screens, quick-create dialogs, JavaScript warnings, validation and report/print templates, not only menu labels. Distinguish internal UI language from any recipient-selected document language.
- Keep exports machine-compatible where integrations depend on stable headers or codes; any translated export option must be explicit.
- Test English alongside Arabic in every migration wave. RTL support must not regress the accepted English layout.

## 7. Employee → system user from the Users screen

### Existing relationship: reuse it

`Employee` already belongs to `User` through nullable **`employees.user_id`**; `User::employee()` exposes the inverse relation. Employee forms can select an existing user account. However, the inspected Users create flow does not offer employee search/prefill.

**`users.employee_id` is a unique string field, not the employee table's primary-key foreign key.** Do not implement conversion by putting an employee database ID into that field or creating a second competing relationship. No unique constraint on `employees.user_id` was found in the inspected migrations, so one-user/one-employee enforcement needs a duplicate-link preflight before adding a constraint.

“Convert” should mean **create/link login access while retaining the employee**, not moving or deleting their HR record. Attendance, documents, leave and payroll references must remain attached to the same employee ID.

### Proposed journey

1. Users → Add User → choose **From employee** or the existing **Standalone user** path.
2. Search authorized employees by name or code; optionally phone/IQAMA where explicitly permitted. Debounce, paginate and limit fields; do not download every employee into the browser.
3. Select a specific employee by ID. Show identifying context sufficient to distinguish matching names and whether an account is already linked.
4. Fetch permitted details from the server, prefill the user form and identify the source employee clearly. Never trust hidden submitted HR fields as authoritative.
5. Admin explicitly sets role, account status, access scope, login/contact details and language. Resolve missing/duplicate email or username without fabricating values.
6. On Save, authorize again, lock/re-read the employee, recheck that it is eligible/unlinked, and create the user plus role/link in one database transaction. Handle uniqueness conflicts clearly.
7. Show success with Open User / Open Employee. A linked employee offers the existing account, not another Create button. Linking an existing account is a separate authorized action with conflict checks, not a fuzzy email match.

### Data ownership and prefill

| Data | Proposed handling |
|---|---|
| Employee identity, code, employment and classification | HR remains authoritative; display/prefill only permitted mapped fields |
| Name, email, phone | Initial prefill; define and show subsequent sync ownership rather than silently overwriting edits |
| Department/designation and organizational context | Prefill as employment context; validate referenced records |
| Project/site/warehouse access | Never infer access grants solely from employment placement; explicit authorized selection |
| Salary, allowances, bank details, documents | Not part of normal account-creation response |
| Roles, temporary roles, hierarchy, MFA, login status | Identity/access administration owns these; explicit authorized setup |
| Password | Never copied from employee data or returned by search; use a secure account-setup flow |
| Language | Explicit `en`/`ar` preference, English default unless selected otherwise |
| Existing classification sync | Current user edits can update the linked employee classification; preserve or deliberately refactor this documented exception, with tests |

Current UserController uses a shared default password (`123456`) when no password is supplied, with a change-password flag. Do not scale that pattern through a new conversion feature. Prefer a time-limited invitation or controlled one-time setup process; decide delivery and activation rules before release. Send invitations after successful commit, with retryable delivery that cannot create duplicate accounts.

Search, prefill, creation and linking each require server authorization and scope filtering. A Users administrator should not automatically gain broad HR read access. Define a narrow employee-link capability if that role legitimately needs identity-only selection. Return no confidential employee details from unauthorized searches.

The authorization middleware derives action permissions from route suffixes, with a default view mapping. A newly named search/link/workspace endpoint can therefore receive the wrong effective check unless explicitly mapped. Add negative tests for crafted writes and cross-scope employee IDs, not merely button visibility. See [OWASP authorization guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).

## 8. Precision rules: what must not change accidentally

1. Reuse existing domain validation, services, transactions and policies. A drawer is another presentation of the same operation, not a second implementation.
2. Preserve route compatibility, IDs, field meanings, status transitions and default redirects. Existing JSON quick-create responses also remain compatible.
3. Keep list filters, pagination, search and return context. Do not make bulk operators repeatedly reopen the same record from page one.
4. Preserve granular permissions for each workspace section/action. A parent Employee view permission does not grant payroll or Users write permission.
5. Scope each child query; verify child ownership on update/download routes. A valid child ID must still belong to the selected parent and permitted project/site scope.
6. Preserve all financial totals, rounding, effective dates, audit events and posted-record immutability. No automatic approve/process/post on Save & next.
7. Keep payroll, attendance, approvals and stock registers available for batch work. Individual workspaces supplement those operational queues.
8. Preserve uploaded-file privacy and cleanup behavior when validation or transactions fail. Moving file controls must not turn private files into public URLs.
9. Do not rewrite accepted business rules to make section-level saves easier. If separate save boundaries are needed, specify and test them first.
10. Keep old read-only routes for reviewers, audit links and printed-document flows. Feature rollout changes preferred navigation before removing anything.

Important transactions need appropriate checking, confirmation or reversibility; this does not require an extra confirmation on every ordinary save. The distinction is consistent with [W3C error-prevention guidance](https://www.w3.org/WAI/WCAG22/Understanding/error-prevention-legal-financial-data.html).

## 9. File impact map for later development

These are **anticipated touchpoints, not files changed by this review**. Follow the call chain for the selected pilot before editing. Proposed filenames are design suggestions and do not currently constitute implementation.

| Existing area | Expected purpose |
|---|---|
| `resources/views/layouts/admin.blade.php` | Load shared JS, locale direction and common navigation dialog |
| `resources/js/app.js`, `resources/css/erp.css`, `vite.config.js` | Guard entry point, workspace behavior, RTL styles and build integration |
| `resources/views/components/admin/topbar.blade.php` | Language switch and guarded logout |
| `resources/views/components/admin/quick-create.blade.php` | Child-form registration, independent dirty state and preserved response contract |
| `resources/views/admin/hr/employees/{index,create,edit,show,_form,_document-row}.blade.php` | Pilot Open/workspace, shared fields, documents and save destinations |
| `app/Http/Controllers/Admin/Hr/EmployeeController.php`, `app/Models/Employee.php` | Save intent, preserved validation/salary linkage and response context |
| `resources/views/admin/hr/salary-structures/`, `app/Http/Controllers/Admin/Hr/SalaryStructureController.php` | Employee-context structure flow without losing history/effective-date rules |
| `app/Http/Controllers/Admin/Hr/EmployeeDocumentController.php` | Preserve scoped private preview/download and child updates |
| `resources/views/admin/users/`, `app/Http/Controllers/Admin/UserController.php` | Employee selection, safe prefill, account settings and legacy standalone flow |
| `app/Models/User.php`, `app/Models/Employee.php` | Canonical relationship and documented data ownership |
| `app/Http/Middleware/EnsureUserHasPermission.php`, `app/Services/UserAccessScopeService.php` | Explicit new-action checks and scoped queries |
| `routes/web.php`, `bootstrap/app.php` | Additive routes and locale middleware registration; preserve middleware ordering |
| `app/Support/SidebarMenu.php` | Translate labels; only later simplify navigation after acceptance |
| `resources/views/admin/master/{customers,suppliers,projects}/`, `resources/views/admin/marketing/leads/` | Subsequent workspace waves; retain existing contextual actions |
| `app/Http/Controllers/Admin/Inventory/{PurchaseOrderController,GoodsReceiptController}.php` and inventory views | Later context-preserving purchasing progression, explicit status transitions |
| `database/migrations/` | Only justified additive changes, such as a validated one-to-one employee link constraint |
| `tests/Feature/ClientFeedbackSeptember21Test.php`, `tests/Feature/HrPayrollTest.php` and related suites | Preserve accepted September feedback and payroll behavior |

Proposed additions:

- `resources/js/admin/unsaved-changes.js`: registry and guarded navigation.
- Shared workspace/save-action/dialog Blade components; extract reusable pieces from existing forms rather than copying complete forms.
- `app/Http/Middleware/SetLocale.php` and domain translation files under `lang/en`, `lang/ar`.
- A dedicated employee-account linking service/action and narrowly scoped search endpoint.
- Tests for guard behavior in a browser, user-link authorization/concurrency, localization and workspace parity. Select browser-test tooling after checking the existing project test setup; adding a new framework is not assumed here.

The current admin layout does not load the JS entry point, so creating a guard JS file without connecting and building it would deliver no working protection. Unlike the prior Blade-only feedback round, this proposed release is likely to require a frontend build.

## 10. Delivery phases, acceptance and rollback

### Phase 0 — baseline and contracts

- Capture accepted screenshots and representative outcomes with sanitized fixtures: employee create/edit, salary structures, document previews, leave, user setup and target modules.
- Run existing suites in an isolated test database and record final exit status. Do not reset the local or production database to obtain test data.
- Inventory all editable forms and route/action permissions; classify bulk, command and read-only flows separately.
- Approve save destinations, employee workspace sections, role visibility and language vocabulary.
- Define independently switchable workspace, guard, locale and account-link rollout controls. Feature flags are proposed, not already present.

Gate: documented baseline and pilot acceptance checklist. No production behavior change.

### Phase 1 — Employee pilot and shared foundation

- Add the shared guard infrastructure; enable on the employee/child pilot first.
- Reuse existing form fields in the workspace and implement save destinations with old defaults preserved.
- Add locale infrastructure and translate pilot/shared components into both languages.
- Keep existing View/Edit screens available as fallback. Demonstrate to the client with real work scenarios but sanitized test data.

Gate: previous feedback passes unchanged; permissions, validation, files and navigation work in English/Arabic. Client accepts the new journey before duplicate buttons are removed in the enabled cohort.

### Phase 2 — employee-to-user workflow

- Implement scoped search/prefill, explicit account settings, atomic linking and safe onboarding.
- Check existing duplicate links before any uniqueness migration. Report collisions for resolution; never automatically reassign them.
- Test two administrators creating access for the same employee simultaneously using the target database engine and separate connections/processes.

Gate: one successful link, predictable loser response, no orphan/duplicate users, no unauthorized HR data, standalone user creation still works.

### Phase 3 — universal coverage and low-risk expansion

- Adopt the guard on every inventoried editable form, including inline/dynamic forms and configuration; test exclusions for filters/commands.
- Extend workspaces to customers, suppliers, projects and existing marketing flows; translate the same scope.
- Maintain a coverage register with screen, form ID, adapter, tests, languages and approval state. “Universal” is not complete until every applicable row passes.

### Phase 4 — financial/inventory workflows and remaining language coverage

- Introduce context-preserving procurement and stock/accounting navigation in small releases.
- Recheck posting, partial receipt, payment allocation, audit and immutable-state behavior against baseline outputs.
- Finish remaining reports, print views, authentication and administration translations, with terminology review.

### Deployment and rollback boundary

This document is not a fresh production command list. Before a later release, verify the server commit, PHP/runtime dependencies, backups, pending migrations and required asset build against that exact release. Do not use an old deployment-guide baseline as proof of current server state.

Use additive, backward-compatible changes. Do not run `migrate:fresh`, truncate records or replace production data with demonstration seeders. Translation files normally need no business-data seeder; any locale normalization or link constraint needs an explicit reviewed migration/backfill plan.

If the new UX fails, disable the relevant rollout flag and restore the old navigation path. That rolls back presentation, **not** saved employees, users, stock or accounting transactions. Do not delete valid records to undo a UI rollout. Retain schema compatibility for the rollback window and document recovery for any migration separately.

## 11. Minimum regression and acceptance checklist

| Area | Required checks before enabling broadly |
|---|---|
| Accepted feedback | FR-01 through FR-06 and OBS-01 remain correct; old and new entry points produce equivalent data |
| Employee save | Create/edit, code preview/manual override, simultaneous creates, salary structure defaults/history and duplicate-submission handling |
| Save destinations | Stay/next/close reach the agreed location, preserve filtered list context and never navigate after failure |
| Dirty tracking | Text, checkbox, multi-select, dynamic rows, remove/re-add, file selection, quick-create and edit-then-revert |
| Navigation | Sidebar, breadcrumbs, Cancel, record switch, language switch, logout, Back/Forward, refresh and close |
| Failure modes | Validation 422, permission 403, expired session/CSRF, offline, slow response, uncertain save and conflicting edits |
| Multiple forms | Parent vs modal state, no nested forms, already-saved children remain saved, correct submitter intent |
| Security roles | Full admin, scoped HR editor, view-only user and Users-only operator; test crafted writes and child IDs outside scope |
| Employee linking | Missing/duplicate email, already-linked employee/user, concurrent requests, rollback on failure, activation/invitation retry |
| Localization | English and Arabic; missing keys, validation/JS messages, RTL tables, search, mixed codes/phone/email, print/export behavior |
| Accessibility | Keyboard tabs/dialogs, focus restoration, error focus, screen-reader labels and no trapped navigation |
| Domain safety | No hidden approval/posting, identical totals, effective dates, statuses, audit trail, private-file access and posted-document immutability |
| Browser coverage | Current supported Chrome/Edge and mobile browser behavior; explicitly record native close/back warning limitations |
| Fallback | Existing deep links and standalone screens still work; turning off new UI does not hide or corrupt saved data |

Measure improvement with comparable tasks, not only screenshots: time and navigation steps to onboard an employee with documents/pay, update pay deliberately, create an account, and resume a filtered list. Agree the target with the client after measuring the current baseline; no invented performance numbers are asserted here.

## 12. Research references and decisions for approval

Official references used for the recommendations:

1. [Laravel localization](https://laravel.com/framework/docs/13.x/localization) — locale files, request locale and fallback.
2. [Laravel validation](https://laravel.com/framework/docs/13.x/validation#xhr-requests) — JSON validation behavior.
3. [MDN beforeunload](https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeunload_event) — browser warning limitations.
4. [MDN requestSubmit](https://developer.mozilla.org/en-US/docs/Web/API/HTMLFormElement/requestSubmit) — preserving form validation/submission behavior.
5. [W3C tabs](https://www.w3.org/WAI/ARIA/apg/patterns/tabs/) and [modal dialogs](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/) — keyboard and focus behavior.
6. [W3C text direction](https://www.w3.org/International/questions/qa-html-dir) — document direction and mixed-direction content.
7. [W3C error prevention](https://www.w3.org/WAI/WCAG22/Understanding/error-prevention-legal-financial-data.html) — protection for consequential operations.
8. [OWASP authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html) — least privilege and request-level checks.

Recommended decisions to approve before implementation:

- Employee is the first workspace; old routes/screens remain during the acceptance window.
- Save & stay / next / close, with prompts only for unsaved navigation; no automatic financial commands.
- English and Arabic scope is confirmed; nominate terminology reviewers and agree report/print language expectations.
- Keep `employees.user_id` as the canonical link; approve narrow employee-search permissions, field ownership and secure account setup.
- Defer unresolved salary/leave/project/accounting policy choices to their existing decision register rather than coupling them to the UX release.

**Handoff:** This review adds only this Markdown plan. No implementation, fresh test-suite result, production migration or deployment is claimed.
