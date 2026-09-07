# Seera — Client change requirements

Prepared 7 September 2026 from the client files in `public/new_requirement` (filenames dated 5 September 2026).

## Review scope and how to read this document

The source package contains **36 files: 16 JPEG screenshots and 20 OGG voice notes**, approximately **8 minutes 36 seconds** of decoded speech. Every file has an individual entry in the accompanying [source review](client-requirements-2026-09-07-sources.md). The HTML edition includes expandable screenshots and playable original recordings.

The voice notes are primarily conversational Urdu with English software terminology. Local speech recognition and translation were used, followed by contextual review against every screenshot and the relevant application source. The source appendix preserves machine output separately from the reviewed English meaning. Machine transcripts are not certified verbatim transcripts; approximate timestamps and unclear wording must not be treated as exact quotations.

Source IDs **A01–A20** identify audio files; **I01–I16** identify images. IDs follow filename order, including `(1)` suffixes. Matching a screenshot with a voice note is based on its content; shared timestamps alone do not establish the order in which the client spoke or sent them.

The code comparison is against local commit **`7cb1dd3`**, inspected on 7 September 2026. This is a requirements review, not an implementation or a new production certification. Historical August audit findings are not automatically counted as current client requests.

- **Client request:** meaning supported by the voice notes, with screenshots as context.
- **Observed:** visible screenshot content or behavior confirmed by source inspection.
- **Proposed:** implementation or acceptance guidance added by this review, not a direct client instruction.
- **Open decision:** the intended outcome is understood, but a business rule or precise scope is missing.

## Consolidated change register

| ID | Requested change | Sources | Current source comparison | Scope |
|---|---|---|---|---|
| CR-01 | Create missing related records directly from dropdowns without losing the current form | A02, A03; I01, I02 | Plain selects and separate create pages | Shared interaction |
| CR-02 | Keep one Add New User button | A05; I03 | Duplicate header/filter-bar links | Small UI change |
| CR-03 | Keep one Add New Role button | A06; I05 | Duplicate header/filter-bar links | Small UI change |
| CR-04 | Show own/sponsored staff versus Freelancer classification in the relevant person-creation flow; exact first label to confirm | A04; I04 | Sponsorship / Freelancer present on Employee form; absent on User form | Resolve form/data ownership |
| CR-05 | Add Select All / clear selection controls to permission editing | A07; I06, I08 | Individual checkboxes only | UI plus permission persistence |
| CR-06 | Show relevant permission modules according to department/role | A08, A09, A11; I06–I09 | Full catalog or manual text search | Permission groups and filtering |
| CR-07 | Standardize role name/code selection to prevent spelling variations | A13; I07, I09 | Role Name and Role Code are free text | Role catalog / controlled creation |
| CR-08 | Support multiple reporting contacts and a sequence of approvers across departments | A10; I09 | One parent role; workflow definitions exist | Substantial workflow change |
| CR-09 | Complete user setup, including related role creation, in one continuous flow | A12; I03, I06–I09 | Separate User and Role pages | Consolidated setup experience |
| CR-10 | Simplify the menu and create branches/departments/designations from the working form | A15, supported by A03/A12; I10 | Separate Master Setup navigation | Navigation and quick-create integration |
| CR-11 | Add more than four employee document attachment rows | A14, contextual follow-up A17; I11 | Employee form hardcodes four rows | Dynamic attachment rows |
| CR-12 | Upload supplier quotation files on a purchase order | A18; I13 | No quotation upload in PO form/model/controller | Attachments and storage |
| CR-13 | Expand PO detail lines to include the fields shown in the reference | A19; I12, I15 | Item, quantity, price; header VAT | Commercial line editor and totals |
| CR-14 | Complete the agreed changes and return the system for another client review; resolve remaining SOON items | A20; I14, I16 | Four pictured entries still point to Coming Soon | Acceptance follow-up; module detail open |

