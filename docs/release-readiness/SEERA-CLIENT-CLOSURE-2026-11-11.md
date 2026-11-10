# Client feedback closure — 11 November 2026

Branch: `feature/seera-client-closure-2026-11-11`.

Source baseline: `eb4d2b7eca4876a674d5b388f06bdb3d437b65ec`. **Tested application commit: `510d88a914545b839ba393323b195e4844b0b85d`.** The following evidence/handover commit changes documentation only. The starting checkout was preserved; three unrelated untracked handover/deployment notes remain outside this change. The exact application file list is in `evidence/client-closure-2026-11-11/changed-files.txt`.

## What changed

| Client point / finding | Result on this development candidate | Location |
| --- | --- | --- |
| NR-06: automatic overdue customer alert | Daily in-app reminder, first day overdue, eligible Finance/Super Admin recipients only. Current permission/scope/settlement state rechecked. Deterministic daily notification ID, transaction/row lock and batched visibility queries. No email, background queue or silent scheduler activation. | Notifications → invoice; `seera:overdue-reminders` |
| NR-29: supplier advances and payments on behalf | Dedicated recoverable asset/sub-ledger; confirmed contractual recoverability, reason/reference, explicit bill application, supplier refund and reversals. Available funds and bill locks, immutable request keys, posted journal protection, original AP-account lookup and separate cash/application display. | Supplier Edit / Manage → Supplier advances; posted Bill → applications |
| Screenshot: raw `ui.deactivate` | Added English/Arabic action labels. | Users → Actions |
| Screenshot: confusing empty Supplier projects | Explain that no projects are available to this account, rather than asserting that none exist anywhere. No dummy master records or scope expansion. | Supplier Create/Edit |
| Screenshot: repeated Employee reference | Read-only private inventory now includes displayed codes, actual linked Employee IDs/codes, conflicts and a duplicate-code list. No automatic relink/deletion. | `seera:entry-audit --json` |
| MyISAM/partially attempted November schema | Withdraw unsafe setup-only fresh-DB recipe. New guarded operation prepares an independent InnoDB database preserving every current source row/column, verifies digests and foreign keys, rejects unsafe drift and does not promote the copy. | Corrected recovery guide; `seera:prepare-storage-copy` |
| Client documentation | November pages use client language, maintain issue → improvement → screen → check → status tables, add overdue/recoverable examples, retain guide/topbar shortcuts. | User Guide → Issues & Fixes / November Instructions |

The existing prepared November fixes are retained: IBAN presentation normalization/checksums; Annual-only/year-specific balance; Employee/User private photos; personal permission exceptions; My Leave; code previews/manual choice; private party/initial Equipment documents; blank Equipment meter handling; protected Super Admin changes; resumable additive migrations. They were already on the feature lineage; this report does not claim they were installed on production or newly reimplemented here.

## Accounting and authorization

- Record: Dr dedicated advance asset / Cr permitted cash-bank. Apply: Dr original bill AP / Cr stored advance asset. Supplier refund: Dr receiving cash-bank / Cr stored advance asset. Reversal is a separate opposite journal; original entries/rows remain.
- AP view, approval and process permissions are required; route parent, source ownership and project/site scope are enforced server-side. Personal Deny and inactive/expired roles remain effective.
- A valid dedicated Finance mapping is required. No financial account, posting rule or master is seeded. Cash/Bank/Both limits remain enforced.
- Applications reduce the bill's outstanding balance without being presented as a new cash payment. Later supplier account mapping changes do not redirect the application from the original bill account.
- Bill VAT, payroll, customer credit/refund flows and ZATCA signed documents are unchanged. No VAT transaction is created by an advance/application/cash return. Finance rounding Option B is not implemented.
- A genuinely new taxable back-charge is not automatically treated as zero VAT; it needs its own approved tax-document classification. Agreed salary/repair recoverables are supported; arbitrary deductions are not silently posted.
- Historical payment submission replays are checked before the new-purpose restriction. Existing purpose descriptions remain readable and exact retries remain idempotent; new on-behalf recoverables use their own workflow.

## Verification

See `evidence/client-closure-2026-11-11/` for the final automated results and fictional screenshots. Browser business writes are blocked by both the local router and request interception; examples are prepared by a guarded CLI fixture against a disposable SQLite database. MySQL probes require the exact separately initialized local datadir and uniquely named synthetic databases.

| Final verification | Result |
| --- | --- |
| Complete accelerated Laravel suite, 8 workers | **936/936 passed; 10,855 assertions; 402.283 seconds** |
| JavaScript (`npm run test:js`) | **12/12 passed** |
| New supplier recoverable / overdue / priority UI browser checks | **233/233 passed** |
| November workspaces / uploads / permissions / My Leave browser checks | **363/363 passed** |
| Client guide browser checks | **91/91 passed** |
| Browser total | **687/687 passed; zero JavaScript errors** |
| MySQL concurrency and real posted Bill/AP/VAT reconciliation | **7/7 passed** |
| MySQL storage-copy positive/negative probes | **28 passing checks across four runs**, including repeated safety guards; 102 source tables preserved in the partial-schema positive case, 308 generated foreign keys verified |
| Release ZIP | **18 entries verified**, CRC, archive layout, extracted bytes and all manifest references; existing package retained because bundled frontend sources did not change |

