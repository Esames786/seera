# Visual training supplement — 2 November 2026

Open VISUAL-EXAMPLES.html (English) or VISUAL-EXAMPLES-AR.html (Arabic), directly from disk or under the existing authenticated /user-guide/ URL family after an approved integration. Both files embed every screenshot as a JPEG data URI: no extra public image route, external fonts or screenshot directory access is needed.

The supplement accompanies the existing 23-chapter manual, Screen Index and 28-workflow index. It is a representative visual walkthrough, not an image of every ERP button or a replacement for the operational manual.

## Coverage

- 43 screens in each interface language: Dashboard; Customer and Supplier Create/View/Manage; Project Create/View/Manage/BOQ; Site Create/View; Employee Create/View/Manage and salary; Leave, Shift, Payroll; User Create and actual employee lookup/autofill; Role and Permission Matrix; Item and Warehouse; PR/PO/GRN, stock issue/transfer; Supplier Bill, Customer Invoice and balanced journal draft; Site Expense; Equipment; Marketing; HR/Project/Trial Balance/VAT reports; My Approvals.
- Four additional 390px full-page mobile screenshots per language: Dashboard, Customer Create, Supplier Create and Employee Personal.
- Verified sample fields and selected fixture dropdown labels; remaining required fields are listed instead of pretending every example is ready to submit.
- Bilingual explanations of View versus Manage, save/stay/next/new/close, previous/back, quick-create, distinct approval/posting/payment actions and connected workflows.
- Worked arithmetic examples preserve existing Finance calculation/rounding policy. No transaction or ZATCA document is created by form capture.

## Critical source/integration boundary

This branch starts at 1833270, based on **core b88eec7**, and does **not** include the notification candidate deployed at 8dcb1d7. Captured forms use the actual core implementation and existing translations. Do not check this branch out on production or merge it as a replacement release. Integrate only reviewed guide/controller allowlist/test/capture changes into the current release candidate through a separate approved integration review. Notification screenshots remain outside this supplement.

No financial or workspace implementation is changed. Some Arabic-mode application headings/fields remain in English (notably User, Project and finance/report areas). The Arabic training narrative is translated; screenshots preserve the real UI and do not disguise existing gaps. Native Arabic review and actual-device acceptance remain client tasks.

## Reproduce locally (Windows / Laragon)

Use a separate checkout with its own normal Composer vendor installation/copy and extracted reviewed public/build.zip. Do not use a shared vendor junction with another branch's optimized App classmap. Supply an installed Playwright module; no production dependency is added.

~~~powershell
$env:SEERA_PLAYWRIGHT = 'D:/laragon2/www/property_handle/node_modules/playwright'
$arguments = @{
  Php = 'D:/laragon2/bin/php/php-8.3.16-Win32-vs16-x64/php.exe'
  Node = 'D:/laragon2/bin/nodejs/node-v20.20.1-win-x64/node.exe'
}
& scripts/capture-user-guide.ps1 @arguments
~~~

The runner creates a uniquely named private temporary folder, new empty SQLite database and fresh key, initializes **synthetic local demo seeders**, starts a loopback-only PHP server, captures, validates, stops only its own server, and restores its process environment. It never copies .env or connects to the existing local/production database. If the chosen port is occupied, it stops without touching that server.

Preparation intentionally creates demo fixture records, including demo posted accounting/stock history through existing demo seeders. These are not production transactions. During subsequent capture, browser interception and PHP router deny every write except POST /login and /admin/locale. A deliberate business-write probe must return 405. No Save/Submit/Approve/Post/Pay/Receive/Issue/Transfer button is clicked.

The direct Node script now requires this attested disposable fixture plus SEERA_GUIDE_CONFIRM_SYNTHETIC=YES. Old username/password-only instructions are insufficient. Arbitrary localhost, staging and production capture are refused.

## Evidence and publication

docs/release-readiness/evidence/visual-guide-2026-11-02/capture-results.json records source SHA, scene/field checks, image hashes, browser errors and before/after business-table hashes. Authentication/preferences/audit/cache/session bookkeeping and SQLite internal tables are excluded; actual business table rows must remain identical. Audit snapshots are saved even if the final invariant fails.

Inspect images for real names, bank details, documents or secrets before publication. This run uses only seeders and a generated training account; no production database/browser profile/uploads were copied. Private fixture JSON contains its random password/key and must never be committed. Evidence contains no such credentials. JPEG originals are retained for audit; served HTML embeds them.

Capture renders both visual editions. Then php docs/user-guide/build-html.php regenerates the four existing guide pages without replacing the visual editions. php scripts/validate-user-guide.php verifies six pages, internal links/anchors and all 94 embedded images. Browser checks must exercise authenticated access and both editions at 320/390/768/1440px.

No migration, seeder run, npm asset build or external delivery service is required on production for a later approved guide-only integration. This does not authorize deployment.
