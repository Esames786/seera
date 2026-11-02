# SEERA visual training guide — completion and QA, 2 November 2026

**Decision: GO for owner/trainer review of the documentation. Not production deployment approval.**

Branch: feature/seera-user-guide-visual-examples-2026-11-02. Starting HEAD verified against GitHub: **1833270b542217c9668aff90314209fb9308b4af**. Base: **b88eec7ac79cd12b09197f4bc55f02dc61e0282d**. Work was completed in a separate worktree at D:/laragon2/www/seera-visual-guide-2026-11-02; the original checkout and its unrelated FABLE handoff/deployment-guide files were preserved.

## Deliverables

- English: [VISUAL-EXAMPLES.html](../user-guide/VISUAL-EXAMPLES.html).
- Arabic: [VISUAL-EXAMPLES-AR.html](../user-guide/VISUAL-EXAMPLES-AR.html).
- **43 application screens per language + four 390px mobile examples per language = 94 real screenshots**. The 43-screen supplement accompanies the existing 23-chapter operational manual and 28-workflow index; it does not claim a screenshot of every ERP action.
- Real sample-filled forms, actual selected fixture dropdown labels, working employee lookup/autofill, read-only View versus Manage examples, remaining-required-field lists, visible button labels and bilingual connected-workflow explanations.
- Every image is embedded in its HTML edition. Authenticated guide serving needs no broad image-directory route. The existing static page allowlist adds only the Arabic edition; auth/active middleware and business permissions/routes remain unchanged.
- Existing HTML home and three reference pages regenerated. Home links survive regeneration; old hardcoded v1.14/21-chapter/19-workflow metadata was corrected to reflect the source manual and current indexes.
- Local reproducible capture runner, attested synthetic fixture/router, screenshot scene definitions, guide validator, authenticated/offline browser QA and regression coverage.

The application screens cover Dashboard, Customer/Supplier, Project/Site/BOQ, Employee/User, Leave/Shift/Payroll, Roles/Permission Matrix, Items/Warehouse, PR/PO/GRN/stock movements, Supplier Bill/Customer Invoice/journal, Site Expense, Equipment, Marketing, reports and My Approvals. Credit/debit notes, allocations/refunds, attendance/overtime and detailed payment/settlement execution remain described by the full operational manual rather than a separate screenshot in this supplement.

## Privacy, data and write boundaries — VERIFIED

A new empty SQLite database was migrated and initialized with the repository's **synthetic demo seeders**, using a unique private temporary directory and random training credential/key. Preparation intentionally creates demo transactions and posted history through those seeders; it is not a production seeder run. No existing DB, .env, real browser profile or uploaded client files were copied.

During capture:

1. The local PHP router attests testing environment, exact disposable SQLite path, fixture identity and production ZATCA disabled.
2. Both browser interception and the PHP router reject business writes. Only login and locale preference POSTs are allowed. A deliberate business-write probe returned **405**.
3. No Save/Submit/Approve/Post/Pay/Receive/Issue/Transfer action was executed.
4. **96 business tables have identical row counts and complete row-content hashes before/after capture**. Authentication/preferences/audit/session/cache bookkeeping and SQLite internal tables are excluded explicitly; this is not a claim that logging in changes no database field.
5. All 94 final image hashes are recorded. Regenerating guide captions from reviewed captures verifies every image hash without recapturing or contacting the app.
6. No external origin was allowed through. **Eight Leaflet CDN asset requests were blocked** on the Site screens. Map rendering/GPS/device behavior is not verified, and no live map imagery was captured. Zero browser page errors and zero failed same-origin application responses were observed.

Images were generated only from known synthetic fixtures. Representative invoice, employee/autofill, supplier and guide overview images were visually inspected; all image sources/explicit field values and fixture provenance were recorded. Generated private credentials are not in screenshots/evidence/Git. The client package contains only reviewed HTML plus START-HERE, not databases, private JSON or source scripts.

## Exact final verification

