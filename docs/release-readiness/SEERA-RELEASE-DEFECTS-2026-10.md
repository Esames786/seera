# Open findings — 26 October 2026

## Closure update — 27 October 2026

See [release defects closure](SEERA-DEFECT-CLOSURE-2026-10-27.md) for the candidate code, test evidence and artifact. The original table below is preserved as the **26 October baseline**, not the current code status.

| ID | Local candidate status |
| --- | --- |
| GAP-01 / R22-02 | Fixed (purchase, 27 Oct): central Super Admin-only manual PO VAT override with reason and transactional audit; configured non-VAT retained; approved source rates carried to receiving/matched billing and grouped VAT register. Fixed (sales, 28 Oct, branch `feature/seera-sales-vat-authorization-2026-10-28`): `SalesVatPolicy` on customer invoice create / update — 15 % default, Super Admin override with logged reason, forged line rates refused, posted invoices immutable, notes inherit the rate. Open: sales has no zero-rated / exempt classification model (Finance decision) |
| UI-01 | Fixed: targeted wrap/shrink rules, all three screens at 320/375/390/430/768px; 46 focused browser checks passed; no global overflow hiding |
| GAP-02 | Priority Dashboard/Employee/Customer scope fixed, including shared controls, related panels and common validation. Wider Finance/Equipment translation and client language review remain separate |
| GATE-01 / GATE-02 | Unchanged external gates; this sprint does not enable production ZATCA, perform client sign-off or run production operations |

Affected tests: 112 passed / 1,283 assertions. JavaScript: 12 passed. Complete Laravel suite on the final sources: 768 tests, 9,344 assertions, 0 failures (a double-escaped ampersand regression found by the first full run was fixed and re-verified). Committed as `cc0e5f9` and `7106173`; production stays at the owner's reported baseline `aca78ee` until the owner deploys.

## Original findings

Baseline `aca78ee`. This register distinguishes missing requirements, policy decisions and reproduced UI defects from accounting failures. No new production code fix or data repair was made in this review.

| ID | Priority / type | Reproduction and evidence | Next action |
| --- | --- | --- | --- |
| GAP-01 | High business-acceptance requirement gap: R22-02 | Non-Super-Admin role with Purchase Orders view/create saves a new PO at header and line VAT 5%, net 100, total 105. `tests/manual/RequirementGapReviewTest.php` characterises this current behaviour: 1 test / 7 assertions. A PASS here means the missing restriction was reproduced, NOT that R22-02 is accepted. PO validation in `PurchaseOrderController::validated` accepts 0–100 and has no override gate. | Agree transaction/line versus global override, ordinary edits to previously authorised non-default drafts, and zero/exempt exceptions. Then implement one coordinated policy across the agreed purchase/sales paths with negative-role tests and an audited override. Preserve historical rates and later agreed Site Expense behaviour. |
| UI-01 | P2: mobile Arabic overflow | Actual Edge browser at 390×844, Arabic RTL: Dashboard document width 463px, Employee Edit 446px, Customer Edit 447px. See screenshots and browser-results.json. Shared topbar uses fixed height and a non-wrapping action row; a targeted responsive layout investigation is required. | Small responsive-header/locale/actions fix, EN/AR desktop/mobile regression and visual review. Do not merely hide overflow and conceal controls. Deferred from this financial-blocker review. |
| GAP-02 | P2: incomplete Arabic coverage | Arabic Dashboard screenshot still shows Dashboard, metrics and section labels in English. Source also has hardcoded Equipment/Finance form labels. Locale and RTL toggle itself works. | Translate agreed user-facing modules and validation/print text; preserve route/permission keys; Arabic-speaking reviewer signs off. |
| GATE-01 | External / owner gate | Company simulation, official SDK, legal/company identity confirmation and Finance rounding decision remain outstanding | Keep production ZATCA disabled; follow its separate readiness checklist. |
| GATE-02 | Acceptance evidence gap | Actual Android/iPhone GPS/offline, client tester participation, live financial reconciliation and backup restore evidence not available | Run the owner pack on authorised staging; record real testers and results rather than treating developer tests as sign-off. |

Original refund/in-flight/false-clearance blockers G01–G03 were fixed in `aca78ee`; see [correction report](../deployment-fixes-2026-10-26.md). No new financial-posting failure was observed in the 183 fresh critical tests or the added integrated procurement scenario. That is a bounded result, not an exhaustive security audit.

Evidence: [browser results](evidence/browser-results.json), [Arabic mobile Dashboard](evidence/mobile-ar_admin_dashboard.png), [Employee](evidence/mobile-ar_admin_hr_employees_1_edit.png), [Customer](evidence/mobile-ar_admin_master_customers_1_edit.png), [desktop Arabic](evidence/dashboard-ar-desktop.png).
