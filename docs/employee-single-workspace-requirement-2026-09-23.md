# Employee first: genuine single-workspace requirement

Owner clarification, September 23: tabs and return links alone are NOT completion. Employee-related entry, editing and saving must happen in the employee workspace without navigating to another form page. Apply this pattern to other modules only after Employee is verified. Work stays on `feature/seera-connected-workspaces-2026-09-23`; no production deployment is authorized by this document.

## Required experience

Open Employees -> one employee -> choose a section -> enter/change data -> save in that section. Keep the selected employee fixed. Preserve unsaved profile and other-section input; show validation/network errors in place. Do not implement this as an iframe or a redirect disguised as a tab.

| Section | Same-workspace behavior | Protected boundary |
|---|---|---|
| Personal, employment, documents, access | Preserve accepted fields/uploads and existing transaction; same employee after save | Failed saves must not advance or discard uploads |
| Salary structures | New effective-dated structure, allowances/additional items and history | Do not rewrite historical structures or past payroll |
| Attendance | Create/edit own employee's entries and history | Date duplication, scope and permission checks remain |
| Leave | Create/edit pending request, attachment, calculated/override days, history and explicit approve/reject | No approval via ordinary Save; no attachment loss on validation |
| Overtime | Create/edit pending claim, own attendance linkage, history and explicit approval | Server computes amount; no cross-employee attendance link |
| Shifts | Assign/edit employee's effective-dated shift and history | No overlapping active assignments; global shift definitions remain shared setup |
| End of service | Create/edit draft and explicit approval/history | Keep existing calculator; approved settlement remains read-only |
| Payroll history | Employee-specific period/pay detail | Batch processing/posting is not an employee profile save |
| System account | Prefilled creation/linking in employee context; explicit role/security settings | No implicit privileges, no duplicate account, no bank/payroll copying |

This is one workspace with independent, clearly labelled section saves, not one transaction that silently approves leave, creates accounts and posts payroll together. A saved child record stays saved when a different unsaved section is discarded.

## Users screen — required in addition

Keep `Create account from employee` search/select on Users -> Add User. Selecting an eligible employee fills permitted identity/employment details and sets an explicit link. Role, password and security capabilities remain explicit choices. Account creation and linking are atomic. Already-linked, inactive, inaccessible employees cannot be linked again. This is already implemented in the first checkpoint and must be regression-tested, not treated as forgotten or reimplemented as a second source of truth.

## Sidebar and compatibility

Employees becomes the everyday individual-record entry point. Group the separate Documents/Shifts/Attendance/Leave/Overtime/Salary/Payroll/EOSB registers under a collapsed `HR Registers & Approvals` group. Keep existing routes for team-wide work, read-only users, approvals, old links and rollback. Do not delete tables/controllers or remove permissions to make the sidebar look smaller. Employee rows should use one Open action rather than duplicate View/Edit actions for editors.

## Implementation contract

- Reuse existing controller validation/calculations/file handling, with an explicit per-module permission gate at the workspace endpoint. HR access alone never grants payroll/account privileges.
- Bind every mutation to the employee in the route; reject foreign employee/record IDs on the server.
- Related forms use unique DOM IDs, independent CSRF-protected requests, bounded paginated history and inline validation.
- Switching sections preserves drafts. Saving one section resets only its dirty baseline. Never clear another form's unsaved state.
- Leave/OT approval/rejection and EOSB approval require their separate permissions. Payroll stays read-only in the individual workspace.
- Retain existing English/Arabic localization patterns; Urdu source audio is not an interface language.
- Employee workspace tests, accepted-feedback tests, authorization tests and real-script browser checks must pass before completion is claimed. Overall production rollout still requires staging UAT.

## Completion checklist

- [x] Same-page salary/attendance/leave/overtime/shift/EOSB entry and permitted editing.
- [x] Inline errors and uploads; employee/record ownership and negative permission tests.
- [x] Independent drafts survive panel switching and another panel's save.
- [x] Payroll history and employee account creation available in place.
- [x] Users search/select/autofill regression verified.
- [x] Sidebar simplified without removing registers/routes.
- [x] Complete regression results and previous rollback checkpoint recorded below.
- [ ] Real authenticated staging UAT and client acceptance; production deployment is not performed.

