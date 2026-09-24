# Remaining modules: one-record workspaces

Date: 24 September 2026. Source baseline: `2d0f9d5`, branch `feature/seera-connected-workspaces-2026-09-23`. **Proposal, not implemented by this document.** Read with [new feedback](client-feedback-2026-09-24.md) and [backend handoff](backend-handoff-august-september-2026.md).

## What the owner wants

Employee ki tarah ek record kholo, uski related information wahin manage karo; har step par dobara search ya doosra menu na kholna pare. This is one persistent record context, not one enormous database transaction or a replacement for all reports and approval queues.

The existing Employee workspace is the pilot. Its consistent saves and inline masters were corrected in `2d0f9d5`; do not copy its earlier “Save here” UX. The September 24 audio explicitly requires separate View/Edit/Deactivate: Edit opens the workspace and View is read-only. The September 22 explicit instruction that **Customer View stays read-only** is still binding.

## Before → after, and where the work belongs

| Module / priority | Before (current source) | Proposed workspace sections | Must remain separate or explicit |
|---|---|---|---|
| **Users / account links — first** | User creation can search and copy Employee fields; excluded matches show reasons, no direct linked-user URL. Users list is paginated. | Identity & account; linked Employee card; Role & security; assignment/scope; access history. Show authorized Open Employee / Open Linked User actions. | Employee payroll/bank data is not copied to User. Access grants, password changes, relinking and deactivation require explicit authority. |
| **Suppliers — finance pilot** | Supplier profile, bank/channel, payment terms, project links and payable control account exist; bills/payments are separate screens. | Profile; commercial/bank setup; served projects; bills; selected bill/payment drawer; payable history. Create allowed masters inline. | A payment must target the correct bill and obey Cash/Bank/Both, balance, date and posting rules. Recording an advance purpose is not a finished advance-accounting subsystem. |
| **Customers — finance pilot** | Contacts/notes are on Create/Edit; View read-only; invoices/receipts separate; allowed channels enforced. | Profile; office/site contacts; shared notes; projects; invoices; selected invoice/receipt drawer; ageing/statement. | Keep View read-only. Invoice approval and receipt posting are separate commands. No customer-switch on a child record. Do not rewrite historical receipt channels. |
| **AP bill / AR invoice — next** | Lines, approval, payment/receipt and correction spread across linked screens. | Header & lines; attachments where supported; approval/posting state; payments/receipts; journal/VAT references; audit/correction history. | Draft Save never posts. Finalized edits remain blocked. Reopen remains restricted, reasoned and reversing-entry based; settled/cleared/finalized-period rules remain intact. |
| **Projects / Locations** | Project master relates customer, manager, sites, warehouses, suppliers and staff; Locations have their own form/map. | Project overview; classification/customer; locations; staff; suppliers; warehouses; permitted purchasing/finance summaries. Add first location inline with project fixed. | Keep Project and Location as separate entities until owner decides otherwise. Geofence, attendance and warehouse scope depend on Location. Operational Phase 5 is not delivered merely by adding tabs. |
| **Purchasing: PR → PO → GRN** | Existing request/order/receipt screens have real state transitions. PO has quote files and discounted/VAT line totals. | Procurement record header; request; order/lines; quotations; partial receipts; linked stock/accounting evidence. Preserve selected supplier/project/location. | PR approval, PO approval and receiving remain explicit. Respect remaining quantities and repeat-receipt protection. Do not imply a complete AP-bill link exists without tracing schema/creation paths first. |
| **Items / warehouses** | Item/category/unit masters, stock-on-hand and stock ledger exist separately. | Item profile; balances by permitted warehouse; ledger/history; movement entry drawer. Warehouse profile; items/balances; receipts/issues/transfers/adjustments; linked location. | Quantity is changed only by the stock service and business movement, never by editing an on-hand number. Preserve weighted-average valuation and atomic entries. |
| **Stock movement records** | Issue, transfer and adjustment records have separate forms/history. | Header; lines; available balance; confirmation/posted status; ledger references; audit trail. | Transfer is one controlled source/destination operation. Enforce scope and insufficient-stock rules. A saved master or next-tab click must not move stock. |
| **Roles / permissions / assignments** | Role form has 7 actions; main matrix has 15; grouping and visible-only bulk selection already exist. Hierarchy has one parent. | Role definition; all supported permissions using one catalogue; assigned users; reporting relationships; workflow references. | Existing hidden permissions must survive saves. Multi-parent runtime approvals are a separate functional project, not a cosmetic second dropdown. Role inheritance must not expand scope accidentally. |
| **Organization / small masters** | Organization Structure hub; branch/department/designation pages and many inline dialogs already exist. | Compact authorized drawers in the hub or originating screen, with existing lists retained. | Do not seed invented company records or auto-create roles/users to fill empty fields. Preserve stable master codes and referenced records. |
| **Marketing leads / visits** | Lead, assignee, visit history, follow-up, won/lost, customer conversion and manager reports exist. | Lead workspace: profile; assignment; visit entry/history; follow-up; conversion result; audit. Reuse existing contextual parts first. | Conversion must not duplicate a customer; close/reopen rights and new statuses/reminder policy need decisions. |
| **Payroll runs** | Employee workspace contains salary and history; Payroll remains a batch process. | Run workspace: period/scope; employee items; preview/exceptions; explicit Process; explicit Approve; audit/exports already supported. | Employee Save is not payroll processing. Historical structures, rates and approved runs cannot be overwritten by profile edits. Do not promise payroll-to-GL posting without a separate source audit. |
| **COA / journal / cost center / posting rules** | Separate financial masters, journal editor and posting configuration. | Contextual account ledger/drill-through; journal header+lines+validation+posting state; inline permitted account selection. | COA trees and posting rules are shared company configuration. Restrict changes; never silently reclassify posted history. Journals require balanced entries and separate Post. |
| **GL / VAT / ZATCA / reports / activity** | Read/report screens plus explicit recalculate/retry/finalize-style actions where implemented. | Filtered read-only context, drill-through and return-to-origin; safe explicit commands where supported. | Do not turn the ledger or audit log into editable tabs. ZATCA retry is currently foundation-only, not verified live clearance. Reports retain global navigation. |
| **Phase 5 expenses / Phase 7 equipment & vehicles** | Reference packages and SOON entries, not operational implementations. | Future record-workspace design only after their business scope is approved. | Tabs and menu changes cannot substitute for missing migrations, services, endpoints, permissions and tests. |

