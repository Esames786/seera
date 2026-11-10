# Internal evidence for the cumulative client-outstanding review

Date: 11 November 2026. Documentation/review only. No production connection, migration, conversion, rebuild, seed, cleanup, deployment or business-record mutation performed.

## Release state

- Owner's current production transcript: `9cc5ea0179c6e104750fcb1249ec3f684194ad38`, `fix/seera-client-guide-backport-2026-11-10`.
- Its application baseline is `0aed8c791e4cd214d23a916026860585983d1cca`, with the two guide-only commits `03aa634` and `9cc5ea0`.
- Compared development branch: `feature/seera-client-feedback-fixes-2026-11-10`, HEAD `eb4d2b7eca4876a674d5b388f06bdb3d437b65ec`, confirmed on origin in this review.
- `git merge-base --is-ancestor 2da7886 9cc5ea0` is false: the November application changes are not in the owner's deployed code. Migration recovery patch `0a1a01a` and the guide-shortcut feature-branch commit do not change that.
- Purchase VAT closure `dc048ff`, Sales VAT anchor `1c446a6` and notification QA anchor `35c79e2` **are ancestors** of the reported deployed revision. Do not reopen them from an earlier September/October pending label.
- The November code has saved QA evidence; those results are development evidence, not a successful deployment or actual client acceptance on `9cc5ea0`. No full application suite was rerun for this document-only review.

## Historical source coverage

| Media round | Original source review | Recorded files | Requirement IDs covered |
| --- | --- | --- | --- |
| 5 September | `docs/client-requirements-2026-09-07.md`, corresponding sources and implementation register | 20 audio / 16 images | CR-01–CR-14 |
| 8 September | `docs/client-requirements-2026-09-09-addendum.md`, corresponding sources and round-2 register | 5 audio / 4 images | CR-15–CR-18 |
| 14 September | `docs/client-requirements-2026-09-16.md`, sources/sequence and implementation register | 49 audio / 49 images | NR-01–NR-34 |
| 21 September | `docs/client-requirements-2026-09-21.md`, sources and implementation register | 6 audio / 10 images | FR-01–FR-06 and OBS-01 |
| 22 September | `docs/client-requirements-2026-09-22.md`, sources and implementation status | 6 audio / 6 images | R22-01–R22-07 |
| 24 September | `docs/client-feedback-2026-09-24.md`, source ledger and workspace programme | 3 audio / 1 image | R24-01–R24-07; some entries are owner/GPT context rather than new audio requirements |
| 10 November | November source review/implementation report on the feature branch; guide issue table | 10 audio / 19 images | R10-01–R10-13 |

These registers cover **99 audio entries and 105 image entries (204 file entries)** across seven media batches. Known repeated files/topics exist; this is not 204 unique inputs or 86 distinct unresolved bugs. The CSV contains **86 unique existing requirement IDs**. Ad-hoc owner instructions on workspaces, save warnings, two UI languages, conversion and Back controls were also checked against later release summaries and source; their overlap is not counted as additional defects.

This pass reused stored source-by-source Urdu/English reviews and did not re-transcribe all audio. No new recording was attached to the current message. The latest chat screenshots were visually reviewed; the repeated Employee Create image is not a second requirement. No private source images, exact bank details, user passwords or audio have been copied into this review package.

