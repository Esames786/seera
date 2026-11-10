# SEERA — client feedback review, 10 November 2026

Status: REQUIREMENTS REVIEW ONLY. No application fixes, database changes, deletions, deployment, reset, migration or seeder execution.

This is the original pre-fix review snapshot. Subsequent implementation, owner-confirmed policy and QA are recorded in [the implementation report](../../release-readiness/SEERA-CLIENT-FEEDBACK-FIXES-2026-11-10.md).

## Evidence and release context

- Local checkout verified: `feature/seera-go-live-reset-2026-11-04`, `0aed8c791e4cd214d23a916026860585983d1cca`.
- Comparison with approved `a3316be`: four changed paths — reset command, command test, and two Markdown guides. The supplied architect update says three code/test files plus two guides; actual Git diff contains **two code/test files plus two guides**. The three reported commits are present.
- Production revision/reset counts are **OWNER-REPORTED**, not independently verified against the live server in this review. The owner reports code at `0aed8c7`, 200 rows removed on 4 November, accounts/setup retained, then zero rows removed on repeat. No SQL backup was available for that reset, according to the owner. This review supplies no restore evidence.
- Source directory: `C:/Users/Dell/Downloads/seera_today`. Originals remain unchanged and outside the public web root.
- Reviewed coverage: **10 voice notes (245.065 seconds total) and 19 images**; 20 baseline ASR passes plus four targeted rereads. All 29 source hashes still match; no exact binary duplicates.
- Supplied production log: repository-root `laravel.log`; this is distinct from `storage/logs/laravel.log`.
- Source IDs, hashes, per-file interpretation and ASR evidence are indexed in [SOURCE-REVIEW.md](SOURCE-REVIEW.md).
- Audio interpretation uses local offline Urdu recognition and an independent English translation pass, cross-checked against screenshots. ASR wording is not certified verbatim. Names and uncertain individual words are not treated as requirements.
- Filename clocks are used as a transport sequence, not proof of original speaking time. `(1)` suffix files have no established within-second order. Audio/image associations below follow content, not just matching seconds.
- Screenshots and the raw log contain personal/bank/business information. Full account numbers and raw SQL values are deliberately omitted here. Do not publish the source images, raw log or private transcript package.

## What the client is saying

The numbered entries below separate the client's request, visible evidence, current source-code behavior and implementation recommendations. A recommendation is not an approved business-policy change.

### R10-01 — IBAN rejected although the client says the bank details are correct

- **Client, Roman Urdu:** “Account bank se check karke sahi dala hai, lekin invalid account/IBAN ka error aa raha hai; backend check karo.”
- **Sources:** A01, I01–I02.
- **Observed:** A validation error asks for a valid IBAN/check digits. A second image shows the entered value. Its exact bank-certified spelling cannot be established from the image alone.
- **Code fact:** `app/Support/Iban.php` normalizes spaces/hyphens/case and checks syntax plus mod-97; `EmployeeController::validated()` invokes it. This checks format/check digits, not bank ownership or an actual bank account lookup.
- **Assessment:** Reported validation problem; a false rejection is **not yet reproduced**. Do not label the validator broken or remove validation solely because the client believes the entry is valid.
- **Proposed follow-up:** Privately compare the exact text submitted with the bank document and normalization result. Improve field-specific feedback if the issue is a missing digit/copy-paste problem. Test valid, spaced and invalid inputs. Preserve existing payroll exports and masking.
- **Acceptance:** A correctly entered valid IBAN saves consistently; invalid inputs show an actionable error without exposing full account details in logs.

### R10-02 — Approved leave should reduce the displayed balance

- **Client, Roman Urdu:** “Leave approve ho gayi lekin 21 din poore aa rahe hain; leave minus karke baqi din dikhne chahiye.”
- **Sources:** A02, I03, with header context I05.
- **Observed:** I03 shows entitlement **2026**, used **0**, remaining **21**, but the saved approved leave is **21–29 August 2025**, **9 days**, type **Urgent / Personal Leave**. This is both a different year and a different category from the displayed annual balance.
- **Code fact:** `Employee::leaveBalance()` sums approved leave starting in the requested/current calendar year, restricted to type code `ANNUAL` when that lookup exists. Pending days are separate. If no `ANNUAL` lookup exists the current conditional filter instead includes all types; that edge case merits a focused later test.
- **Assessment:** Client expectation is clear; the screenshot does **not** prove a 2026 annual-leave arithmetic error. Do not silently change annual entitlement, cross-year treatment or personal-leave policy.
- **Decision needed:** Does personal/urgent leave consume annual entitlement? Which leave year should this record affect? Is carry-forward wanted? The voice note does not specify these policies.
- **Proposed follow-up:** First test a current-year approved annual request and show the selected year/type clearly. Only after policy confirmation change the affected balance rule, with historical records preserved.
- **Acceptance:** Agreed leave types and year reduce the intended balance exactly once; rejected/pending records and other years/types follow the agreed rule. Profile summary and workspace agree.