## Recommended delivery order

1. **Close the September 24 Employee/account UX findings.** Direct linked-record navigation, agreed View/Edit/Deactivate presentation, no ambiguous identity matching; verify current asset deployment.
2. **Finance readiness audit and agreed month-end minimum.** Trace existing AP, AR, cash/bank, GL and VAT paths with the accountant. Identify real functional gaps before spending the entire period rearranging screens.
3. **Supplier workspace + selected bill/payment**, then **Customer workspace + selected invoice/receipt**. These share a pattern but must not share authorization assumptions.
4. **Project + Location**, then the procurement chain and inventory workspaces. Keep their state machines and shared financial effects intact.
5. **Roles/permissions alignment** can be a small independently tested correction; multi-parent/all-required approvals must have a separate schema/runtime design and migration plan. Prioritize earlier if it blocks month-end finance acceptance.
6. Marketing, organization masters and report drill-through polish; full EN/AR coverage across each delivered increment.
7. New operational phases only after owner/GPT architect confirmation. This is not a commitment to deliver all remaining modules by month end.

## Proposed screen pattern

```text
Supplier workspace — supplier identity and status stay visible
Profile | Commercial & Bank | Projects | Bills | Payment history
                         Selected bill drawer
                         Lines | Status | Payments | Journal reference
Cancel     Save & stay     Save & next     Save & close
                         [Approve] / [Record payment] = explicit commands
```