The November original technical/source review is on the feature branch rather than the guide-only release: [source review](https://github.com/Esames786/seera/blob/eb4d2b7eca4876a674d5b388f06bdb3d437b65ec/docs/requirements/2026-11-10/SOURCE-REVIEW.md), [implementation report](https://github.com/Esames786/seera/blob/eb4d2b7eca4876a674d5b388f06bdb3d437b65ec/docs/release-readiness/SEERA-CLIENT-FEEDBACK-FIXES-2026-11-10.md).

## Targeted code findings

| Finding | Source checked / interpretation |
| --- | --- |
| Missing November controls | Git diff from `9cc5ea0` to the feature candidate contains new photo/document components, customer/supplier document models, MyLeaveController, UserPermissionOverrideController and both November migrations. Those paths are absent or differ in the deployed source. |
| Leave example needs careful classification | Base `Employee::leaveBalance()` filters approved Annual Leave by year when the ANNUAL lookup exists, but omits the type filter if the lookup is missing. Candidate always filters by ANNUAL IDs and adds year/type clarity. Approved 2025 Personal Leave does not establish a 2026 Annual arithmetic defect. |
| Auto-code screenshot | The existing Auto placeholder/backend blank-code path is real. Candidate adds the explicit preview/manual choice and concurrency handling; do not claim all numbering was absent. |
| Raw Deactivate label | `resources/views/admin/users/index.blade.php` at `9cc5ea0` passes `__('ui.deactivate')`; neither `lang/en/ui.php` nor `lang/ar/ui.php` defines `deactivate`. The latest screenshot shows that exact fallback key. The candidate dictionaries also lack it, so it is not already fixed by the November application patch. |
| Duplicate displayed codes | Users' text `employee_id` is not the numeric Employee foreign key. The latest screenshot appears to reuse a displayed code, but actual `employees.user_id` links and retained account ownership need a live read-only identity audit before any change. |
| Overdue alerts | Customer source has overdue summaries. Notifications are approval-source notifications; the new bell does not implement scheduled overdue delivery. |
| Supplier purpose versus accounting | `PostingService::postSupplierPayment()` debits the supplier AP account and credits cash/bank; no purpose-specific advance receivable/back-charge accounting is delivered there. |
| Marketing scope | MarketingLead/MarketingVisit and controller have default statuses, assignment, visits, outcomes and follow-up fields. MarketingVisit has no attachment fields; a follow-up date/filter is not an automatic reminder service. Additional attachments/reminders were unresolved scope options, not all explicitly approved client requirements. |
| Approval scope | ApprovalRuntimeService supports purchase_request, site_expense and supplier_bill. Every required configured step must approve; role reporting-parent edges do not automatically install a workflow on HR/payroll/stock modules. |
| Salary/leave policy | `Employee::ensureSalaryStructure()` creates the first structure only; `salaryStructureOutOfDate()` raises a mismatch rather than silently superseding history. LeaveRequestController uses inclusive calendar days with an explicit manual/half-day override. These defaults exist; changed business policy requires approval. |

## Deployment and acceptance boundaries

The last supplied production engine inventory lists the business tables—including journals, AP/AR, stock, approval instances and document sequences—as MyISAM; only notifications and the partial customer_documents table were listed as InnoDB. No later successful conversion/restoration audit has been supplied. MyISAM cannot provide the transaction rollback/row-lock guarantees assumed by the source code. This is a significant unresolved operating/release gate, not an allegation that a particular live balance is already wrong.

An engine conversion alone also does not recreate historical constraints that never existed. A repaired database needs a reviewed foreign-key/index inventory, orphan/data checks and actual financial reconciliation. Do not infer that published rebuild commands or a synthetic rehearsal prove that current live rows are expendable. The client has entered a fifteenth account and may have entered other real data; a setup-only restore must not discard unreviewed business data. This review authorizes no `migrate:fresh`, broad reset or cleanup.

The existing November 1 handover records client business acceptance, physical Android/iPhone testing, Arabic acceptance, backup restoration and live Finance reconciliation as pending/not run. The current message does not supply signed replacement evidence. VAT rounding, proper Sales tax-category modeling and company ZATCA onboarding remain separate Finance/external gates; standard 15% authorization is not the same issue.

No new performance root cause was established. The prior supplied process snapshot showed sync queue configuration and no matching queue/scheduler worker. MyISAM has locking limitations, but that alone does not prove it caused the reported slowness. Profile current requests/queries and hosting resource limits before claiming a performance fix.

## Evidence reused without treating historical results as fresh

- September implementation registers and the September 29 reporting-parent/Back note.
- October 27 defect closure, later Arabic and Sales VAT QA, and November 1 core integration/UAT handover.
- November 2 notification and visual-guide QA; the visual guide contains 94 training screenshots, not a narrated video or signed client walkthrough.
- November 10 application/migration recovery evidence and guide-only backport evidence.

See `review-scope.json` and `CLIENT-FEEDBACK-TRACEABILITY-2026-11-11.csv` for the compared revisions and complete requirement index. These are private review documents, not newly served portal-guide pages.