These are **14 consolidated work items**, not 14 equally sized fixes. CR-08 and the unresolved future modules in CR-14 must not be estimated as cosmetic edits.

## Detailed requirements and acceptance guidance

### CR-01 — Create related records from dropdowns

**Client request.** When a needed client, project manager, branch or other selectable record is absent, provide an Add/Create action at that dropdown. The client gives Wafiq's `+ Create contact` interaction as the example and repeatedly asks to avoid navigating back or opening a separate working page. A03 applies the idea broadly wherever users select a record, including names, branches and places.

**Observed.** I01 shows the Project form's Client Name, Branch and Project Manager selectors. I02 shows an actual Create contact action in the reference dropdown. Current Seera master controllers return redirects to their own listing after creation.

**Proposed behavior.** Use a reusable searchable selector with `+ Create …` and an inline dialog or side panel. Save the related record, refresh the selector and select the result while retaining the parent form's draft. Cancel should return without clearing entered data. Reuse the record's validation and the logged-in user's permission to create it. A user allowed to create a project is not automatically allowed to create a user or grant roles.

**Acceptance.** Enter a partial project, create a missing customer from Client Name, and return to the same unsaved project with that customer selected and all other input retained. Repeat for Branch. Define an authorized flow for Project Manager because that selector currently points to user accounts. For dependent records such as Site, retain the selected Project and refresh dependent options.

**Open.** Confirm which selectors allow new master records. Status, access scope and other controlled enumerations should not silently become editable master catalogs. City is mentioned as a general example; there is no standalone City selector in I01.

### CR-02 — Remove duplicate Add New User action

**Client request.** A05 says the two buttons open the same form and create confusion; keep the action in one place.

**Observed.** I03 and `admin/users/index.blade.php` both show a header button and a filter-bar button.

**Proposed / acceptance.** Retain the page-header action and remove the duplicate in the filter bar. The client does not specify which location to keep. Searching/filtering/pagination must continue to work, and the surviving action must respect create permission.

### CR-03 — Remove duplicate Add New Role action

**Client request.** A06 repeats the same instruction for Add New Role.

**Proposed / acceptance.** Keep one page-header action and remove the redundant filter-bar action, consistent with CR-02. I05's totals are example data, not target counts.

### CR-04 — Employee classification in the correct setup flow

**Client request.** A04 repeats the earlier request for a classification choice distinguishing company-sponsored/own staff from freelancers. The working terminology in the existing application is **Sponsorship / Freelancer**.

**Observed.** I04 is the User form's Employment Information block. The separate Employee form already has a required `employee_classification` field with Sponsorship and Freelancer values; the Employee model/controller persist it. The User form/model/controller do not have this field. Adding it again to Employee alone would not explain the client's screenshot.

**Proposed / acceptance.** Make the classification visible in the person setup flow the client is using. If User creation is intended to create or link an Employee, persist classification on that Employee and show it consistently on reopen. Preserve the employee classification already stored.

**Open.** Decide whether every ERP user must link to an Employee, and whether external/admin accounts need classification. Choose one authoritative classification field rather than creating two independent values on User and Employee. Confirm the final display label for sponsored staff; do not automatically migrate names based on a garbled transcript.

### CR-05 — Bulk permission selection

**Client request.** A07 describes the difficulty of ticking many individual permissions. In its latter half the client explicitly asks for an **All checkbox before View**, selecting the row's View/Create/Edit/Delete/Approve/Export/Mobile actions together, then allowing individual exceptions such as Delete to be unticked.

**Required row behavior.** Add the All column before View and preserve per-cell editing. Apply this consistently to role creation/editing and the standalone permission page. **Proposed additions:** an indeterminate state for a partly selected row and a clearly labelled Select All Visible/Clear Visible action for the entire matrix.

