# Seera cumulative deployment review - 25 October 2026

**26 October follow-up:** the defects below have corrective local changes and regression evidence in [deployment-fixes-2026-10-26.md](deployment-fixes-2026-10-26.md). This report records the original `d66a6ea` review; it does not certify an uncommitted working tree as a deployed release.

## Decision: HOLD production deployment

Do not deploy the current candidate `d66a6ea` to the live ERP yet. Three new diagnostic regressions reproduce release-blocking defects. One affects customer refunds independently of ZATCA, so leaving ZATCA disabled is not sufficient to clear this release. Staging verification is appropriate; production approval requires fixes and a new release gate.

Live ZATCA activation is also NOT approved: company simulation, official SDK verification, owner-confirmed identity and a Finance VAT rounding decision remain outstanding. Sandbox success is not company production readiness.

## Scope and release identity

- Review date: 25 October 2026, Asia/Karachi.
- Last deployment evidenced by the owner's terminal output: `4d77c77` on October 14. No new live-server inspection was performed; the server may have changed since that output.
- Current local and remotely verified tip: `d66a6ea07cee0eca22a875279b3ed68382c46714`, branch `feature/seera-connected-workspaces-2026-09-23`.
- Cumulative range: `4d77c77..d66a6ea`, 25 commits, 159 changed files. Includes October 14 offline foundations needed by the October 15-24 work.
- The owner's final-gate attachment only describes `e16e6a2..d66a6ea` (five code/docs commits). Its "no migration / no bundle step" statement is NOT the deployment instruction for a server still on October 14.
- This is a targeted code, test, artifact and release-risk review, not an exhaustive security/compliance audit of every line.

| Work delivered | Endpoint / evidence |
| --- | --- |
| Offline mobile attendance and site-expense queue, replay protection, service worker/PWA | `a2b8d4d`, migration dated October 15 |
| ZATCA XML, credentials, onboarding, chain/signature/QR, clearance/reporting screens | `b75ca46` |
| Sandbox-driven cryptographic corrections and evidence | `1d8507b` |
| Customer credit/debit notes, AR/VAT/GL correction and ZATCA note flow | `fef2152` |
| Credit allocations, customer refunds and reversals | `e16e6a2` |
| Bilingual invoice/note printing, expiry readiness and release documentation | `d66a6ea` |

## Confirmed blockers

### G01 - High: refund consumes credit without a posted accounting journal

Sources: `app/Services/Accounting/CustomerCreditResolutionService.php:181-192`, `app/Services/Accounting/PostingService.php:692-720`.

Reproduction: start with a posted credit note containing SAR 230 available credit. Set the active Customer Receipt automatic-posting rule to review mode (`auto_post=false`). Refund SAR 100. The request succeeds, available credit becomes SAR 130, and the refund's journal remains **draft**. The posted GL has not recorded the payment while the sub-ledger reports refunded credit. The new refund rule has the same exposure when configured for review mode.

Required correction: use an explicit pending/refund-finalized lifecycle or reject/roll back a refund that cannot produce a posted journal. Do not silently override Finance review policy. Only completed posted refunds should consume credit as paid; reservations, if introduced, must be separate and explicit. Add regression coverage for direct refund rules, receipt-rule fallback, retries and reversal. Inspect related credit-source journal eligibility too; the current source check uses document status, not journal status.

### G02 - High: an in-flight ZATCA submission can be claimed again

Sources: `app/Services/Zatca/ZatcaSubmissionService.php:171-224` and subsequent response writes.

`submission_pending` is an allowed entry state. A fresh second request sees the incremented attempt counter and successfully claims the pending row again. The compare-and-swap only blocks callers holding an older snapshot; it does not prevent a new caller during the HTTP request.

Reproduction: mock the first HTTP call; while it is in flight, submit the same document through a fresh model. The nested submission returns CLEARED; the original returns a duplicate rejection. **Two calls occur and final stored state becomes rejected**, overwriting the accepted result.

Required correction: reject active pending submissions; use a deliberate expired-lease/outcome-unknown recovery path for abandoned requests. Fence every response update by the claim/attempt identity and preserve accepted state. Add a real MySQL overlap probe alongside this deterministic interleaving test.

### G03 - High: transport success is mistaken for invoice clearance

Sources: `app/Services/Zatca/ZatcaClient.php:101-111`, `app/Services/Zatca/ZatcaSubmissionService.php:209-224`.

Reproduction: the clearance endpoint returns HTTP 200 with only `{"message":"unexpected proxy response"}`. No CLEARED status, no cleared XML, no validation evidence. Seera still stores **cleared**. HTTP 202 is also broadly classified as acceptance; explicit validation errors without a `NOT_` status need defensive coverage.

Required correction: endpoint-specific response validation. Require positive clearance/reporting evidence, reject conflicting validation errors, and require a parseable cleared artifact matching the submitted identity for standard clearance. Malformed/ambiguous success responses must remain unverified/unknown, not produce success badges. Preserve UUID/hash on controlled retry.