### R10-03 — Employee profile photo upload is missing

- **Client, Roman Urdu:** “Employee ki profile picture add karne ka option kahin nahi mil raha.”
- **Sources:** A03, I05, I15.
- **Code fact:** The employee header renders initials; the employee fillable fields and reviewed form have no profile-photo upload path.
- **Assessment:** Missing feature/unfinished UX, distinct from the existing employee-document uploads.
- **Proposed follow-up:** Add a real photo control inside the employee workspace, with preview, validation, replace/remove and access-controlled storage. Specify what linked User accounts display before deciding whether to share a photo or retain separate photos.
- **Acceptance:** Authorized HR can add/replace/remove a photo; it persists and renders on relevant employee views. Invalid uploads fail safely; unauthorized users cannot read private files.

### R10-04 — Add User photo button/plus does nothing

- **Client, Roman Urdu:** “Add User mein option hai lekin plus par click karne se kuch nahi hota, picture add nahi hoti.”
- **Sources:** A04, I04.
- **Code fact:** `resources/views/admin/users/_form.blade.php` contains an initials/plus avatar and `type="button"` labelled `Upload Profile Photo`, with no file input or upload wiring in the reviewed form. The form has no multipart encoding.
- **Assessment:** Confirmed nonfunctional UI control by source inspection; no fresh browser reproduction claimed.
- **Proposed follow-up:** Implement the upload end to end, including update behavior and the employee-link photo rule in R10-03. Wiring only the file chooser would not complete this request.
- **Acceptance:** Clicking the visible control opens a chooser; a valid image is previewed, saved and shown after reload. Existing employee autofill, role assignment and save actions remain intact.

### R10-05 — Permissions must support individual people, not only shared roles

- **Client, Roman Urdu:** “Do Accounts Managers ya Site Supervisors ko zaroori nahi same access dena ho. Naye/probation wale employee ka limited access ho; employee-wise permissions chahiye.”
- **Sources:** A05, I06.
- **Code fact:** The Permission Matrix edits `role_permissions`. `User::hasPermission()` takes the union of active effective roles' permissions; it does not apply a per-user permission override. Temporary role dates exist, but they do not supply individual allow/deny exceptions.
- **Assessment:** Additional authorization requirement, not a request to grant every Accounts user all modules.
- **Recommendation for later design:** Keep role defaults and add explicit **Inherit / Allow / Deny** exceptions per linked system user. Show inherited/effective permissions and a reason/audit trail. A denial must have a defined precedence across multiple roles. Keep record/project/site scope checks and privileged operations intact; do not let a generic permission exception bypass Super Admin-only VAT policy.
- **Decisions needed:** Confirm allow/deny precedence, who can manage exceptions, whether probation limitations expire automatically, and the treatment of restricted administrative roles. Employees without a login do not need login permissions.
- **Acceptance:** Restricting person A does not change person B with the same role; forged requests and direct routes enforce the same effective permissions as the UI; scope and protected business actions remain enforced.

### R10-06 — Remove old demo login accounts, retaining the client's account and Super Admin

- **Client, Roman Urdu:** “Mera user aur Super Admin chhor kar purane users delete karo; asli users main khud dobara banaunga.”
- **Sources:** A06, A07, I07–I08.
- **Code/context fact:** The 4 November reset deliberately **always retains users, role assignments and permissions**. Therefore seeing old login accounts after that reset is consistent with the documented command, not proof that its deletes failed.
- **Assessment:** Newly explicit cleanup scope differs from the earlier instruction to retain all logins. The instruction in this turn is to document first, not to perform that cleanup.
- **Required before any future cleanup:** Identify exact protected account IDs (do not infer from names/initials), inventory candidate users and their transaction/approval/audit references, distinguish newly entered real accounts, verify a restorable backup and agree deactivate versus delete. Never reuse the whole-business reset to solve a user-list problem.
- **Acceptance:** Approved demo identities are removed/deactivated as agreed, protected accounts still log in, and authored financial/HR records keep valid references. No action performed in this review.

### R10-07 — Clear remaining old entry data so real setup is unambiguous

