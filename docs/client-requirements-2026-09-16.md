# Seera — client requirements and fixes from the September media batch

Review started 16 September 2026; completed 17 September 2026. Source: `public/seera_new`, filenames dated 14 September. **All 98 supplied files reviewed.** This is a requirements handoff, not an implementation or release certification. Open decisions must be settled before developing the affected item.

## Deliverables and coverage

- [Source-by-source review](client-requirements-2026-09-16-sources.md): every image and recording, reviewed English meaning, Roman Urdu explanation, qualifications and raw timestamped machine transcripts.
- [Complete sequence ledger](client-requirements-2026-09-16-sequence.md): 98 exact filenames, stable IDs, provisional order and requirement links.
- [Browser-ready report](client-requirements-2026-09-16.html): this handoff and full evidence, with embedded images/audio and print controls. Private: do not publish.
- [Evidence manifest](client-requirements-2026-09-16-evidence.json): hashes, duplicate relationships, interpretations and recognition passes.

There are **49 OGG voice notes, approximately 20 minutes 29 seconds, and 49 JPEG images**. No videos were found in the folder or its subfolders. Additional videos, if intended, still need to be supplied. All images were inspected individually. Each audio received a local Urdu transcription and English translation; 15 difficult/key recordings received an additional medium-model Urdu cross-check. Reviewed meanings reconcile these passes with screenshots and surrounding clips. Raw ASR is not an exact or certified transcript: it sometimes repeats words or misrecognizes technical terms.

No supplied media was uploaded, edited, renamed or removed. No application code, migrations, seeders, database records or production configuration were changed. Runtime/database tests were not performed for this documentation task.

## What the client is asking for overall

He wants the ERP to work as an integrated daily-use system: less duplicate entry, editable/renewable employee documents, meaningful leave balances, usable account/payment selectors, consistent invoice/VAT/dashboard information, and correct manager visibility. He also requests a new Marketing/Sales workflow and wants to test working backend flows while requirements are still being refined.

This batch produces **34 review items, NR-01 through NR-34**. These include new features, reported defects, questions and enhancements to existing functions—not 34 proven bugs. Repeated older requests are retained separately rather than counted again.

## Sequence and relationship to earlier requirements

Filename times span only 21:25:43–21:26:33, whereas the recordings last over 20 minutes. Those timestamps describe an exported/forwarded batch, not a reliable recording chronology. The ledger sorts filename time and uses local download time only as a provisional tie-break. Suffixes such as `(1)` do not prove which clip was spoken first. Screen/audio pairings are content-based; ambiguous ones are labelled.

For understanding, follow this topic sequence:

| Order | Audio | Topic | Associated images |
|---|---|---|---|
| 1 | C01–C02 | Repeated supplier account/payment-term requests | K01 |
| 2 | C03–C08 | Suppliers, numbering, payment types and customer ageing | K02–K04, K06 |
| 3 | C09–C11 | Office/site locations, contacts and notes | K05, K07–K08 |
| 4 | C12–C21 | Employee catalogs/codes/dates and documents | K09–K17, K20, K22, K24 |
| 5 | C22 | Employees visible from their project | K18 |
| 6 | C24 then C23 | Marketing workflow, then its example visit report | K19, K21 |
| 7 | C25–C31 | Document consistency, leave and validation | K14, K17, K23–K30 |
| 8 | C32–C37 | Reports, date presets, financial dashboard and labels | K31–K36 |
| 9 | C38–C40 | Classification editing and Project/Site duplication | K37–K40, K05 |
| 10 | C41–C46 | Accounts, payments and invoice/VAT consistency | K39–K46 |
| 11 | C47–C49 | Integrated testing, hierarchy, corrections and project access | K47–K49; C48 refers back to bills/invoices |

Baseline inspected: local commit `40d33b9`; earlier `4bbc89d` implemented CR-15–CR-18. See the [existing change register](client-change-register-2026-09-07-status.md). This does not confirm that the client's server has those commits or seed data.

- C01 is byte-identical to earlier B04: Linked Payable Account dropdown/New, prior CR-18.
- C02 is byte-identical to earlier B05: configurable supplier payment terms, prior CR-17.
- K01 repeats an earlier supplier screenshot. K07 and K08 are byte-identical within this batch.
- Keep the earlier CR-01–CR-18 register; this document supplements it. Do not reopen implemented items solely because old media was forwarded again. Prior outstanding approval-flow/future-module scope is not silently resolved here.

## Supplier and customer requirements

