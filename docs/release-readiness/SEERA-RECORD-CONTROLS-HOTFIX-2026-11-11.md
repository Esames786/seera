# Customer/Supplier entry controls — 11 November 2026

## Confirmed defect and cause

The owner reported that **Enter my own code instead** was checked but typing did not work on both Customer and Supplier Create after activation of `48ea709`. Anonymous read-only requests confirmed HTTP **403** for the release JS, CSS and manifest while `/login` returned 200. This prevented the shared code/photo/document JavaScript from loading; browser checkbox selection alone does not reveal the manual input.

The Windows-built ZIP has DOS creator metadata and Unix permission bits `0` for those entries. The deployment shell uses `umask 077`, so extraction can leave public assets owner-only. The owner was given a fix scoped strictly to `public/build`: directories 755, files 644, refusing symlinks. Subsequent fresh public requests returned **200** for JS/CSS/manifest and matched the installed release bytes. No private file permissions, database records, users or configuration were changed by that repair. [Public-request evidence](evidence/asset-permissions-2026-11-11/public-assets.json).

Deployment scripts and both November runbooks now normalize public asset modes after extraction. Private backups/environment files keep their existing protection; the global private umask is not weakened.

## Shared control correction

Hotfix application/guide commit: **`b1818c27de071309febbe1a2156f0823d84e885d`** on `feature/seera-clean-start-two-accounts-2026-11-11`.

- Customer and Supplier use the same component and JS initializer.
- Selecting manual mode shows the editable input and hides the readonly automatic preview and its automatic-mode help, so the user has one visible code field.
- Initial/browser-restored checkbox state and `pageshow` are synchronized, including browser Back. Returning to automatic mode clears/hides the manual value.
- After validation errors, a retained manual value renders editable in the server HTML, with its matching label and hidden automatic preview.
- The submitted name remains `code`; the automatic preview is never submitted. Existing generation, uniqueness, permissions, persisted record codes, GL/AP/AR/VAT and master data behavior are unchanged.
- The owner-reported live cleanup/activation status is now reflected in the November client guide. The guide distinguishes installed changes, client acceptance and remaining Finance/bank/master decisions.

## Verification

| Verification | Fresh result | Scope |
| --- | --- | --- |
| Affected Laravel tests | **41 passed / 592 assertions** | `ClientFeedbackClosureTest`, `CustomerWorkspaceTest`, `SupplierWorkspaceTest`, `UserGuideTest`; source-rendered manual validation recovery, party uploads/photos, permissions/scope and related workspaces |
| Browser controls | **183 passed / 0 failed** | Real rebuilt release JS/CSS, offline synthetic component markup; Customer/Supplier, en/ar direction, widths 320/390/768/1440, editable manual value/FormData, automatic reset, restored state, document add/remove and photo preview |
| Failure reproduction | **Confirmed** | Mocked 403 reproduces checked-checkbox/hidden-manual-field behavior; loaded release script resolves it |
| JavaScript suite | **12 passed / 0 failed** | Existing JS regression suite; manual-mode behavior is covered by the browser checks above |
| Guide validation | **8 pages / 615 links and anchors / 13 issue rows** | Rebuilt November HTML, stable guide IDs and embedded image validation |
| Build | **Vite succeeded; 18-entry ZIP verified** | SHA-256 **`A0B6A8840C633152048487712FF8EEF78B190CCF559A29927240715FD0CD9115`**; JS `app-BTBDvR6M.js` |
| Deployment script | **Bash syntax / embedded PHP checked** | No migration, seeder, reset, DB switch or password change |

[Browser evidence and synthetic screenshots](evidence/asset-permissions-2026-11-11/) are control-focused and **not authenticated production-page acceptance**. The public 200 checks concern the already installed previous bundle; the rebuilt hotfix bundle needs the controlled update below. Existing full application evidence (944 tests / 10,892 assertions before this small UI follow-up) is reused, not represented as a new full-suite run.

No background browser/PHP server was started for these checks. A proposed temporary local server launch was rejected by the tool policy; the safer browser fixture fulfills every request offline, needs no credentials and performs no form submissions. No production business data was used or modified by developer QA.

## Install on the already activated clean portal

Use `scripts/deploy-record-controls-hotfix.sh` from the reviewed tools commit, passing the application hotfix SHA above. It preserves `.env`, hosting `.htaccess` and all current records, fast-forwards the existing branch, verifies/extracts the new ZIP, repairs public asset permissions, clears/caches Blade views, checks JS/CSS HTTP availability and reopens the portal. It uses a brief maintenance window and does not replay the clean-start procedure. It refuses backend, route, Composer or migration drift.

Expected completion: `RECORD CONTROLS UPDATED:`. Reload the actual Customer and Supplier forms and check manual typing and a return to automatic mode before client handover. Do not rerun clean-start preparation or create another database for this UI fix.

## Remaining acceptance/configuration

- Exact client's IBAN confirmation is still required; invalid check digits remain refused.
- Supplier recoverable posting needs its dedicated Finance account mapping; reminders need runtime setting/scheduler confirmation.
- The old SAR 500 master opening balance was deliberately retained; Finance must review it if the intended financial starting balances are zero.
- Specific old master removal is separate from the owner's instruction to preserve masters. Ordinary client access remains Finance Manager.
- Actual client workflows, Arabic/physical-device acceptance and production ZATCA restrictions remain as previously recorded; production ZATCA is disabled.

**Decision:** GO for the scoped UI/assets update. Client acceptance remains pending; do not claim all business setup or every original client point is unconditionally closed.