**Observed integration issue.** The embedded role form shows seven actions; the standalone matrix uses all fifteen `Permission::ACTIONS`. `RoleController::update()` currently synchronizes exactly the submitted permission list. Filtering that form or removing its matrix without changing the save logic can revoke unseen permissions. The standalone `PermissionMatrixController` already preserves filtered-out grants.

**Acceptance.** Tick All before View in one module, untick Delete, save and reopen. Filter to one department/module group, save a change, and verify hidden grants remain unchanged. If a matrix-wide selector is added, define whether it covers visible rows or the complete catalog; **visible rows only** is the proposed default, not a client-specified rule.

### CR-06 — Contextual permission modules

**Client request.** A08/A09 ask for the permission matrix to change with department/role selection; A11 repeats this for the standalone permission screen. Accounts should show accounting-related choices, HR should show HR/payroll-related choices, and other departments should see relevant options. The client specifically considers unrelated Warehouse, Expense Categories, Suppliers and Customers entries distracting when configuring HR.

**Proposed behavior.** Define department/role-to-module groups and render the relevant group first. Preserve existing exceptions and an authorized way to inspect wider grants. Changing a display filter must not itself grant or revoke permissions. Explain the difference between a permission group and Access Scope: the latter determines which records a user can access.

**Acceptance.** Selecting Accounts produces the agreed accounting module group; selecting HR produces its agreed group on both permission screens. Saving an HR-only view does not remove grants outside that view. A department change warns about any intended permission changes and does not silently modify all users assigned to a role.

**Open.** The complete department-to-module matrix is not supplied. Confirm cross-functional permissions, common Dashboard access, Super Admin behavior, and whether the trigger is Department, Role or both. Do not infer that every employee in a department gets all actions.

### CR-07 — Standard role names and codes

**Client request.** A13 asks for dropdown/automatic role names and codes, comparable to the Department selector, to avoid inconsistent spelling and unreliable filtering. The client references the existing hierarchy and roles such as supervisor, manager and assistant.

**Observed.** I07/I09 use text inputs. `SITE_SUPERVISOR` is an existing stable code whose display name is Site In-Charge. Role codes and display names therefore cannot simply be assumed to be identical.

**Proposed behavior.** Let users select an existing role or a controlled role template, with code and name kept consistent. Provide a deliberate Create New Role path from CR-09 when appropriate. Existing role codes remain stable identifiers; new codes can be generated under a defined naming rule and uniqueness validation.

**Acceptance.** Selecting a role/template fills consistent identifiers without retyping. Searching/filtering finds the same role for every assigned user. Choosing an existing role does not create a duplicate. Creating a genuinely new role remains possible for authorized administrators.

**Open.** Confirm whether the client wants a fixed catalog, templates, or a searchable selector with Create New. Do not replace role codes across seeders and existing records merely to match displayed labels. Parent Role selection must not automatically turn the selected role into its parent.

### CR-08 — Multiple reporting contacts and approval stages

**Client request.** A10 says a person can have two reporting/approval contacts. The concrete example is a mechanic reporting a material need/problem to the Site Supervisor and Purchase Assistant/Manager. The note also describes approval progressing from Site Supervisor to Purchase Assistant and then Purchase Manager.

**Observed.** `roles.parent_id` supports one parent only. The workflow builder can save ordered approver steps, but that definition alone does not implement the requested runtime process. For example, `PurchaseRequestController::approve()` sets the whole request to approved and records one approver directly.

**Proposed design separation.** Model reporting/notification recipients independently from approval stages. A request may notify two recipients while still requiring sequential approval. Do not equate adding a second parent to granting that parent authority over every request or over financial posting.

**Illustrative flow from the note; exact routing needs confirmation:**

```text
Mechanic raises a material request
    ├─ Site Supervisor receives it
    └─ Purchase contact is informed

Proposed approval progression:
Site Supervisor → Purchase Assistant → Purchase Manager → approved
```

**Acceptance.** An agreed mechanic request reaches both appropriate contacts, shows its current stage, records each decision with actor/time, and cannot skip a required stage. Define and verify rejection/send-back and duplicate-click handling. A reporting hierarchy must not contain a self-link or cycle.

