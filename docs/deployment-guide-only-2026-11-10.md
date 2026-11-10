# Publish the client feedback guide on the rolled-back stable portal

The owner's terminal output confirms the live server was restored to `0aed8c791e4cd214d23a916026860585983d1cca`. Its old guide controller does not allow the November page, and that HTML file does not exist in the old checkout. This fully explains the reported authenticated 404; a login redirect alone did not verify the page.

Branch: `fix/seera-client-guide-backport-2026-11-10`, based directly on that stable revision. This is a separate documentation-only candidate, not the November feature branch.

## Scope

- Adds the two HTML/Markdown guide supplements and discovery links in the guide Home, Contents and navigation.
- Adds the Client Issues & Fixes shortcut beside the existing portal User Guide button, with its Arabic label.
- Adds only the two supplement names to the existing authenticated controller allowlist. Guest/inactive-user restrictions remain.
- Carries the guide generators, static link validator and guide access tests.
- Clearly marks the November features as a separate candidate, not installed by this documentation update.
- Leaves application migrations, models, services, financial rules, Composer dependencies and frontend assets identical to `0aed8c7`.

## Operator deployment

Use the pinned full commit reported with this candidate's delivery. First confirm the server is still on `0aed8c7` and inspect local edits. Fetch the guide-only branch, verify the reviewed target is its expected tip, and confirm it descends directly from the stable revision.

Preserve `.env`, server `.htaccess`, previous HEAD and the local patch in a private folder outside the web root. Permit only the known tracked `.htaccess` edit; stop for any other unexpected tracked/staged change. Review the target diff: only the guide controller, topbar, one Arabic label, documentation and guide validation/tests may change. Any migrations, other application PHP, dependency or frontend asset changes are a stop condition.

During a short maintenance window, switch to the pinned guide-only branch, preserve `.env`/`.htaccess`, clear Laravel caches and cache views. Confirm the HTML files exist and the controller allowlist accepts them, then bring the portal up. No Composer install, npm build, build ZIP upload/extraction, migration, seeder, storage-engine conversion or data reset is required for this candidate.

After signing in, open:

`/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html`

Check both entry points: portal **Client Issues & Fixes**, and **User Guide → Contents → Client Issues & Fixes**. Guest requests should redirect to Login. Existing stable business workflows retain their previous behaviour.

If the CLI sees the new controller/file but the browser still shows the old 404, verify the domain's actual document root and refresh that domain's web PHP/OPcache through the hosting procedure. A CLI OPcache reset does not reset PHP-FPM's cache.

## Recovery and separate database work

For a code rollback, return to the previously saved `0aed8c7` branch/revision, preserve the server configuration files and clear/cache views again. No SQL rollback or database restore belongs to this guide-only change.

The owner also supplied the complete engine inventory: only `notifications` and the partial `customer_documents` are InnoDB; the listed business/setup tables, including GL/AP/AR/stock/approval tables, are MyISAM. This is a separate significant production-readiness issue. Publishing these documents does not repair transaction/locking guarantees or authorize a database rebuild. The earlier local rebuild rehearsal failed and is not valid production restore evidence. Retain real data and use a separately reviewed, backed-up InnoDB conversion/recovery plan.
