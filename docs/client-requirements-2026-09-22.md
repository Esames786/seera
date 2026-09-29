# Seera — 22 September client feedback decoded

Private requirements review. Baseline inspected: `9e295b7`. This is documentation, not implementation, production verification or release approval.

**September 23 implementation update:** Work has started on `feature/seera-connected-workspaces-2026-09-23`. The baseline observations below describe the source before implementation; use [the implementation status](implementation-status-2026-09-23.md) for current delivered/pending items and test evidence. The owner confirmed that **all required approvers must approve**, and that **Customer Cash/Bank/Both must be enforced on receipts, with existing customers defaulting to Both**. Approval order was not specified.

## 1. Seedha matlab: client ab kya keh raha hai?

Client keh raha hai ke kuch pehle wale points abhi incomplete hain, aur kuch cheezen galat screen par aayi hain:

1. **Role ke do reporting parents** select ho saken. Ek parent dropdown se requirement poori nahi hui.
2. Purchase/sales mein **VAT 15% automatically aaye**; ordinary roles usay change na kar saken. Override sirf Super Admin ko ho.
3. Supplier aur Customer rating mein **actual Green/Amber/Red colour** nazar aaye, sirf colour ka naam nahi.
4. **Customer Type ke saath + New** chahiye.
5. Customer par bhi **Cash / Bank / Both payment type** chahiye, jaise Supplier par diya gaya hai.
6. Customer ke **Office aur Site location/contact sections** Create/Edit mein hon.
7. **Contacts aur Shared Notes Customer View mein edit/add na hon.** View sirf read-only dashboard/report ho; data entry Customer Create/Edit par ho.

**Important correction to the previous UX proposal:** Customer View must not become an editable all-in-one screen. A connected **Customer Edit workspace** can hold the related sections; the Customer View remains read-only. Do not apply a global “remove View” rule against this newer instruction.

## 2. Scope and evidence

- Folder: `public/seera_22_09_2026`.
- Sources: **6 OGG audio + 6 JPEG images; no videos**.
- Total audio duration: **190.879 seconds**, approximately 3 minutes 11 seconds.
- Sources were inventoried and SHA-256 hashed before recognition; originals were not changed.
- The source folder is under `public/`. Deployment exposure has not been checked; keep the generated report private and review access to raw feedback before deploying. No media was relocated or published by this review.
- Local machine speech recognition is evidence, not an exact/verbatim guarantee. Reviewed interpretations below reconcile Urdu output with the screenshots and current source. The companion source review retains the raw model output, including imperfect translations.
- Filenames provide a provisional order only. Match by content, not merely by adjacent timestamps. Download times are not the original speaking sequence.
- The two chat screenshots are owner context U01/U02, not extra files in this folder. U01 circles the HR menu; U02 says employee search/autofill on Users.

See [source-by-source review](client-requirements-2026-09-22-sources.md), [evidence ledger](client-requirements-2026-09-22-evidence.json), and the [module scope](seera-module-scope-2026-09-22.md).

