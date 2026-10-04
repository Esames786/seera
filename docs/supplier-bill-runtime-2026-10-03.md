# Supplier Bill Runtime — source audit and implementation ledger

3 October 2026. Branch `feature/seera-connected-workspaces-2026-09-23`; starting HEAD `eedc786`, clean. No deployment, merge or production DB access. Approved brief: Supplier Bill Runtime + Site Expense credit bridge + Finance workspace; stop before Wave 2.

## Supplier Bill runtime audit (before implementation)

1. Legacy bill lifecycle is draft → unpaid → partially_paid/paid; cancelled/approved are also existing catalogue values. No creator or independent approval status exists. Do not replace financial status with pending/rejected.
2. AccountsPayableController::approve locks bill, calls GrnMatchingService::commit, then PostingService::postSupplierBill, then records unpaid/journal/balance in one transaction. Extract exactly this orchestration into one reusable bill posting service; retain PostingService accounting formulas.
3. Edit/delete check isEditable; payment checks financial status only. Bill UI incorrectly labels any journal as Posted, including draft review journals. Runtime needs separate approval metadata and actual posted-journal gating.
4. F04 commits under bill then GRN-line locks; increments invoiced quantity only during accounting transaction. Draft matches are NOT reservations today: competing drafts can hold the same quantity until one approves. To protect newly submitted bills without redesigning F04, add a nullable reservation marker to matches, reserve under ordered GRN locks on submit, and subtract other uncommitted reservations at commit. Legacy unsubmitted drafts retain optimistic matching. Rejection retains reservations; corrections replace their reservations transactionally. No early invoiced quantity/AP/VAT.
5. Reopen is Super Admin only, unpaid/no payments, withdraws VAT under open-period lock, reverses journal and releases F04 quantities. Runtime history must remain; mark correction state requiring a new explicit attempt, never reuse old approved authority. Site Expense source financial values remain independently immutable.
6. Payment replay already locks bill and compares immutable request data by idempotency key. Preserve it. New payment must additionally require a posted journal; approval alone or a draft review journal is insufficient.
7. Site Expense credit bridge creates one uniquely linked draft bill, no expense AP journal. Existing bill/journal after-commit hooks update expense posting state. New bridge bills will require their own runtime; old bills/history are not silently enrolled.
8. Safest integration is a SupplierBill subject adapter plus optional lifecycle hooks for separate pending state and explicitly reopened approved attempts. Keep PR/Site Expense default semantics; no new approval tables or parallel engine. Final approval commits before one existing-path accounting attempt, with source-locked retry.
9. Additive bill approval_mode (legacy for existing data), approval_status, requested_by, last_edited_by, rejection_reason and posting_error; match reserved_at; missing AP permission rows only. Controller-created and new bridge bills explicitly use runtime. Existing drafts enrol only through Submit. No fabricated history, data resets, broad grants or production seeder.
10. Risks/tests: stale edits vs final approval; exact/conflicting decisions; reservations vs competing bill; review-mode payment; posted journal immutability; reopened old approval reuse; VAT failure rollback; credit bill/AP duplication; direct/matched/mixed price variance; scoped queue/POSTs; legacy approval; PR/expense/Project/P2P/settlement regression. Real disposable MySQL required, not SQLite-only concurrency claims.

## Policy boundaries

- Required sequential workflow slots all approve; role slot candidates are eligible alternatives, not an invented all-users-of-role vote. Parallel groups and amount limits remain unsupported.
- New bills require runtime. Existing legacy drafts retain their explicitly labelled legacy path until Submit enrols them; there is no fallback once runtime-mode. Historical paid/posted bills are untouched.
- Rejected reservations are retained until correction; no implicit expiration or competing consumption. Existing F04 accounting calculations and Project cost formula remain unchanged.
- Runtime approval and financial recognition are separate. Existing Auto Post setting remains authoritative; draft review journals require explicit Finance posting before payment.

## Implementation and verification ledger (updated 5 October 2026)

Implemented on the same feature branch; no merge, deployment, production DB access or Wave 2 work.

- Existing `PostingService` accounting formulas are unchanged. One `SupplierBillPostingService` extracts the existing orchestration; the adapter calls it after approval commit. Finance retries use the same source mutex and posting path.
- AP metadata is additive; `SeparateApprovalState` is an optional adapter capability, so PR/Site Expense status contracts are preserved. Creator, submitting editor and last editor are excluded from bill approval snapshots and decisions. Workflow activation/submission rejects unsupported configuration.
- New bill workspace Approval section, unified My Approvals, separate financial/approval labels in Supplier/PO/Project context, linked credit-expense bill state, safe review-journal controls and actual-posting payment gate are implemented. Pending/approved bill fields freeze; old history survives rejection, failures and Reopen.
- Legacy Finance/F04 regression fixtures explicitly identify their pre-runtime bills; no production fallback or authorization bypass was introduced to make tests pass. The Site Expense credit regression now executes separate two-step bill runtime approval and verifies one journal/VAT entry and one project cost contribution.