**Open.** Confirm whether multiple parents apply to roles or individual users; whether two recipients both approve or one is informed; exact stage order; whether Purchase Assistant and Purchase Manager are alternatives or consecutive stages; and which request types this covers. A10 is not a complete workflow policy.

### CR-09 — One continuous user setup flow

**Client request.** A12 asks to bring role selection/creation into User setup with a Create New action and reduce the many separate steps/windows. It references the earlier decision to attach employee documents from the employee form as the same usability principle.

**Proposed behavior.** Keep identity, department/designation, existing-role selection or new-role creation, relevant permissions, scope and save in a coherent sequence. Inline dialogs are acceptable if the original form state is retained. Make it clear whether changing permissions edits a shared role and affects other users.

**Acceptance.** Start a user, create a missing authorized role without navigating away, return with it selected, and complete the user. Preserve the input through a validation error. Linking an existing employee must not create a second employee accidentally.

**Open.** Removing every standalone administration page is not required to achieve this. Confirm which management pages remain for maintenance. User-specific permission overrides are not explicitly requested; do not introduce them implicitly when combining the screens.

### CR-10 — Consolidate master setup navigation

**Client request.** A15 says the separate Branch/Department options should not be in that location and asks to create Department, Branch or Designation directly from the User workflow. I10 supplies the menu context. The original-language second pass confirms that Designation is also named.

**Proposed behavior.** Provide inline create from Branch, Department and Designation selectors and organize less-frequent master maintenance behind a compact Master Setup area. This extends CR-01 and CR-09. Removing a menu entry must not delete existing master records or break their relationships.

**Acceptance.** An authorized user can add a branch/department during setup and continue. Existing records remain editable through an agreed maintenance route. Links no longer force unnecessary round trips.

**Open.** Confirm whether the instruction means hide individual menu entries, group them, or eliminate standalone screens. The screenshot contains Company Profile, Projects, Sites and Warehouses too; their deletion is not explicitly requested.

### CR-11 — Dynamic employee document rows

**Client request.** A14 explicitly describes filling the four rows with IQAMA, Passport, Contract and Medical Insurance and asks how to add a fifth document. A17 mentions a missing Add New option, but its exact target is not named; association with attachments is contextual.

**Observed.** The form loops exactly four times, even though available types include Driving License and Other. The controller already accepts an array of document rows.

**Proposed behavior.** Add `+ Add Document` and a remove action for new unsaved rows. Each row retains Type, Number, Issue Date, Expiry Date and File. Show field validation beside the appropriate row and preserve text/date values on errors; browsers may require file reselection. Keep existing uploaded documents distinct from new rows.

**Acceptance.** Save five or more attachments in one employee submission, including Driving License; reopen and download them through the existing authenticated document route. Adding/removing unsaved rows does not overwrite existing attachments. The existing private storage, file restrictions, scope checks and atomic write behavior remain effective.

**Open.** Any maximum number of attachments, duplicate-type rules, replacement/version history and changes to deleting saved files need separate rules; they are not specified in A14.

### CR-12 — Quotation attachments on purchase orders

**Client request.** A18 requests quotation upload on a PO, accepting an existing digital quotation or a scan of a hard copy.

**Observed.** The current PO form has no file field or multipart encoding, and neither its controller nor model stores quotation attachments.

**Proposed behavior.** Add a Quotation Attachments section with upload, file name and authorized download/view. Store ownership against the PO, use private storage, and validate allowable document/image formats and file size. Update the save transaction and failure cleanup so a failed upload cannot produce misleading success. These storage protections are implementation guidance.

**Acceptance.** Upload a PDF quotation and a scanned image on the agreed creation/edit path, save, reopen the PO and retrieve the same files. Unauthorized users cannot download them. Invalid files produce useful validation errors without leaving an inconsistent PO.

