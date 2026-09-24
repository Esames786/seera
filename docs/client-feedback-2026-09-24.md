# Seera — 24 September voice notes, screenshots and fixes backlog

Review date: 24 September 2026. Source checkpoint: `2d0f9d5`. **Research/specification only: no application fix or production change in this review.** Latest instructions supersede earlier UX proposals where noted. Companions: [remaining-module plan](remaining-modules-connected-workspaces-2026-09-24.md), [August–September GPT handoff](backend-handoff-august-september-2026.md), [raw ASR/hash ledger](client-feedback-2026-09-24-evidence.json).

## 1. Seedha matlab

1. **Employee list par Open ki jagah View, Edit, Deactivate chahiye.** Sirf dekhne wala banda edit form mein na jaye. Edit ke andar tamam related cheezen ek jagah manage karne ka idea barqarar hai.
2. **Linked employee/user ka naam aur authorized link dikhna chahiye.** Sirf “already linked” message se operator ko doosra account dhoondhna padta hai.
3. **Finance module ki current position aur month-end completion scope clear chahiye.** Jo core agreed finance flow hai uski readiness pehle assess karo; entire ERP ya live statutory integration complete hone ka dawa mat karo.
4. **Product ka workflow samjhane ke liye walkthrough material chahiye.** A02 likely video mangta hai; exact wording/format mein ASR ambiguity hai, isliye video plus short written process proposal ko confirm karna hai.
5. **Baaki modules bhi record-wise connected hon**, lekin View read-only, Edit connected, aur approval/posting alag authorized actions hon.
6. Supplied GPT screenshot ka **Role form 7 actions vs matrix 15 actions** source mein abhi bhi confirm hai; matrix usability ko existing grouping/bulk controls ke saath improve karna hai.

## 2. Inventory and review method

Actual folder found: `public/24_09_2026` (not a guessed `seera_24_9` path). All **4 files** reviewed: **3 OGG, 1 JPEG, no video**. Total audio **63.859 seconds**. Filename time gives provisional message sequence, not reliable real speaking/forwarding chronology. Content connects I01 with A03.

Original files were SHA-256 hashed and not modified. Audio processed locally with cached faster-whisper **medium and small**, Urdu transcription plus English translation for each: **12 passes**. No source audio was uploaded and no new model download was required. ASR output contains errors; reviewed interpretations below are not an exact/verbatim transcript. Low-confidence commercial wording and walkthrough format remain flagged. Screenshots were visually inspected separately.

Raw source location is under `public/`; live exposure is unknown. Keep this package local/private, do not upload raw recordings or the whole public directory, and do not publish screenshots containing employee identity/pay. No media was moved or deleted during review.

| Order | ID | Exact filename | Duration | Meaning / pairing |
|---|---|---|---:|---|
| 1 | A01 | WhatsApp Ptt 2026-09-24 at 2.10.17 PM.ogg | 27.387 s | Finance priority, month-end and current status |
| 2 | A02 | WhatsApp Ptt 2026-09-24 at 2.10.31 PM.ogg | 12.326 s | Explain product/work process; likely walkthrough video |
| 3 | I01 | WhatsApp Image 2026-09-24 at 2.18.02 PM.jpeg | — | Employee row with Open and Deactivate |
| 4 | A03 | WhatsApp Audio 2026-09-24 at 2.18.03 PM.ogg | 24.146 s | Replace Open with distinct View/Edit/Deactivate; refers to I01 |

### A01 — finance delivery and status

**Reviewed Roman Urdu:** “Meeting mein baat hui hai ke finance module ko maximum by the end of this month complete karein. Jo cheezen reh jayengi unka context alag hai; ek invoice clear karne ki bhi baat hui hai. Ab finance mein hamara software kahan stand karta hai?” This is a meaning-level reconstruction, not quoted exact speech.

