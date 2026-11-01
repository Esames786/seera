# Core release integration review — 1 November 2026

Release branch: `release/seera-core-integration-review-2026-11-01`.

Expected starting release HEAD **`e55a9fb307c6e52244a6f99c3bd34d018e05b10b`** was confirmed on origin. Application candidate **`c19943f9cb8df2dfe3dae30eee6031b736f3a777`** was tested separately before the release branch was advanced with `git merge --ff-only c19943f`. No merge commit, rebase, reset or application modification was required. The final branch tip includes the accompanying QA evidence commit; the application tree remains the tested candidate.

The pre-existing untracked `docs/FABLE-HANDOFF-2026-10-08.md` was preserved and excluded. Original feature branches and the `release/seera-core-uat-baseline-2026-11-01` branch were not advanced by this review.

## 1. Approved work included

All **16** approved revisions below are Git ancestors of the candidate. They were already present in the release baseline `e55a9fb`; the final three Supplier Create commits are the only application delta brought into this review branch.

| Area | Approved inclusion anchor |
| --- | --- |
| Finance/ZATCA deployment-blocker corrections | `aca78ee` |
| Purchase VAT authorization and Arabic mobile closure | `dc048ff` |
| Sales VAT authorization | `1c446a6` |
| Procurement / Finance / Equipment Arabic integration | `ef3114b` |
| Priority Arabic completion | `cd212a0` |
| AR register Arabic | `bf3e6b0` |
| Invoice workspace/reopen messages | `70b31e0` |
| Project Reports MVP | `588d83b` |
| HR dashboard permissions | `a4d0322` |
| HR dashboard Arabic | `5cc6819` |
| Integrated HR Reports and accelerated test tooling | `5f852e2` |
| Project workspace UX | `05b183b` |
| Project panel Arabic/financial guidance | `5de645d` |
| Project computed-cell Arabic | `707c5eb` |
| Customer / Supplier / Employee connected workspace UX | `2997db4` |
| Supplier Create/Edit form Arabic | `e55a9fb` |

Machine evidence: [approved-inclusions.json](evidence/core-integration-review-2026-11-01/approved-inclusions.json). The existing QA reports in this directory explain each approval and its bounds.

The raw remote-branch audit is also preserved as `branch-ancestry.json`. Some original source branches are not literal ancestors: Equipment/topbar work was incorporated by `c9e2636`, HR Reports and test tooling by `91820dd`, and the earlier PO VAT prototype was superseded by the reviewed central PurchaseVatPolicy. This is not a missing-feature finding. The later approved integrated revisions above are included. The supplier branch was deliberately ahead of the release baseline and is now included through the authorized fast-forward. No claim is made that every development branch tip should be merged indiscriminately.

## 2. Separate Supplier Create commit review and gate

Reviewed the commits individually and their aggregate diff from `e55a9fb`:

- **`df52020`**: wraps Create document title, breadcrumb, visible heading and introduction in existing Laravel translation calls. Form inclusion and routes unchanged.
- **`bf8a40c`**: adds the exact Arabic breadcrumb key. The visible heading/introduction entries from the form closure are retained.
- **`c19943f`**: adds an Arabic Create regression asserting its title/breadcrumb vocabulary and unchanged form names/active value.

Exactly three files change: `suppliers/create.blade.php`, `lang/ar.json`, `SupplierFormArabicClosureTest.php`. No controller, financial, permission, query, schema, model or runtime bundled asset changes.

Before moving the release branch:

| Gate on detached candidate `c19943f` | Result |
| --- | --- |
| SupplierFormArabicClosureTest + SupplierWorkspaceTest + MasterWorkspaceUxClosureTest | **20 passed / 267 assertions / 0 failures**, exit 0; runner wall 144.04 s |
| Supplier Create/Edit browser gate | **117 / 117 passed**, zero page errors, exit 0 |

The browser gate covers EN/AR at 320, 390, 768, 1440px, Create/Edit document titles and breadcrumbs, translated form/dialog labels and help, identical fields/options/endpoints across locales, actual category/term/payable-account creation and selection, actual Supplier Save and Save & Close, retained related tabs, read-only View and permission refusals. All business writes occur only in the disposable local fixture.

## 3. Verification on the advanced release branch

| Run | Result |
| --- | --- |
| Full accelerated Laravel, 8 workers + CLI OPcache | **839 passed / 10,227 assertions / 0 failures**, exit 0; runner wall 459.73 s |
| JavaScript (`npm run test:js`) | **12 passed / 0 failed / 0 skipped**, exit 0; 329.1621 ms |
| Critical browser journeys, initial run | **116 checks: 115 passed / 1 failed / 0 page errors**; the failed receipt step used an invalid cash-account / bank-transfer pair in the harness |
| Targeted receipt rerun after correcting the harness | **4 / 4 passed**, zero page errors, exit 0; closes that initial failure |
| Read-only browser invoice/GL/VAT reconciliation | **11 / 11 passed**, exit 0 |

