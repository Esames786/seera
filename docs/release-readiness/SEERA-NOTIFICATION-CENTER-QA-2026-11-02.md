# Notification Center MVP verification — 2 November 2026

Branch: `feature/seera-notification-center-mvp-2026-11-01`.

Delivered HEAD **`d916ae072f372f0fdca3c58165f743c004890723`** and ancestry from **`b88eec7`** were verified after fetch. The unrelated untracked `docs/FABLE-HANDOFF-2026-10-08.md` was preserved. Verification crossed into 2 November, Asia/Karachi. No release branch merge or production operation was performed.

## 1. Review of all 12 delivered files

| File | Review finding |
| --- | --- |
| `database/migrations/2026_11_01_000001_create_notifications_table.php` | Standard Laravel UUID notification primary key, recipient morph columns/index, text JSON payload, nullable read timestamp and timestamps; adds its own table without altering financial/approval history. Down drops only this feature's table. Migration was exercised on disposable local SQLite, never production. |
| `app/Notifications/ApprovalActionNotification.php` | Database channel only. Stores event/module/reference/source identity/approval-instance identity, not money, document bodies, credentials or recipient permissions. Translation occurs when displayed, so changing the user's locale does not rewrite stored data. |
| `app/Services/Approvals/ApprovalRuntimeService.php` | Hooks run inside existing source-lock/DB transactions after submission/decision state changes. Exact submit/decision replays return before sending another event. Required sequential step selection and financial adapters are unchanged. |
| `app/Services/Notifications/ApprovalNotificationPublisher.php` | Only pending current step's snapshotted candidate IDs, with fresh active user/effective role/view/approve/document-scope checks. Later steps receive nothing early. Final approved/rejected event goes to the original requester, not merely the editor/submitter/decider; requester must still have view/scope. |
| `app/Services/Notifications/NotificationAccess.php` | Resolves each supported source through its existing approval subject/scoped query; current view permission is required. Added recipient ownership enforcement and corrected count/preview limits described below. |
| `app/Http/Controllers/Admin/NotificationCenterController.php` | Actor-owned relation queries, UUID read route, current visibility check, no acceptance of a posted user ID. Read is idempotent; Mark All updates only the actor's currently accessible unread notices. Inbox paginates 20 after filtering its bounded recent history. |
| `routes/web.php` | Notification GET/POST routes use web/CSRF, auth, active-account and forced-password-change middleware. They intentionally do not grant a new module privilege: source access is rechecked per notice. No arbitrary source URL/redirect is accepted from the request. |
| `resources/views/components/admin/topbar.blade.php` | Functional native-details bell, current unread count, scoped preview and inbox link. Fixed off-screen open dropdown at small widths; data stays escaped and long references wrap. No global overflow hiding added. |
| `resources/views/admin/notifications/index.blade.php` | Escaped event/reference/date/status, localized actions, actor-owned POST forms, preserved pagination and guarded My Approvals link. |
| `lang/en/notifications_ui.php` | All 17 UI keys cover bell/inbox/events/read actions/limits and describe approval-only MVP. |
| `lang/ar/notifications_ui.php` | Matching 17-key Arabic vocabulary; Arabic labels/actions exercised in browser. Client language acceptance is separate. |
| `tests/Feature/ApprovalRuntimeTest.php` | Delivered sequence/rejection assertions retained and expanded with eligibility, original-requester, rollback and replay coverage. One prior whole-page queue assertion needed scoping to the queue itself because bell history legitimately contains document references. |

The notification storage model is Laravel's existing `Illuminate\Notifications\DatabaseNotification`, reached through User's existing `Notifiable` trait. No separate custom notification/user model or financial table is introduced. Ownership includes both `notifiable_type` and ID.

## 2. Confirmed issues and corrections

