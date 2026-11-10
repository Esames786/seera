# Client feedback: what is still outstanding

Prepared: 11 November 2026. Coverage: the feedback received from September to November, plus the latest shared screenshots.

This is the **pre-implementation/live-gap audit**. Subsequent development on the isolated closure branch is recorded in [implementation decisions and closure](IMPLEMENTATION-DECISIONS-AND-CLOSURE.md). It does not change the last reported live revision or mark the client acceptance complete.

## Why the client still sees previously reported issues

The current website has the earlier application plus the updated client guide. **The November feature fixes are not installed on that website.** Updating the guide did not update Employee photos, party documents, personal permissions or My Leave.

The client's report must therefore be taken seriously. A change can be prepared and tested while the client still cannot use it on the website.

## November points still outstanding on the website

| Reference | Client issue | What is still outstanding | Screen |
| --- | --- | --- | --- |
| R10-01 | Correct bank details appear to be refused | Improved handling of copied spaces/digits and clearer errors is prepared but not installed. The exact disputed IBAN still needs private comparison with the bank document; a false rejection of a valid number has not been established. | Employee → Salary & payment → IBAN |
| R10-02 | Approved leave appears not to reduce the 21-day balance | The clearer selected-year explanation and missing-Annual-type safeguard are not installed. The existing system already deducts approved Annual Leave for its year when that type exists. The supplied 2025 Personal Leave must not reduce 2026 Annual Leave. | Employee → Leaves / leave balance |
| R10-03 | No Employee profile photo control | The prepared upload, preview, replace and remove controls are not installed. | Employee → Personal |
| R10-04 | Add User's photo/plus control does not work | The working User photo uploader and saved profile photo are not installed. | Users → Add / Edit / Manage |
| R10-05 | People with the same role need different access | Personal Inherit / Allow / Deny exceptions are prepared but not installed. The current shared Permission Matrix changes the role, not one individual. | Users → Edit / Manage → Individual permissions |
| R10-06 | Old demo users remain | Actual account review and approved deactivation have not been completed. The latest screenshot still shows 15 accounts, including old/demo-looking entries. Confirm the exact client/Super Admin accounts and any newly created real users before deactivating anything. | Users |
| R10-07 | Old projects, sites and other entries cause confusion | An item-by-item keep/remove review and approved cleanup remain outstanding. Newly entered real data must be preserved. | Projects, Locations and relevant master lists |
| R10-08 | Employee cannot find where to request their own leave | The separate My Leave page for a correctly linked employee is prepared but not installed. Existing HR-wide leave entry is a different workflow. | Topbar → My Leave |
| R10-09 | Clear automatic Customer/Supplier codes during entry | The explicit next-code preview, manual-code choice and refreshed Save & New behaviour are not installed. Blank-code numbering and an Auto placeholder already exist; neither all party numbering nor Employee auto-codes should be described as completely missing. | Add Customer / Add Supplier |
| R10-10 | Supplier papers cannot be attached during entry | Prepared private document rows and saved-file View/Download are not installed. | Supplier → Create / Edit / Manage → Documents |
| R10-11 | Customer papers should stay with the Customer | Prepared private Customer document entry and saved-file access are not installed. | Customer → Create / Edit / Manage → Documents |
| R10-12 | Equipment documents require a separate step after saving | Prepared document uploads on the initial Create/Edit form are not installed. Existing equipment-document functionality is not the same as uploading during initial entry. | Equipment → Create / Edit |
| R10-13 | Equipment save returns a Server Error | The prepared blank-initial-meter fix is not installed. The earlier error log traced a save failure to a blank meter; a separate failure of every Equipment page was not proved. | Equipment → Create → initial meter |

**November position: 10 software changes are prepared but not installed; 1 bank-detail case remains partly unconfirmed and its improvement is not installed; 2 actual cleanup actions remain incomplete.** These are 13 feedback points, not 13 newly proved software bugs.

## Earlier requests that still need completion or a decision

Some historical documents call these “open”. The remaining part is listed below so that already delivered work is not reopened as missing.

