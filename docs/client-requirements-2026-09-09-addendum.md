# Seera — New client requirements, September follow-up

Prepared 9 September 2026 from **all nine files in `public/new_requirement2`**: four screenshots and five voice notes dated 8 September 2026. This adds **CR-15 to CR-18** to the [earlier 14 requirements](client-requirements-2026-09-07.md). The combined package now contains **45 source files: 20 images and 25 voice notes**.

Read the [standalone combined HTML](client-requirements-2026-09-09.html) for both rounds, expandable screenshots, and playable recordings. The [new source appendix](client-requirements-2026-09-09-sources.md) preserves individual audio meanings and machine transcripts. For implementation of the earlier round, consult the [existing implementation status](client-change-register-2026-09-07-status.md); the original requirements document describes its historical baseline, not today's outstanding work.

## What he is saying now

The following is a faithful, cleaned **meaning**, not a claim of word-for-word transcription. The recordings are conversational Urdu with English field names. Local small-model translation and Urdu transcription were cross-checked with a larger local Urdu model and the screenshots. Unclear grammar is not converted into additional scope.

| ID | Client's meaning in plain English | Screen | Evidence |
|---|---|---|---|
| CR-15 | “The Classification option is still missing. On this Project form, add a Classification dropdown where we can add our own classifications. Adding New Client / Branch buttons did not add this field.” | Create Project | B01, B02; J01 |
| CR-16 | “Where you show the coordinates, the Map + Geo-Fence Circle area on the right should show the actual location on a map.” | Site Details | B03; J02, J03 |
| CR-17 | “Give Payment Terms a New option. We should be able to change/add the number of days according to the terms agreed with each party.” | Add Supplier | B05; J04 |
| CR-18 | “Linked Payable Account also needs a dropdown, with the options below it, and a New option.” | Add Supplier | B04; J04 |

**Important correction:** CR-15 is **Project Classification**, not the Sponsorship / Freelancer employee classification delivered for CR-04. Do not reuse those employee categories for projects. The voice notes specify no initial project classification names.

IDs B01–B05 and J01–J04 follow filename order; the `(1)` suffix does not establish speaking order. B01 and B02 are two explanations of one missing field, not two separate features. B04 concerns accounts; B05 concerns payment terms even though both filenames have the same time.

## Requirements and acceptance checks

### CR-15 — Project Classification with inline creation

**Client request:** Add a distinct Classification field to the existing Project form. It must be a dropdown whose choices can be extended by the user from that form. The existing New actions for Client, Branch and Project Manager are not a substitute. B01's opening phrase is imperfectly recognized; the rest of B01, B02 and J01 consistently identify an additional field on this form, not a separate new screen.

**Current observation:** The pictured form has Project Name, Project Code, Client, Branch, Project Manager, dates, budget, location, description and status, but no Classification. The inspected Project form, controller and model also have no project classification field. The app already has a reusable inline quick-create pattern.

**Proposed acceptance checks:**

1. An authorized project creator sees a labeled Classification dropdown on Create Project.
2. The user can select an existing classification or use **+ New** to create one without leaving or clearing the project form; the new value becomes selected.
3. Saving the project retains its classification. Editing and viewing that project show the saved value. Edit/detail visibility is proposed consistency work beyond the create screenshot.
4. A newly created classification is reusable for another project within its permitted scope.
5. Required-name validation, duplicate handling, and create permissions also apply to inline creation. Failed saves preserve the unsaved project fields.
6. Existing projects still load. A migration does not invent categories or classify old projects without an approved mapping.

**Open decisions:** Is Classification mandatory for new projects? What initial names, if any, should exist? Is the list company-wide or scoped more narrowly? Who can create/rename/deactivate it? A single selection is the natural reading of the pictured form; multiple project classifications were not requested.

### CR-16 — Show the real site location in the map panel

**Client request:** Replace the blank Map + Geo-Fence Circle placeholder on Site Details with a visible map of the saved location. The phone map image illustrates the expected geographic view.

