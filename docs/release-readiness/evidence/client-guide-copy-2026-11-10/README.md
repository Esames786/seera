# November client-facing copy cleanup

10 November 2026. Starting commit `03aa634`, guide-only branch `fix/seera-client-guide-backport-2026-11-10`.

Removed deployment, migrations, branches, local-QA and installation explanations from the November client feedback page and entry-instruction introduction. The two November pages now have a simple client-guide header/footer and navigation limited to guide Home and the November pages. The Home notice and summary use client-facing wording. Internal engineering and recovery records remain separately retained; no repository-wide document deletion was performed.

All 13 client points remain. Status wording is now **10 Ready for review, 1 Needs confirmation and 2 Pending**. The page clearly says prepared changes must be checked on the client's portal before acceptance; wording does not falsely certify the separate features as deployed. Bank-detail confirmation and account/master cleanup remain open.

Fresh checks:

- **4 Laravel guide tests passed, 89 assertions**.
- **91 synthetic browser checks passed**, zero failures/JavaScript errors, English/Arabic sessions at 320, 390, 768 and 1440px, no page overflow, working guide/portal links, all 13 rows and the truthful 10/1/2 split, print and offline opening.
- **8 HTML pages, 593 internal links/anchors, 94 embedded JPEG images and 13 unique issue rows** passed the static guide validator.
- Browser checks confirm technical deployment notes are absent from the client feedback body. Screenshots in this directory show the updated client text.

No application business behaviour, database schema, dependency, release ZIP or production environment changed. Production publication still requires the operator to update the guide-only branch.