- **Client, Roman Urdu:** “Zero se original entry karni hai; purane sites/projects aur doosri cheezen confuse kar rahi hain. Employee, role, permissions poori hierarchy se dobara set karunga.”
- **Sources:** A07, I09–I10 and I07–I08 as related context.
- **Observed:** I10 shows a retained project-type Cost Center. I09 is a sidebar crop of HR registers, not evidence of business records. Screenshots alone cannot establish which other listed records are stale versus newly created.
- **Code/context fact:** The reset retains setup including Cost Centers, lookups and roles by design. Root cause for every remaining record is not established. The owner also reports new real entry has started since the reset.
- **Proposed follow-up:** Read-only classify each remaining record as protected setup, old demo, new real entry or browser autocomplete. Use an itemized keep/remove decision, including any Cost Center linked to a deleted project. Do not wipe all settings or current client entry.
- **Acceptance:** Agreed entry lists contain only retained/new real records; setup needed by permissions/accounting still exists; browser-only history is treated separately. Cleanup is **not executed** here.

### R10-08 — Client cannot find how to submit their own leave request

- **Client, Roman Urdu:** “Apna login bana kar permissions di hain, phir bhi limited cheezen nazar aa rahi hain. Main apni leave request kahan se apply karun?”
- **Sources:** A08, I09; I06 shows the permissions-filter context.
- **Code fact:** HR leave routes require `HR` permissions; overtime, salary structures, payroll and EOSB use `Payroll`. The Accounts permission-group filter includes Payroll but not HR. Filtering the matrix only affects displayed rows; it does not itself grant the hidden modules. Sidebar links reflect effective module view permission.
- **Assessment:** Reported access/navigation issue; effective live role permissions and employee/user link have **not** been inspected. “Same as Super Admin” is the client's description, not a verified role assignment.
- **Proposed follow-up:** Inspect the linked identity, effective HR view/create permissions and scope read-only; test the existing leave route. Recommend an obvious **My Leave / Apply Leave** action with own-record enforcement, without granting HR-wide employee/salary access merely to submit personal leave. A new self-service route is a design proposal, not a confirmed existing feature.
- **Acceptance:** Authorized linked users can request their own leave and see its status; they cannot choose or access another employee by forging an ID. HR approval remains a separate authorized action.

### R10-09 — Customer and Supplier codes should auto-increment visibly like Employee codes

- **Client, Roman Urdu:** “Employee ko SP-002 khud mil jata hai. Supplier aur customer mein bhi agla code 002, 003, 004 khud aaye; har dafa main number na likhun.”
- **Sources:** A09, I11–I15; the code-autofill dropdown is particularly visible in I14.
- **Code fact:** Both controllers already generate `SUP-` / `CUS-` codes when the submitted code is blank, preserving a submitted manual value. The customer form shows a static `Auto: CUS-001` placeholder; it is not a live next-number preview. Employee Create already distinguishes a readonly preview from an optional manual-code input.
- **Assessment:** Existing server-side generation and desired visible/automatic UX are different. Do not report auto-generation as completely absent, nor assume that a screenshot of browser suggestions proves saved database contamination.
- **Recommendation:** Reuse the Employee pattern: automatic next-code preview, deliberate optional manual code, authoritative server allocation on save, refresh after Save & New, no renumbering existing records. Check duplicate/concurrent creation before release.
- **Acceptance:** Create successive Customer/Supplier records without typing codes; each receives a distinct correctly sequenced saved code. The next blank form shows an appropriate preview, not stale `001`; custom codes remain explicit and validated. Browser autocomplete must not silently submit an earlier manual code.

### R10-10 — Supplier master needs document attachments

- **Client, Roman Urdu:** “Supplier ke documents attach karne ka option nahi hai; company/paperwork saath attach karna hai.”
- **Sources:** A09 approximately 38–57 seconds, I11.
- **Assessment:** Missing master-document facility in the reviewed Supplier form/workspace. Supplier bills, GRNs and PO attachments do not replace legal/master documents belonging to a supplier.
- **Examples decoded:** CR / national-address papers and PO/application-type paperwork are suggested by the translation pass. The precise wording of the document examples is less reliable than the clear request for attachments; confirm the final document-type list before making it mandatory.
- **Recommendation:** A private Documents section in Supplier Create/Edit/Manage, with type, optional reference/issued/expiry fields where relevant, preview/download and uploader/date. Store against the Supplier, with access scoped to that Supplier. Do not copy attachments into accounting postings or make uploads a new approval/payment action.
- **Acceptance:** Authorized users can attach, review and retrieve files during initial entry and later Manage; read-only View remains read-only. Files are private and cannot be fetched through a forged supplier/document ID. Failed master save leaves no orphan upload.