English and Arabic were checked at 320, 390, 768 and 1440px. Screenshots use fictional data. The expanded recoverable payment form and connected actions fit the page; tables scroll inside their containers. Supplier/Customer/Employee View pages stay read-only. No global overflow-hiding workaround was added.

The focused affected classes passed before the final full run: 176 tests/1,346 assertions across recoverables, approval runtime, Supplier Bill runtime, Site Expenses, customer credits, migration recovery and guide classes; the corrected payment replay/HTTP/link-audit group then passed 26 tests/181 assertions. These overlapping focused counts are not added to the full-suite count.

An initial full run found one payment-replay regression (new purpose validation preceding replay matching); the implementation was corrected and the affected 26 tests passed before the final full rerun. The first browser harness needed to wait for a collapsed form's attachment rather than visibility; the payment-form expansion check now opens its own enclosing details element. These intermediate runs are not presented as final passing results.

## Owner-confirmed rules preserved

Only approved Annual Leave deducts from annual entitlement, in its calendar year. Personal/Urgent Leave does not. Calendar-day counting, no automatic carry-forward/accrual and explicit later effective-dated salary structures remain as requested. No historical payroll backfill was performed.

Multiple reporting parents and configured all-required approvals remain implemented for PR, Site Expenses and Supplier Bills. Reporting links alone do not activate additional module approvals. Whether Leave/Overtime/Payroll must join that runtime, and whether a narrated Urdu video is needed beyond the written/visual walkthrough, remain clarification items; no new policy was inferred.

## Still pending — do not label these fixed/live

| Item | Status | Required evidence/action |
| --- | --- | --- |
| Live November feature rollout | BLOCKED pending storage rehearsal and separate owner approval | Latest owner-reported live source is guide-only `9cc5ea0`. Do not assume this feature branch is deployed. |
| Full backup and restored rehearsal | NOT RUN on the actual production copy | Verified full restore, stopped writers, data-preserving InnoDB destination, all-row/constraint checks, independent GL/AP/AR/VAT/stock/payroll reconciliation, controlled smoke checks. Synthetic probes do not substitute for this. |
| Exact disputed IBAN | PENDING client private comparison | Compare actual bank document and entered value. Invalid check digits remain refused. |
| R10-06 demo accounts | PENDING owner ID list/inventory | Identify exact client/Super Admin/real users to retain, then approve deactivation. No account was deactivated by this work. |
| R10-07 master cleanup | PENDING owner keep/review list | Preserve new real entries/dependencies. No production reset or deletion. |
| Repeated Employee codes/links | PENDING actual private inventory | Current schema uniqueness differs from some legacy schemas. The copy refuses conflicting unique keys; no record is silently rewritten or discarded. |
| Real Project/Location task | PENDING client role/data walkthrough | Check actual visibility and relationship; no automatic entity merge. |
| Finance advance mapping | PENDING company setup | Choose the dedicated advance asset before posting. Taxable back-charge policy remains separate. |
| Overdue scheduler | PENDING approved activation | Default off; verify timezone, configure hosting scheduler once and explicitly enable. No worker/scheduler was started on production. |
| Arabic/business/device acceptance | PENDING client sign-off | Browser viewport QA is distinct from Arabic business-language acceptance and physical GPS/offline devices. |
| Marketing optional refinements | PENDING specific scope if requested | Existing leads/visits/follow-up/report flow retained. Extra custom statuses, visit attachments and reminder policy were not inferred. |
| Performance | NOT DIAGNOSED on current production | No new live queue job was launched. Earlier sync configuration/no-worker evidence is historical; profile real hosting requests/resources before naming a root cause. |
| Production ZATCA and external integrations | BLOCKED/PENDING their own gates | Keep production ZATCA disabled; SDK/company onboarding, Finance decisions, actual devices and contracted integrations require separate evidence. |

## Decision and handover

**GO for review and controlled restored-copy staging rehearsal.** No confirmed P0/P1 software regression remains from this implementation's verification. **Client closure is conditional**, because the confirmations, data review and actual live rehearsal above are outstanding. Production remains **NO-GO for a blind pull/migrate deployment** because the reported database storage condition is unresolved. See [operator prerequisites](../client-closure-operations-2026-11-11.md), [implementation decisions](../requirements/2026-11-11/IMPLEMENTATION-DECISIONS-AND-CLOSURE.md), and the retained 86-ID source traceability index.

No production deployment, migration, seeder, reset, database replacement, account deactivation or ZATCA activation was executed.
