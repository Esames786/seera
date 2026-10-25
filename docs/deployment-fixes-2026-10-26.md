# Deployment review corrections — 26 October 2026

Corrective release on `feature/seera-connected-workspaces-2026-09-23`, based on `d66a6ea`. Verify the published commit against the release handoff before deployment. Publication does not mean the live server has been updated. The original [25 October review](deployment-review-2026-10-25.md) remains the evidence for rejecting the unfixed base revision.

## Fixed behaviour

| Review finding | Correction | Evidence |
| --- | --- | --- |
| G01: refund consumes customer credit while its payment journal is only draft | Require a posted payment journal before committing a refund. Review-mode refusal rolls back refund and draft journal together; no posting-rule override. Source credit-note journal must also be posted before new applications/refunds. Legacy draft refunds cannot be replayed or reversed as posted payments. | CustomerCreditResolutionTest: review-mode rollback, retry, reversal, source-journal and legacy-refund cases. |
| G02: a new caller reclaims a request already in flight | Atomic status/attempt/hash claim; pending calls are refused. Result updates lock the record and match the attempt, so late responses only add audit evidence. An expired pending request first becomes outcome unknown without another HTTP call. | ZatcaPhase2Test overlap, expiry, late-response cases; five real MySQL chain/concurrency scenarios passed, including a fresh worker during HTTP. |
| G03: HTTP success without clearance evidence marks a document cleared | Require endpoint-specific positive status. Clearance also requires returned XML with matching UUID and canonical invoice hash. Missing/conflicting evidence becomes outcome unknown; regeneration remains blocked. | Generic 200, missing XML, invalid XML, conflicting validation errors, reporting response and mismatched UUID/hash cases. |
| Credit-register filtering and totals only cover one page | Apply usage filter before pagination; compute cards across the complete matching, authorised set. | More-than-one-page, fully used/available, second-page totals and project-scope regression. |
| Finance guide arithmetic and pre-approval claims | Correct E1 to SAR 300 VAT; clearly state that XML readiness currently requires a posted document. Remove advice to modify prices merely to pass validation. Mark prospective rounding reconciliation as design work. | Reviewed against current readiness code and fixture arithmetic. No financial calculation changed. |
| Production-onboarding instructions enable the flag too late | Document an owner-controlled activation window: the current flag covers onboarding and submission, so it must be enabled before production onboarding calls. | Procedure corrected; no environment setting or permission changed. |

## Verification and release boundary

- The three originally failing deployment-gate checks now pass: 3 tests / 11 assertions.
- Focused ZATCA, customer note/credit, invoice-print and offline suite: **120 tests / 1,537 assertions passed**. After the final legacy-refund safeguard and malformed-validation-status handling, the targeted follow-up also passed: **3 tests / 53 assertions** (legacy draft refund, review-mode rollback/retry and ambiguous success evidence). These runs overlap; they are not 123 distinct tests or a full application-suite run.
- Disposable MySQL 8.0.30, REPEATABLE-READ: **five ZATCA chain/concurrency scenarios passed**, including additive migration rerun and legacy-status normalization.
- Disposable MySQL credit-resolution probe: **five concurrency scenarios passed** (allocation/allocation, allocation/refund, refund/refund, refund/reversal, allocation/reversal), plus migration rerun. The private test server was shut down after checking its exact datadir; other database instances were untouched.
- JavaScript tests: **12 passed**. Generated user guide: **452 internal links/anchors passed**. `git diff --check` passed.
- `npm run build:release` passed and verified every archived file against the build output. Refreshed `public/build.zip`: **197,790 bytes, 18 files**, SHA-256 `E51BFFBC4C02B5FDEA33AE0281B8DEC02CAD236B97BD59F6A1CE7E0D0E2AF228`. Manifest and assets are at the archive root.
- No live database, company simulation, production onboarding, official SDK validation or live invoice submission was performed. Automated tests are not live browser acceptance or tax certification.
- No new migration, seeder, permission or financial-policy change is needed for this correction. A server still at `4d77c77` needs the four cumulative migrations listed in the deployment guide.
- Existing inconsistent refunds and previously accepted ZATCA records are **not** silently repaired. Review them before authorising a data correction.

## Read-only historical refund audit

Run through an authorised database console after confirming the database and taking the normal private backup. It returns identifiers and accounting state only; do not change rows based solely on this query.

```sql
SELECT r.id AS refund_id, r.customer_adjustment_note_id,
       r.journal_entry_id, j.status AS journal_status
FROM customer_credit_refunds r
LEFT JOIN journal_entries j ON j.id = r.journal_entry_id
WHERE r.reversed_at IS NULL
  AND (j.id IS NULL OR j.status <> 'posted');
```

If rows exist, Finance must reconcile the actual payment, credit balance and journal before deciding a scoped repair. Do not blanket-post drafts, delete refunds, reset credit, or run demonstration seeders.

For earlier ZATCA records marked cleared, review stored response evidence and the private cleared XML against the signed identity. Do not resend or relabel accepted documents automatically.

## Deployment handoff

Use [the cumulative cPanel guide](deployment-cumulative-2026-10-25.md) with the exact published release commit supplied in the handoff. Staging/browser acceptance remains an operator check; this report records automated verification only. Keep `ZATCA_PRODUCTION_ENABLED=false` for the code rollout. The separate company-simulation, official-SDK, owner identity and Finance-policy gates still apply to production ZATCA activation.

The release archive must be rebuilt with `npm run build:release`; deploy its contents with `unzip -oq public/build.zip -d public/build`. Preserve the server's `public/.htaccess`, APP_KEY and private storage. Prefer `artisan optimize:clear` followed by `artisan view:cache`; do not repeat the stale-cache failure from the earlier deployment.