**Open.** One quotation versus several; required versus optional; whether approved POs can receive additional attachments; replacement/history; and upload limits. Do not assume attaching a file makes it a separate quotation comparison/approval module.

### CR-13 — Detailed PO line editor

**Client request.** A19 asks for the boxes/details shown in the reference to be available in purchase-order details. I12 is the current minimal editor; I15 is the richer reference.

| Field / control | Current PO | Reference / required interpretation |
|---|---|---|
| Item | Existing item dropdown | Preserve catalog selection unless the client approves a different model |
| Description | No line-description field | Editable multiline description, including equipment/service details |
| Quantity | Present | Retain quantity entry |
| Unit price | Present | Retain price entry |
| Tax rate | Header VAT copied to every line | Visible per-line tax selection, subject to agreed policy |
| Discount | Absent | Optional percentage discount shown in the reference |
| Line total | Not visible in entry rows | Display recalculated line total |
| Currency | SAR implied in ERP | Reference displays SAR selector; multi-currency support is not otherwise specified |
| Tax basis | No entry toggle | Reference displays prices excluding tax; clarify whether a toggle is wanted |
| Edit Fields | Absent | Visible reference control; configurable columns need explicit scope |
| Reorder/delete row | No visible controls in I12 | Icons appear in reference; proposed interaction, confirm exact scope |
| Add row | At least four rows rendered; no add control | Dynamic row entry is proposed for practical use, not explicitly shown in I15 |

**Proposed calculation acceptance using only the client's sample.** Quantity 1 × price SAR 28,500, no discount, 15% VAT, prices excluding tax: taxable amount SAR 28,500, VAT SAR 4,275, total SAR 32,775. This verifies the screenshot's arithmetic and is not a recommendation about tax treatment.

**Observed integration impact.** `purchase_order_lines` already has tax/totals columns but no description or discount. `PurchaseOrderController` currently derives each line's VAT from a single header rate and stores totals on the server. Adding visible columns alone will not implement the change. PO display and downstream goods receipt handling must be assessed so line tax/discount values are not lost or silently recalculated differently.

**Acceptance.** Descriptions and agreed discounts/tax choices survive save/edit/reopen; displayed totals equal the server result and PO summary. Verify decimal rounding, invalid discounts and mixed tax rates if supported. A new line must not erase other rows' data.

**Open.** Clarify catalog items versus free-text services, discount basis and tax rounding, inclusive/exclusive support, permitted currencies, configurable columns and downstream receiving behavior. The equipment-with-operator example does not by itself define a new equipment-rental subsystem.

### CR-14 — Outstanding screens and client re-review

**Client instruction.** A20 says the client has checked the rest, described the changes, and will go through the system again after completion/rectification. A16 is a progress message: more review/comments may follow when time permits; it is not a new feature request.

**Observed.** I14 shows Projects & Site Expenses and Equipment & Vehicles as SOON. I16 shows HR Reports and Project Reports as SOON. The current sidebar confirms these placeholders. Inventory Reports is already linked; Accounting has a project cost report, which is not proof that the separate Project Reports section is implemented.

**Required follow-up.** Put the four named unfinished areas on the scope decision list and return the agreed changes for client acceptance. The package does not specify their complete data model, screens, report columns or calculations. Do not treat removal of SOON badges as implementation.

**Acceptance.** The client receives a change-by-change checklist with the implemented items and agreed deferred items. Each future module has a scope/status decision before delivery claims. No payroll or end-of-service formula change is requested merely because those entries appear next to SOON modules.

## File impact map for a later implementation

Paths below are files inspected or relevant neighboring screens, not a record of edits made during this review. New components, endpoints and migrations are proposals whose final names are still to be chosen.

