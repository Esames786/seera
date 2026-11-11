# November screenshot walkthrough — 11 November 2026

The existing `CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.md` and its HTML edition now include **21 real application screenshots**, representing 16 screen examples: all 13 November feedback IDs, the readable user action, and the earlier overdue-reminder/supplier-recoverable work. Five examples also have Arabic captures. Each example includes the point ID, change, navigation steps, canonical screen URL and honest pending/review status.

Captures use the existing attested disposable SQLite training fixture, at localhost only. Production was not accessed. No business form was submitted; login/locale are the only permitted POSTs. Blue profile images demonstrate the actual chooser/preview control using a fictional training PNG. The bank field is blanked before capture; private client bank details and original client screenshots are not published.

Record-specific example IDs remain in the internal capture manifest, while client links open the corresponding live list to select an authorized record. No training record ID is presented as a real live-record link. Existing login and permission checks still apply. New-feature URLs may require the approved feature rollout before they become available on production.

## Delivery

- Main file: `docs/user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html#screenshots-where-each-change-is`.
- The HTML embeds JPEGs so a single file can be shared and opened offline. No new public screenshot route or permission bypass was added.
- Markdown uses reviewed relative image paths for GitHub rendering; raw captures and SHA-256 manifest are in `docs/user-guide/november-screenshots/`.
- The builder only embeds JPEG/PNG files resolved inside that dedicated folder. Missing/outside files or unsupported MIME types stop the build.
- November Instructions and Guide Home link to the same issue/fix walkthrough.

## Verification

| Check | Fresh result |
| --- | --- |
| Screenshot capture | 21 images; zero JavaScript errors; zero business submissions |
| Laravel User Guide tests | 5 passed / 99 assertions |
| Screenshot integrity/render/access/URL/offline checks | 34 passed |
| Existing EN/AR guide, links, mobile and offline browser QA | 91 passed |
| Browser total for this guide change | 125 passed |

Images were visually inspected. Expanded captures fit 320, 390, 768 and 1440px; screenshot references are embedded in authenticated HTML and all 21 also load offline. The original 13-row status table remains 10 ready for review, one needing bank confirmation and two pending approved cleanup. A screenshot does not prove live deployment, cleanup or bank ownership.

The application controllers/models/routes, database migrations, Composer dependencies and Vite sources/build ZIP are unchanged by this visual-guide follow-up. The earlier full application evidence remains 936 Laravel tests / 10,855 assertions and 12 JavaScript tests. The complete application suite was not rerun for the documentation-only change; the affected guide tests and browser checks were run above.

No production deployment, migration, seeder, reset, scheduler activation or account deactivation was performed. The hosting candidate previously prepared at `063c42f` needs this documentation update to contain the new images; the still-live `9cc5ea0` does not contain them.