The full suite includes the existing HR, Project, Procurement, Finance, authorization/scope, credit/debit notes, customer credit/refund, immutable-document and ZATCA control regressions. These results are local regression evidence, not real-device concurrency proof or client acceptance.

Critical browser coverage:

- **44 module/locale navigation checks:** Dashboard, HR dashboard/Reports, Employee workspace, Project View/Manage/budget/reports, Customer and Supplier workspaces, PR/PO/GRN registers, Supplier Bills, AR invoices/credits, My Approvals, journals, ledger, VAT report and trial balance, each in English and Arabic.
- **56 responsive checks:** Dashboard, HR dashboard/Reports, Employee, Project, Customer and Supplier at all four widths in both languages; no page-level horizontal overflow in those checks.
- **Sales VAT:** reason required for Super Admin override, reasoned 5% draft accepted, ordinary Finance rate read-only, forged 5% refused, standard 15% draft accepted.
- **Real local finance journey:** draft with net 200 / VAT 30 / total 230 → Approve & Post → bilingual print → receipt 100 → balance 130. The posted invoice edit URL returns 403.
- **Roles/scope:** Finance denied HR and Supplier management; HR allowed its workforce report and denied AR/journals; the site user can open its project and is refused the foreign project.

The receipt harness initially selected the first receipt account (Cash) with Bank Transfer. The server correctly rejected that pair and wrote no receipt. Only the harness was corrected to choose Bank. The targeted rerun deliberately rechecks that refusal, then records the valid receipt and confirms its persisted balance and posted-edit refusal. Original failed evidence remains intact rather than being relabelled green; the 115 unaffected checks were not repeated. There is no unresolved application failure from this run.

The read-only probe checks taxable amount 200, VAT 30, total 230, receipts 100, outstanding 130, partially-paid status, one VAT-register record, the receipt, balanced invoice and receipt journals, and AR journal net 130. It uses SQLite query-only mode and refuses paths outside the uniquely named disposable fixture.

Evidence: [core-integration-review-2026-11-01](evidence/core-integration-review-2026-11-01). Separate JSON results/logs preserve gate, initial browser, receipt rerun and reconciliation counts. Selected 390px Arabic screenshots are included. Other screenshots remain in the temporary QA directories.

## 4. Source/build and production boundaries

The application under `app`, `resources`, `lang`, `routes`, `database`, `composer.json` and `composer.lock` remains identical to `c19943f`. This review only adds QA harness/probe/report/evidence changes. The release manifest records the candidate tree hash and the three commit hashes.

`public/build.zip` SHA-256: **`B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E`**. All **18 entries** were decompressed and hash-compared to `public/build`; no Vite rebuild was necessary for these Blade/dictionary changes.

Against the owner's last reported production commit `aca78ee`, `database/` has no change. Composer JSON adds only the ParaTest development dependency, with its lockfile changes. Production still needs a reviewed dependency/cache/asset deployment process, but this report does not authorize or perform it. No live server revision, flag, database reconciliation or restore was inspected in this review.

Local fixture: `testing`, isolated copied SQLite and storage/cache, sandbox configuration, `ZATCA_PRODUCTION_ENABLED=false`. No production operation, migration, seeder, remote tax submission or ZATCA activation was performed.

## 5. GO / NO-GO and remaining gates

**GO for the core integration candidate and controlled staging/client UAT. NO-GO for unconditional production deployment or production ZATCA activation.** All automated checks have passed or, for the isolated receipt harness error, passed the documented targeted rerun. No unresolved application regression was found. The following acceptance/operational gates remain.

| Gate | Remaining requirement |
| --- | --- |
| Client business UAT | Owner/Finance/HR/Procurement testers must accept the workflows, approvals, permissions, project/site scope and representative balances. Automated tests are not that sign-off. |
| Arabic acceptance | Arabic-speaking client reviewer must approve business wording. Supplier Create title/breadcrumb is now closed; existing controller flash/custom-validation messages, wider module text and generated term suffixes in other views remain outside this closure. |
| Real devices | Android/iPhone GPS, geofence and offline/reconnect behavior still require physical-device testing and client evidence. |
| Operations before deployment | Explicit owner approval, verified current server revision/configuration, database/uploads backup and demonstrated restore, dependency/asset/cache plan and live financial reconciliation evidence. No production checks were performed here. |
| Finance tax decisions | VAT rounding Option A/B remains pending. No Option B or historical recalculation was implemented. Sales still has no full zero-rated/exempt/out-of-scope classification model; Finance must decide that separate scope. |
| Production ZATCA activation | Official SDK run, client/company simulation, confirmed legal/VAT/address identity, OTP/credentials and production CSID/onboarding remain separate required gates. Keep production ZATCA disabled. |

Nothing is deployed by this recommendation. Production approval belongs to the owner after the relevant gates are met.