| Area / files | Expected change or investigation | Requirements |
|---|---|---|
| [ProjectController](../app/Http/Controllers/Admin/Master/ProjectController.php), [project form](../resources/views/admin/master/projects/_form.blade.php) | Inline client/branch/manager creation and option refresh | CR-01 |
| [CustomerController](../app/Http/Controllers/Admin/Master/CustomerController.php), [customer form](../resources/views/admin/master/customers/_form.blade.php), [BranchController](../app/Http/Controllers/Admin/Master/BranchController.php), [DepartmentController](../app/Http/Controllers/Admin/Master/DepartmentController.php), [DesignationController](../app/Http/Controllers/Admin/Master/DesignationController.php), [SiteController](../app/Http/Controllers/Admin/Master/SiteController.php) | Reusable create forms/responses and dependent master choices | CR-01, CR-09, CR-10 |
| [Users listing](../resources/views/admin/users/index.blade.php), [Roles listing](../resources/views/admin/roles/index.blade.php) | Remove duplicate create links | CR-02, CR-03 |
| [User form](../resources/views/admin/users/_form.blade.php), [UserController](../app/Http/Controllers/Admin/UserController.php), [User](../app/Models/User.php) | Coherent setup, inline role creation, employee-link/classification decision | CR-04, CR-09 |
| [Employee form](../resources/views/admin/hr/employees/_form.blade.php), [EmployeeController](../app/Http/Controllers/Admin/Hr/EmployeeController.php), [Employee](../app/Models/Employee.php) | Reuse classification; dynamic attachment rows and linked-user consistency | CR-04, CR-11 |
| [Role form](../resources/views/admin/roles/_form.blade.php), [RoleController](../app/Http/Controllers/Admin/RoleController.php), [Role](../app/Models/Role.php), [Permission](../app/Models/Permission.php) | Standard role catalog, bulk selection, relevant modules, safe permission saves | CR-05–CR-09 |
| [Permission matrix view](../resources/views/admin/roles/permission-matrix.blade.php), [PermissionMatrixController](../app/Http/Controllers/Admin/PermissionMatrixController.php) | Department/role filtering and bulk controls while preserving hidden grants | CR-05, CR-06 |
| [RoleHierarchyController](../app/Http/Controllers/Admin/RoleHierarchyController.php), [hierarchy view](../resources/views/admin/roles/hierarchy.blade.php), [tree node](../resources/views/admin/roles/_tree-node.blade.php) | Multiple reporting links cannot be represented as the current simple one-parent tree | CR-08 |
| [ApprovalWorkflowController](../app/Http/Controllers/Admin/ApprovalWorkflowController.php), [ApprovalWorkflow](../app/Models/ApprovalWorkflow.php), [ApprovalWorkflowStep](../app/Models/ApprovalWorkflowStep.php), [workflow form](../resources/views/admin/roles/_workflow-form.blade.php) | Define approver versus notification recipients, runtime stages and decision history | CR-08 |
| [PurchaseRequestController](../app/Http/Controllers/Admin/Inventory/PurchaseRequestController.php), [PurchaseRequest](../app/Models/PurchaseRequest.php) | Integrate real staged approval if the mechanic material request maps to purchase requests | CR-08 |
| [SidebarMenu](../app/Support/SidebarMenu.php), [routes](../routes/web.php) | Consolidated navigation, quick-create routes, and agreed future module routing | CR-01, CR-09, CR-10, CR-14 |
| [Dependent selector](../resources/views/components/admin/dependent-select.blade.php), [app JavaScript](../resources/js/app.js), shared components under `resources/views/components/admin/` | New reusable quick-create selector/dialog and dynamic row controls. Existing helper snapshots options at initialization, so injected options need refresh support | CR-01, CR-05, CR-11, CR-13 |
| [EmployeeDocument](../app/Models/EmployeeDocument.php), [EmployeeDocumentController](../app/Http/Controllers/Admin/Hr/EmployeeDocumentController.php) | Preserve private downloads and document types; distinguish new rows from saved documents | CR-11 |
| [PO form](../resources/views/admin/inventory/purchase-orders/_form.blade.php), [PO details](../resources/views/admin/inventory/purchase-orders/show.blade.php), [PurchaseOrderController](../app/Http/Controllers/Admin/Inventory/PurchaseOrderController.php), [PurchaseOrder](../app/Models/PurchaseOrder.php), [PurchaseOrderLine](../app/Models/PurchaseOrderLine.php) | Quotation attachments, rich lines, persisted description/discount and agreed line tax computation | CR-12, CR-13 |
| [GoodsReceiptController](../app/Http/Controllers/Admin/Inventory/GoodsReceiptController.php), [GoodsReceiptLine](../app/Models/GoodsReceiptLine.php), [PostingService](../app/Services/Accounting/PostingService.php) | Downstream assessment for PO discount/tax treatment; not a request to change unrelated accounting rules | CR-13 |
| [Permission middleware](../app/Http/Middleware/EnsureUserHasPermission.php), [scope service](../app/Services/UserAccessScopeService.php) | Integrate new endpoints/actions and contextual access without weakening existing permissions | CR-01, CR-06, CR-08, CR-12 |
| [OrganizationHierarchySeeder](../database/seeders/OrganizationHierarchySeeder.php), [ProductionSeeder](../database/seeders/ProductionSeeder.php), [DatabaseSeeder](../database/seeders/DatabaseSeeder.php) | Keep any agreed role catalog/module groups consistent across setup paths; no seeder run is required for this report | CR-06, CR-07 |
| New additive migrations under `database/migrations/` | Potential PO attachment ownership, line description/discount, reporting links and approval-instance tables. Existing user classification schema depends on CR-04 decision | CR-04, CR-08, CR-12, CR-13 |
| Relevant tests under `tests/Feature/` | Later implementation tests for draft preservation, permission saves, classification, multiple files, staged approvals and PO totals | Cross-cutting |

