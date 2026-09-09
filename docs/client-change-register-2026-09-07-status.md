# Seera — Client change register: implementation status

Companion to [client-requirements-2026-09-07.md](client-requirements-2026-09-07.md). That document records what the client asked for (CR-01 to CR-14) from the 5 September 2026 screenshots and voice notes; this one records what was built in response, what was decided where the client left a gap, and what is deliberately left open for the client's confirmation.

Built on 7 September 2026 against commit `7cb1dd3`. Everything below is covered by `tests/Feature/ClientChangeRequestsTest.php` unless stated otherwise.

## Change-by-change checklist

| ID | Client request | Status | What was delivered |
|---|---|---|---|
| CR-01 | Create missing records from a dropdown without leaving the form | **Done** | A **+ New** button next to the dropdown opens a small dialog; the record is saved and selected in place, the rest of the form is untouched. Applied to: Project form (Client, Branch, Project Manager account), User form (Department, Designation, Branch, Role), Employee form (Department, Designation, Branch), Role form (Department), Purchase Order form (Supplier). The button only appears for users allowed to create that record. |
| CR-02 | One Add New User button | **Done** | Filter-bar duplicate removed; header button kept. |
| CR-03 | One Add New Role button | **Done** | Same as CR-02. |
| CR-04 | Sponsorship / Freelancer on the user setup screen | **Done** | Employee Classification added to the User form's Employment Information block and shown on the user detail page. It is kept in step with the linked HR employee record in both directions, so there is one answer per person. Labels stay **Sponsorship / Freelancer** (see decisions). |
| CR-05 | "All" checkbox before View, then untick exceptions | **Done** | Both the role form matrix and the full Permission Matrix have an **All** column first (indeterminate when a row is partly ticked) plus **Select all visible / Clear visible** buttons. Saving the role form no longer revokes permissions it did not display (Post, Process, Receive, etc.). |
| CR-06 | Show only the modules relevant to the department | **Done** | Choosing a department on the role form narrows the matrix to that department's modules; the full Permission Matrix defaults to the role's department group with a "Modules for: …" filter. A **Show all modules** switch is always available. Hidden rows keep their current permissions; filtering never grants or revokes anything. |
| CR-07 | Consistent role names and codes | **Done** | New roles pick a **Role Type** (Manager, Assistant, Supervisor, In-Charge, …); department + type fills the name and the code (Purchase + Assistant → `PURCHASE_ASSISTANT`). Code is generated from the name if left blank and is read-only after creation. Existing codes such as `SITE_SUPERVISOR` are unchanged. |
| CR-08 | Multiple reporting contacts and staged approvals | **Not started — needs decisions** | See "Open for the client" below. |
| CR-09 | Finish user setup in one flow, including a new role | **Done** | The User form's Primary Role has **+ New Role**: department, role type, name/code suggestion, access scope, level, and "start with permissions of …" to copy an existing role's permissions. Permissions can then be refined on the Permission Matrix. |
| CR-10 | Branches/Departments/Designations should not be separate menu entries | **Done** | The three menu items are replaced by one **Organization Structure** entry (Master Setup) showing counts and recent records with links to the full lists. Day-to-day creation happens inline (CR-01). Nothing was deleted; the full pages still exist. |
| CR-11 | More than four document rows | **Done** | **+ Add Document** adds rows without limit; unsaved rows can be removed. Saved documents are listed separately and are never touched by adding/removing new rows. Verified with five attachments including Driving License. |
| CR-12 | Upload supplier quotation on the PO | **Done** | "Supplier Quotation" section on the PO form (PDF/JPG/PNG/WEBP, up to 10 MB each, up to 10 files) plus an upload box on the PO page for quotations that arrive later. Files are stored privately and served only through an authenticated download route. Draft orders may remove a file; approved orders keep them for audit. |
| CR-13 | PO lines like the reference (description, discount, tax, total) | **Done** | Each line now has Description, Qty, Unit Price, Disc %, VAT % (blank = order default) and a live Line Total; rows can be added/removed; live Gross / Discount / Taxable / VAT / Total footer. Server recalculates everything. Goods receipts raised from the order value stock at the **discounted** unit price. Client sample verified: 1 × 28,500 at 15% = 32,775.00. |
| CR-14 | Finish, then the client re-reviews; four SOON items | **Partly** | This checklist is the return package. The four SOON entries (Projects & Site Expenses, Equipment & Vehicles, HR Reports, Project Reports) are separate phases (5 and 7 reference packages are in the repo) and remain unscheduled. |

## Decisions taken where the client did not specify

These are reasonable defaults, all reversible. Please confirm or correct.