**Current observation:** J02 shows populated coordinates, a 300 m radius and enabled geo-fence settings; the right panel still displays only an icon/text placeholder. The Site create/edit and detail views contain map placeholders although latitude, longitude and radius fields already exist. J03 is a phone mapping application, not proof of a required map vendor.

**Proposed acceptance checks:**

1. For a site with valid coordinates, Site Details displays a real map centered at those coordinates with a visible site marker.
2. Show the configured geo-fence radius as a circle and fit the view so it is useful. The client explicitly asks for the location picture/map; rendering the circle follows the named panel and existing radius field.
3. Use saved site data, not the example coordinates/radius or the viewer's current GPS location.
4. Missing/invalid coordinates and map-loading failures produce an understandable message; textual coordinates and radius remain available.
5. The map remains usable on desktop and narrow screens. Viewing it does not silently change site coordinates or attendance rules.

**Open decisions:** Map/tile provider, network availability and any provider credentials; whether a static map is sufficient or pan/zoom is expected; whether create/edit also needs a clickable location picker. A picker is a useful possible extension, but the voice note specifically points to the details panel. Phone GPS tracking, employee live tracking, navigation, search, weather and recreating the entire phone application are **not requested**.

### CR-17 — Custom supplier payment terms and number of days

**Client request:** Payment Terms must not be limited to the current fixed choices. Provide a New action so the user can add/change terms, including a number of days appropriate to the agreement with the particular supplier.

**Current observation:** J04 and the supplier form show only Cash, 15 Days, 30 Days and 60 Days. The controller/model currently accept/store a short `payment_terms` string, rather than a related payment-term record.

**Proposed acceptance checks:**

1. The supplier Payment Terms dropdown offers existing terms and **+ New** from the same working form.
2. The user can enter a descriptive label and an agreed number of days, save it, and immediately select it without losing supplier details.
3. Save/reopen the supplier and verify the selected term persists. The user can switch that supplier to a different term.
4. A custom term is reusable for another supplier. Validate a numeric, non-negative whole number of days; the allowed maximum and meaning of zero need a documented product rule.
5. Preserve existing supplier values during any data migration. Map legacy text deliberately and retain unrecognized values for review instead of silently changing agreements.

**Open decisions:** Whether “change” also means editing a shared term's definition or simply choosing/adding a term for a supplier; whether terms are reusable company-wide; whether payment days are calendar or business days; and whether invoice due dates should be calculated automatically, and from which date. The client asks to customize terms, **not explicitly to recalculate existing invoices**. Discounts, penalties, instalments and multi-stage terms are not specified.

### CR-18 — Linked Payable Account dropdown and inline New

**Client request:** Replace the plain Linked Payable Account input with a dropdown of available options and add a New action there.

**Current observation:** J04 shows a text input containing “Accounts Payable - Suppliers.” The supplier form and controller/model store `linked_account` as free text. The system already has a Chart of Accounts model and management controller. Its current create handler returns a redirect, while the existing quick-create component expects JSON `{id, label}`; inline account creation therefore needs a compatible response path, not only a new button. In the inspected posting service, supplier bills, supplier payments and goods receipts resolve the payable account using the service's fixed `PAYABLE` account; they do not use `Supplier.linked_account`.

**Proposed acceptance checks:**

1. Show an account dropdown with recognizable account code/name labels, preserving the supplier's current selection on edit.
2. An authorized user can use **+ New** to create a valid account in the same form context; after success it is selected and the remaining supplier fields remain intact.
3. Use the account creation rules and permissions, rather than inserting an arbitrary supplier-only text label or a financially incomplete account.
4. Validate that the selected account exists and is allowed for this purpose. Eligibility rules, including account type and whether parent/non-postable accounts are excluded, must be agreed.
5. Convert existing text to account references only where the mapping is unambiguous; report unresolved values and retain the existing information.
6. Test normal dropdown selection, inline creation, denied creation, validation failure, editing and save/reload.

