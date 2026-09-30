# F08 source audit (before implementation)

Actual start: `9f8a045`, `feature/seera-connected-workspaces-2026-09-23`. The brief's
`06913c4` predates the verified reporting-parent/navigation patch; preserve both.
No live database inspection or rewrite is part of this sprint.

| Module | Current behavior / approver source | Builder used? | Single/multiple | Gap | Integrate now? |
|---|---|---|---|---|---|
| Purchase Request | Controller's authorized actor changes draft/pending to approved/rejected; approved_by/at; no accounting posting | No | Single | No per-step decisions, resolver, queue or snapshot | Yes, prospective runtime plus explicit legacy enrolment |
| Purchase Order | Controller actor changes draft to approved; approved_by/at | No | Single | No runtime history | No |
| Supplier Bill | AccountsPayableController locks draft, commits GRNI matches, calls PostingService, sets unpaid/journal link | No | Single | Workflow does not gate financial posting | No: preserve finance/F04 |
| Customer Invoice | AccountsReceivableController locks draft, posts accounting and creates local ZATCA record | No | Single | No configured steps | No |
| Leave | HR actor directly approves/rejects; approved_by/at/rejection_reason | No | Single | No immutable instance history | No |
| Overtime / EOSB | Module-specific direct approval (EOSB locked draft) | No | Single | No workflow runtime | No |
| Payroll | Processed run approved by current authorized actor | No | Single | No workflow runtime | No |
| Site Expense | Builder seed/reference only | No | Configuration only | Full module not implemented | Contract only |

Sources: `ApprovalWorkflowController`, `ApprovalWorkflow`, `ApprovalWorkflowStep`,
`Role`, `User`, `EnsureUserHasPermission`, `UserAccessScopeService`, `ActivityLog`,
the document controllers above, `SidebarMenu`, `routes/web.php`, `DatabaseSeeder`,
and migrations `2026_07_02_000005` / `000007`.

## Configuration actually present

Workflow: module, trigger_action, department_id, scope, auto_posting,
notify_requester, lock_after_approval, status. Step: step_no, approver_role_id,
optional approver_user_id, note, is_required, amount_limit, sla_hours,
escalation_role_id, can_reject, can_send_back. Builder explicitly says steps run in
step-number order and renumbers them uniquely. **No parallel/group field exists.**

Primary role hierarchy controls existing activity visibility. Additional reporting
parents are stored separately; neither kind grants document approval automatically.
Permission matrix already separates approve/reject. Workflow configuration uses
Roles CRUD permissions, not document approval permissions. Source records carry
project/site/warehouse global scope. No approval notifications/channel implementation
exists beyond User's general Notifiable trait and workflow configuration flags.

## Conservative implementation decisions

- Explicit workflow selection at Submit; no selection by display names or guessed
  priority. Validate module/trigger, department and scope. Existing seeded PR workflow
  contains amount limits: do not silently reinterpret these as skip/escalation rules.
  Reject enrolment with non-null amount limits until semantics are agreed/configured
  without thresholds. Do not invent new threshold UI.
- Sequential required steps only. Optional rows are snapshotted as skipped, with no
  approval authority. Each configured role row is a required approval slot; "Any
  assigned user" means an eligible member can satisfy that slot, not every user in
  the role. Explicit user also must hold the configured active role.
- Resolve and snapshot eligible actor IDs at submission, then recheck current active
  role/permission/scope at every decision. No new role holder silently joins an old
  instance. Empty required approver sets fail submission atomically.
- No requester or submitting actor self-approval. No Super Admin override of required
  role membership. No role-name or person-name special cases.
- SLA/escalation/send-back/notification flags remain visible snapshot metadata, not
  active jobs. No automatic posting, delegation, fallback approver or timeout action.
- New HTTP-created PRs require explicit runtime submission before approval. Existing
  rows retain legacy behavior and are labelled accordingly; explicit enrolment is
  possible only while editable. No backfill or fake decisions. Rejected runtime PRs
  can be corrected then explicitly resubmitted into a new instance; old history stays.
- Purchase Request view plus separate Approval History/view govern detailed history;
  approve/reject and workflow configuration remain distinct permissions. The additive
  migration grants history view to existing PR-view roles; it can be revoked independently.
  No generic history endpoint
  bypasses source-document view/scope.

The implementation/status report and tests must distinguish these policies from
unsupported parallel approvals, additional-parent expansion and future integrations.