### NR-01 — Supplier city and project relationships

**Request:** Select supplier city/location and the project for which it works; opening a project should identify its suppliers. Sources: [C03](client-requirements-2026-09-16-sources.md#C03), K03.

**Current source:** Supplier has address/contact fields but no explicit city/project relationship in the inspected model/controller. **Acceptance:** Save/edit the agreed relationship and show linked suppliers in project details without exposing unrelated projects. **Open:** One/multiple projects per supplier? City catalog/free text? Does a link mean approved supplier, contracted supplier or actual assignment?

### NR-02 — New actions for business dropdowns

**Request:** Add New to extensible selectors, specifically Supplier Category, Nationality and document-related catalogs; review relevant business dropdowns consistently. Nationality's Other choice does not provide the requested way to add a value. Sources: C04, C12, C16; K02, K09, K13.

**Acceptance:** Authorized users add a value without losing the parent form, immediately select it and reuse it later; avoid duplicates. **Boundary:** This does not mean arbitrary new roles, workflow states or security choices. Existing department/designation/branch quick-create controls provide a pattern; identify the actual missing catalogs before coding.

### NR-03 — Automatic codes and independent employee sequences

**Request:** Establish a code pattern once and generate subsequent numbers automatically. Sponsorship and Freelancer employees need distinct prefixes and independent sequences, avoiding duplicate codes. Sources: C04, C13; K03, K10.

**Current source:** Blank supplier/customer codes already have SUP/CUS fallback generation; employee codes are manually required. Existing generation is not proof of safe concurrent allocation. **Acceptance:** Server allocates unique codes safely under simultaneous saves and preserves historical references. **Open:** Exact prefixes, initial numbers, manual override, gaps after failure/deletion, and classification changes. Do not hard-code the inconsistently recognized sample letters or silently recycle identifiers.

### NR-04 — Supplier rating

**Request:** Red, Green and Amber supplier rating. Source: C05; K03. **Acceptance:** Persist/display the selected rating in agreed screens, with accessible text as well as color. **Open:** Who may rate, each color's meaning and history requirements. No automatic scoring formula was specified.

### NR-05 — Supplier banking and allowed payment types

**Request:** Supplier bank/account or IBAN details and payment method, separate from payment terms. Extend the expense-style payment-type control to suppliers and customers. Sources: C06–C07; K03–K04.

**Distinction:** Allowed channel (Cash/Bank/Both), actual method (cash/transfer/cheque), payment terms (when due) and posting account (ledger) differ. **Acceptance:** Capture agreed banking details and allowed types; apply consistently with NR-28. **Open:** One/multiple bank accounts, exact methods, precedence between category and party restrictions. C06 does not explicitly request customer bank-detail fields.

### NR-06 — Customer rating, overdue days and alerts

**Request:** Rating and ageing beside customer balance; show days beyond the agreed payment term and raise an alert. Source: C08; K06.

**Acceptance:** Overdue days derive from a defined invoice due date/as-of date; partial settlements and multiple invoices have an agreed display. **Open:** Rating criteria/colors, due-date source, alert recipients/channel/frequency/threshold. Thirty days is an example, not a mandatory term; no seven-day alert threshold was established.

### NR-07 — Locations instead of the confusing Sites / Geo-Fence workflow

**Request:** Rename the entry point to Locations and Add Site to Add Location. Link customer/project context and keep office/site locations, including multiple locations for a customer. Sources: C09, C40; K05, K07–K08, K40.

**Acceptance:** Agreed relationships let users find customer/project locations without duplicate entry. **Open:** Confirm the Project/Location model jointly with NR-26 before schema changes. Label replacement is not an instruction to remove coordinates, geofences or attendance relationships. Preserve existing site data if later migrated.

### NR-08 — Separate office and site contacts

**Request:** Keep customer information and add distinct office/site details, including contact person, telephone and email, so visiting staff know whom to meet. Source: C11; K07–K08.

**Acceptance:** Contacts belong to and display under the appropriate location. **Open:** Multiple contacts, titles and default contact behavior. Reference to existing upper fields is not permission to delete unrelated customer fields.

### NR-09 — Shared customer notes

**Request:** Notes at the bottom of the customer record for visiting staff and Accounts to record information for colleagues. Source: C10; K07–K08.

**Acceptance proposal:** Author/time, controlled visibility and edit/history policy. **Open:** Read/edit/delete rights and whether NR-16 visit notes appear here too. “Everyone” is not authorization for public or unrestricted access.

## Employee, document and leave requirements

### NR-10 — Field-specific date rules

**Request:** Prevent inappropriate future transaction/data-entry dates; Joining Date must be today or earlier. Contract End, invoice due dates and planned supplier payments must allow future dates. Sources: C14–C15, C17; K11–K12.

**Acceptance:** Field-by-field matrix enforced server-side and reflected in pickers; valid past entries remain possible. **Open:** Exact transaction fields, timezone, backdating permissions/exceptions. No blanket future-date ban; do not hard-code “today is the 8th.” Current EmployeeController permits joining date without the requested upper bound.

### NR-11 — Document types and relevant subtypes

**Request:** Document Type plus relevant subtype/profession/class, with New where appropriate. Examples: profession on IQAMA; private/heavy/light driving-licence class. Sources: C16, C20; K13, K15–K16.

**Current source:** Fixed document-type constants, no subtype model in the inspected path. **Acceptance:** Save/reuse relevant subtype with number/issue/expiry metadata. **Open:** Metadata by type and catalog maintenance rights. Do not require licence class on every document.

### NR-12 — One document entry source and consistent expiry

**Request:** Remove the upper duplicate document-details section and retain one coherent set of fields. Dashboard and register must not disagree on expiry. Sources: C18, C25; K13–K14, K17, K24.

**Current source:** Employee-level document metadata coexists with EmployeeDocument rows; screens can read different sources. Screenshots show different stored dates, not just a proven arithmetic error. **Acceptance:** One authoritative source feeds form/view/register/expiry indicators. Explicitly reconcile conflicting historical values; do not discard data or generate blank duplicate rows.

### NR-13 — Edit and renew existing employee documents

**Request:** Edit saved attachments, change expiry/details and replace the file when renewed. Sources: C19–C20; K15–K16.

**Current source:** Register controller exposes listing/download; employee form handling adds rows rather than a complete existing-document renewal flow. **Acceptance:** Authorized updates target the correct record and appear everywhere; file access remains protected. **Open:** Renewal history, replace-versus-new-version and deletion rights. Adding another blank row is not the requested Edit feature.

### NR-14 — Document filters at the employee/dashboard entry point

**Request:** Filter by document type and Expired/Expiring Soon. Source: C21; K17, K20, K22, K24.

**Important:** The separate document register already has type/validity filters. **Acceptance:** Agreed employee/HR dashboard entry point exposes useful filters tied to NR-12's source. **Open:** Exact screen, expiring-soon window and handling multiple matching documents per employee. Do not claim these filters are absent everywhere.

### NR-15 — Assigned employees in project details

**Request:** Show assigned employee names/roles, just as warehouses appear; examples include supervisor, mechanic and driver. Source: C22; K18.

**Current source:** Project show loads warehouses/sites and manager but not the requested assigned-employee presentation. **Acceptance:** Scoped employees/roles display and update after assignment changes. **Open:** Current assignments versus history; multiple concurrent projects.

### NR-16 — Marketing/Sales module and visit reports

**Request:** Marketing alongside Operations/Finance/Inventory. A manager creates a lead; sales/marketing staff take it up, visit and record outcomes/supporting information. The example report includes client, location, visit time, person met, follow-up and remarks. Sources: C24 then C23; K19, K21.

**Current source:** No complete marketing/lead workflow identified in the inspected route/model inventory. **Acceptance proposal:** Lead creation/assignment → visit → outcome/follow-up → manager report, with permissions and customer linkage. **Open:** Roles/statuses, ownership, attachments, reminders, conversion to customer/project and report format. This is a new module, not a complete CRM specification or just a menu label. Respect deliberately obscured names in K21.

### NR-17 — Leave types and supporting attachments

**Request:** Empty Leave Type must offer usable choices, such as annual, sick and urgent/personal-work leave. Add attachments: doctor note for sick leave; tickets for annual leave. Sources: C26, C30; K23, K29.

**Current source:** Types are database-backed and a development HR seeder supplies examples; screenshots may reflect missing setup. No supporting-upload feature in the inspected request flow. **Acceptance:** Approved types exist in the target environment; attachments save/view securely. **Open:** Approved list, mandatory evidence by type, retention/access. Do not run a broad demo seeder against production to populate one selector.

### NR-18 — Entitlement and Leave Data balance

**Request:** Enter total employee leave entitlement and show it on View. Replace the profile's Leave Requests presentation with Leave Data: approved type, start/end, days, total entitlement, used and remaining balance. Sources: C27–C29; K25–K28.

**Interpretation:** C28 means total leaves in context, not date of birth, age or service length. C29's “pending” appears to mean unused balance; distinguish it from awaiting-approval requests. **Acceptance:** Approved leave changes balance exactly once; rejection/cancellation/reversal follow agreed rules. **Open:** Annual/contract entitlement, accrual, carry-forward, working/calendar days, partial days, per-type balances and pending reservations. No legal entitlement formula is specified by this media.

### NR-19 — Required fields must block incomplete saves

**Request:** A starred field was left unfilled but Save succeeded; warn and refuse incomplete submission. Source: C31; K30 gives End-of-Service form context.

**Current source:** Required validation exists with some numeric defaults of zero; the exact missing field is not identifiable. **Acceptance:** Reproduce, align stars/backend validation and give field-level errors. **Open:** Which field, and is zero valid, unintended default or missing input? Do not assume every zero amount is invalid.

## Reports and dashboard requirements

### NR-20 — Excel alongside PDF export

**Request:** Excel export. Source: C32; K31–K32. **Acceptance:** Agreed reports export the same filtered values with access scope enforced. **Open:** Reports, XLSX/CSV, columns/format, large-export limits. Balance Sheet already has Export PDF; PDF is not absent everywhere.

### NR-21 — VAT/report filters and quick date ranges

**Request:** VAT report filtering and consistent quick date selection in accounting reports for quarterly/yearly reporting and custom ranges, as in the reference. Sources: C33–C34; K31–K33.

**Acceptance:** Presets show their effective dates and match manual filtering/export. **Open:** Exact presets, financial-year start, inclusive boundaries, as-of versus period-based reports. Balance Sheet already has manual dates; this is an enhancement, not proof all date filters are missing.

### NR-22 — Separate Cash and Bank dashboard balances

**Request:** Cash and Bank/Account under separate heads. Source: C35; K35–K36. **Current source:** Dashboard adds CASH and BANK into cashBalance. **Acceptance:** Separate figures reconcile to agreed ledger accounts; optional combined total clearly labelled. **Open:** Multiple banks, account hierarchy, posted-only versus forecast display.

### NR-23 — Explain ZATCA Failed Invoices

**Request:** Explain this indicator's logic. Source: C36; K35–K36. **Classification:** Question, not a confirmed defect. **Acceptance:** Explain state counted, transitions and where the reason is inspected; distinguish draft/submission/failure/retry/clearance. Screenshots do not prove real authority submission or compliance; do not invent either.

### NR-24 — Escaped labels and ageing wording

**Request:** Fix displayed AND/ampersand coding in Profit & Expense and review receivable/payable ageing labels. Source: C37; K34–K35. **Current source:** Pre-escaped `Profit &amp; Expense Trend` passed through escaped rendering explains the visible entity. **Acceptance:** Human-readable labels without double escaping and consistent terminology. Ageing/Aging are both valid spellings; choose product style.

## Project, payment and accounting requirements

### NR-25 — Inline editing of Project Classification

**Request:** New adds another classification; selecting an existing one should permit editing its name/description. Sources: C38–C39; K37–K38.

**Current source:** Classification and separate maintenance editing already exist. This is inline edit/discoverability, not rebuilding the feature. **Acceptance:** Authorized New versus Edit, parent form preserved, selected label refreshed and duplicates prevented. Browser autofill in K38 is not proof of the intended saved-record selector.

### NR-26 — Avoid duplicate Project and Site setup

**Request:** Project/Site are the same working job for this client; avoid duplicate entry/selection and connect the Locations change. Source: C40; K40, K05.

**Decision:** Reconcile this with NR-07's multiple office/site locations. Project linked to one/more Locations with a default is a possible design, not an approved schema. **Acceptance:** No redundant user entry while preserving location/geofence/accounting references. No authorization to delete Sites or historical links is implied.

### NR-27 — Explain/select the correct Expense Account

**Request:** Why does an equipment supplier's bill show Default material expense, and where is the appropriate account maintained? Source: C41; K39, K42.

**Separate visual observation:** K44's Revenue Account dropdown shows only its default. No reviewed voice clip explicitly identifies that field; C44 says payment account and belongs to NR-28. Retain K44 as a configuration/UI check, not a falsely quoted request.

**Acceptance:** Clear account meaning, usable authorized choices and documented defaults. **Open:** Category suggestion versus control; missing accounts versus selector filtering in the actual environment. Supplier category, expense category and ledger account differ; do not retroactively recode journals from supplier labels.

### NR-28 — Usable payment choices and configured channels

**Request:** Category Cash/Bank/Both should affect payment choices, followed by actual method such as cash, transfer or cheque. Required payment-account selector is empty and blocks proceeding; guide where to create accounts or fix the missing setup/control. Sources: C43–C44; K41–K43.

**Current source:** Choices restrict to active CASH/BANK-coded accounts; actual method is not a separate implemented field in the inspected flow. Current production account seeder may address missing setup but was not executed or verified on the client server here. **Acceptance:** Valid configured choices, separate method/account, actionable guidance when setup is absent. **Open:** Mixed-category bills and precedence with NR-05 party restrictions.

### NR-29 — Payment purpose, advances and back-charges

**Request:** Record purpose—advance, operator salary paid on a supplier's behalf, repair/back-charge or similar adjustment—and reflect it in the related ledger. Source: C42; K41, K43.

**Acceptance:** Traceable purpose/references and correct approved balance/settlement behavior. **Open:** Ordinary payment versus advance/deduction/on-behalf receivable; allocation, approval, reversal and journals. A description field alone does not define correct accounting. No automatic financial treatment is approved here.

### NR-30 — Invoice/VAT/dashboard consistency and draft visibility

**Request:** An approved customer invoice is missing from output VAT/recent VAT transactions; show source/reference/details. Reconcile dashboard, draft queue, ageing, VAT and ZATCA summary. VAT should also be visible while an invoice awaits client finalization/payment in draft. Sources: C45–C46; K34–K35, K45–K46.

**Current-source lead, not reproduced cause:** `PostingService::postCustomerInvoice()` can return null when RECEIVABLE is absent, before VAT recording; approval can still set unpaid. Inspect actual account configuration, journal link, VAT entry and posting state. Local HEAD adds production-account/bootstrap support, so old screenshots do not prove the same problem remains.

**Acceptance:** Agreed counts/states and posted amounts reconcile; failures cannot silently leave approval/posting/VAT inconsistent. Investigate idempotent repair only after establishing cause. **Open:** Expected Draft count conflicts with saying the invoice is approved; define draft/issued/approved/journal-posted/unpaid/clearance separately. An explicitly labelled draft VAT forecast is a proposal requiring approval, not permission to include drafts in formal tax totals or posted ledgers. Do not hard-code the example count of two VAT exceptions.

### NR-31 — Controlled corrections after finalization

**Request:** Super Admin can correct bills/invoices and reverse finalization into a correctable workflow; Super Admin decides delegation. Source: C48; K39, K45 contextual.

**Current source:** Non-draft edit/delete intentionally blocked. **Acceptance proposal:** Authorized correction with reason/history and appropriate reversal/replacement, not silent overwrite. **Open:** Settled/part-settled documents, closed periods, inventory/payroll links, tax/clearance consequences and delegation. Do not simply remove draft guards.

## Access, hierarchy and acceptance testing

### NR-32 — Hierarchy-based activity visibility

**Request:** Super Admin sees all; Accounts Manager sees relevant Accounts staff/subordinates; Project Manager sees relevant supervisors/mechanics/project staff. Super Admin activity must not show to lower roles. Source: C47; K47.

**Current source:** Dashboard reads recent ActivityLog records globally; that model is not among scoped models. This is a source-backed concern, not a completed authorization audit. **Acceptance:** Query/API scoping, not template hiding; positive/negative user/project tests. **Open:** Reporting tree, cross-project staff, multiple managers, permitted event fields and historical access.

### NR-33 — Integrated testing while refining requirements

**Request:** Working backend flows now, so real/dummy entries reveal missing fields/integrations before the interface is considered final. Source: C47.

**Meaning:** “Backend not attached” describes the client's experience; this repository already has Laravel backend code. **Acceptance proposal:** Demonstrable end-to-end scenarios with record IDs, expected/actual effects and a shared issue log. Cover customer/project/employee assignment, document renewal, leave, bills/payments, invoice/VAT/reporting and role restrictions. No actual test execution or release certification is claimed here.

### NR-34 — Assigned manager cannot see the project

**Request:** Project exists for Super Admin but not on assigned Project Manager's dashboard/list. Source: C49; K47–K49 directly support this.

**Likely current-source cause:** Assignment saved in `Project.manager_id`, but `UserAccessScopeService` scopes by `User.project_id` and denies all projects when absent; inspected ProjectController does not synchronize these paths. Not a live database reproduction. **Acceptance:** Correct list/dashboard/detail/dependent-record visibility without access to unrelated projects, including crafted read/write IDs. **Open:** One/multiple managed projects and interaction with employee/user project membership.

## Likely implementation ownership — planning only

Inspected entry points, not an exhaustive file-change list. Trace routes, validation, policies, services, views and tests before implementation.

| Area / items | Current entry points | Additional design/testing |
|---|---|---|
| Supplier/customer NR-01–09 | Supplier/Customer; master SupplierController/CustomerController; forms; CodeGenerator | Relationships, catalogs, safe numbering, permissions, migration/backfill |
| Locations/projects NR-07/08/15/26/34 | Project/Site; master ProjectController; UserAccessScopeService; project/site screens | Domain model, assignments, geofence preservation, scoped tests |
| Employee/documents NR-10–14 | Employee/EmployeeDocument; EmployeeController/EmployeeDocumentController; forms/register | Authoritative source, renewal/subtypes, file authorization, dates |
| Leave/EOSB NR-17–19 | LeaveType/LeaveRequest; LeaveRequestController/EndOfServiceController | Safe setup, entitlement, attachments, validation reproduction |
| Marketing NR-16 | New workflow; existing Customer/Project integration | Roles, lead/visit/follow-up, report |
| Accounting NR-20–24/27–31 | AccountingDashboardController; AccountsPayableController/AccountsReceivableController; PostingService; reports/VAT | Configuration, methods, status reconciliation, exports, audit-safe correction |
| Classification NR-25 | ProjectClassificationController; maintenance; quick-create controls | Inline update rights/response contract |
| Activity NR-32 | DashboardController; ActivityLog; AppServiceProvider scopes | Hierarchy query policy and negative tests |
| Acceptance NR-33 | Feature tests and workflow services | End-to-end client scenarios, not screenshot-only signoff |

## Decisions before affected development

1. Project/Location identity, multiplicity, geofences and existing-reference migration.
2. Numbering prefixes/counters, override, gaps and classification changes.
3. Ratings, ageing due-date source and alert policy.
4. Allowed channels versus methods versus accounts; banking multiplicity/restriction precedence.
5. Document authoritative source, subtypes, renewal history, conflict resolution and filter screen.
6. Leave entitlement period/accrual/carry-forward, day counting, pending reservations and evidence rules.
7. Accounting states, draft VAT forecast versus formal totals, advances/back-charges, correction/delegation.
8. Staff hierarchy/project membership, including multiple managers/projects.
9. Marketing role chain, statuses, assignment, follow-up/report scope.
10. Exact EOSB missing field, current server version/setup, export scope, date presets and any missing videos.

## Recommended development sequence

Proposed dependency order, not original speaking order or permission to begin coding.

| Stage | Work | Exit evidence |
|---|---|---|
| 1 | Confirm decisions, current deployment/setup; reproduce reported failures safely | Agreed rules, failing records/screens, safe test environment |
| 2 | Access/project visibility NR-32/34; define model NR-07/26 | Positive/negative access tests and agreed relationship design |
| 3 | Master catalogs/codes/party fields NR-01–09/25 | Complete create/edit flows, uniqueness and permissions |
| 4 | Documents/dates/assignment/leave NR-10–15/17–19 | Preserved data, consistent expiry and approved leave-balance cases |
| 5 | Accounting setup/payments/invoice/VAT NR-27–31 | Reconciled states/balances, idempotency, audited corrections |
| 6 | Reports/dashboard NR-20–24 | Filters/exports match ledger values |
| 7 | Marketing NR-16 | Lead-to-visit-to-follow-up demonstration |
| Throughout | Integrated testing NR-33 | Client acceptance log keyed to requirements/source IDs |

## Privacy, deployment and limits

HTML embeds original private business/person media. Keep it and the evidence documents out of public distribution. Original media under `public/` may be web-accessible if deployed. This review did not move/delete media or change server rules; decide private storage/exclusion before deployment.

No deployment commands, resets or seeders ran. The earlier requested production migration/seeding guide must follow development and be based on the final implemented commit and actual server state. Test data is not permission to wipe a database. This package establishes the reviewed backlog; it does not claim release readiness.

## Document validation

Validation passed: all 98 original hashes unchanged; every source reviewed and mapped; 49 image and 49 audio embeds match their originals; 98 initial recognition passes and 15 medium cross-checks retained; all 34 requirement anchors present; unique HTML IDs and valid local/anchor links; print styling and no external rendering dependencies. The generated report was also visually checked in a local headless browser. These are document checks, not ERP runtime tests.