| Audio | Duration | Relevant image(s) | Topic |
|---|---:|---|---|
| [A01](client-requirements-2026-09-22-sources.md#a01) | 61.627 s | I01 | Multiple reporting parents / pending CR-07–CR-08 discussion |
| [A02](client-requirements-2026-09-22-sources.md#a02) | 39.526 s | I02 | VAT fixed/defaulted at 15% for ordinary roles |
| [A03](client-requirements-2026-09-22-sources.md#a03) | 16.787 s | I03 | Supplier rating must show actual colours |
| [A04](client-requirements-2026-09-22-sources.md#a04) | 26.547 s | I04 | Customer Type + New, payment type, coloured rating |
| [A05](client-requirements-2026-09-22-sources.md#a05) | 12.966 s | I05; clarified by A06/I06 | Office/site location information on Customer Create/Edit |
| [A06](client-requirements-2026-09-22-sources.md#a06) | 33.426 s | I06 | Move contact/note entry out of View; keep View read-only |

The client references older CR numbers conversationally. Those spoken numbers do not consistently match the existing repository numbering: for example, repository CR-05 is bulk permission selection, while A03 discusses rating colours. Keep the new stable R22 IDs below; do not silently relabel the earlier register.

## 3. Actionable backlog

### R22-01 — Multiple reporting parents and approval routing

**Client request — A01/I01.** The single Parent Role field is insufficient. A person/role may report to two contacts, including a purchasing-side role and a site-side role. Client says this was already requested and is still pending; ask for clarification where needed rather than treating it as finished.

**Observed.** I01 shows one Parent Role selector on Create Role. The screenshot does not demonstrate a completed approval transaction.

**Current source.** `roles.parent_id`, `Role::parent()` and `Role::descendantIds()` model a single-parent hierarchy. `ApprovalWorkflowController` can save ordered steps, but the inspected `PurchaseRequestController::approve()` directly approves the entire request with one `approved_by`. A workflow configuration screen is not proof of a runtime staged-approval engine. The earlier status register already leaves CR-08 open.

**Proposed change.** Represent additional reporting relationships separately from the primary hierarchy until the exact semantics are approved. Define notification recipients separately from required approvers. If multiple parents must affect the actual hierarchy, handle it as a graph change with cycle prevention and deduplicated traversal, not a second cosmetic dropdown.

**Acceptance.** Two permitted reporting contacts survive save/edit; duplicate/self/cyclic links are rejected; existing primary hierarchy remains compatible; access scope is not accidentally expanded; the agreed request proceeds only through its required approval stages and records an audit trail.

**Decision status, September 23.** The owner confirmed all required approvers must approve (not just be informed). Still specify roles versus individual users, parallel versus sequential configuration, request types, rejection/revision behavior, substitutions and scope rules. Existing CR-08 contains earlier examples, but a single universal sequence is not confirmed by this recording or the owner's answer.

**Ownership/files.** Roles, Role Hierarchy, Assign Users, Approval Workflows, relevant requesting modules; `app/Models/Role.php`, `app/Models/User.php`, `app/Services/UserAccessScopeService.php`, role/workflow controllers and views, `PurchaseRequestController`, relationship and approval-event migrations/tests as justified. High-risk functional work, not only UX.

### R22-02 — 15% VAT default with Super Admin-only override

**Client request — A02/I02.** Wherever the discussed purchase/sales VAT appears, automatically show 15%. Lower roles must not alter the rate; only Super Admin may change it. Client mentions a separate future 20% profit/loss-related point and says its placement will be explained later. **Do not interpret that as a 20% VAT instruction.**

**Observed.** I02 has line VAT values `0.04` and `0`. This shows the selected form state, not proof that those rates were saved or posted.

**Current source.** PO has a default VAT and per-line overrides; `_line-row.blade.php` exposes editable numeric VAT. PO, GRN, AP and AR validation accept submitted rates between 0 and 100 in inspected paths. No Super Admin-only rate restriction was found in those validation sections.

**Proposed change.** Use a shared rate policy for approved purchase/sales paths, not unrelated hardcoded values in each Blade template. Ordinary-role forms display the permitted rate; the server also rejects or otherwise explicitly handles an unauthorized changed rate. Add an explicit, audited Super Admin override. Preserve existing calculations, rounding and historical transaction snapshots.

**Acceptance.** Fresh ordinary-role purchase/sales drafts show 15%; new PO rows cannot introduce another rate; crafted requests cannot bypass restrictions; Super Admin override is audited; saved historical and approved documents are not mass-rewritten; order/receipt/invoice totals remain consistent.

**Decision needed.** Does the override change a global default or one transaction/line? How should ordinary users edit an existing authorized non-default draft? Which tax-category exceptions, if any, must be supported? Confirm these before removing the existing per-line ability. This is a client configuration request, not a legal assertion that every transaction is taxable at 15%.

**Ownership/files.** PO/GRN, AP supplier bills, AR customer invoices, their views/controllers, any applicable shared rate configuration, authorization and tests. Supplier/customer receipts select accounts and settle balances; do not add VAT to a receipt merely because the client said “everywhere”.

### R22-03 — Actual colour indicators for Supplier and Customer ratings

**Client request — A03/I03 and A04/I04.** Green/Amber/Red should be visually represented by their colours, not only written as words in a dropdown.

**Current source.** Both models have `RATINGS = ['Green', 'Amber', 'Red']`. The inspected form dropdowns render textual options. Customer detail already has coloured metric/badge presentation; the missing form experience must not be described as no rating feature at all.

**Proposed change.** A small colour swatch/traffic-light selector with a selected state; keep accessible labels/tooltips and keyboard operation. Display a neutral “Not rated” choice. Do not use colour alone as the only accessible meaning or change stored rating identifiers.

**Acceptance.** Supplier and Customer create/edit show three distinct colours; saved selection returns correctly; English/Arabic labels are readable; keyboard/screen-reader users can distinguish options; no automatic rating recalculation is introduced.

**Ownership/files.** Supplier/Customer form partials and any shared rating component. No new business rating algorithm requested.

### R22-04 — Customer Type with reusable + New

**Client request — A04/I04.** Add a New option/action for Customer Type, consistent with the inline creation used elsewhere.

**Current source.** Customer form offers only Company/Individual; `CustomerController::validated()` hardcodes `in:Company,Individual`. Changing only the dropdown would fail to support new types correctly.

**Proposed change.** Authorized reusable type lookup with inline + New and duplicate handling. Preserve Company/Individual and all existing customers. Update filtering, validation, translated labels and dependent reports together. Reuse existing catalogue patterns where appropriate; do not create two competing type stores.

**Acceptance.** Authorized user adds a type without losing the customer draft; it is selected immediately and reusable; unauthorized creation fails; existing records retain their types; create/edit/filter/report paths recognize the new value.

**Decision needed.** Who may add, rename or retire types? Is the client asking for a specific new type, or arbitrary catalogue management? Do not infer tax/accounting behavior from a custom type name.

### R22-05 — Customer payment channel: Cash / Bank / Both

**Client request — A04/I04.** Supplier has the requested payment-type choice, but Customer does not. Customer should have Cash, Bank or Both as well.

**Important wording distinction.** The speaker initially says “payment terms”, then corrects to “payment type” and lists Bank/Cash/Both. Those options establish the payment-channel request. They do **not** establish a new net-days/due-date policy. Separate customer payment terms remain an open confirmation, not silently bundled into this item.

**Current source.** Supplier has `allowed_payment_types`, an existing payment-term relationship and channel-based account filtering. Customer has no equivalent field in its inspected model/form/validation. AR receipt validation currently allows active configured cash/bank control accounts without using a customer-specific channel preference.

**Confirmed change, September 23.** Add a backward-compatible customer channel restriction and apply it to the relevant receipt selector and server validation. Keep the account selector and receipt method consistent. Do not rewrite past receipts when a preference changes. The owner approved Both for existing records.

**Acceptance.** Preference saves/reloads; permitted cash/bank options match the customer selection; forbidden crafted receipt requests fail if enforcement is approved; existing customers and historic receipts remain intact.

**Decision status.** Enforced restriction and existing-customer default Both are confirmed. Separate Payment Terms are not included: the speaker corrected that wording, and any additional terms policy still requires an explicit request.

### R22-06 — Office and Site information in Customer Create/Edit

**Client request — A05/I05, clarified by A06/I06.** Provide separate customer Office and Site location/contact areas where the customer is created or edited. Client cannot find the requested options in the edit form.

**Current source.** The data capability is partly present: `CustomerContact` records distinguish office/site and have contact/address fields; site links can be selected. The Add/Edit form instead tells users to enter contacts and notes on the customer detail page after saving. This is mainly a workflow-placement gap, not proof that all contact storage is missing.

**Proposed change.** Put Office and Site sections/tabs inside Customer Create/Edit. Preserve existing records, multiple-contact capability and authorized site links. Decide the first-save boundary: either validated parent+children in an appropriate transaction, or Save & continue retaining the customer in the same editable workspace. Never send the operator to a separate list just to select the same customer again.

**Acceptance.** New and existing customers expose both sections; inputs survive validation failure; existing contacts remain linked; sites must belong to the permitted customer/project context; no cross-customer child-ID updates; the resulting View shows saved information read-only.

**Decision needed.** Does “location” require a physical office/site address independent of a named contact, map coordinates or attachments? Existing contact forms require a name. Do not force a fake contact to store an address or add map features without confirmation. Exact tab versus section styling can be agreed in the next preview.

### R22-07 — Customer View must be read-only; Notes move to Create/Edit

**Client request — A06/I06.** The screen the client calls “report” is the Customer Details/View page. “Office & Site Contacts — Who to meet, and where” and Shared Notes input belong in customer entry/edit, not the report/view. View should be a customer dashboard with no data-entry controls.

**Observed/source fact.** I06 and `customers/show.blade.php` contain Add Contact and Add Note forms; authorized users also have removal actions. This directly explains the client's complaint. The statement does not mean every formal reporting module needs new features.

**Proposed change.** Retain a read-only Customer View for summaries, projects, contacts and notes. Move create/update/remove controls into Customer Create/Edit and preserve child routes, ownership checks, author/timestamp history and existing records. An authorized Edit navigation link may remain; no inline mutation controls belong in View.

**Acceptance.** View contains no Add Contact/Add Note/remove input controls even for an admin; summaries remain available; Create/Edit can perform permitted operations; read-only users cannot write via crafted requests; validation returns to the correct editable section.

**Conflict resolved.** This new client instruction supersedes any earlier proposal to put editable contacts/notes in Customer View or eliminate its read-only role. The owner's broader Employee workspace proposal remains a proposal; do not extrapolate the Customer rule to every module without review.

## 4. Evidence quality and unresolved items

- Cross-check the raw Urdu transcript rather than relying on machine English alone. Small-model English output for A02 incorrectly contains “50%” in one sentence even though the Urdu says 15%; A06 translation can omit most of the message. Neither should become a requirement.
- Spoken older CR labels are retained as speech evidence, not used to overwrite repository IDs.
- The 20% profit/loss point is explicitly deferred by the client. No calculation, tax or ledger change is specified yet.
- Do not claim the new media shows missing functionality everywhere: first salary structure, document previews, supplier channels and customer contact storage already exist in source.
- Do not change approved EOSB rules based on the owner's circled sidebar screenshot.

## 5. Work order and boundaries

Recommended small releases, after requirements approval:

1. Shared English/Arabic and unsaved-navigation foundations; preserve accepted UI fallback.
2. Customer Create/Edit versus View correction and coloured ratings; test existing contacts/notes and permissions.
3. Customer Type + New and customer payment preference, with approved migration/enforcement decisions.
4. Central VAT permission/default policy with all affected purchase/sales entry points tested.
5. Dual reporting and approval runtime work as a separately designed, higher-risk stream after its business decisions are signed off.
6. Continue the Employee/Users workspace pilot and broader module sequence described in the [module scope](seera-module-scope-2026-09-22.md).

These recommendations are not a promise that all changes fit one release. This review is source evidence, not deployment approval. Implementation has since started; current changes and verification are tracked separately in [the September 23 status](implementation-status-2026-09-23.md). No production deployment is claimed.
