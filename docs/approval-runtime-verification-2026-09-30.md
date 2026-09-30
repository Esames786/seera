# F08 Approval Runtime — verification and handoff

Implementation begun 30 September; release verification completed 1 October 2026.
Branch: `feature/seera-connected-workspaces-2026-09-23`.
Starting HEAD: `9f8a045` (newer than brief's `06913c4`; existing fixes preserved).
Implementation and test commit: `b31ab81`. Documentation, guide verification and
the rebuilt release ZIP follow in the release-documentation commit on this branch;
use the final branch HEAD from the handoff, not the implementation commit alone.
No merge, deployment, production DB connection or production data change.

## Measured quality gates

| Gate | Result |
|---|---|
| Final full `php artisan test --compact` | **413 tests, 4,349 assertions, 0 failures**; exit 0; 617.350 seconds |
| Focused ApprovalRuntimeTest | **48 tests, 214 assertions, 0 failures**; also included in final full suite |
| Earlier approval + inventory selection | 66 tests, 405 assertions, 0 failures, before four additional hardening cases |
| MySQL concurrency | **9/9 passed**, MySQL 8.0.30, REPEATABLE READ, separate worker processes with lock-wait IPC proof |
| Fresh migration | Passed on isolated SQLite full suite and disposable MySQL |
| Additive migration | Passed on SQLite and disposable MySQL; approved PR fields preserved, default legacy mode, no fake runtime history, history permission granted |
| Production assets | `npm run build:release` passed after final full suite; includes `npm run build` |
| Release ZIP | **18 files**, root manifest.json and assets/, every archived file SHA-256 checked against current build |
| Guide | Four dark HTML pages regenerated; combined HTML generator exercised locally; **396 internal links/anchors checked, 0 missing**; WF-017 renders as a table row |
| `/user-guide` compatibility | Existing authorization/path protections plus APR-001/002 and WF-017 HTTP checks pass in full suite |
| Formatting/patch integrity | Changed PHP formatted with Pint; `git diff --check` clean |

Build ZIP: `public/build.zip`.
SHA-256: `335F9C14C9A3E75174FAF9FDAF5D5856B8288DF8BDBA74959B54AEB00FC8D2AE`.
Build emitted a non-failing plugin timing advisory, not a missing-asset or compilation error.
Browser click automation and a live-server smoke test were not performed; rendered HTTP
tests, authorization tests, real database concurrency probes and the asset build are
the verification boundary. No claim of production acceptance is made.

## Approval tests and regression coverage

`tests/Feature/ApprovalRuntimeTest.php` covers source/config/eligible-user snapshots;
required role and explicit user resolution; sequential gating; all-required completion;
no requester/submitting-editor self-approval; unauthorized configurator; project/site/
warehouse scope; revoked permission/role/user/assignment and expired temporary roles;
no silent addition of later role holders; exact approve/reject retries and conflicts;
rejection reason/history; corrected new resubmission attempt; stale resubmit refusal;
pending edit/delete protection; foreign document/instance rejection; separate history
permissions; actionable queue filters; legacy direct approval and explicit enrolment;
unsupported/no-actor configuration; optional steps; no accounting side effects;
completion-failure transaction rollback; and literal `"0"` comment preservation.

The final full suite includes existing ProjectWorkspaceTest, PurchaseOrderWorkspaceTest,
InventoryTest, EmployeeWorkspace/RelatedWorkspace/Followup tests, SupplierWorkspaceTest,
CustomerWorkspaceTest, AccountingTest, FinancePostingIntegrityTest, FinanceAcceptanceScenariosTest,
FinanceStateTransitionTest, FinanceScopeAuthorizationTest, FinanceReportBalancesTest,
DeploymentHardeningTest and UserGuideTest. PR's old new-document single-approval test
now verifies that a new HTTP-created PR requires explicit runtime submission. The
legacy PR test still verifies its original authorized single-action behavior.

## Actual MySQL scenarios

1. Concurrent duplicate submission: one instance / one requested event.
2. Duplicate same-actor step approval: one decision / one event.
3. Competing role members: first decision retained, second conflicting actor refused.
4. Approve wins against reject: history is not rewritten.
5. Reject wins against approve: later approval is refused.
6. Next required step waits for first step's transaction, then completes correctly.
7. Concurrent final approval retry: one completion / no duplicate audit.
8. Concurrent resubmission: one new attempt, old rejected attempt retained.
9. Permission revoked while an approver waits: authorization is refreshed after source
   lock acquisition; decision refused, document remains pending.

Probe: `tests/manual/approval-runtime-concurrency.php`. It refuses cached app config,
non-loopback targets, wrong port, wrong real MySQL datadir and non-probe database names.
It creates a uniquely named database, uses synthetic actors/items only and does not
load app DB credentials into its configured connection. Latest disposable database:
`seera_approval_lock_test_cedef82dde75`; temporary datadir prefix
`seera-approval-lock-test-ae01f6bacf794fb0b962f8f944e5fc0b`. The dedicated MySQL server
was shut down after testing; its local temporary data is retained for inspection.
No running production/local application MySQL service was stopped or modified.

## Scope and release caveats

- Runtime AVAILABLE for explicit Purchase Request submissions only. New HTTP-created
  PRs cannot use the old direct approval. Existing records default to legacy and are
  not auto-enrolled. No other document approval/posting engine was replaced.
- Workflow builder supports ordered steps, not parallel groups. Required role slots
  each need one eligible member, or the explicitly assigned user; all required slots
  must approve. Reporting-parent links do not automatically become workflow steps.
- Current actor authorization is reloaded after acquiring the source mutex. Internal
  unscoped locking queries are followed by explicit source scope authorization before
  any read result or mutation is allowed; they are not public browse endpoints.
- Amount routing is undefined in existing builder semantics. Non-null amount limits,
  Branch Level workflow scope and auto-posting configurations are rejected for PR
  submission. Existing seeded thresholds are not silently changed. Owner must review
  and deliberately configure a supported workflow before new submissions can succeed.
- No parallel groups, delegation/reassignment, timeout escalation, send-back action,
  automatic reporting-parent expansion, email notifications or accounting posting.
  If all snapshotted users lose eligibility, there is no administrative bypass; review
  role assignments rather than granting a new actor silently into old history.
- Approval History/view is distinct from document view/approve/reject and Roles CRUD.
  Additive migration grants history view to existing PR-view roles; it is independently
  revokable. No production seeder needed. Do not run demo seed/reset commands.
- A source-only rollback after runtime transactions exist can re-enable legacy approval
  bypass. Prefer forward correction; coordinated code/data rollback needs owner review.

## Deliverables

- [Source audit and interpretation](approval-runtime-audit-2026-09-30.md)
- [Deployment / rollback instructions](approval-runtime-deployment-2026-09-30.md)
- [Site Expense integration/accounting contract](site-expense-runtime-contract-2026-09-29.md)
- [Connected workspace roadmap](connected-workspace-standard-2026-09-27.md)
- [Guide v1.3](user-guide/SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.md),
  [Screen Index](user-guide/SCREEN-INDEX.md), [Workflow Index](user-guide/WORKFLOW-INDEX.md)
- Dark HTML equivalents; stable existing IDs plus APR-001, APR-002 and WF-017.

Next recommended sprint: **FULL SITE EXPENSE MODULE + PROJECT PHASE B INTEGRATION**,
subject to the contract's Finance decisions. Site Expense schema/posting and Supplier
Bill approval integration have NOT been started. Stop at this boundary.
