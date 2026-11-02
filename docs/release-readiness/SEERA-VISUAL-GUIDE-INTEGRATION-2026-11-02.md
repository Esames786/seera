# Visual guide + Notifications integration — 2 November 2026

**GO for owner review / controlled guide-only rollout planning. No production operation performed.**

Candidate branch: integration/seera-visual-guide-rollout-review-2026-11-02.

- Previously deployed baseline, reported by owner: **8dcb1d731cdc012340525565e38677f549d73260**.
- Verified screenshot feature: **dff75fa2a6f48e369041dce93232141dcef43e33**.
- Combined source merge: **6572f95a98f32bf906d9b171dc0e996e38ebf4b7**. Both parents' approved features are ancestors. The subsequent evidence/documentation commit changes no application implementation.
- Original Notifications/release and visual feature branches were not modified by this integration.

## Source boundaries verified

Compared with notification baseline 8dcb1d7, the **only runtime application change** is adding VISUAL-EXAMPLES and VISUAL-EXAMPLES-AR to UserGuideController's existing static allowlist. Documentation, synthetic screenshots, manual capture/QA tooling and guide tests are the remaining changes.

Notification publisher/access/runtime/controller/topbar/routes are byte-identical to baseline. No changed database migrations/seeders, application config, business views, financial/HR/stock/Equipment services/models, Composer/npm dependencies or public assets. Existing permissions and project/site scoping are preserved. The reviewed build ZIP is unchanged:

**B1FF72DAC9A9BE51DAD766EF16238D2FC46AC3F9B9E4FA36A27B9B6E06B39C4E**.

## Fresh combined verification

| Verification | Result |
| --- | --- |
| UserGuideTest + NotificationCenterTest + ApprovalRuntimeTest + SupplierBillApprovalRuntimeTest + SiteExpenseTest | **145 passed / 979 assertions / 0 failures**, exit 0; 146.56s runner wall |
| JavaScript suite | **12 passed / 0 failed / 0 skipped**, 198.6405ms |
| HTML validation | **Six pages / 551 internal links and anchors / 94 valid embedded images** |
| Combined browser checks | **33/33 passed**, no page errors or failed guide responses |
| Notification coexistence | Authenticated dashboard retains bell; notification inbox HTTP 200 |
| English/Arabic guides | Authenticated + offline; 94 image decodes; language navigation; 320/390/768/1440px without page overflow |

Browser checks used a new private **copy of the already synthetic training fixture**, on loopback 18774. The notification migration was applied only to that disposable copy because the original screenshot fixture was core-only. This is not a production migration. Production ZATCA disabled in the fixture; HTTP business writes blocked by the training router. No live DB, authenticated production browser or client uploads were used.

Full cumulative Laravel/business UAT was not repeated: this integration leaves the approved notification/business implementation unchanged and exercises its relevant runtime classes alongside guide access. Prior standalone 856-test Notifications QA and 736-check visual capture evidence remain historical evidence, not fresh full-suite/production checks. Client acceptance, native Arabic review, physical-device/Finance acceptance and production operational sign-off are not invented.

Evidence: [visual-guide-integration-2026-11-02](evidence/visual-guide-integration-2026-11-02). The helper gained an output-directory override so this combined run does not overwrite the original visual QA evidence, plus optional bell/inbox coexistence checks.

## Rollout contract

This is an **8dcb1d7 → combined guide candidate** code/documentation update. The server must already have the notification migration recorded and table present, with no pending migrations. Verify actual server HEAD first; do not assume the remote branch is deployed.

**No production migration or seeder is required or authorized for this guide-only delta.** No Composer install/update or new npm build is required. Preserve server .env/APP_KEY, .htaccess and local overrides; use the unchanged verified ZIP if extracting assets. Rehearse and retain normal private backup/restore evidence; execute only after owner authorizes the exact candidate/window.

The final deployment instructions pin the evidence/documentation HEAD containing this report. A failed command must leave maintenance on for diagnosis. On success, the deployment block includes artisan up, avoiding a forgotten maintenance step.

Code rollback to recorded notification baseline is additive-table compatible because no schema/financial implementation changes were introduced. Review any new server changes, preserve notification/financial records and restore matching prior assets; never roll back migrations or replace the live database for this guide-only update.