1. **Classification labels** stay `Sponsorship` / `Freelancer` (the wording already in the system). The voice note's first label was unclear; if the client prefers "Own staff" or "Company sponsored", it is a two-line change.
2. **Which dropdowns get "+ New"**: masters a user may genuinely need mid-task (client, branch, department, designation, role, supplier, project-manager account). Status, scope and other fixed lists are not editable inline. Projects, sites and warehouses still use their own pages because they need geo-fence / project context.
3. **Department → module groups** for the permission filter live in one file, `app/Support/PermissionGroups.php` (Administration, Accounts, Human Resource, Projects, Site Operations, Purchase & Stores, Marketing). Dashboard is always shown. Adjusting a group is a list edit, no migration.
4. **Role codes** are never rewritten once created. Renaming a role changes its display name only.
5. **Quick-created user accounts** (Project Manager from the project form, or any user saved with a blank password) receive the temporary password `123456` and must set their own at first sign-in, matching the organization-chart accounts.
6. **Auto codes** for inline-created masters: customers `CUS-001…`, suppliers `SUP-001…`, branches `BR-001…`, departments a 4-letter abbreviation of the name. The full forms still let the user type a code.
7. **Quotations**: several files per PO, optional, allowed until the order is fully received or cancelled; removable only while the order is a draft.
8. **PO line VAT**: per-line rate, defaulting to the order's rate; prices excluding VAT; discount is a percentage of the line before VAT; rounding to 2 decimals per line. No multi-currency, no tax-inclusive toggle, no configurable columns (the reference showed these but the client did not ask for them).
9. **Organization Structure hub** replaces the three menu entries rather than deleting the pages, so nothing becomes unreachable.

## Open for the client (not built)

**CR-08 — multiple reporting contacts / staged approval.** The mechanic example mixes three things: who is *informed*, who *approves*, and in what *order* (Site In-Charge → Purchase Assistant → Purchase Manager). Before building, the client should confirm:

- Do both contacts approve, or does one only receive a notification?
- Is the sequence Site In-Charge → Purchase Assistant → Purchase Manager fixed, and are Assistant and Manager consecutive steps or alternatives?
- Does this apply to purchase requests only, or also to leave, overtime, stock issues and other requests?
- Is the second reporting line defined per role or per individual?

The current system has one parent role and a workflow builder that stores ordered steps but does not yet drive the approve buttons. Implementing CR-08 properly means approval instances with a decision log, notifications, and changes to every approve action, so it is scheduled as its own piece of work once the answers are in.

**CR-14 — the four SOON modules** need a scope decision (this change order or a later release).

**Bilingual English/Arabic** (raised in the previous round) is still pending a decision on translation source and right-to-left layout.

## Deployment notes

The release is additive: two new migrations (purchase-order line fields and attachments; user classification). On production:

```bash
cd ~/seera && php artisan down --retry=60
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
mkdir -p public/build && unzip -oq public/build.zip -d public/build   # only if build.zip is shipped
php artisan optimize && php artisan up
```

No seeder run is required. Quotation files are written to `storage/app/private/purchase-orders/quotations`; include `storage/app` in backups.

## Round 2 — 8 September follow-up (CR-15 to CR-18)

Source: [client-requirements-2026-09-09-addendum.md](client-requirements-2026-09-09-addendum.md) (four screenshots, five voice notes). Built 9 September 2026; covered by `tests/Feature/ClientChangeRequestsRound2Test.php`.

| ID | Client request | Status | What was delivered |
|---|---|---|---|
| CR-15 | Classification dropdown on the Project form with its own **+ New**; the company adds its own names | **Done** | New `project_classifications` list. Dropdown with **+ New** on the project form, shown on the project page, column and filter on the Projects list, maintenance page reachable from the Projects list ("Classifications"). List starts empty by design. Separate from the Sponsorship / Freelancer field on people. |
| CR-16 | Show the real location in the Map + Geo-Fence Circle panel | **Done** | Leaflet + OpenStreetMap map (no API key) on Site details: pin at the saved coordinates, geo-fence radius as a circle, view fitted to the circle. The site form has the same map as a picker: click or drag sets latitude/longitude, the radius field resizes the circle live. Sites without coordinates show a plain message. |
| CR-17 | Payment Terms with **+ New** and an agreed number of days | **Done** | New `payment_terms` list (name + days) seeded with Cash / 15 / 30 / 60 by the migration; existing suppliers mapped by their old text. Dropdown with **+ New** on the supplier form, maintenance page from the Suppliers list. A supplier bill saved without a due date falls due after the supplier's days. |
| CR-18 | Linked Payable Account as a dropdown with **+ New** | **Done** | Dropdown of the Accounts Payable control account (2100) and its sub-accounts, **+ New** creates a liability sub-account with a generated code (2101, 2102 …). Bills, payments and goods receipts post to the supplier's linked account; suppliers without one keep posting to 2100. The finance dashboard payables figure totals the whole 2100 group. Existing text values are mapped to 2100. |

Decisions taken (all reversible):

1. **Posting follows the link.** The label promises an accounting link, so it is one. The dropdown is restricted to 2100 and its children, which keeps the control total meaningful; anything else is refused on save.
2. **OpenStreetMap tiles** are used directly. Fine for a few dozen sites; if usage grows, switch the tile URL to a paid provider or Google Maps with a key (one line in `components/admin/site-map.blade.php`).
3. **Days count from the bill date** in calendar days, 0–365. Business-day terms, discounts and instalments were not requested.
4. **Legacy text columns** (`suppliers.payment_terms`, `suppliers.linked_account`) are kept and written automatically from the linked records, so older reports and the seeders keep working.

## Client review page

A static walkthrough for the client lives at `public/client-review/` and is served at
`/client-review/` on any deployment (for example `https://seera.tech-brit.co.uk/client-review/`).
Each stop pairs the client's own screenshot and voice note (copied to `media/` as
`I01…I16.jpeg` and `A01…A20.ogg`) with the updated screen rendered from the real code
(`screens/`). The frames are static: links are inert and the "+ New" dialogs use a
practice endpoint, so nothing typed there is saved. The page is `noindex`, but anyone with
the link can open it; move it behind the admin login if the client prefers.