## Delivered behavior: before versus after

| Before this change | After this change |
|---|---|
| Employee list offered View and Edit separately | One Open action goes directly to the workspace for editors; view-only users retain a read-only page |
| Eight HR registers were visible within Operations | They are under the collapsible HR Registers & Approvals group; Employees remains the normal starting point |
| Salary creation opened another page, even from the employee | Salary tab contains effective-dated creation, additional allowance/deduction lines and saved history |
| Attendance, leave, overtime, shifts and EOSB required separate register forms | Their permitted individual-record forms and actions are inside the selected employee workspace |
| Opening another form meant losing employee context or selecting the employee again | Employee is fixed by the workspace route and rechecked on every request |
| Employee account setup required moving to Users | System Account tab supports prefilled account creation and editing; standalone Users search/select remains available too |
| A child form save could navigate away from other unfinished work | Related sections save through independent requests and retain other sections' drafts |

### Save behavior, precisely

- The five accepted profile sections still share their original multipart employee form. **Save & stay** / **Save & next** post back to the same employee workspace, after server validation. **Save & close** is the explicit exit action. This preserves the accepted document-renewal and first-salary-structure transaction.
- Related panels use independent in-place AJAX saves. A successful save refreshes only that panel. Validation or network errors keep its inputs and selected attachment. If saving succeeds but refreshing its history fails, submission is hidden and Retry loading is offered, to avoid accidentally creating a second record.
- Profile saves perform a page reload; if related panels contain drafts, the shared guard asks before leaving that browser state. Save those panels first or explicitly discard them. This is **not** an automatic Save All transaction. Native profile form server-validation redirects retain text via Laravel old input, but browsers require file reselection; the stronger no-reselection guarantee applies to the new AJAX related forms.
- Changing tabs does not save or discard anything. Editing a different history row, changing a history page or leaving the page is guarded if the affected form has changes.
- A saved account link is not subsequently cleared by a stale profile form. Account management is in the System Account tab; the existing employee profile no longer posts a stale `user_id` selector.
- Ordinary save never approves a leave/overtime request, approves EOSB, or processes/posts payroll. These remain explicitly permission-checked actions. Existing approved history is not made editable through this workspace.

### Verification scope

The new focused PHP tests exercise all eight panels, validation, fixed-employee/foreign-record checks, immutable salary/EOSB history, attachment preservation on failed related updates, leave-day calculation, overtime calculation, shift overlaps, account linking, and restricted-role denial. A test initially reused a cached company-scope user after changing its role to site scope; the test now authenticates a fresh instance, matching a new HTTP request, before asserting the scope denial.

Browser fixtures run the actual application scripts with local mocked responses, not production: 33 existing foundation checks plus 20 related-workspace checks passed. They verify independent drafts, selected files, inline errors, failed requests, save/refresh separation, unique IDs and no nested forms. They are not a substitute for real authenticated browser UAT, actual upload storage checks on staging, MySQL concurrency tests, or human Arabic review.

Final verification recorded September 24: **204/204 PHPUnit tests passed, 1,885 assertions, exit 0** (186,792 ms). Application code stayed unchanged throughout that complete run. Tests used SQLite `:memory:`, not the local application or production database. Both browser fixtures passed (53 checks total), as did the Vite production build, Pint and `git diff --check`.

### Deployment and rollback boundary

This employee-only increment introduces no migration or seeder. It uses existing HR, payroll and user tables. Do not run `migrate:fresh` or seed demo records. The earlier branch-wide customer migration remains a separate deployment concern documented in the implementation status file. Production was not changed. Roll back this increment with the previous feature-branch checkpoint (`cd6699e`) or revert its implementation commit and rebuild assets; preserve all legitimate records created in the meantime.

## Later modules (not claimed complete here)

Customers, Suppliers, Projects, purchasing and inventory should adopt the same record-context pattern after Employee acceptance. Preserve the client's exception that Customer View is read-only; entry belongs to Customer Create/Edit. Finance posting, stock dispatch/receipt and batch approval actions must stay explicit even when shown in a shared workspace.