## Decisions to settle before implementation

| Question | Why it matters |
|---|---|
| Is classification required on User setup via a linked Employee, or only on Employee setup? What is the exact sponsored-staff label? | Avoids duplicate or inconsistent personal records |
| Which master selectors permit inline creation, and who can create each type? | Defines reusable quick-create scope and permissions |
| Which module groups belong to each department/role? Can administrators add exceptions? | Needed for contextual permission editing |
| Is role selection a fixed catalog, templates, or existing roles plus Create New? | Resolves the apparent tension between automatic codes and new-role creation |
| Do multiple reporting contacts approve, receive notifications, or both? In which order and for which document types? | Defines CR-08's runtime behavior |
| Which standalone menu entries should be hidden/grouped, and where will existing masters be maintained? | Prevents loss of access to maintenance functions |
| How many quotations per PO, required or optional, and can they be replaced after approval? | Defines attachment lifecycle and schema |
| Does the PO reference require multi-currency, inclusive tax, free-text services, configurable columns and row reordering, or only the visible business fields? | Separates a field-level enhancement from larger commercial features |
| What are the agreed discount/tax/rounding and goods-receipt valuation rules? | Needed before implementing new financial calculations |
| Are the four SOON modules part of this change order or a later release? Which reports are required? | Screenshots identify gaps but do not fully specify new modules |

## Suggested implementation order

1. Confirm the decisions above and agree a client acceptance checklist.
2. Complete duplicate-button cleanup and dynamic employee attachments.
3. Introduce a reusable quick-create pattern; apply it to project/user setup and consolidate navigation.
4. Resolve classification and role-catalog ownership, then add bulk/contextual permission editing with regression coverage.
5. Add PO quotations and the agreed rich line model, including downstream totals checks.
6. Implement and verify the approved multi-recipient/staged workflow separately from the navigation cleanup.
7. Scope the unfinished modules, deliver agreed changes, and ask the client to repeat their review as requested in A20.

Application code and client media were not changed during this requirements review. The companion source appendix is the coverage record for all 36 supplied files.