| Gate | Measured result |
|---|---|
| Focused runtime/F04/Finance/Site Expense/PR/settlement/VAT/Supplier/PO selection | 208 tests, 1,929 assertions, PASS |
| Final targeted runtime/bootstrap/user-guide rerun | 34 tests, 321 assertions, PASS |
| First full suite | 481 tests; 480 passed; one obsolete bootstrap fixture attempted legacy approval for a newly created runtime bill. Fixture corrected to explicitly test historical legacy posting |
| Final full suite | 481 tests, 4,869 assertions, zero failures, PASS (4,315,577 ms) |
| Final 5 October disposable MySQL 8.0.30 | 8/8 runtime concurrency scenarios PASS, REPEATABLE READ (including distinct final approvers) |
| Additive MySQL migration / rerun | PASS; existing historical paid bill attributes preserved, legacy mode and no fabricated history |
| Release build | Final rebuild after the full green suite: `npm run build:release` PASS; 18 archive files |
| ZIP integrity | Actual `unzip -t public/build.zip` PASS; root `manifest.json`, `assets/` (plus fonts manifest) |
| ZIP SHA-256 | `09BC94EF2D0A9BAEA5682B699027AA547A24CD16594939B76E7260B89EF6E129` |
| Authoritative HTML | Four pages, 408 internal links/anchors PASS; existing dark theme and `/user-guide` compatibility retained |
| IDs | No Screen IDs added/renumbered; FIN-AP-003/related context and APR-001 updated. WF-019 added after verifying WF-018 was highest existing ID |

### Real MySQL scenarios

`tests/manual/supplier-bill-concurrency.php` requires a private loopback server on port 33479 and an explicit `seera-bill-lock-test-*` datadir. It verifies `@@datadir` BEFORE writes, creates a new uniquely named synthetic database and does not use application/production credentials. The optional `shutdown` action rechecks the same identity before stopping only that probe server. The first rerun connection was attempted before slow startup finished; after the server reported ready, all scenarios passed.

1. Duplicate final approval: one completed history, one source journal/AP/VAT and one GRN quantity consumption.
2. Final approval versus Finance retry: approved state commits independently; only one posting wins.
3. Final approval versus a stale draft edit: current locked state rejects the stale financial mutation.
4. Final approval versus a competing legacy matched bill: pending reservation/committed quantity cannot be stolen; competitor remains draft without accounting.
5. Two retries after a real inactive-account posting failure: approved history retained and accounting created once after configuration correction.
6. Final approval versus Reopen: early request safely refused, or an eligible posted/unpaid correction reverses once and keeps old approved history. Old approval cannot authorize correction reposting.
7. Payment while posting remains pending: request blocks on the real bill mutex and is refused; no payment, journal, VAT or quantity consumption.
8. Two different eligible Finance users race the same final step: the losing actor receives a decision conflict; one completed history and one journal/AP/VAT/GRN consumption remain. This supplements, rather than replaces, exact duplicate-click coverage.

Each contender demonstrably waits on the InnoDB bill/GRN row lock using a separate PHP/MySQL connection and IPC barrier. This is not a SQLite concurrency claim. HTTP permission/scope boundaries, direct/matched/mixed accounting, price variance and review-mode payment are additionally exercised by feature tests. No production data was inspected or altered. Browser automation was not run for this sprint; UI evidence is rendered HTTP tests plus the asset build, not a claimed live browser acceptance test.

## Reviewable commits

- `78e61bd` — additive approval state and optional subject lifecycle contract.
- `3deca74` — bill adapter/runtime, existing posting extraction, reservation/financial guards and routes.
- `d989d48` — approval workspace, unified queue and Supplier/Project/P2P Finance context.
- `4cca078` — separate Site Expense supplier-credit bill approval and linked-state wording.
- `d93999c` — focused runtime coverage, explicit legacy regression fixtures, Site Expense bridge test and seven real MySQL races.
- `1d05253` — eighth real MySQL race verifies two distinct eligible final approvers without duplicate accounting.
- The documentation/build commit follows these on the same feature branch. The final delivery report identifies the pushed tip; no merge or deployment is implied by these local commits.

## Deployment and next step

See [deployment instructions](deployment-supplier-bill-runtime-2026-10-04.md): back up privately, preserve server `.htaccess`, fast-forward the feature branch, validate/extract the committed build ZIP, clear caches, run the one additive migration, view-cache and bring up only after successful commands. **No production seeder.** Administrators must configure eligible bill workflows and explicitly grant new post/retry/reject permissions where appropriate. Review journals need separately authorized Journal Post before payment. Rollback is not automatic/destructive schema reversal; after runtime history exists, old-code-only checkout is unsafe.

Remaining unsupported: parallel groups, amount thresholds, delegation, automatic parent expansion, SLA/escalation and notification jobs. Runtime is available for PR, Site Expense and Supplier Bill only; PO/AR/Leave/Payroll are not integrated.

Next recommended (not started): Wave 2 A Users + Sites; B Items + Warehouses; C Customer Invoice document workspace; D Employee-centered Attendance/Leave/Overtime/Payroll optimization. See the current-status section of `connected-workspace-standard-2026-09-27.md`. The final full suite is green; the delivery report records remote push verification separately.