**Important accounting decision:** Does this field merely identify an account, or must the selected account control financial postings? The label suggests a real accounting link, but the recording only describes the dropdown/New interaction. If it must drive posting, explicitly include bill, payment, goods-receipt and reversal/settlement consistency in a separate approved accounting change. Do not silently alter past journals or change the payable control account while implementing a UI-only request.

## File impact map — proposed work, not changes made

Source comparison was checked against local **`ad83f0f`** on 9 September 2026. This review does not certify the running production server or claim these four requests are implemented. Paths below identify existing owners; newly needed migrations/models/catalog screens are proposals, not files already present.

| Item | Existing owners to change or inspect | Proposed additions / checks |
|---|---|---|
| CR-15 | `app/Http/Controllers/Admin/Master/ProjectController.php`; `app/Models/Project.php`; `resources/views/admin/master/projects/` | Project-classification persistence/catalog, project relation and validation, inline create route/dialog, create/edit/detail display, permissions and regression tests |
| CR-16 | `app/Http/Controllers/Admin/Master/SiteController.php`; `app/Models/Site.php`; `resources/views/admin/master/sites/` | Reusable map display and error state, provider configuration if needed, coordinate/radius rendering tests; no need to invent a new GPS schema |
| CR-17 | `app/Http/Controllers/Admin/Master/SupplierController.php`; `app/Models/Supplier.php`; `resources/views/admin/master/suppliers/` | Payment-term catalog and day-count persistence, inline creation, compatible migration of existing strings, supplier create/edit tests |
| CR-18 | Same Supplier files; `app/Models/ChartOfAccount.php`; `app/Http/Controllers/Admin/Accounting/ChartOfAccountController.php`; `resources/views/admin/accounting/chart-of-accounts/`; `app/Services/Accounting/PostingService.php` only if accounting behavior is approved | Account relation/dropdown, permitted inline account creation with a JSON response, legacy mapping, account eligibility tests; posting/reversal tests if financial integration is in scope |
| Shared | `resources/views/components/admin/quick-create.blade.php`; `routes/web.php`; `tests/Feature/ClientChangeRequestsTest.php` | Preserve form state, permission checks, accessible dialogs and user feedback; extend existing patterns rather than duplicate them |

The precise new filenames and schema should be chosen during implementation. No requirement justifies a database reset, demo seeder, replacement of existing client data or a production deployment in this documentation task. Any future catalogs need an additive migration and explicitly approved bootstrap values; there is **no new seeder to run from this review**.

## Relationship to the earlier round and handoff

- CR-15 is an additional Project field and a correction to the scope understood for CR-01; it does not invalidate or replace the separate person-classification request CR-04.
- CR-17 and CR-18 extend the inline creation pattern to two specific Supplier fields. They were not satisfied by adding New Supplier on Purchase Orders.
- CR-16 requests an actual location display now; the existing placeholder's promise of a later mobile-phase map does not satisfy it.
- The earlier status register records most CR-01–CR-14 work as delivered, with approval routing and future-module scope still open. That is an implementation record, not a new test run or production verification performed here.
- This addendum and the evidence appendices document **18 consolidated requirements across both rounds**. They do not imply that all 18 remain unbuilt.

Suggested implementation order: settle catalog ownership and permissions; implement Project Classification and Payment Terms; implement the site map with agreed provider behavior; finalize the payable-account financial decision before changing account linkage. This ordering is engineering guidance, not a sequence specified by the client.

## Evidence coverage and handling

All five new recordings have individual reviewed meanings and all four screenshots have observations in the [source appendix](client-requirements-2026-09-09-sources.md). Raw machine text, timestamps, model identifiers and SHA-256 source hashes are retained in the [new evidence JSON](client-requirements-2026-09-09-evidence.json). The earlier 36 files have their own [source appendix](client-requirements-2026-09-07-sources.md) and [evidence JSON](client-requirements-2026-09-07-evidence.json).

Audio recognition ran locally; the client recordings were not uploaded to a transcription service. The HTML embeds original images/audio and therefore contains client information. Keep it private; do not deploy it as a public review page without approval. The source folders themselves are under `public/`, which can expose their contents if deployed unchanged; this review did not move or delete the user's files.