### R10-11 — Customer master needs document attachments too

- **Client, Roman Urdu:** “Client ke documents ka bhi option nahi hai; jo documents hain usi customer ke saath attach hon.”
- **Sources:** A09, I12–I14.
- **Code fact:** Existing Customer Notes are text notes (`customer_id`, `user_id`, `note`); these are not a master-document upload facility. Invoice/credit-note files likewise have a different purpose.
- **Recommendation:** Apply the same private document pattern as Supplier while keeping customer ownership, existing contacts/notes, project scope, AR balances and invoice/ZATCA snapshots unchanged. Confirm document categories rather than inventing statutory requirements.
- **Acceptance:** Create/Manage can upload and review files with clear saved/unsaved state; Customer View shows only authorized records and read-only file actions. Existing contacts, notes and financial panels continue to work.

### R10-12 — Equipment details and documents must be entered together on the first screen

- **Client, Roman Urdu:** “Equipment save karne ke baad View mein ja kar upload karna padta hai. Employee ki tarah pehle page par sab cheezen saath hon; detail/document diya hai ya nahi foran pata chale.”
- **Sources:** A10, I16–I17.
- **ASR caution:** Initial medium/VAD transcription omitted the opening explanation about Equipment and View. The independent translation captured it; a targeted no-VAD reread is retained in the source ledger. Do not interpret the shortened first pass as a different module request.
- **Code fact:** Equipment Create saves the asset profile; its form has no multipart document input. Document upload and a saved-document table exist on the asset's `show` workspace. Therefore attachments exist, but are reached after initial save.
- **Recommendation:** Put initial document rows and profile details into the Equipment entry experience, with saved document status, View/Download and consistent Save actions. Keep existing assignment/fuel/maintenance panels and explicit financial posting behavior. “One page” is a navigation request; it does not authorize silently assigning, paying or posting every section.
- **Decisions needed:** Which documents, if any, must block completion? A visible completeness/checklist state can be added without making every possible paper mandatory. Confirm replace/remove rules.
- **Acceptance:** The operator can supply the agreed asset details/documents without a second screen; a failed save retains usable input and removes partial uploads. Saved documents are clearly listed; existing routes and permissions still apply.

### R10-13 — Equipment save/page returns HTTP 500

- **Sources:** I18 and later I19, plus the supplied production log. There is no separate voice note explaining the 500.
- **Observed:** Both screenshots show `/admin/equipment` with 500 Server Error. They are separate image files showing a similar state, not identical binary duplicates. A screenshot of that URL alone cannot distinguish a failed POST from a failing GET register.
- **Log-confirmed defect:** Three production inserts on **9 November 19:20:15**, **9 November 19:21:22**, **10 November 10:14:24** fail with `SQLSTATE[23000]`, MySQL 1048, **`Column 'current_meter' cannot be null`**. Log line starts: 1855, 2015, 2175. Log timezone was not independently verified.
- **Code-confirmed mismatch:** Equipment form allows blank initial meter; request validation marks it nullable; `store()` passes the validated data directly into `EquipmentAsset::create()`. The schema declares a non-null decimal with default zero. An explicit null bypasses that default and fails. Update already removes the meter from the generic profile update and handles a non-null reading separately.
- **Proposed fix, not applied:** Decide a deliberate create-time blank-meter behavior matching the existing zero-default schema, or require a clear field validation error where an initial reading is genuinely mandatory. Preserve supplied readings, forward-only meter/correction rules and all historic readings. A later fix should test blank/zero/positive/negative/no-meter input on MySQL semantics as well as the existing test database.
- **Priority:** **P1 confirmed operational defect**. Investigate whether the register GET separately fails; the supplied log only proves failed inserts and is not a diagnosis of every possible 500.
- **Acceptance:** Authorized Create with the agreed blank-meter behavior never causes 500; valid positive readings save; invalid inputs return validation errors with input retained; register/workspace remain accessible. No claim of a completed fix or passing test in this review.

## Screenshot observations that are NOT additional confirmed requests

1. I12–I14 show customer rating/payment selections, receivable fields, address and an autocomplete menu. These are context; the client did not request a change to VAT, AR, credit limits, rating semantics or payment restrictions in these clips.
2. I13 shows two Add Customer buttons (header and filter row), matching the current template. This is optional UI duplication, not an explicitly voiced defect and not a reason for a workspace redesign.
3. I16 contains registration/chassis fields while Equipment is selected. Current controller clears those fields for non-vehicle assets, and the form help says they are vehicle-only. Do not silently widen the asset model from this image alone; confirm if equipment registrations must also be retained.
4. I17 already has private document filename/View and Download links on a saved Equipment workspace. R10-12 is about entry location/completeness; it is not evidence that Equipment has no attachments at all.
5. No voice note in this batch requests changing ZATCA environment, Finance VAT rounding, GL posting rules, multi-parent approval semantics or the English/Arabic language scope.