| ID | Reproduced issue | Final correction |
| --- | --- | --- |
| NC-01 | Unread badge counted at most 100, so 101 accessible unread rows displayed 100. | Stream all actor-owned supported unread rows through current access checks; regression asserts 101 then zero after Mark All. |
| NC-02 | Bell scanned only the latest 100 stored notices, so an accessible row behind 100 hidden ones was absent even though the inbox's 300-row window could show it. | Preview and inbox share `NotificationAccess::HISTORY_LIMIT = 300`, with deterministic timestamp/ID ordering. Regression covers the hidden-row case. |
| NC-03 | `NotificationAccess::visible()` could return true for another recipient's row if the actor could view the same source. Controllers already constrained ownership, so no cross-user HTTP exploit was reproduced. | Central policy now also checks morph type and recipient ID. Foreign IDs remain 404 in controller and browser tests. This is defense in depth, not a claim that the original controller exposed others' notices. |
| NC-04 | Opening the dropdown went off-screen in EN and AR at 320/390/768px. Examples: EN 390px left −204.5, AR 390px right 625.9. Closed inbox remained within the viewport. | Fit the open panel to a 12px viewport gutter on toggle/resize; keep long text wrapped and vertical scrolling. Existing workspace/navigation actions unchanged. |
| NC-05 (test compatibility) | My Approvals filter/completion test searched the entire HTML for absence of a reference. The global historical bell may legitimately retain that reference after the task is filtered/completed. | Assertions now inspect the actionable-task collection and still check the rendered approve action. Queue rules are unchanged. |

Additional regression tests are in `NotificationCenterTest`, with notification assertions added to SupplierBillApprovalRuntimeTest and SiteExpenseTest. No approval, posting, payroll, tax calculation, original requester identity or project-scope policy was changed to make tests pass.

## 3. Verification results

| Run | Result |
| --- | --- |
| Requested original three classes on delivered feature | 127 tests / 820 assertions: **126 passed / 1 failed**, whole-page My Approvals assertion NC-05 |
| New controller/access regression reproducer before corrections | 10 tests / 62 assertions: **7 passed / 3 failed**, NC-01/02/03 |
| Final focused: ApprovalRuntimeTest, SupplierBillApprovalRuntimeTest, SiteExpenseTest, NotificationCenterTest | **142 passed / 927 assertions / 0 failures**, exit 0; 15.38 s runner wall time |
| Complete accelerated Laravel, 8 workers + CLI OPcache | **856 passed / 10,331 assertions / 0 failures**, exit 0; 459.83 s runner wall time |
| JavaScript (`npm run test:js`) | **12 passed / 0 failed / 0 skipped**, exit 0; 363.1143 ms |
| Initial browser run | **35 checks: 29 passed / 6 failed / 0 page errors**, all six failures NC-04 dropdown geometry |
| Final focused browser | **35/35 passed / 0 failed / 0 page errors**, exit 0 |

The three required classes were executed together through the runner filter; the final filter additionally includes NotificationCenterTest. Logs/JSON: [notification-center-2026-11-01 evidence](evidence/notification-center-2026-11-01). Original failures remain distinguished from final passes. A baseline browser-driver wait on a nonexistent zero-count badge was corrected to test element presence immediately; that delay was not an application error.

