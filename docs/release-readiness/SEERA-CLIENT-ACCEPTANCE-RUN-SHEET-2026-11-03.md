# SEERA — final client UAT execution and acceptance sheet
Prepared 3 November 2026. **Code review anchor:** `a3316be4209b13efdc54ce329fa21218e0a95165` (Notifications + bilingual visual training guide in Git ancestry).

## Decision
**DEVELOPMENT COMPLETE CANDIDATE — PENDING SIGNED CLIENT ACCEPTANCE.** The owner has reported production deployment of `a3316be`; actual server HEAD, live migrations, ZATCA flag and health are not independently inspected in this document. No additional software scope or production operation is authorized.

## Before client attendance
1. Operations records actual server HEAD, route to `/user-guide`, Notification Bell and the English/Arabic Visual Examples. Note which role can view which records.
2. Finance captures approved **read-only** opening GL, AP, AR, GRNI, stock and VAT balances, with a cutoff date. Keep confidential personal/financial information out of Git.
3. Use separately isolated staging/training records for transaction testing. Do not invent invoices, payroll or payments in real client balances.
4. Appoint business testers: Owner, HR, Finance, Procurement/Warehouse, Project/Site, Arabic reviewer and Android/iOS testers.
5. Use the original detailed `SEERA-FULL-BUSINESS-UAT-MATRIX-2026-10.md`; no scenario in this run sheet is pre-marked PASS.

## Demonstration order / UAT assignments

| Session | IDs | Demonstrate and verify | Signer |
| --- | --- | --- | --- |
| Identity and linked workspaces | 01–08 | Secure login, cross-project refusal, Employee/View/Manage/account, Customer and Supplier form drafts | Owner / HR |
| Procurement approval to payment | 09–13, 28 | PR → current/next approver → PO → receive 6 of 10 → Supplier Bill with VAT → AP payment 200 then 490, stock behavior and ordinary versus Super Admin VAT | Procurement / Finance |
| Sales finance and controls | 14–17 | Invoice 1,000 + VAT 150, receipts 300+850, notes/refunds/reversal, closed VAT periods | Finance |
| Payroll, expenses and projects | 18–21 | Payroll posting/settlement, Site Expense, project BOQ versions and GL costs, Equipment maintenance | HR / Finance / Project |
| Localization and documents | 22–23 | Arabic terminology and RTL, English/Arabic prints and QR (physical scanning), visual guide sample screens | Saudi Arabic reviewer / Finance |
| Physical device and nonproduction compliance | 24–26 | Real Android/iPhone GPS/geofence/offline/reconnect/switch-user; nonproduction-only ZATCA checks | Site / Finance |
| Final reconciliation | 27 | GL/AP/AR/GRNI/VAT, payroll and project ledger totals; every variance explained | Finance / Owner |

### Known policy and evidence qualifications
- The original UAT-28 row contains an old diagnostic note about non-default VAT. The newer 1 November core integration review records server-enforced purchase/sales VAT authorization and Super Admin exception audit. **Do not alter the historical matrix**; rerun UAT-28 with current code and attach *fresh observed* evidence.
- The visual guide has **94 synthetic screenshots** (43 desktop scenes and four mobile examples per language); it is a training aid, not a replacement for client UAT.
- In-app Notifications MVP covers runtime PR, Supplier Bill and Site Expense approvals; it **does not** cover all business alerts, email, SMS or WhatsApp, nor does it backfill older approvals. Empty bell does not mean there are no pending My Approvals.
- Production ZATCA remains **disabled** pending company simulation, SDK, verified identity/credentials, owner onboarding and Finance VAT-rounding Option A/B decision. This UAT sheet never enables production compliance transmission.
- Equipment telematics GPS provider and statutory GOSI/WPS/Mudad integration are separate, **not operational** without contracts/policy/credentials. Mobile attendance GPS/offline needs physical-device acceptance.

## Every test must have an observed result
```text
UAT-ID:
Tester / department:
Date and environment / actual SHA:
Record identifiers (anonymized):
Preconditions:
Expected:
Actual / numbers:
Status: PASS / FAIL / BLOCKED / NOT RUN
Screenshot / audit / reconciliation evidence:
Defect ID / severity / owner:
Department signature and date:
Owner signature and date:
```

## Delivery acceptance record
```text
Actual production HEAD:
Login/dashboard/notifications smoke test:
Visual English/Arabic guide opened:
28 UAT cases: __ PASS / __ FAIL / __ BLOCKED / __ NOT RUN
Finance reconciliation signed:
Arabic reviewer signed:
Real Android tested:
Real iPhone tested:
Backup location/checksum privately documented:
Isolated backup restore verified:
Live ZATCA production flag confirmed disabled:
Deferred out-of-scope decisions signed:
Client / Owner / Finance / HR / Procurement signers:
Final handover: ACCEPTED / ACCEPTED WITH EXCEPTIONS / NOT ACCEPTED
Date:
```

**Current entries are blank because business results and signatures were not supplied.** Treat exceptions as named, owned follow-ups; don't claim 100% completion from synthetic automated test counts.
