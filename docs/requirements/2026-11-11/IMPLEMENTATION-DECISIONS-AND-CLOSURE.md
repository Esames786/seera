# Client closure implementation — 11 November 2026

Branch: `feature/seera-client-closure-2026-11-11`. Starting source: `eb4d2b7eca4876a674d5b388f06bdb3d437b65ec`.

This is a development record. The owner's latest reported live revision is `9cc5ea0`: the stable application plus client-guide changes. No live database, deployment, migration, reset or account deactivation was performed by this implementation.

## Confirmed owner decisions

| Topic | Confirmed rule | Implementation boundary |
| --- | --- | --- |
| Customer overdue reminders | Daily in-app notice starting one day overdue; authorized Finance/Super Admin recipients | No email/SMS. Paid/unposted/cancelled invoices excluded; current scope and role checked again when reading. Same invoice/user/day deduplicated. Explicit scheduler activation required. |
| Supplier advance / paid on behalf | Owner delegated the accounting design | Dedicated recoverable asset; explicit contractual recoverability and reference. Explicit bill application, supplier cash return and reversals. No automatic netting, invented tax treatment or changes to old payments. |
| Annual Leave | Only approved Annual Leave reduces annual entitlement | Existing yearly separation retained. Personal/Urgent Leave does not reduce Annual Leave. |
| HR dates and salary | Preserve existing rules | Calendar days, no automatic carry-forward/accrual, explicit effective-dated later salary structure. No historical payroll rewrite. |
| Demo/master cleanup | Inventory first, then approved deactivation | Do not guess which of the 15 logins are disposable. Owner must approve exact IDs and retained identities; history and newly entered real data stay. |

## Additional approval/video scope

An optional clarification was sent about adding Leave/Overtime/Payroll to the reusable approval runtime and about a narrated Urdu video. Until answered, the supported runtime remains PR, Site Expenses and Supplier Bills. No new HR approval policy or promise of a narrated video is inferred from the existing reporting-parent hierarchy.

## Supplier accounting design

| Explicit action | Debit | Credit | Bill balance | VAT |
| --- | --- | --- | --- | --- |
| Recoverable advance or salary/repair paid on behalf | Configured dedicated supplier-advance asset | Permitted cash/bank | Unchanged | None generated |
| Apply recoverable to a posted open bill | Original bill's posted AP account | Stored advance asset | Reduced by application | Bill VAT unchanged |
| Money returned by supplier | Permitted cash/bank | Stored advance asset | Unchanged | None generated |
| Reverse | Exact opposite of the original journal | Exact opposite | Application reversal restores balance | No VAT change |

The original payment, journal and movements remain in history. Applications are shown separately from cash payments. Payment and approval permissions are both required. A supplier/project/site match is required for application; project/site scope is rechecked server-side. Available funds and the bill are locked before settlement. Submission keys identify an immutable request and refuse changed-payload replays.

Finance must create/map its own dedicated asset through one active `Supplier Advance / Payment Recorded` posting rule. No company account or journal is seeded. The rule must enable automatic posting and disable an extra approval request: the explicit posting action itself is restricted to users with both AP approval and payment permissions. A missing/invalid mapping refuses the whole transaction. Core cash/bank/AR/input-VAT/inventory control accounts and their descendants cannot be used as the recoverable asset.

Salary/repair on behalf is supported only as an agreed recoverable payment. A newly taxable back-charge is not silently treated as zero VAT; it requires the proper approved tax-document treatment. Finance rounding Option A/B remains separate; this work does not change it.

Design references: [SAP supplier down payments](https://help.sap.com/docs/SAP_ERP/72b431fb78a649da9c8b46951e64fb88/c1ecd353ca9f4408e10000000a174cb4.html), [IAS 32 offsetting requirements](https://www.ifrs.org/issued-standards/list-of-standards/ias-32-financial-instruments-presentation/). These explain the separation/explicit-clearing approach; they are not evidence of the client's tax classification or approval of a particular company account.

## Database recovery correction

The earlier setup-only `migrate:fresh` recipe was withdrawn. It would not preserve arbitrary current business entries. The replacement prepares an InnoDB copy in a different, explicitly empty database, preserves every source row/column/ID, verifies digests and foreign-key integrity, and never switches `.env` or promotes the copy. Backup/restore evidence and stopped writers remain required before an operator executes it. Unknown source fields, orphans, occupied destinations and concurrent source changes cause refusal. Production execution has not occurred.

## Evidence boundaries

Test results and screenshots must be recorded in the final closure evidence after verification completes. Existing September–November traceability files remain the historical source audit. A local passing test is not a live fix or client acceptance. The exact disputed IBAN, approved cleanup identities, physical device/GPS checks and business UAT require their own evidence.