Do not hide essential master creation inside a separate module again. Each dropdown must identify whether it is a fixed enum, a shared master with + New, a contextual child selector, or a security-sensitive user/role reference. An empty list needs an explanation and an authorized remedy. Master dialogs say Save & select and leave the parent draft intact.

## Implementation design (proposal)

- Start with existing controllers, validators, policies/middleware and services. Map every read/write/action before extracting reusable presentation. Reuse accepted form fragments; avoid iframe embedding or copying controllers into a generic CRUD engine.
- Parent identity is route-bound, read-only inside each child panel and enforced server-side. A forged child ID or parent ID must fail, including on edit/delete/approve and file-download endpoints.
- Separate permission to view a tab, edit its data, create its master, approve, post, receive, export or deactivate. Never infer authority from the fact a user can open the parent.
- Lazy-load related sections. Cache unsaved forms client-side in the page, retain files across tab switches and failed AJAX submissions, and reset dirty state only for the successfully saved form.
- Save & stay returns the canonical saved record; Save & next advances only after successful validation/save; Save & close returns to the parent list with filters preserved and guards other drafts. Disable Next at the end. Do not add a fake Save to a read-only history tab.
- Make no blanket promise of retaining uploads across a native full-page validation redirect. Either keep the accepted behavior explicit or implement a tested AJAX/upload mechanism deliberately.
- Keep child saves small and transactional. Only actions that are already one business transaction should be atomic together. Avoid Save All across finance, stock, HR and account security.
- Protect against double submissions, slow saves, failed refresh after successful POST, stale versions and conflicting writers. Introduce durable idempotency/version handling where the existing domain requires it; disabling a button alone is insufficient proof.
- API responses contain only permitted fields and server-generated internal URLs. Arbitrary return URLs must not introduce open redirects. Confidential payroll/account fields must not appear in a general relationship picker.
- EN/AR only: translation keys, RTL layout, keyboard focus, labels and error summaries included in each patch. Database codes/permission keys remain stable.

## Sidebar and route policy

Reduce routine clicks, not the user's ability to see and audit global work. Keep primary entity lists easy to find. Group related global registers/approval queues under collapsible sections after the workspace passes UAT. Do not delete existing routes, bookmarks, report entry points or permission checks. A finance clerk may need all overdue invoices, not one customer's workspace.

The latest Employee feedback explicitly requires View/Edit/Deactivate rather than one generic Open button. Apply this correction in the pilot before standardizing other lists. Customer View must remain read-only regardless.

## Required acceptance for each module

- Before/after screenshot and field/action inventory approved; no accepted field or document preview lost.
- Equivalent old/new requests pass the same validation and produce the same financial/stock effects.
- Positive and negative module/action/scope tests, including direct endpoint calls and forged cross-parent records.
- No surprise save/approval/posting; historical records immutable where required; no duplicate posted transaction on retry.
- All save intents, unsaved navigation, modal cancel/Escape, stale records, 422/network failures, attachments and refresh-after-save tested.
- Relevant global lists/reports still reconcile with the workspace; permission filters preserve hidden grants.
- Arabic/RTL, mobile and keyboard review; authenticated staging sign-off by actual operators.
- Dedicated feature-branch commit, matching build, additive migrations only if justified, explicit rollback and backup instructions. Never use demo/reset seeders on production.

## Decisions to bring back from the backend architect

Which finance journeys are mandatory for month-end acceptance? Does that include payroll and statutory e-invoicing or only current finance foundations? Which entity should be the next pilot, Supplier or Customer? What are the approval actors/order/revision rules? What is the accounting treatment of advances and on-behalf payments? Which screenshots/steps comprise client acceptance? Keep those decisions separate from layout approval.
