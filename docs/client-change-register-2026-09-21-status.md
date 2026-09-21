# Seera — 21 September client feedback (FR-01 to FR-06, OBS-01): implementation status

Companion to [client-requirements-2026-09-21.md](client-requirements-2026-09-21.md), which records what the client asked for in the six recordings and ten screenshots of 20–21 September. This document records what was built in response, the default taken where the client left a decision open, and what still needs his answer.

Work started 21 September 2026 from commit `ce2b1ad`. Status values: **Done** (built and covered by a test), **Partly** (built with an item still open), **Decision needed**.

Test evidence: `tests/Feature/ClientFeedbackSeptember21Test.php`, plus adjusted assertions in the existing HR suites. The client's six items are all HR/payroll screens, so no accounting or marketing behaviour changes.

## Build order (as executed)

| Order | Item | Why here |
|---|---|---|
| 1 | FR-04 salary structure linkage | Highest risk: without a structure the payroll run used basic salary only and silently dropped allowances |
| 2 | FR-01 Contract Start date rule | One-line validation gap, same rule as Joining Date |
| 3 | FR-02 + FR-03 document type and preview | Same screen and same form, built together |
| 4 | FR-05 live leave days | Form behaviour plus an authoritative server recalculation |
| 5 | FR-06 visible employee code | Preview on open, generation still at save |
| 6 | OBS-01 escaped ampersands | Template cleanup alongside the HR work |

## Item-by-item status

| ID | Client request (short) | Status | What was delivered / decision taken |
|---|---|---|---|
| FR-01 | Contract Start must reject future dates | Done | `contract_start_date` now validates `before_or_equal:today` and the picker carries the same maximum, matching Joining Date. Contract End still accepts future dates, as agreed in the earlier round. The two fields stay separate: same rule, no forced equality. |
| FR-02 | "+ New" must create a reusable Document Type | Done | Document Type is now an extensible catalogue (`lookup_values` type `document_type`) seeded with the six standard names plus any type already stored. HR users add one with "+ New" without leaving the form, and it appears for every later employee and in the Documents register filter. |
| FR-03 | View / preview the attached file | Done | Saved documents show **View** (opens the file inline in a new tab) beside **Download**, on the employee detail page, in the edit form and in the Documents register. Choosing a new file shows its name and an **Open** preview link before saving, so a wrong attachment is caught before Save. Access still goes through the authenticated private-storage route. |
| FR-04 | Reuse employee pay in Salary Structures | Partly | Saving a new employee with a basic salary now creates their first salary structure automatically, effective from contract start (else joining date, else today), carrying basic and all five allowances. Employees saved earlier get the same structure the first time they are edited. Editing an employee never rewrites an existing structure: the employee page shows a notice when the structure no longer matches the profile, with a prefilled "New structure from profile" action. The payroll fallback now also uses the employee's own allowances instead of basic salary alone. **Open:** effective-date rule and whether later profile edits should supersede the structure automatically. |
| FR-05 | Total Days must appear as dates are picked | Partly | The leave form fills Total Days as soon as both dates are valid, on create and on edit, and the server recalculates the inclusive count on every save so a stale total can no longer be resubmitted. A **half-day / manual override** checkbox keeps the field editable for exceptions. **Open:** whether an override should be allowed at all, and whether weekends and public holidays should be excluded. |
| FR-06 | Employee code visible on opening Add Employee | Done | Add Employee shows the next code for the selected classification (SP-006, FL-003 …) in a read-only field, and it updates when the classification changes. The number is still assigned at Save, so two users filling the form at once cannot take the same code; the saved code is confirmed in the success message. "Enter my own code" unlocks the field for a manual code. |
| OBS-01 | `&amp;` on HR screens | Done | Breadcrumbs, section titles and panel titles across the HR screens now read "HR & Payroll", "Documents & Attachments", "Personal & Documents". |

## Decisions taken where the client left a gap

1. **Joining Date and Contract Start are not merged.** Both now reject future dates. Forcing them to be equal, or copying one into the other, would rewrite real contract data, so it was not done. If the client wants them always equal, that is a one-line change after he confirms.
2. **The first salary structure is created automatically**, not opened as a prefilled form for confirmation. The client's complaint was that the structure screen was empty after entering the pay, so an empty screen with prefilled fields would not have answered it. Later edits still need a deliberate new structure, because silently rewriting a structure would change payroll history.
3. **Effective date of that first structure:** contract start date, else joining date, else today. This keeps the structure valid for any payroll period from the day the person started.
4. **The employee code shown on open is a preview, not a reservation.** Reserving numbers would leave gaps whenever a form is abandoned, which is exactly what the client objected to in the earlier round.
5. **Leave total days stay editable** behind an override checkbox, because half-day leave has been mentioned before. Without the checkbox the server value always wins.
6. **Document preview opens in a new tab**, not a modal, so PDFs use the browser's own viewer and nothing is re-implemented.

## Still needs the client's answer

1. **FR-04 effective dates and later changes.** When an employee's salary is edited later, should the system create a new structure from that date automatically, or keep asking the HR user to do it? Historical payroll must not change either way.
2. **FR-04 for employees already in the system.** Their first structure is created when they are next edited. If he wants all of them created in one go, that is a one-command backfill, but it needs his approval because it writes salary records.
3. **FR-05 counting rule.** Are leave days calendar days (today's behaviour) or working days excluding Fridays and public holidays? And should the manual override stay?
4. **FR-02 catalogue management.** Who may add a document type, and may a type be renamed or retired once documents use it?
5. **FR-01** whether Joining Date and Contract Start must always match.

Carried over from earlier rounds and still open: NR-26 project versus location, NR-29 advance and on-behalf payment accounting, NR-06 alert channel, NR-18 leave accrual, NR-16 marketing statuses, CR-08 staged approvals.

## Deployment notes

- Migration: `2026_09_21_000001_add_document_type_lookup_values` (additive; seeds the six standard document types into `lookup_values` and backfills any custom type already stored).
- No seeder is required. No asset rebuild is required unless `resources/css` changed in this round.
- Deploy with the standard Path A in [production-deployment-guide.md](production-deployment-guide.md): `migrate --force`, then `optimize`.