## Supplied production log review

File inspected: repository-root `laravel.log`, **440,684 bytes**, SHA-256 `d0e5c0c44137e547fbacf6a1774db560c34ac05b530f0595363e17cc8715eeb4`.

There are **20 top-level error entries**, from 2 July through 10 November 2026. Three entries are recent Equipment null-meter failures; **17 are older historical entries**. No warning/critical entry was found by the header parser. This is the supplied copy, not a fresh tail of production.

| Log group | Count | Dates | Meaning in this review |
| --- | ---: | --- | --- |
| Missing cache table | 1 | 2 Jul | Historical migration/cache setup failure; current live status not established here |
| Index/key too long (`password_reset_tokens`, `failed_jobs`, `activity_logs`) | 4 | 2 Jul | Historical schema failures; not evidence to rerun/reset production |
| Missing Vite manifest | 2 | 2 Jul | Historical missing build package, not proof of a current asset failure |
| ProductionSeeder admin identity validation | 2 | 21 Aug | Setup refusal; no seeder should be run based on this old log |
| Invalid Seeder class invocation | 2 | 21 Aug | Historical command invocation error |
| ProductionSeeder password-length validation | 3 | 21 Aug | Historical setup refusal |
| `admin.locale.update` route missing | 2 | 23 Sep | Historical route/cache mismatch; current source contains the locale route |
| Supplier bill/GRN match index identifier too long | 1 | 26 Sep | Historical migration failure; current migration state not read from live DB |
| Equipment `current_meter` null insert | 3 | 9–10 Nov | Current reported save failure with a matching source-code defect — R10-13 |

No SQL dump, database credentials or production connection was used. No fresh live DB counts, effective permission matrix, backup/restore test or financial reconciliation was performed. The log contains no separate IBAN or leave exception; ordinary validation responses need not appear as server errors.

## Suggested work sequence — after the owner reviews these points

| Order | Work | Boundary |
| ---: | --- | --- |
| 1 | R10-13 Equipment 500 | Reproduce in an isolated fixture, correct null handling/validation, focused Equipment tests and existing reading/accounting regressions |
| 2 | R10-01 IBAN and R10-08 own-leave access | Reproduce exact validated input and effective permissions; distinguish configuration/input issues from code defects |
| 3 | R10-02 leave balance | Obtain year/type/carry-forward policy first; show correct selected-year/type context without altering historical approvals |
| 4 | R10-09 automatic visible codes | Reuse existing Employee UX and server allocator; verify Save & New and concurrent attempts |
| 5 | R10-03/R10-04 photos | One linked-identity photo design, complete upload/display lifecycle |
| 6 | R10-10/R10-11/R10-12 documents | Private document ownership and initial-entry UX; preserve read-only View and explicit accounting/approval actions |
| 7 | R10-05 individual permissions | Review inheritance/deny design and privileged-action protection before schema/runtime changes |
| Separate controlled operation | R10-06/R10-07 cleanup | Read-only candidate inventory and exact keep list first; verified backup/restore and explicit approval before deleting anything |

## Decisions still needed

- Whether personal/urgent leave consumes annual balance; relevant year/carry-forward rule.
- Identity/photo-sharing rule between Employee and linked User; who can replace/remove images.
- Individual permission exception semantics and allowed administrators; probation expiry behavior.
- Exact protected login IDs and itemized old-data candidates; deletion versus deactivation. Screenshots are not a safe deletion specification.
- Supplier/Customer document categories and any mandatory/completion criteria; Equipment required-paper rules.
- Bank-verified exact submitted IBAN, privately, if the error cannot be reproduced from a known-valid sample.

## Verification and completion boundary

- Media inventory, original hashes, two ASR passes per audio, targeted rereads, all image review, log parsing and targeted source inspection are the evidence for this report.
- Automated Laravel/JS suites and authenticated browser reproduction: **NOT RUN**. This is requirements decoding, not an implementation QA claim.
- Existing local untracked documents were preserved. No feature code changed, no commit/push, no database reset/migration/seeder, no production deployment and no ZATCA activation.
- Updated status: the earlier core QA is historical evidence. This new feedback adds a confirmed Equipment save defect and additional client requests. **Do not describe this feedback batch as already fixed or client acceptance as complete.**
