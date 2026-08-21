# Seera Deployment Hardening

**Date:** 2026-08-22  
**Baseline:** `8f32d7283b79fe689845dbe80c0eb24364151d5c` plus the pre-existing client-change worktree  
**Result:** The release blockers in `full-system-test-report-2026-08-22.md` are fixed and covered by regression tests.

## Fixed release blockers

- Every admin route now enforces the existing role/action permission matrix.
- Inactive, pending, and locked users cannot log in or retain an authenticated session; login is rate limited.
- Project, site, and warehouse access scopes constrain lists, reports, relationships, and route-model binding.
- Sidebar links are permission-aware and no longer advertise inaccessible modules.
- Filtered permission-matrix saves preserve permissions that were not rendered.
- Supplier payments and customer receipts lock their source document and recheck the live balance inside the transaction.
- Accounting and inventory posting operations lock their source document; warehouse stock rows are locked for every movement.
- Stock adjustments calculate the document, ledger, and journal from the same live movement.
- Document numbers use a locked database sequence instead of `count() + 1`.
- Trial balance, balance sheet, and profit/loss include chart opening balances.
- Project cost reporting uses grouped aggregates instead of per-project queries.
- The five-year resignation boundary now remains in the one-third tier; Article 87 marriage and childbirth reasons can be recorded; the UI requests final wage rather than basic salary alone.
- Approved EOSB settlements are immutable and cannot be deleted.
- Employee relation pairs are server-validated, employee plus attachment writes are atomic, uploads are MIME-restricted, and HR documents are private authenticated downloads.
- The Employee Documents register has no edit, update, or delete routes.
- Existing employee documents are moved from public to private storage by migration.
- The tracked production asset archive was rebuilt from the current Vite output.

## Verification completed

- `php artisan test`: **107 passed, 682 assertions**.
- Deployment-hardening regression suite: **7 passed, 37 assertions**.
- Fresh MySQL `migrate:fresh --seed --force`: passed.
- `npm run build`: passed.
- `php artisan optimize`: config, event, route, and view caches passed; caches were cleared after verification for local development.
- `git diff --check`: passed.

## Deployment boundary

The implemented phases are a deployable release candidate. The following intentionally remain outside this release: Phase 5 site expenses, live ZATCA clearance, Arabic/RTL, and sidebar badge caching. They are product-scope gaps, not hidden implementations.

Before production cutover, take a database and uploaded-file backup, deploy to staging with production MySQL settings, run migrations, build assets, run the full test suite, and exercise concurrent payment and stock-posting smoke tests from separate clients. Then run `php artisan optimize`. This release has no dispatched queue jobs or scheduled tasks, so no worker or scheduler process is required yet.
