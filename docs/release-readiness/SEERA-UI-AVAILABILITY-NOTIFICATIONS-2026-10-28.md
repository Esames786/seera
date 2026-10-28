# SEERA UI availability and notifications audit — 28 October 2026

Baseline: `dc048ff` on `feature/seera-connected-workspaces-2026-09-23`.
Isolated delivery: `feature/seera-topbar-comingsoon-2026-10-28`.
No database changes, production deployment, merge or ZATCA activation.

## Confirmed runtime inventory

- **Actionable approvals are real:** `admin.my-approvals.index` displays permission- and project-scoped actionable Purchase Requests, Site Expenses and Supplier Bills. It is **not** a general notification inbox or an email/SMS push service.
- **Topbar before this patch:** magnifying-glass search, bell and settings glyphs were decorative spans without links, actions, or inbox support. This patch removes the misleading controls and exposes a clearly named **My Approvals** link only for users permitted to view at least one supported approval module.
- **Coming Soon is real:** `SidebarMenu` still intentionally uses it for HR Reports, Project Reports, System Settings, and fallback missing inventory routes. A feature being planned must not be represented as implemented.
- **Coming Soon before this patch:** message incorrectly described the current ERP as only Phase 1 (Users & Roles) and Phase 2 (Dashboard & Master Setup). The message is replaced with neutral English/Arabic availability wording, with no fabricated ETA.

## Deliberately NOT built

- No notification table, notification feed, delivery worker, read/unread state, push transport or overdue customer alerts.
- No new HR/Project reports, System Settings module, fake placeholder charts or dummy information.
- No alteration of approvals, permission policies, finance, jobs or controller routing.

## Known requested follow-ups

1. **Actual HR Reports / Project Reports:** review agreed report definitions, output fields, access rules and source of truth before implementation. The screen remains Coming Soon until a working version is accepted.
2. **System Settings:** identify specific agreed settings and their ownership; many settings already live in dedicated screens. Avoid a second conflicting configuration store.
3. **Notifications:** define recipients, channels, triggering events, read/unread behavior and access scope. Existing My Approvals queue is not a substitute for general alerts.
4. **Equipment Arabic:** parallel branch `feature/seera-equipment-arabic-2026-10-28` modifies separate files and requires its own Laravel/browser QA.
5. **Sales VAT:** Claude's independently assigned branch; no overlapping changes were made here.

## Verification required on an isolated local/staging installation

```bash
php artisan test --filter=TopbarAvailabilityUiTest
php artisan test --filter=AdminScreensTest
php artisan test --filter=ReleaseArabicUiTest
php artisan test
npm run test:js
```

In a real browser, validate Arabic and English Dashboard topbar as Super Admin and restricted roles; click My Approvals and confirm scope, open HR Reports / Project Reports / System Settings Coming Soon for an authorized role, and confirm there are no fake notifications or broken links. Rebuild release assets only if the release includes changes requiring asset packaging. Record exact outcomes, not inferred PASS.

**As of this GitHub-only review:** source changes and test code pushed; PHP, database, full browser and physical-device tests NOT RUN. Do not merge/deploy until they pass.
