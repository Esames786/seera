# Guide-only backport verification

10 November 2026. Candidate branch: `fix/seera-client-guide-backport-2026-11-10`, directly based on the owner's restored production revision `0aed8c791e4cd214d23a916026860585983d1cca`.

The supplied server diagnostic showed `page_allowed=false`, `html_exists=false` and `html_readable=false` on that old revision. This establishes the reported 404 cause without guessing at browser cache. This backport installs the two guide supplements and controller allowlist entries plus visible English/Arabic links, while retaining the stable application.

Fresh evidence:

- **4 Laravel tests passed, 89 assertions:** active login access, guests/inactive users refused, known-page allowlist, no arbitrary file exposure, guide home and portal shortcut discovery.
- **91 browser checks passed**, zero failures/JavaScript errors, against the stable-base candidate in an attested local synthetic SQLite environment. English/Arabic application sessions, Dashboard/guide widths 320/390/768/1440px, all 13 issue rows and status split, Contents shortcut, offline opening and print-table checks. The fresh screenshots include a documentation-only notice.
- **8 guide pages / 603 internal links and anchors / 94 existing embedded JPEGs / 13 unique client-issue rows** validated.
- Pint and `git diff --check` passed.

The new worktree initially lacked its test encryption key and extracted stable asset manifest. Those local fixture prerequisites were supplied before the final successful run. No production key, credentials, database or files were used or changed.

Only the existing guide controller, the topbar link, one Arabic label and documentation/guide validation tests differ from the stable base. Database migrations/models/services, accounting logic, Composer/npm manifests and locks, and public release assets remain unchanged. Existing built assets were extracted locally only for QA; the tracked ZIP was not rebuilt or changed.

The November feature descriptions are clearly marked as a separate candidate. Publishing the guide does not install those features, complete their migrations, resolve MyISAM transaction limitations or imply a fresh full-business UAT sign-off. No production deployment, schema change, seeder, reset or engine conversion was executed by this work.