Commands used for final automated runs (local checkout, PHP 8.3.16):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode focused -OpCache -Filter 'NotificationCenterTest|ApprovalRuntimeTest|SupplierBillApprovalRuntimeTest|SiteExpenseTest'
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode parallel -Processes 8 -OpCache
npm run test:js
node tests/manual/notification-center-browser.cjs <private-disposable-fixture-root>
```

The browser helper requires an already prepared isolated fixture and local server; it is not a production setup command. Its default Playwright resolution uses another installed local workspace package; `SEERA_PLAYWRIGHT` can point to a compatible Playwright installation. Edge was used headlessly. The helper blocks external origins.

Transaction evidence includes an injected failure *after* a real database notice write: the submission/document/notice all roll back. Another failure after writing the requester's completion notice rolls back that notice and decision; retry produces exactly one completion. Normal submit, decision and rejection replay paths retain one event per transition. Supplier Bill and Site Expense tests assert recipient/next-step/completion behavior alongside their existing financial posting and retry checks.

Access/controller coverage includes: anonymous/inactive/forced-password-change refusal; foreign IDs; central ownership; role/view revocation; project and site changes; deleted/unsupported/malformed sources; escaped references; pagination; idempotent individual read; Mark All leaving foreign/hidden rows unchanged; matching EN/AR notice identity.

Browser checks use a separate copied SQLite fixture at **127.0.0.1:18772** with isolated cache/storage, `APP_ENV=testing`, sandbox and production ZATCA disabled. The new migration was applied only to those disposable fixtures. Browser helper operations deliberately create synthetic users/roles/PR/workflow/notices and change only fixture access; they are CLI-only and guarded against another environment/database path. No production notification or approval was created.

The 35 browser checks cover:

- Bell count 13, no foreign reference, foreign/malformed read IDs refused.
- Localized title/breadcrumb and read controls; open/closed layout at **320 / 390 / 768 / 1440px**, EN/AR, including long reference content and open dropdown bounds.
- Individual Read reduces count to 12; Mark All removes badge/unread controls.
- Second approver initially has zero; first approval and replay create exactly one next-step notice; second approval notifies original requester.
- Live permission revocation and project reassignment hide previous references/counts.

Selected final screenshots: [Arabic 390px](evidence/notification-center-2026-11-01/notification-ar-390.png), [English 390px](evidence/notification-center-2026-11-01/notification-en-390.png). Full Laravel tests use SQLite in-memory; no new MySQL concurrency/high-volume benchmark, physical-device or client language sign-off is claimed.

## 4. MVP usage and limits

The native bell opens recent history; View all opens the inbox. Read/Mark All record acknowledgement only and never approve a document. Use My Approvals to review actionable work. Completed/rejected outcomes are distinct from successful financial posting: an approval can complete while the existing accounting integration still requires a permissioned retry/review.

- In-app database alerts only: PR, Site Expense and Supplier Bill **runtime** approval events. Legacy approvals and unrelated payroll/document/stock alerts are not newly subscribed.
- No email, SMS, push, queue worker or external provider delivery introduced.
- No historical backfill. Existing pending tasks remain in My Approvals; a zero unread badge is not proof of no pending approvals.
- Inbox considers the latest **300 stored feature notices** and then hides those no longer accessible; 20 visible rows per page. Bell previews up to five accessible entries from that same history window. Badge counts all currently accessible unread feature notices, including older rows; Mark All acknowledges all such accessible unread rows. Older-than-window browsing/retention/search and high-volume optimization are outside this MVP.
- A historical notice may remain after its approval step is complete if the recipient can still view its source; it is not authorization to approve it. If view/role/scope is lost, its reference and actions are hidden.
- Client review of Arabic wording and actual-device acceptance remain pending; these automated checks are not owner UAT.

## 5. Migration, assets and decision

**Do not deploy or merge this feature without owner approval. Do not run `2026_11_01_000001_create_notifications_table` on production during this verification.** The bell queries the new table on authenticated pages; a future approved rollout must apply the migration under a reviewed maintenance sequence before serving this feature's code. The previous core code-only deployment runbook is insufficient for this feature. No production seed or historical notice backfill is required or authorized here.

No Vite CSS/JS bundle source changed. The viewport handler is rendered by Blade. Existing build archive SHA-256 remains **`B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`**; all **18 archive entries** decompressed successfully during verification. No new bundle build was required. Public assets and the core release branch are not changed by this review.

**GO for feature integration review.** No release-blocking defect remains in the verified notification scope. All required automated checks passed after the corrections above. Owner approval of integration, client acceptance and a controlled migration/rollout plan remain pending; this is **not production deployment approval**. Production ZATCA remains disabled.

### Fix files and evidence integrity

Application fixes: `NotificationAccess.php`, `NotificationCenterController.php`, `components/admin/topbar.blade.php`. Test changes: `ApprovalRuntimeTest.php`, `SupplierBillApprovalRuntimeTest.php`, `SiteExpenseTest.php`, new `NotificationCenterTest.php`. Local-only helpers: `tests/manual/notification-center-fixture.php` and `notification-center-browser.cjs`. Documentation/evidence are the remaining review changes; no equipment, accounting, permissions, routes, migration or bundled assets were modified by the corrections.

[source-sha256.json](evidence/notification-center-2026-11-01/source-sha256.json) records the reviewed delivered paths, final correction/test/helper contents and unchanged release archive. The Git commit containing this report identifies the exact reviewed final source. Full/focused/JS/browser result files and baseline reproducers are retained alongside it; absolute temporary fixture locations are local execution details, not a live environment claim.

Only this feature branch is to be pushed. Production migration/deployment/integration, release-branch merge, production configuration and ZATCA activation are outside this verification and were not performed.