## Additional observations (not dynamically reproduced blockers)

- `CustomerCreditController.php:37-43` paginates before filtering credit status and sums only the resulting page. The status filter can omit matching notes on other pages; top cards are page-local without an explicit page-total label. Filter before pagination and compute clearly labelled scoped totals.
- VAT decision brief example E1 says `150 + 300 = 450` for `10 x 100; 5 x 200`. Both lines are SAR 1,000 taxable, so line VAT should be `150 + 150 = 300`. Correct this before Finance signs a policy decision.
- The same brief suggests a pre-approval XML preview as mitigation, but `ZatcaReadiness::document()` refuses non-posted invoices. An actual pre-approval arithmetic/readiness gate is not implemented by that description.
- The production activation checklist places `ZATCA_PRODUCTION_ENABLED=true` after onboarding, but onboarding's `assertEnvironment()` calls `assertUsable()` for production. The controlled activation sequence needs correction before the owner attempts it. Do not solve this by enabling production casually during deployment.

## Verification actually performed today

| Check | Result |
| --- | --- |
| Existing focused PHPUnit suite: ZATCA, notes, credit resolution, invoice print, offline attendance/expense sync | **112 passed / 1,437 assertions**, 344,979 ms |
| Existing production-controls class | **5 passed / 96 assertions** (overlaps the above; repeated outside sandbox while investigating delayed output) |
| New gate regressions | **3 failed / 10 assertions**, 6,943 ms; rerun from final `tests/manual` location reproduced the same three failures (76,614 ms). Failures are G01-G03 above |
| JavaScript queue/sync checks | **12/12 passed** |
| User-guide validation | **4 pages / 452 links and anchors passed** |
| Release ZIP CRC verification | **18 files, all passed** via `unzip -t` |
| Release ZIP SHA-256 | `436847840B7FC77904572B45ED3388752BA76CBC0A12290EFBE821A31BD18B7C`, 197777 bytes |
| QR static script SHA-256 (local artifact identity, not an independent upstream audit) | `18AE399F81182BC9DE916E9C77B195DF20CC58D6F2D55A62B085A299F1BF1780` |
| Composer manifests/lock and public .htaccess compared with last deployed commit | Unchanged |
| Remote feature-branch tip | Verified with `git ls-remote`, matches `d66a6ea` |

PHPUnit ran on PHP 8.3 with explicit `APP_ENV=testing`, SQLite `:memory:`, empty `DB_URL`, no cached app config, false production-ZATCA flag and mocked external calls. No production database was accessed. Initial test processes were quiet until JSON summaries; they completed, not abandoned. No fresh full 749-test suite, MySQL concurrency probe, real SDK, company simulation, live portal call, browser acceptance or phone QR scan was performed. Historical claims in the attachment remain prior evidence, not fresh verification. No counts above should be added as distinct tests.

Diagnostic reproduction (Windows, PHP 8.3 on PATH, from repository root):

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:ZATCA_PRODUCTION_ENABLED='false'
php vendor/phpunit/phpunit/phpunit tests/manual/DeploymentGateReviewTest.php --filter test_gate_review --colors=never
```

The diagnostic harness is under `tests/manual` to avoid adding inherited fixture tests to the normal suite. It deliberately asserts the required safe behavior and currently fails. Only this harness and review/deployment documents were added; application code was not fixed, committed, pushed or deployed in this review. The pre-existing untracked Fable handoff was preserved.

## ZATCA readiness boundary

The repository records sandbox acceptance for six document types with a dummy identity. It also explicitly records missing official SDK execution, company simulation, confirmed legal/tax fields, Finance rounding decision and production onboarding. These remain separate owner/Finance gates even after the defects are fixed. Do not change posted history or adjust commercial prices merely to force XML validation.

The official [ZATCA Developer Portal manual](https://www.zatca.gov.sa/en/E-Invoicing/SystemsDevelopers/ComplianceEnablementToolbox/Documents/Developer%20Portal%20User%20Manual.pdf) distinguishes SDK validation and the integration sandbox. The official [FAQ](https://www.zatca.gov.sa/en/E-Invoicing/Introduction/FAQ/Pages/default.aspx) distinguishes public sandbox access from taxpayer production access. These support keeping test acceptance separate from live activation; this review does not certify legal compliance or assert that every official standard was exhaustively rechecked.

## Next action

Fix G01-G03, correct the misleading operational notes, rerun the new regressions and affected suites, and verify concurrent submission handling on disposable MySQL. Publish an exact corrected release hash and artifact checksum. Then use [the cumulative deployment guide](deployment-cumulative-2026-10-25.md), initially with ZATCA production disabled. Company simulation/SDK/Finance/owner approval are a later activation gate, not part of the Git deployment.
