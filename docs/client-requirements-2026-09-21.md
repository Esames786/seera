# Seera — client feedback after the September implementation

Reviewed: **21 September 2026**. Source folder: `public/seera_21_9_2026`. Local code baseline: `ce2b1ad`. Scope: decode all supplied media, compare with prior requirements/current source, and document the next changes. **No implementation, database updates, migrations, seeders or deployment were performed.**

## Read this package

- [Every file explained, with transcripts and original media](client-requirements-2026-09-21-sources.md)
- [Complete 16-file sequence](client-requirements-2026-09-21-sequence.md)
- [Private browser report with embedded images/audio](client-requirements-2026-09-21.html)
- [Evidence manifest and source hashes](client-requirements-2026-09-21-evidence.json)
- Previous baseline: [September requirements NR-01–NR-34](client-requirements-2026-09-16.md) and [Claude's implementation register](client-change-register-2026-09-16-status.md)

**Coverage:** 6 OGG recordings (149.759 seconds, approximately 2 minutes 30 seconds), 10 JPEG screenshots, no videos or other documents in this folder. All 16 files are individually accounted for. No byte-identical duplicates were found within this batch or against the three earlier media folders. Repeated *requirements* are therefore distinguished from repeated *files*.

Audio is Pakistani Urdu mixed with English application terms. Local Urdu recognition and English translation assist interpretation; reviewed English/Roman Urdu meanings are the working requirements, not certified verbatim quotations. Raw passes are retained separately, including their mistakes. In particular, the first English pass reverses the date restriction by dropping **not**, and misses much of the document-preview and salary explanation. The Urdu passes and screenshots resolve those errors. No client media was uploaded to an external transcription service.

## What he is saying now

The client acknowledges the changed screens by using them, but expects several features to be connected or immediately visible rather than only available after Save or in another screen:

1. Restrict **Contract Start** to today/past just like Joining Date; Contract End is a different field.
2. Let him create a named **Document Type**, not merely select Other or add another attachment row.
3. Give him **View/preview** access to the attached file so he can confirm it is correct.
4. Reuse the **salary and allowances already entered on the employee** in Salary Structures; do not make him enter them twice.
5. Show **leave Total Days immediately** when dates are selected.
6. Show the **automatic employee code on the new-employee form**, without requiring typing.

These are **six client feedback items**, FR-01–FR-06, plus **one separate visual observation**, OBS-01. They do not imply that the previous 34 requirements were all unimplemented or that all six are new backend defects.

## Sequence and interpretation

| Reading order | Sources | Topic |
|---|---|---|
| 1 | L02, L01, D01 | Compare Joining Date and Contract Start pickers |
| 2 | L03, D03 | Add a custom Document Type |
| 3 | L03, L04, D02 | View the selected/saved document |
| 4 | L07, L05, L06, L09, D04 | Employee pay exists, but Salary Structures is empty |
| 5 | L08, D06 | Leave days should calculate on-screen immediately |
| 6 | L10, D05 | Employee code should be visible automatically |

IDs follow deterministic filename sorting within each media kind, **not original speaking order**. Thus D03 is read before D02, and D06 before D05, according to content and provisional download sequence. The ledger preserves exact filenames and timestamp order. The midnight filenames span only a few seconds despite containing over two minutes of audio; do not treat them as real recording durations or proof of original chronology. One recording and two images are dated 20 September; the remainder 21 September.

## Comparison with work already delivered

The current repository contains Claude's stages A–F (`c7c4303`, `2a7b4a1`, `cde7028`, `6bc7147`) and follow-up documentation commits through `ce2b1ad`. The earlier status register reports 163 passing tests at `6bc7147`; this is historical evidence, **not a test run performed for this review**. The client's deployed commit was not verified.

| Item | New or repeated? | Earlier relation | Current-source finding |
|---|---|---|---|
| FR-01 | Repeated date rule, now explicitly applied to Contract Start | NR-10 | Joining restriction exists; Contract Start lacks it |
| FR-02 | Repeated scope not fully covered by earlier catalog work | NR-02, NR-11; CR-01 | New exists for some catalogs; Document Type still uses six fixed choices |
| FR-03 | Newly explicit preview requirement in this batch; client says he asked before | NR-13 / CR-11 document workflow | Saved downloads exist elsewhere; employee details only says Uploaded; no new-file preview |
| FR-04 | Newly concrete integration complaint | NR-33 general integrated testing | Employee defaults and salary structures remain separate; promised prefill is absent in the inspected path |
| FR-05 | New live-form behavior clarification | NR-18 leave workflow | Server calculates on Save when blank; form has no live calculation |
| FR-06 | Repeated auto-code request with stronger visible-on-open expectation | NR-03 | Blank-code generation exists on submission, but not a displayed next-code preview |
| OBS-01 | Screenshot-only continuation of escaped-label issue | NR-24, now HR screens | HR breadcrumbs and Documents title still display `&amp;` |

Do not mark earlier features wholly absent: document consolidation/renewal, subtype text, dynamic rows, leave types/attachments, annual entitlement, classification code prefixes and Joining Date restrictions are visible or present in current source. This document adds focused follow-ups without rewriting the earlier status register.

## Detailed feedback and acceptance guidance

<a id="FR-01"></a>

### FR-01 — Contract Start must reject future dates

**Sources:** [D01](client-requirements-2026-09-21-sources.md#D01), [L01](client-requirements-2026-09-21-sources.md#L01), [L02](client-requirements-2026-09-21-sources.md#L02). **Type:** Repeated request / incomplete field coverage. **Priority proposal:** Medium.

**Reviewed meaning:** “Joining aur contract start dono future mein nahin hone chahiye; past date ho sakti hai. Joining par restriction hai, contract start par nahin.” He also describes the dates as the same in his workflow.

**Evidence and current behavior:** L02 disables future dates in Joining Date; L01 leaves future dates selectable in Contract Start. EmployeeController validates `joining_date` with `before_or_equal:today` but `contract_start_date` only as a nullable date. The form similarly gives Joining Date a maximum but none to Contract Start. This is a code-backed gap, not just a screenshot inference.

**Requested outcome:** Apply the no-future rule to Contract Start in both picker and server validation. Continue allowing valid past dates.

**Acceptance checks:** Today and yesterday accepted; tomorrow rejected even in a manually crafted request; edit/create agree; a valid future Contract End remains accepted. Use the agreed business timezone.

**Open decision:** Does “same” require Joining Date and Contract Start to always match, or only follow the same validation? Auto-copying or forcing equality must be confirmed. Do not merge fields or retroactively rewrite dates. Earlier C15 explicitly allowed future Contract End; this feedback does not reverse that.

**Ownership:** [EmployeeController](../app/Http/Controllers/Admin/Hr/EmployeeController.php), [employee form](../resources/views/admin/hr/employees/_form.blade.php), date-validation tests in [round-three tests](../tests/Feature/ClientChangeRequestsRound3Test.php).

<a id="FR-02"></a>

### FR-02 — New must create a reusable Document Type

**Sources:** [D03](client-requirements-2026-09-21-sources.md#D03), [L03](client-requirements-2026-09-21-sources.md#L03). **Type:** Repeated request / partial earlier delivery. **Priority proposal:** Medium.

**Reviewed meaning:** “Document Type ke saath New chahiye. Aap ne chhe naam diye hain; Muqeem paper ya koi aur document ka naam hum khud bana saken.” Muqeem is the likely term in the example; the requirement does not depend on exact spelling.

**Evidence and current behavior:** The six choices remain IQAMA, Passport, Contract, Medical Insurance, Driving License and Other. `_document-row.blade.php` reads `EmployeeDocument::TYPES`; current lookup catalogs only cover supplier category and nationality. Profession/Class is a different field. Add Document adds a row, not a new named type.

**Requested outcome:** Authorized users can create a named document type without leaving/losing the employee form, select it immediately and reuse it on later records.

**Acceptance checks:** Create one custom type; save an employee document under it; reopen/view/filter it correctly; add another employee and find the same type. Handle duplicates/whitespace, validation errors, dynamic rows and access restrictions. Existing standard types and their expiry summary mapping remain intact.

**Open decisions:** Who manages types, rename/deactivate policy, and whether custom types carry special expiry/summary behavior. A generic business-catalog request is not permission to make security roles or workflow statuses arbitrarily extensible.

**Ownership:** [EmployeeDocument](../app/Models/EmployeeDocument.php), [document row](../resources/views/admin/hr/employees/_document-row.blade.php), EmployeeController, [EmployeeDocumentController](../app/Http/Controllers/Admin/Hr/EmployeeDocumentController.php), [LookupValue](../app/Models/LookupValue.php) and its controller if that pattern is extended. Catalog storage/backfill is a design task, not an authorized migration in this review.

<a id="FR-03"></a>

### FR-03 — View/preview attached employee documents

**Sources:** [D02](client-requirements-2026-09-21-sources.md#D02), [L03](client-requirements-2026-09-21-sources.md#L03), [L04](client-requirements-2026-09-21-sources.md#L04). **Type:** Newly explicit interaction requirement / existing document workflow gap. **Priority proposal:** Medium.

**Reviewed meaning:** “File attach kar rahe hain lekin View nahin aa raha. Kaise confirm karun ke sahi document lagaya? Attachments mein aur employee detail par bhi View hona chahiye.”

**Evidence and current behavior:** L04's File column is plain Uploaded, exactly matching employee `show.blade.php`. Saved edit rows do offer Download current, and the register has an authenticated download route. New document rows only have a file input. Therefore, saying “files cannot be accessed anywhere” would be incorrect.

**Requested outcome:** A visible way to inspect the actual file from the employee attachment workflow and detail page, making correctness easy to verify.

**Acceptance proposal:** View opens supported image/PDF content, Download remains available, and no-file/missing-file states are clear. Consider immediate local preview after choosing a file, before saving, so users can verify replacements. Saved preview and pending replacement must be clearly distinguished. Access controls/private storage must remain enforced; a preview URL must not expose another employee's file.

**Open decisions:** Modal versus new tab; whether preview must work before Save; supported formats and legacy stored-file handling. The speaker says “when attaching” but does not explicitly specify the save boundary, so before-save preview is an implementation proposal, not a verbatim demand. Wider preview of purchase/leave attachments is not specifically requested by these sources.

**Ownership:** [employee detail](../resources/views/admin/hr/employees/show.blade.php), employee form/document row, EmployeeDocumentController and [HR document routes](../routes/web.php). Preserve current private-file and legacy-file safeguards.

<a id="FR-04"></a>

### FR-04 — Connect employee payroll information to salary structures

**Sources:** [D04](client-requirements-2026-09-21-sources.md#D04), [L05](client-requirements-2026-09-21-sources.md#L05), [L06](client-requirements-2026-09-21-sources.md#L06), [L07](client-requirements-2026-09-21-sources.md#L07), [L09](client-requirements-2026-09-21-sources.md#L09). **Type:** New concrete integration complaint. **Priority proposal:** High, because the inspected fallback affects salary components.

**Reviewed meaning:** “Employee add karte waqt basic salary aur allowances sab diye thay. Payroll Information mein dikhte hain, Salary Structure mein kuch nahin; dobara kyun maang raha hai? Documents ki tarah yeh bhi linked hona chahiye.”

**Evidence:** Employee salary and allowances are populated in L07/L09; L05 says no salary structure defined. L06 opens Add Salary Structure with no employee selected and zeros. L09's help promises prefilled salary structures, which does not match the inspected implementation.

**Current-source trace:**

1. EmployeeController saves salary/default fields on Employee but does not create a SalaryStructure in its create/update path.
2. Employee detail renders those Employee values in Payroll Information and a separate `salaryStructures` relation below.
3. Add Structure links to generic create without employee context; SalaryStructureController supplies employee options but no selected employee defaults.
4. The salary form defaults amounts to zero and contains no employee-selection prefill behavior in the inspected form/scripts.
5. PayrollRunController selects an active structure for the payroll period. Without one, it uses employee **basic salary only**, with allowances/deductions defaulting to zero.

The last point is a source-backed risk, **not proof of an incorrect payroll run or payment in production**. No payroll was processed during review.

**Requested outcome:** Enter salary information once and reuse it in the related salary workflow; no manual retyping of the same base values. The employee's structure/profile/payroll views should agree under an explicit effective-date policy.

**Implementation alternatives requiring agreement:** Automatically create the initial effective-dated structure when an employee is saved, or open a context-aware prefilled structure for confirmation. The latter avoids retyping but may not fully meet the expectation that the structure already appears immediately. Confirm the choice. Do not silently synchronize every later employee edit into historical structures or finalized payroll.

**Acceptance checks:** With screenshot values (basic 3,500; allowances 500 + 300 + 300 + 100 + 50), the intended structure should carry basic 3,500 and total allowances 1,250 without re-entry. Under a test case with no extra items/overtime/deductions, total is 4,750. This is an example fixture, not a statutory salary formula. Verify agreed effective dates, existing employees without structures, subsequent salary changes, repeated saves, prior structure history, and no unintended alteration of processed/approved payroll. Missing setup should be explicit rather than silently dropping expected allowances.

**Open decisions:** Initial effective date (joining/contract start/manual), automatic creation versus confirmation, behavior when employee defaults change, pre-existing employees and historical runs. No backfill/reprocessing is authorized here.

**Ownership:** [EmployeeController](../app/Http/Controllers/Admin/Hr/EmployeeController.php), [employee details](../resources/views/admin/hr/employees/show.blade.php), [SalaryStructureController](../app/Http/Controllers/Admin/Hr/SalaryStructureController.php), [salary form](../resources/views/admin/hr/salary-structures/_form.blade.php), [SalaryStructure](../app/Models/SalaryStructure.php), [PayrollRunController](../app/Http/Controllers/Admin/Hr/PayrollRunController.php). Review payroll integration tests as well as form behavior.

<a id="FR-05"></a>

### FR-05 — Calculate leave days immediately when dates change

**Sources:** [D06](client-requirements-2026-09-21-sources.md#D06), [L08](client-requirements-2026-09-21-sources.md#L08). **Type:** New live-form behavior clarification. **Priority proposal:** Medium.

**Reviewed meaning:** “Start aur end date select karte hi Total Days aa jane chahiye. Ab Save karke dobara kholne par dikhte hain.”

**Current source:** LeaveRequestController calculates inclusive days on submission only when `total_days` is null/blank. The form displays the saved/old value or an Auto from date range placeholder; no live date-change calculation exists in the inspected path. Thus calculation is present, but the timing/display differs from the client's expectation.

**Requested outcome:** Show the count as soon as both dates are valid and refresh when either changes, on create and edit.

**Acceptance checks:** Same-day range = 1; screenshot 2–15 September = 14 using the existing inclusive calendar-day rule. Handle missing/reversed dates and date changes without stale totals or timezone/DST errors. Server validation remains authoritative even if browser scripts are bypassed. Test editing a previously saved leave: the current controller trusts a supplied nonblank total, so stale totals must not be accidentally resubmitted.

**Open decisions:** Retain manual/half-day override, make total derived/read-only, or provide an explicit override mode? This recording does not approve a change to annual entitlement, weekend/holiday counting or carry-forward policy.

**Ownership:** [leave form](../resources/views/admin/hr/leaves/_form.blade.php), [LeaveRequestController](../app/Http/Controllers/Admin/Hr/LeaveRequestController.php), leave form and server-calculation tests.

<a id="FR-06"></a>

### FR-06 — Show an automatic employee code on opening Add Employee

**Sources:** [D05](client-requirements-2026-09-21-sources.md#D05), [L10](client-requirements-2026-09-21-sources.md#L10). **Type:** Repeated request / stronger UX expectation. **Priority proposal:** Medium.

**Reviewed meaning:** “Jab naya employee add karne lagen to us ka code khud likha nazar aaye; hamein likhna na pare. Pehle ke baad doosra add kiya to phir code dena para.”

**Evidence and current behavior:** L10 shows a blank field with a placeholder, not an actual assigned code. Current create validation permits blank code and generates it on submission from classification-specific SP-/FL- sequences. The round-three test explicitly covers blank-code server generation and manual codes, not a pre-save next-code display. The screenshot does not show a failed blank-code submission.

**Requested outcome:** Make automatic numbering visible and usable without typing; preserve the previous separate Sponsorship/Freelancer sequences. Investigate any genuine deployment-specific save rejection separately with its exact response/version.

**Acceptance checks:** Open Add Employee and see a meaningful automatic-code display; choose/change classification and see the corresponding behavior; save without typing; verify the persisted code, then add a second employee. Keep existing IDs stable. Two simultaneous users must not save the same identifier; handle refresh, failed validation and abandoned forms safely.

**Open decisions:** Is the displayed code a provisional preview or a reserved identifier? Are manual overrides still allowed? Should a read-only preview differ from the final code if another user saves first? Current `CodeGenerator::sequential` is a read-next loop, not a transactional reservation guarantee; rendering a number into the input alone is insufficient for concurrency safety. No actual collision was reproduced here.

**Ownership:** EmployeeController, employee form, [CodeGenerator](../app/Support/CodeGenerator.php), [code configuration](../config/seera.php), round-three code tests plus new UI/concurrency cases.

<a id="OBS-01"></a>

### OBS-01 — Escaped ampersands remain on HR screens

**Sources:** L01–L03, L06, L08. **Type:** Visual/source observation only; not a seventh spoken request. **Priority proposal:** Low.

Breadcrumbs show `HR &amp; Payroll`, and the employee document section shows `Documents &amp; Attachments`. These strings remain pre-escaped in Blade while the component renders its title with escaped output. The earlier NR-24 dashboard label fix should not be assumed to cover these HR labels.

**Suggested acceptance:** Human-readable ampersands with safe escaping retained. Do not disable escaping globally. This is a small follow-up cleanup candidate, not an instruction from an additional audio clip.

## Development order and unresolved choices

**Suggested order:** first agree and test FR-04's salary linkage/effective dates because the fallback can omit expected allowances; then FR-01 date rules; bundle FR-02/FR-03 document workflow; finish FR-05 live days and FR-06 visible codes with authoritative server checks. OBS-01 can accompany the affected template work. This is planning only.

Questions to resolve before the affected implementation:

- Must Joining Date and Contract Start always equal, or just both be today/past?
- Is document preview needed before Save, after Save, or both? Modal/new tab?
- Should saving an employee automatically create the first salary structure, or open a prefilled confirmation? What effective date and later-change policy?
- Are leave totals derived only, or may users override for half days/other approved exceptions?
- Should automatic employee code be a preview or reserved value, and do manual overrides remain?
- What server commit and exact response produced the reported request to enter an employee code?

No answer is inferred from a previous Done label. Equally, no previously implemented feature is declared missing just because the client restates its desired outcome.

## Review limits and privacy

The source-backed findings above describe local `ce2b1ad`, not an authenticated runtime reproduction on the client's server. No application tests, payroll runs or production probes were executed. Acceptance checks are proposed future tests, not results.

All media and reports stay local. The browser report embeds private employee/document/bank information; do not publish it or commit it to a public repository without approval. Media currently resides under `public/`; it could be served if deployed as-is. This review did not relocate/delete it or modify hosting rules. Existing untracked files and previous reports were preserved.

## Package validation

Passed: 16 original source hashes unchanged; all files reviewed and linked; 18 recognition passes retained (two Urdu passes and one English pass per audio); embedded 10 images and 6 recordings match the originals; six feedback anchors and one observation anchor; unique HTML IDs and valid local/anchor links; print styling with no external rendering dependency. A local browser preview was visually checked. These checks validate the documentation package, not the ERP runtime.
