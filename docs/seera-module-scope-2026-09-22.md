# Seera — proposed module scope and English/Arabic decision

Date: 22 September 2026. Planning only; no application implementation or deployment in this review.

## 1. Language decision — latest instruction wins

Only **English and Arabic** are in scope. Urdu remains the language of some client recordings, not a required interface language. This replaces the previous three-language proposal.

Use **Laravel's built-in localization component**: `lang/en`, `lang/ar`, translation helpers such as `__()`, per-request locale middleware, English fallback, and a persisted user preference. Arabic requires RTL layout as well as translated text. No separate third-party localization package is needed for these requirements. If a named package or language-prefixed routes are subsequently required, assess that separately before changing accepted URLs. [Official Laravel localization documentation](https://laravel.com/framework/docs/13.x/localization).

This means translating menus, forms, validation, flash messages, save dialogs and the agreed report/print labels. It does not mean translating database IDs, route names, permission keys, employee names or financial values. A language dropdown by itself is not a complete multilingual implementation.

The [plan](seera-connected-workspaces-and-localization-plan-2026-09-21.md) and [interactive preview](seera-ui-transformation-preview-2026-09-22.html) have been revised to English/Arabic. The old Urdu screenshot is a historical artifact, not current scope; use the current [Arabic screenshot](seera-ui-transformation-preview-arabic.png).

## 2. Exactly which modules would change?

### Phase A — common UI foundation

| Area | Work proposed | What does not change |
|---|---|---|
| Shared admin layout/navigation | English/Arabic switch, RTL, consistent return paths | Existing URLs and authorization |
| Editable forms throughout implemented modules | Unsaved-change protection; applicable Save & stay / next / close choices | Validation and existing business transactions |
| Inline `+ New` dialogs | Preserve parent inputs, track child changes independently | Existing permissions and independently saved lookup records |

“Universal” means a coverage inventory of all editable forms, including inline forms. Reports, filters and approval/posting commands are not automatically treated as draft-edit forms. Browser refresh/close uses the native browser warning; the custom save/discard/stay dialog applies to controlled in-app navigation.

### Phase B — HR and account access, first pilot

| Module in the circled sidebar | Proposed work | Boundary |
|---|---|---|
| HR Dashboard | Improve links into the selected employee/context and translate labels | Not a new payroll calculation engine |
| Employees | One Open workspace: profile, employment, documents, pay, leave/time and account link | Reuse tested fields and server validation; keep old screens as fallback |
| Documents / IQAMA | Employee-context attachment, preview and expiry information | Keep global expiry register and authenticated private downloads |
| Salary Structures | Show employee pay and structure history together; contextual deliberate new structure | Preserve the already implemented first-structure creation and historical pay |
| Shifts | Show relevant employee assignment/context; translate and guard editable forms | Shared shift definitions remain a separate master |
| Attendance | Employee history/context and links | Bulk attendance entry remains its own screen |
| Leaves | Prefilled employee request and history | Approval queue, accepted counting and override rules remain |
| Overtime | Prefilled employee entry and history | Approval and payroll integration rules remain |
| Payroll | Employee-specific summary/drill-through and return path | Batch processing/finalization/posting remain separate deliberate actions |
| End of Service / EOSB | Employee-context settlement entry/history and return links | Calculation policy and immutable approved settlements are not redesigned here |
| Users | Search employee, fetch permitted details, set role/scope explicitly and create/link an account | No automatic permissions; keep standalone-user creation and employee history |

The circled screenshot includes Projects & Site Expenses and Equipment & Vehicles marked **SOON**. Their presence in the screenshot is not an instruction to implement entire new modules. They are excluded from this UX pilot until separately specified.

### Phase C — master data and current client feedback

| Module | Proposed work | Boundary |
|---|---|---|
| Roles / Role Hierarchy | Dual reporting-parent requirement R22-01, with approval routing clarified before implementation | Do not flatten hierarchy, grant permissions automatically or remove approval controls |
| Permission Matrix / Assign Users / Approval Workflows | Shared language/save protection and consistent context | Specialist security/approval screens remain available |
| Customers | Connected Create/Edit with Office/Site contacts and notes; separate read-only View; new Type + New, payment-channel preference and colour ratings | No inline mutation on View; do not confuse opening receivable, credit limit and live balance |
| Suppliers | Connected profile, projects and permitted payables context; incorporate September 22 rating feedback | Preserve accounting linkage and explicit payment controls |
| Projects / Locations | Contextual related staff, locations, suppliers and warehouses | No unapproved merger of Project and Location entities |
| Organization structure and small masters | Reuse existing hub and inline creation; translate and guard forms | Do not duplicate catalogues or bypass creation permissions |
| Marketing Leads / Visits | Retain and polish the existing contextual visit pattern | New statuses or conversion policies need separate approval |

### Phase D — purchasing, inventory and accounting navigation

| Module group | Proposed work | Boundary |
|---|---|---|
| Purchase Orders | R22-02: 15% default and Super Admin-only rate override, coordinated with other VAT entry paths | Confirm exceptions and scope; do not rewrite historical rates or posting |
| Purchase Requests / Goods Receipts | Improve linked progression and return paths | Approval, partial receipts and stock/accounting posting stay explicit |
| Items / Categories / Units / Warehouses | Contextual selection and authorized quick-create where approved | No stock movement simply from creating a master |
| Stock Issues / Transfers / Adjustments | Related record context, consistent save/navigation | Dispatch/receipt stages and immutable posted records remain |
| Accounts Payable / Accounts Receivable | Bill/invoice context, related payments/receipts and retained filters | Posting, allocation and accounting permissions remain separate |
| COA / Journals / GL / VAT / ZATCA / Cost Centers / Posting Rules | Language coverage and safe navigation; context links where useful | No mass conversion into one editable screen; no legal/accounting policy change |
| Reports / activity logs | Translation and drill-through/return paths | Read-only reports and audit logs are not editable workspaces |

## 3. What “single screen” means

It means **one record context**, not one giant save operation across HR, payroll and finance. For example:

1. Open an employee once.
2. Edit profile, save and move to the next section.
3. View the current salary structure; create a later structure deliberately when needed.
4. Add documents without searching for the employee again.
5. Open the account-link flow with identity already known, but still require account permissions.

Already saved child records stay saved when unsaved profile edits are discarded. Payroll, approvals and financial posting never happen implicitly through Save & next.

## 4. New media versus owner instructions

The [September 22 client requirements package](client-requirements-2026-09-22.md) records the six new audios and six images separately, with source IDs and uncertainty notes. It adds the new client-specific changes to the work scope; it does not claim they are implemented.

The two screenshots attached in the chat are separate owner context:

- **U01 — HR/EOSB sidebar circle:** points to the group of related HR operations. No new EOSB formula can be inferred from the circle.
- **U02 — Add User “search employee auto fill”:** explicitly illustrates the owner’s employee-search/prefill requirement. Full sensitive HR data should not be returned merely because a user may create accounts.

Do not merge these two chat screenshots into the twelve-file folder count, and do not assume the folder audios refer to them.

## 5. Recommended implementation order

1. Sign off the new decoded client requirements and resolve the specifically listed ambiguities.
2. Implement shared save/locale foundations with English/Arabic only.
3. Pilot Employee + Documents + Salary context, then independently gate Users account linking.
4. Deliver confirmed Roles, Customer, Supplier and PO changes in small tested releases.
5. Extend workspace patterns and translation coverage to remaining implemented modules.

Keep current screens accessible during acceptance. Re-run existing feedback tests and add role/scope, concurrency and browser checks before broad enablement. UI rollback must not delete saved records or reverse valid financial transactions.
