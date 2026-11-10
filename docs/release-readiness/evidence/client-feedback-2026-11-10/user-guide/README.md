# Client feedback user-guide verification

Date: 10 November 2026.

Branch: `feature/seera-client-feedback-fixes-2026-11-10`. Starting HEAD: `4c5ab91`. This evidence covers the guide changes in the commit containing this file, not a fresh production check.

## Delivered

- Main user guide updated to version 1.23 with a dated November supplement and links.
- Easy-English client table: all 13 reported issues, changes, screens, check steps and status; 10 implemented, 1 partial and 2 pending.
- Existing English/Arabic entry instructions expanded with a quick screen/task table.
- Both supplements generated as self-contained HTML and added to the authenticated guide allowlist, home and navigation.
- Shareable single-page artifact generator includes the supplements and reads the current version.
- Generated tables scroll within their own keyboard-accessible regions; no global overflow hiding. Print styles preserve all columns and visible emphasis.

## Fresh checks for this guide update

| Check | Result |
| --- | --- |
| `php artisan test --filter=UserGuideTest --compact` | **4 tests passed, 88 assertions**, no failures. Covers guest and inactive-user access, allowed supplement pages, discovery links and refused Markdown/file access. |
| `npm run test:js` | **12 tests passed**, no failures. |
| `php scripts/validate-user-guide.php` | **8 guide pages, 597 internal links/anchors, 94 embedded JPEG images and 13 unique issue rows** passed. Existing screen/workflow IDs remain valid. |
| `node tests/manual/client-feedback-guide-browser.cjs <private synthetic fixture root>` | **71 browser checks passed**, no failures or JavaScript errors. See `browser-results.json`. |
| Browser coverage | Authenticated guide Home, main guide and both supplements; English and Arabic application sessions at **320, 390, 768 and 1440px**. No page-level horizontal overflow. The new client table is written in English and correctly declares `lang="en"`, including in an Arabic application session. |
| Table and offline checks | All 13 rows and 10/1/2 status split confirmed; mobile table scrolls locally; both supplements open offline and print without clipping the first table. |
| Screenshots | `issues-fixes-390.png` and `issues-fixes-1440.png`, generated from the synthetic read-only environment. No client media, bank details or actual user records embedded. |
| Single-page artifact | `build-artifact.php` generated successfully to a temporary local file; no hosted publication performed. |
| Formatting | Pint passed for all five touched PHP files; `git diff --check` passed. |

Only the two explicit supplement names were added to the existing guide allowlist. No new application business route, field, accounting rule or permission grant was introduced. No application JS/CSS bundle changed, so the existing release assets were retained. No full cumulative UAT rerun was needed for this guide update; previous 894-test application evidence remains separately dated.

## Remaining boundaries

The exact disputed IBAN still requires private bank-document confirmation. Live account/master-data inventory and approved cleanup are pending. Deployment and client acceptance are pending. No production connection, migration, seeder, reset, account deactivation, data deletion, deployment or ZATCA activation was performed for this update.
