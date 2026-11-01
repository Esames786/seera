# SEERA — Notification Center MVP integration and rollout gates
**Prepared:** 2 November 2026  
**QA-approved feature HEAD:** `35c79e21046e81885be57d66fa9ce1c27900d671`  
**Previous release baseline:** `b88eec7ac79cd12b09197f4bc55f02dc61e0282d` (reported as deployed by the owner; actual live SHA needs rechecking).  
**Integration review branch:** `integration/seera-notifications-rollout-review-2026-11-02`.  
**Decision:** GO for *review and staging rehearsal only*. **NO-GO for production migration or release until the owner approves the exact SHA, backup, rehearsal and change window.**

## 1. QA evidence and exact scope

Source: `docs/release-readiness/SEERA-NOTIFICATION-CENTER-QA-2026-11-02.md` on feature HEAD `35c79e2`.

| Gate | Recorded outcome |
| --- | --- |
| Focused approval and notification tests | 142 passed, 927 assertions |
| Full accelerated Laravel suite | 856 passed, 10,331 assertions, 0 failures |
| JavaScript | 12 passed |
| Synthetic EN/AR browser QA at 320, 390, 768 and 1440px | 35/35 passed, 0 page errors |
| Approved scope | In-app bell, unread badge, dropdown, up to 300 recent inbox entries, 20 per page, read/read-all, approval requested/next approver/approved/rejected |
| Protection | Actor-owned notification IDs; current role, module permission and document/project/site scope rechecked before exposure; failed/replayed approvals do not produce duplicate notices |
| Out of scope | Email, SMS/WhatsApp/push, approval backfill, legacy approvals, low-stock, expiring documents, overdue invoice and leave alerts; no retention/search beyond latest 300 messages |

All the above are local automated/synthetic QA evidence; **not** production UAT, actual-device acceptance, or production database rehearsal. Empty notification bell just after rollout is normal: there is **no backfill**. My Approvals remains the source of actionable pending approvals.

## 2. Critical release dependency

A new migration `database/migrations/2026_11_01_000001_create_notifications_table.php` adds Laravel's notifications table. The bell is rendered in the authenticated topbar and queries this table. **Applying new application code before the migration is available may cause widespread HTTP 500 errors.** Use the approved maintenance/write-freeze sequence on staging first; never run the migration separately on live production without owner authorization.

The migration creates a new UUID-keyed notification table and does not modify accounting or approval tables. The migration's `down()` *drops the table*, destroying any notification history written since rollout. **Never call `migrate:rollback` as a blind rollback strategy**; prefer restoring the previously reviewed code while retaining the now-unused table, subject to operator compatibility checks.

## 3. Owner-approved staged rehearsal checklist

**Preflight (read-only unless otherwise stated):**

1. Record the real staging/live document-root location, `git rev-parse HEAD`, `git status --short`, PHP version, active branch and current web assets. Distinguish the reported baseline `b88eec7` from actual server evidence.
2. Confirm existing `.env`, APP_KEY, uploaded receipts/private storage, session/queue drivers, web-root overrides and `public/.htaccess`. Never print/commit secrets.
3. Record `php artisan migrate:status`; specifically verify whether `2026_11_01_000001_create_notifications_table` has run and whether a table named `notifications` already exists. If either differs from the rehearsal assumptions, STOP and reconcile; do not force a duplicate migration.
4. Record pre-change AR/AP/GL/GRNI/VAT control totals and current approvals counts from authorized read-only reports.
5. Obtain and **restore-test** a private DB backup and relevant uploads/configuration backup on isolated staging. Owner signs backup custody, checksum and restore result. This requirement is not satisfied by a message saying only “backup done.”

**Rehearsal (staging only, with operator authorization):**

6. Announce a write freeze/maintenance interval; account for mobile offline queues and background workers, avoiding lost writes.
7. Deploy the exact reviewed application SHA to staging, preserving private environment, uploads, server-only changes and APP_KEY. Dependencies use the existing reviewed Composer lockfile with production `--no-dev` policy. Do not overwrite local server modifications blindly or run `composer update`.
8. Under maintenance mode and on the isolated staging database, apply the specific reviewed migration as part of a **controlled** `php artisan migrate --force` once its pending list is verified. No seeders, backfills or unrelated data updates.
9. Verify the notifications table, PHP/Blade compilation, login/dashboard/sidebar/topbar, the notification routes, multilingual display, existing financial pages and service-worker/assets; build archive expected SHA-256 `B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E` if this exact archive is deployed.
10. With synthetic staging accounts, test PR → first approver notice → next approver notice → requester final outcome; Site Expense and Supplier Bill flows; mark-read; cross-user 404; role/scope revocation; mobile English/Arabic display; refresh count after reload. **Do not create dummy transactions in live finance.**
11. Reconcile financial control totals and verify existing approval history remained unchanged. Document browser errors, logs, exact migration status, timestamp and authorized operator.

**Production gate (still CLOSED):** Only after a successful staging restore/rehearsal, signed client/owner acceptance and an approved exact production SHA + change window may an authorized operator adapt the staged process for live rollout. Keep production ZATCA disabled; the official SDK/company simulation/VAT-rounding gates are independent.

## 4. Post-release smoke (when and only when approved)

| Check | Expected |
| --- | --- |
| Login / dashboard / project workspaces | No HTTP 500; no broken CSS/JS; existing permissions unchanged |
| Bell on first visit | Visible; unread count may legitimately be 0 |
| Existing My Approvals | Pending approval tasks remain visible regardless of unread count |
| New staged approval submission | Correct eligible *current step* recipient, no premature next-step notice |
| First step approval | Next eligible approver receives one notice |
| Final approval/rejection | Original requester receives correct one-time outcome |
| Read actions | Own notice only, mark-read idempotent, mark-all limited to currently visible/authorized actor notices |
| Access revocation | Cross-user IDs 404; revoked role or project/site scope hides sensitive references |
| Arabic / mobile | EN/AR labels, 320–1440px layout, dropdown within viewport |
| Financial control totals | Unchanged except for separately authorized business transactions |
| Compliance | Production ZATCA disabled, no external email/SMS provider activated |

## 5. Evidence + sign-off ledger

```text
Expected reviewed feature commit: 35c79e21046e81885be57d66fa9ce1c27900d671
Actual pre-change server HEAD: NOT VERIFIED
Actual environment/domain and document root: NOT VERIFIED
Notification migration pre-state: NOT VERIFIED
Backup checksum and private custody: PENDING
Isolated restore success: NOT RUN
Staging migration and approval walkthrough: NOT RUN
Read-only finance reconciliation: NOT RUN
Physical-device / Arabic business review: NOT RUN
Approved deployment commit and window: NOT AUTHORIZED
Owner release approval: NOT GIVEN
Post-release production smoke: NOT RUN
Production ZATCA: MUST REMAIN DISABLED
```

Use PASS/FAIL/BLOCKED/NOT RUN and attach anonymized evidence; never manufacture sign-off or put credentials, company tokens, private invoice XML, or financial customer data in GitHub.

## 6. Rollback and exception handling

If the staged migration fails, keep maintenance on and investigate the exact error before retrying. Do not replace live DB from an old snapshot while new writes may exist. If new code fails after a successful migration, the earlier reviewed application code can generally run with an unused notifications table, **but confirm dependency and state compatibility first**. Retain the table and its history for assessment; neither destructive drop nor secret/key reset is an automatic remedy. Escalate any mismatch in live revision, pending migrations, accounting totals or ZATCA settings to the owner for a documented NO-GO decision.

**Status:** Development/QA GO; staging and deployment approvals outstanding. This is a planning document, not authorization or execution.