| Original reference | Original concern | Delivered part | Remaining part / next decision |
| --- | --- | --- | --- |
| NR-06 | Customer overdue alerts | Rating, overdue days/balances and on-screen warning exist. | Automatic reminder delivery needs recipients, channel and threshold agreed, followed by implementation. Approval-bell notifications do not provide overdue reminders. |
| NR-29 | Supplier advances, salary paid on behalf and repair/back-charges | A payment purpose can be recorded. | A complete separate advance/on-behalf/back-charge accounting workflow is not delivered. Finance must agree the balances and postings before it is built. A purpose label alone does not change the accounting. |
| CR-08 / R22-01 | Multiple reporting contacts and everyone required approving | Multiple reporting parents exist. Configured required-step approvals exist for Purchase Requests, Site Expenses and Supplier Bills. | Additional reporting parents do not automatically become approvers for every module. Any expected Leave, Overtime, Payroll or other multi-step flow still needs explicit scope/configuration and implementation where unsupported. The owner has already confirmed that all configured required steps must approve. |
| NR-18 | Clear leave entitlement/balances | Annual entitlement and approved annual usage exist. Annual-only deduction is confirmed. | Accrual, carry-forward and other leave-type balances need HR rules if required. These were not silently added. |
| FR-05 | Correct leave-day count | Live inclusive calendar-day calculation and a half-day/manual exception exist. | Confirm working days versus calendar days, public holidays and whether the manual exception remains allowed. This is a policy question, not proof that the live date calculation is absent. |
| FR-04 | Salary entered twice / later pay changes | First salary structure, allowances, profile defaults and mismatch notice exist. | Confirm whether a later raise should automatically create a new effective-dated structure or continue requiring an explicit action. Any old-employee backfill also needs approval; historical payroll must be preserved. |
| NR-26 | Duplicate Project/Location setup | Project/Location context and connected entry have been improved. | Walk through the client's real entry task and accept the remaining relationship/selection behaviour. Projects and Locations remain separate records by design; merging or deleting Locations is not an approved automatic fix. |
| NR-16 | Marketing lead/visit workflow | Lead assignment, visits, outcomes, follow-up fields, conversion and reports exist. | Confirm any additional custom statuses, closure policy, visit attachments or automatic reminders. These were unresolved scope options, not a promise of a complete CRM. |
| R24-04 | Explain the full product workflow | Written English/Arabic guides and a visual guide with 94 training screenshots exist. | A narrated client walkthrough/video has no completion evidence in this review. Confirm whether that format is required, then record the client's business walkthrough and acceptance. |

## Issue visible in the latest screenshots

| Item | Finding | Status |
| --- | --- | --- |
| Users action label | The red button shows `ui.deactivate` instead of a readable Deactivate label. The deployed source uses that translation key, but the English and Arabic dictionaries lack it. | **Confirmed UI defect; not fixed by the guide update or the prepared November feature patch.** |
| Repeated Employee-code text | An Employee-code reference appears reused in the Users screenshot. A displayed code is not proof of the actual Employee-to-User relationship. | **Needs an identity/link audit.** Do not delete or relink accounts based only on the visible code. |
| Empty Project choices | The Supplier screen says there are no projects, while another form shows a project selection. These screens alone do not establish whether the cause is setup, record status or role scope. | **Needs a role/data check.** Do not create demo records merely to fill the list. |

## Earlier items that should not be called completely unfixed

The current application already includes the following source changes. Client acceptance of the actual task still matters.

- Multiple reporting-parent selection; core configured approval flows in the three supported modules.
- Standard 15% purchase/sales VAT defaults and reasoned Super Admin override controls.
- Customer/Supplier coloured ratings, Customer Type + New, Cash/Bank/Both restrictions, contacts/notes entry and read-only Customer View.
- Connected Employee/User, Customer/Supplier, Project/Location, purchasing and inventory workspaces; existing Back, Save and unsaved-change controls.
- Employee document types, preview/renewal, automatic employee codes, first salary-structure linkage and live leave-day calculation.
- Site Expenses, Equipment, HR Reports and Project Reports. The old “four SOON modules” list is historical, not their current implementation status.
- Notification bell/inbox and the written/visual guide. Neither replaces personal leave self-service, overdue reminders or the November feature rollout.
- Much of the English/Arabic work. The remaining raw action label and Arabic business-language acceptance must still be addressed.

## Separate go-live items

These are important release conditions, not additional complaints inferred from the supplied voice notes:

1. Resolve the database issue that prevented the November update, preserving current data and verifying a restorable backup. The supplied database check also raises concerns about reliable financial/stock transactions until the storage issue is repaired and verified.
2. Finance must confirm VAT-rounding policy and any legitimate zero-rated/exempt/out-of-scope sales treatments. Standard 15% authorization is already delivered; these are separate decisions.
3. Production ZATCA, official/company validation and company-specific activation remain restricted. Real equipment tracking and statutory payroll integrations require their own approved scope/provider inputs.
4. Complete real Finance reconciliation, client task acceptance, Arabic business-language review and physical Android/iPhone testing where applicable.

## Recommended meeting order

1. Agree that the website currently lacks the November feature update; do not mark guide publication as feature completion.
2. Review the 13 November rows above with the client and select the exact accounts/master records to retain.
3. Fix the raw Deactivate label and audit ambiguous identity references.
4. Resolve the release database gate through a separately approved data-preserving plan, install the reviewed feature fixes, then demonstrate each row on the actual accepted release.
5. Assign an owner and decision to the genuinely open older items. Record each result as Accepted, Still failing, Awaiting decision or Outside agreed scope.

This review uses the stored audio/image requirement reviews, supplied screenshots and the application source. It does not claim that every original recording was replayed today, that every issue was freshly reproduced on production, or that client acceptance has been given.

The accompanying [86-row traceability sheet](CLIENT-FEEDBACK-TRACEABILITY-2026-11-11.csv) preserves every existing requirement ID, including delivered items and duplicate topics across rounds. The [evidence notes](CLIENT-OUTSTANDING-ISSUES-EVIDENCE-2026-11-11.md) provide the internal release/source references.