| Check | Result |
| --- | --- |
| Real application capture and field/security checks | **736/736 passed**; 43 scenes × EN/AR; 94 screenshots |
| Business data invariance | **96/96 tables unchanged**; row counts and hashes |
| UserGuideTest | **3/3 passed, 52 assertions**, exit 0; 5.25s runner wall |
| JavaScript suite | **12/12 passed**, 0 failed/skipped; 209.8139ms |
| HTML/document validation | **Six pages, 551 internal links/anchors, 94 valid embedded JPEG images**, stable screen/workflow IDs |
| Final guide browser QA | **31/31 passed**, 0 page errors / failed guide responses |
| Responsive guide checks | EN/AR **320 / 390 / 768 / 1440px**, no page-level horizontal overflow |
| Portable package | **Seven entries**, all six HTML entries match source SHA-256; extracted EN/AR editions decode all images and switch language with network unavailable |
| Build archive / bundled runtime assets | Unchanged; no npm build required |

Browser QA includes guest redirect, authenticated EN/AR serving, actual language/direction, 43 scene sections per edition, 47 decoded images per edition, example data/coverage notes, both language links, all four existing guide pages, and offline extracted-package image/navigation checks. Laravel regression checks cover guest/inactive access, exact HTML MIME, embedded screenshots and rejection of arbitrary files/private filenames/image-directory paths.

Commands:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode focused -OpCache -Filter UserGuideTest
npm run test:js
php scripts/validate-user-guide.php
node tests/manual/user-guide-screenshots.cjs <private-fixture-root>
node tests/manual/user-guide-browser-qa.cjs <private-fixture-root> <private-extracted-package>
~~~

The focused PHP run used an ephemeral APP_KEY and SQLite :memory:, not the local app's DB/key. In this isolated worktree, the first attempt lacked an encryption key; the harness environment was corrected and all three tests passed. Browser harness corrections accounted for intended post-login redirects and forced eager image decoding during QA (the guide itself still lazy-loads pictures). These were verification setup issues, not reported application defects.

Full cumulative Laravel/business UAT was **NOT RERUN**: no financial, HR, stock, Equipment, approval, permission or scope implementation changed. No new MySQL concurrency probe, physical-device/GPS/offline test, Finance acceptance or native Arabic business sign-off is claimed. The PowerShell all-in-one launcher was syntax-checked; the equivalent preparation/server/capture/validation sequence was executed manually during this run. A separate complete launcher invocation was not repeated.

Evidence: [visual-guide-2026-11-02](evidence/visual-guide-2026-11-02). Capture JSON identifies the actual application snapshot at 1833270; business application views were unchanged from core b88eec7. The final documentation commit contains the corrected capture tooling and generated editions. File/source hashes distinguish tested files and captures from prior reports.

## Portable handover artifact

Local ignored export: storage/app/private/guide-exports/SEERA-TRAINING-GUIDE-2026-11-02.zip.

- Size: **8,874,032 bytes**.
- SHA-256: **240D13499716834932D59B2267F293CE7E931814A99889EB707AD27417C36076**.
- Extract into one folder and open index.html. Choose English or Arabic Visual Examples. The six HTML documents must stay together for relative guide links.
- The ZIP is a local handover artifact, not a production build or migration package. Source HTML/JPEGs and evidence are tracked on this feature branch; private fixtures and this reproducible archive are not committed.

## Integration restrictions and remaining acceptance

**This branch is based on core b88eec7 and does not contain the notification code deployed at 8dcb1d7. Do not deploy/switch production to this branch.** Review/integrate only its guide/controller allowlist/test/tool changes into a separate candidate based on the current production release. Do not merge or deploy as part of this task. Notification Center screenshots are absent; existing My Approvals is illustrated and remains distinct from reading a notification.

Some real Arabic-mode app labels remain English, particularly User/Project and finance/report screens. The training narrative is bilingual; screenshots faithfully show the app and do not pretend those gaps are translated. Client Arabic terminology review, trainer walkthrough, actual-device acceptance and notification screenshots on a future combined candidate remain pending.

No production access, configuration change, migration, seeder, merge or deployment was performed. Production ZATCA was not enabled or revalidated; local fixture configuration kept it disabled.