- Approximate segments: 0–6 s meeting/context; 6–14 s finance module/month end; 14–22 s remaining work and commercial invoice remark; 22–27 s asks current finance standing. ASR segment times are approximate.
- Medium transcript misrecognizes “module” and the final software/status phrase. Small English pass supports “finance module” and “end of this month”. Do **not** read the distorted module word as a separate confirmed payroll instruction.
- Commercial wording differs across passes (“one more/current” invoice). Treat as project-payment coordination; amount, due date and exact commitment are unknown. It does not request an ERP clearance feature or authorize any real payment.
- **Requirement:** finance readiness/gap assessment and agreed prioritized delivery milestone. “End of this month” provisionally means 30 September 2026 if the recording date is original; confirm it and the acceptance scope.
- Confidence: high for finance/status/month-end intent; low for exact commercial wording. No completion percentage or guarantee inferred.

### A02 — show how the product works

**Reviewed Roman Urdu:** “Unko aisa walkthrough bhejna hai jisse samajh aaye poora product kaise chal raha hai aur work process kya hai.” Likely a video, but this sentence intentionally avoids claiming an exact transcript.

- 0–12 s is one short request about sending explanatory material and the product's work process.
- Medium translation renders the unclear word as “idea”; small translation explicitly says “video”. Urdu passes render the same word imperfectly as “ایڈیو”. Strong common meaning: explain the end-to-end product workflow. Format confidence: medium, not certain.
- **Proposal for confirmation:** one short narrated screen recording plus a written workflow map. Use agreed staging examples, not personal/payroll/bank data. Existing static client-review pages are useful input, not proof this request has been satisfied.
- Suggested flow: setup/master data → Employee/User distinction → Supplier/Customer → purchase/bill or invoice → explicit approval/payment → journal/VAT/report; explain draft vs posted and what is still pending. Do not demonstrate simulated ZATCA retry as live clearance.
- Confirm recipient, narration language, duration and whether finance-only or whole-product overview is wanted. Urdu audio narration does not add Urdu UI scope.

### I01 — Employee list screenshot

One Employee row shows code Emp-002, employee identity, department/designation, project/site, classification, document expiry, pay, mobile/status, and actions **Open / Deactivate**. There is no distinct View button. The screenshot cannot demonstrate saving a changed date, a corrupted record or who the employee's linked user is.

The separately attached chat image is a presentation of this same list topic with the caption “View-Edit-Deactivate”; do not count it as a second folder file.

### A03 — explicit View / Edit / Deactivate

**Reviewed Roman Urdu:** “Teen options hone chahiye: View, Edit, Deactivate. Open na rakhein, kyunki us se Edit par chala jata hai. Banda sirf dekh raha ho aur koi date/data change ho jaye to masla hota hai. View mein sirf dekhein; Edit mein jayein to edit ho.”

- Approximate segments: 0–5 s three actions; 5–10 s Open enters Edit; 10–18 s accidental date/data-change concern; 18–24 s repeats View/Edit/Deactivate separation.
- Medium Urdu and English strongly agree. Small transcription is garbled and partly multilingual, and is **not** used as authoritative wording; its English pass agrees on the three action names.
- **Requirement:** restore explicit record modes without dismantling the employee workspace. View must be read-only; Edit opens the connected workspace; Deactivate keeps history and requires permission/confirmation. No hard-delete request.
- Confidence: high. Client reports a risk/UX concern; no evidence in these files proves that viewing has actually persisted unintended changes.

## 3. Additional chat screenshots / owner requests (not folder audio)

**U01 — Users page 2.** Shows 14 users total, rows 11–14 and a browser Ctrl+F name search returning 0/0. Browser Find searches loaded page content, not all database records. Use the application's server-side search instead:

