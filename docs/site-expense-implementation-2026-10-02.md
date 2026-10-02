# Site Expense sprint — implementation and verification ledger

Started 1 October; continued 2 October 2026. Branch `feature/seera-connected-workspaces-2026-09-23`, starting HEAD `f0a44be` (clean). No merge, deployment or production database access.

## Source audit and decisions

1. Reuse ApprovalRuntimeService through SiteExpenseApprovalSubject; only the server-side adapter registry and supported posting-mode validation are extended. PR behavior is unchanged. Reuse DocumentNumberService, PostingService balancing/VAT locks, SaveAction, user scopes, ActivityLog, Supplier Bill approval and the Project panel kit.
2. Add SiteExpense and private receipt metadata tables, explicit category `chart_of_account_id`, unique bill `site_expense_id`, journal/bill references and reversal/settlement metadata. Historical category account text is retained but never guessed into a GL mapping. Short indexes and rerunnable schema checks; forward-only financial migration.
3. Cash/Bank use the existing Finance payment catalogue (active asset control accounts 1110/1120), not arbitrary GL selections. Employee-paid expenses use shared liability 2310. Supplier Credit uses one draft direct/service bill; no Site Expense AP journal.
4. Net entry, server-rounded VAT and total. Existing VAT 15% categories default to 15; Non-VAT to zero. No blanket tax. Site staff can select applicable/not applicable but cannot override calculated VAT. Site Expenses post permission allows rate override. Category debit must be an active expense account. Project cost center is derived from one active `type=project, linked_id=project.id`; missing required or ambiguous mapping blocks accounting, not approval.
5. Workflow module Site Expenses, trigger Expense Submitted, mode Create Accounting Entry. Required sequential slots all approve; amount limits/parallel routing unsupported. Rejected record remains rejected while edited; explicit resubmit starts another immutable attempt. Completed approval commits before the accounting callback. A crash/failure leaves a retryable approved document.
6. Responsive single-screen web entry with scoped Project/Site, category, amount, payment, receipt and description. Mobile route checks Mobile Access plus create permission. No native app, GPS or offline claim.
7. Project Site Expenses panel and permission-gated summaries. Existing ProjectCostReport is unchanged: posted expense-account journal lines only. Supplier Credit enters cost via bill posting; settlement does not add expense cost; reversal nets it off.
8. Authenticated active-user routes, module/action permissions, scope-aware route binding, source-row mutex and fresh approval eligibility. Private JPG/PNG/PDF up to 10 MB; MIME and extension validation; scoped controller streams with no filesystem path exposure. No public receipt URL. Financial errors are shown only to Finance-capable users.
9. No unresolved contradiction blocks implementation. Existing posting rule Auto Post is authoritative: automatic final-approval attempt may produce a draft journal in review mode. It is NOT labelled posted until Finance posts it. No workflow/master settings are silently overwritten. Existing seeded threshold workflows must be reviewed explicitly. Employee payable code collision is retained, not repurposed; posting explains the conflict.

## Lifecycle and financial boundaries

- Draft -> pending -> approved_pending_posting -> posted; rejection -> corrected rejected -> new pending attempt. Draft cancellation retains the record. Posted cash/bank/unsettled reimbursement can become reversed through an opposite journal and a required reason. No financial hard delete.
- A generated Supplier Bill remains draft until existing Finance Approve creates its journal. A review-mode bill journal also needs Journal Entries Post. Source bill links back to the expense and its approved financial values cannot be edited/deleted independently. Existing unpaid-bill Reopen reverses its accounting and returns the expense to awaiting posting; a broader source-amendment/credit-note workflow is not supplied here.
- Employee settlement is one full reimbursement per expense, Finance-only with Site Expenses post + process. Dr original reimbursement liability, Cr selected Cash/Bank, no new expense/VAT. Source lock and stored settlement journal prevent duplicate payment. Partial/batch reimbursement and reversal of an already settled reimbursement remain unsupported.
- Expense reversal reuses the existing Finance opposite-entry/open-VAT-period correction policy. Closed VAT periods refuse correction. No credit note for a sealed period is invented.
- Pending errors are safe messages; retries reuse existing journal/bill. Failed accounting does not undo approved history. No new mail/SMS infrastructure: Activity, document status and My Approvals are the notification boundary.
- Site Expense view has explicit business-action forms; GET does not post or synchronize accounting. Bill/journal status synchronization runs after Finance writes commit.

## Verification ledger — final release gate

- Prior PR regression: 48 tests, 214 assertions, passed.
- First Site Expense pass: 25/26; fixture missing required employee first_name corrected.
- Expanded Site Expense + ProjectWorkspace + FinanceStateTransition: 62 tests, 553 assertions, passed.
- First full suite: 455 tests, 4,635 assertions; 453 passed, two existing VatPeriodLockTest cases failed because their current-quarter tenth-day fixture was future-dated on 2 October. Production date validation was correct and unchanged. The fixture now caps its open-period date at today; focused VAT rerun: 9 tests, 55 assertions, passed.
- Final full rerun: **455 tests, 4,648 assertions, zero failures**, 514.655 seconds. This includes all 42 SiteExpense cases and the existing Project/P2P/F04/Finance/HR/permission/guide regressions. Mobile category choices were aligned with the existing server visibility rule on both entry routes, with regression assertions.
- Disposable MySQL 8.0.30 / REPEATABLE READ: 5/5 concurrency scenarios passed; fresh/additive migration and rerun retained historical category data. Only the verified temporary localhost server was shut down after testing; no production/local application database touched.
- Chrome 390px/1440px: no horizontal overflow after a form-local header wrap fix; one/two columns, 44px minimum form buttons, zero browser JS errors. Temporary browser server stopped after verification.
- Guide validation: four pages, 405 internal links/anchors, stable EXP-SE-001–004 and WF-017/WF-018 IDs passed. `git diff --check` clean.
- After full-suite green, `npm run build:release` passed (Vite 8.1.3, 21.52 seconds; non-failing plugin-timing advisory). ZIP contains 18 verified files, root `manifest.json` and `assets/`; every archived file SHA256 matched the current build. `public/build.zip` SHA256: `2A2687E57C770649C5B762A4D43EA91C4428DE18D6C0B14CD4997413EC61DE65`.

## Operational setup (no production seeder)

Run the additive migration only after database/files backup; preserve production `.env` and `.htaccess`. It inserts reimbursement liability 2310 only if that code does not exist, and adds standard Site Expense permission rows without broad grants. Existing grants remain unchanged. Assign site staff view/create; editors edit, draft cancellation delete; approvers approve/reject; Finance post/retry/process as needed. Journal Entries and AP permissions remain separate.

Finance must explicitly map each usable category to an active expense account; configure Project cost centers if required; verify active Cash/Bank/Input VAT/AP and reimbursement liability; review Site Expense automatic posting rule (Auto Post on for immediate posting, off for review). Administrator must create/review active Expense Submitted workflow with sequential required reviewers, blank amount limits and Create Accounting Entry. Do not run DatabaseSeeder, migrate:fresh, or reset transactions.

## Remaining roadmap

Project Phase B is partial: Site Expenses implemented; BOQ/budget-line approval, labour/payroll-to-GL, equipment cost and progress remain separate. Runtime covers PR and Site Expenses only. Recommended next scoped sprint: Supplier Bill approval-runtime rollout (then PO/invoices/leave), preserving the current credit bridge and GRNI. GPS/geofence, HR/payroll GL, Equipment and BOQ require separate approved briefs; none are started here.
