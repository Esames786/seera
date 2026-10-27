# Gate decision — 26 October 2026

**27 October update:** this document records the earlier baseline review. The VAT/RTL/priority Arabic fixes and their current verification are tracked in [Release defects closure](SEERA-DEFECT-CLOSURE-2026-10-27.md). Its results supersede the open-defect and unchanged-working-tree statements below. This sprint does not authorise or execute a production deployment.

**CONDITIONAL GO — SPECIFIED GATES STILL REQUIRED.** This is a statement about the existing scoped ERP functionality and next acceptance work, not new deployment authorisation or a declaration that the complete client product is ready.

The owner has already supplied successful deployment evidence for `aca78ee`; this review did not perform another production deployment. No application code changed in this pass. The current release can proceed through focused owner evaluation; full-scope acceptance remains pending, especially R22-02 VAT override restrictions, Arabic mobile usability and real business/device sign-off.

| Gate | Result and boundary |
| --- | --- |
| Current branch/revision | CONFIRMED locally: feature branch, `aca78ee` |
| Production baseline / migrations | CONFIRMED from owner transcript: `aca78ee`, four October migrations Ran, app live; not a fresh remote inspection |
| Critical functional regression | PASS: 183 tests / 2,049 assertions on isolated SQLite |
| Connected runtime procurement/payment | PASS: additional 1 test / 36 assertions; real GRN posting → both bill approvals → two payments → balanced GL/GRNI/AP |
| AR/refund/ZATCA correction tests | REUSED passing evidence at unchanged `aca78ee`; see correction report |
| Full application suite on current release | 27 October: 768 tests / 9,344 assertions, 0 failures on `7106173` (final sources of the defect-closure sprint) |
| MySQL concurrency | REUSED October 25–26 ten-scenario evidence; no financial source code changed since it |
| Build/archive/guide | REUSED verified `aca78ee` artifact and 452-link guide result; no runtime asset changed |
| Actual local browser smoke | 27 October: the three failed scenarios re-run, 46 / 46 focused checks PASS at 320–768 px (`7106173`) |
| R22-02 VAT override policy | FIXED 27 October (`cc0e5f9`): server-enforced Super Admin override with reason and audit; owner acceptance pending |
| Full English/Arabic coverage | Priority screens (Dashboard, Employee, Customer) and navigation translated 27 October; wider Finance / Equipment screens and client language review remain |
| Remote staging isolation/rehearsal | NOT RUN |
| Sanitised-copy upgrade / restore rehearsal | NOT RUN |
| Production financial integrity/reconciliation | NOT RUN; current evidence covers synthetic fixtures only |
| Physical Android/iPhone offline/GPS | NOT RUN |
| Formal Finance/HR/client acceptance | PENDING |
| Official SDK / company simulation | NOT RUN |
| Finance VAT-rounding decision | PENDING |
| Production ZATCA | Must remain DISABLED; source/test default verified, current server flag not independently inspected in this pass |

## Recommended next development

1. **VAT override policy (R22-02):** settle its three recorded exceptions/override decisions, then implement across the agreed paths. This is a real remaining client requirement, not merely extra testing.
2. **Mobile header/RTL and Arabic completeness:** fix the reproduced overflow, then translate/review accepted core screens. Keep existing routes and financial actions intact.
3. **Owner UAT in parallel:** short Finance/HR/procurement scenarios, then real-device sessions; record discrepancies and sign-off in the pack.
4. **External integrations separately:** company ZATCA and any expressly agreed GOSI/WPS or vehicle GPS work. Do not invent provider integrations or statutory policy.

No claim is made that 30 automated browser checks constitute client approval. No blanket pause of unrelated development is needed, but these acceptance gaps must stay visible.
