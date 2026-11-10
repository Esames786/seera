# Visible client-feedback guide links

10 November 2026. Starting commit: `0a1a01a`. Evidence covers the working-tree change committed with this file; production was not updated.

The portal topbar now has a direct **Client Issues & Fixes** link beside **User Guide**, translated as **ملاحظات العميل والإصلاحات** in Arabic. The generated guide pages also show **Client Issues & Fixes** at the top of the left Contents menu. Existing Home-table, main-manual and navigation links are retained. All links target the existing authenticated page; no public access bypass was introduced.

Fresh verification:

- `UserGuideTest`: **4 passed, 88 assertions**.
- Static guide validation: **8 pages, 603 internal links/anchors, 94 embedded JPEG images and 13 client issue rows** passed.
- Attested synthetic browser QA: **91 checks passed**, zero failures/JavaScript errors. Includes both application languages; topbar translation and guide contents links; Dashboard and guide widths **320, 390, 768 and 1440px**; no page-level overflow; existing offline/print checks.
- `browser-results.json` records individual checks. The two screenshots show the guide with its new Contents shortcut. Previous 71-check evidence remains unchanged in the sibling `user-guide` directory.

No migration, seeder, accounting rule, database-engine change or frontend bundle change is part of this link update. The earlier reported live 404 is not independently resolved by local link QA; installed controller/file/cache state still needs verification on the server.