[Search Users for Emp-002](https://seera.tech-brit.co.uk/admin/users?search=Emp-002)

This is a **search link, not a verified linked-account URL**. It can reveal an account using that code, but a code match alone does not establish the actual `employees.user_id` relationship. An account can have a different display name. Do not create another account or force a relink based on the screenshot.

**U02 — View/Edit/Deactivate caption.** Corroborates A03/I01; implementation must revise the previous one-Open-action decision.

**U03 — existing GPT conversation screenshot.** Only points 19 and 20 and part of a quick summary are visible. Point 19: 15 permission actions in main matrix versus 7 in Role Create/Edit. Point 20: wide matrix; suggestions for sticky first column, horizontal scrolling, grouping, bulk selection and presets. The preceding 18 points and cropped summary are not available here and are not reconstructed as requirements.

Source confirms the action mismatch. Horizontal overflow, grouping and visible-only bulk selection already exist; this is not an all-new matrix feature request. Role template copying also exists in some creation flows; determine which preset UX remains necessary. Screenshot statements such as “backend deployed” do not prove current integration completeness.

## 4. Fix backlog — stable R24 IDs

### R24-01 — distinct Employee modes (high priority)

- Source: A03/I01/U02. Confirmed client change. **Implemented in `f12e228`** (branch `feature/seera-connected-workspaces-2026-09-23`), tests in `tests/Feature/EmployeeViewEditDeactivateTest.php`.
- Current: `resources/views/admin/hr/employees/index.blade.php` sends Open and the name to Edit for users with HR edit. Existing show route/view still exist.
- Fix: View → existing Employee show route for permitted viewers; Edit → existing connected Edit workspace for editors; Deactivate → existing history-preserving action for authorized users. Prefer employee name → View to avoid an implicit edit mode. Hide unauthorized actions; endpoints still enforce permissions/scope. Review header Attach Document/Edit links on the show page for permission-aware visibility.
- Preserve profiles, independent drafts, document renewal, all save intents, historical salary/approvals and global HR registers. Deactivation of employee must not silently deactivate/relink User unless separately approved policy requires it.
- Acceptance: view-only user sees no edit/deactivate controls; authorized editor can choose either mode; repeated View visits make no DB writes; Edit retains complete workspace; no date change without explicit save; deactivate keeps history and confirmation. EN/AR/mobile verified.
- Likely files: Employee index/show Blade, translation keys, HR permission regression/browser tests. No schema migration expected.

### R24-02 — actual linked-account identity and navigation (high priority)

- Source: owner's direct request/U01, not one of the three recordings. **Implemented in `f12e228`**; live relationship verification on the server still to be done by the owner after deployment.
- Current: `UserController::employeeSearch` returns non-selectable reasons for actual link, inactive employee or used employee code; response has no direct account URL. `Employee::user()` and `User::employee()` exist; actual link is `employees.user_id`.
- Proposed: linked Employee card on User View/Edit and linked User card on Employee View/System Account; show linked account display name and status only if authorized. Add **View linked user** / **View employee** and optional **Edit** when permitted. In account creation search, an actual linked employee should offer the appropriate authorized destination instead of a dead-end message.
- Distinguish true link from code conflict: “Code already used” is a diagnostic, not proof of linked identity. Display a review action only where allowed; no automatic linking, duplicate creation, password changes or permission grants.
- Server resolves canonical internal route from actual relationship and checks target visibility/scope before emitting it. Permission to search/create does not automatically grant permission to view/edit another account. Return no hidden identity or URL for out-of-scope targets. No guessed `/users/{id}` from employee ID and no client-supplied arbitrary destination.
- Acceptance: actual link, unlinked employee, inactive link, used-code-without-link, scoped outsider, missing Users view/edit, name mismatch and dangling/inconsistent relation handled safely. Clicking link while current form is dirty invokes the shared guard. Payroll/bank/security fields are not added to lookup payload.
- Likely files: UserController/search JS, Employee/User show/edit views, translations, user conversion/scope/browser tests. No migration assumed unless relationship audit shows a separate data-integrity need.

### R24-03 — finance readiness and agreed month-end scope (high priority)

- Source: A01. **Analysis/delivery-planning request**, not automatic authorization for all missing finance functionality.
- Produce a must-have/defer/decision matrix for COA/journal/GL, AP/payment, AR/receipt, cash/bank, VAT, stock-accounting reconciliation, payroll inclusion and ZATCA boundary. Current summary is in the handoff.
- Do not equate a module menu or successful migration with end-to-end acceptance. Confirm month-end date and acceptance scenarios with owner/accountant. Keep all-required approval runtime, coordinated VAT override and advance/on-behalf accounting visible as unresolved scopes.
- Acceptance: agreed scenario list, expected journal/balance results and named sign-off owner; no invented percent-complete claim.

### R24-04 — client workflow walkthrough (medium priority)

- Source: A02; likely video format, confirmation needed. **Not yet produced.**
- Proposal: short narrated staging demo plus MD workflow map; explain View/Edit, independent saves, roles/scopes, draft/approved/posted states, finance reports and known gaps. No client PII, secrets or live transactions.
- Acceptance: owner confirms format/language/audience; sample flow approved; only implemented capabilities demonstrated; no claim that live ZATCA or pending approval orchestration works.

### R24-05 — remaining-module connected workspaces (planned programme)

- Source: owner message, not implicit audio scope. [Separate module plan](remaining-modules-connected-workspaces-2026-09-24.md) maps every implemented module family, rollout order, affected layers and acceptance gates.
- Preserve explicit View/Edit separation. Customer View must remain read-only as previously instructed. Global registers, batch payroll, approvals, posting and reports remain available.

### R24-06 — permission action catalogue alignment (confirmed source issue)

- Source: U03 plus code. **Implemented in `a028b00`**: `RoleController::FORM_ACTIONS` now equals `Permission::ACTIONS` (15) with shared labels; a save revokes only displayed permissions on both paths.
- Proposal: one shared supported-action catalogue and reusable presentation. Do not generate nonexistent module/action grants or drop hidden permissions on update. Keep intentional module filters and inactive actions explicit.
- Acceptance: both screens cover the same supported actions; create/edit round-trip preserves existing extra permissions; filtered saves cannot revoke hidden permissions; unauthorized updates denied; export/mobile/post/process/retry/receive/issue/transfer/adjust/reject covered where supported.

### R24-07 — matrix usability (partly existing, needs focused polish)

- Source: U03. **Implemented in `a028b00`**: sticky module column and action header (`.matrix-wrap`), RTL-aware; grouping, overflow and visible-only bulk selection kept as they were. Presets remain a proposal.
- Proposal: sticky module column/header, manageable groups and widths, keyboard-accessible row/column selection and cautious reusable presets. Changing filters must preserve unsaved permission intent or warn. Bulk changes must clearly state visible scope.
- Acceptance: desktop/mobile/RTL, no accidentally hidden/revoked permission, explicit confirmation for broad presets, no privilege grant just from switching a tab or group.

## 5. Suggested execution and decisions

1. Confirm R24-01 modes and R24-02 actual relationship/navigation design; implement as a small tested patch, not a broad account-data repair.
2. Agree R24-03 finance milestone with backend GPT/owner/accountant and use it to order remaining workspaces.
3. R24-06/07 permission correction can be a separate bounded patch; do not conflate it with pending multi-parent approval runtime.
4. Record R24-04 walkthrough after a stable accepted build; finalize format first.
5. Roll out R24-05 one module at a time per the companion plan, retaining rollback checkpoints and staging UAT.

Questions still needed: exact month-end finance acceptance scope/date; walkthrough format/language/audience; whether linked-account view permissions are broader than current role policy (default is no expansion); pending accounting and approval business rules. **No need to ask again whether every required parent approves: owner already said all approved.**

## 6. Verification of this review

- Four originals inventoried; all source hashes recorded; one image visually reviewed; every audio has medium/small Urdu and English passes retained in the evidence ledger.
- Observations, speaker intent, code facts, proposals, inferred deadline and unresolved wording separated. No claim of exact verbatim transcription or manual listening beyond the available ASR/image review.
- Current source checked for Employee routes/actions, account lookup/relations, permission catalogues/matrix CSS, module relationships, posting/stock service boundaries, approval handler and foundation-only ZATCA retry.
- Historical status and Git chronology reconciled with the owner's server output, not treated as fresh production verification.
- No application/runtime/seeder change, new build, live DB query, push, deployment or communication to another GPT session performed by this documentation review.
