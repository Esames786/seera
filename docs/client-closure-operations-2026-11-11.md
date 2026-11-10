# Client closure — operator prerequisites

This is preparation for a separate owner-approved rollout, not an instruction to deploy immediately. Current development branch: `feature/seera-client-closure-2026-11-11`. Latest owner-reported production source: `fix/seera-client-guide-backport-2026-11-10` at `9cc5ea0`.

## Blocking storage condition

The reported live database is mainly MyISAM, with partially attempted November DDL. Do not run a generic `git pull; migrate; up` sequence. Transactional financial workflows require InnoDB and actual constraints. See [the corrected recovery guide](deployment-feedback-recovery-2026-11-10.md#path-c-prepare-a-complete-innodb-copy-without-discarding-current-data).

The replacement `seera:prepare-storage-copy` defaults to read-only inspection. An operator must supply a different existing **empty** database on the same server. Execute requires the full private backup file and SHA-256, successful restore attestation, stopped-writer attestation, destination-name confirmation, and production maintenance plus `--force`. It prepares a copy only; it does not alter the source or change `.env`. Inspect the private verification report, reconcile business balances, and rehearse the destination before requesting approval to promote it.

The earlier setup-only `migrate:fresh`/selected-table restore recipe is withdrawn. New real records, all existing logins, business documents, posting history, IDs and uploaded files must be preserved. Do not reset the database or delete old/demo entries as part of this repair.

## New schema and release assets

The complete candidate requires both 10 November additive migrations plus `2026_11_11_000001_create_supplier_recoverables.php`. The latter creates `supplier_advances` and `supplier_advance_movements`; no historical backfill or financial seeder is included. The copy preparation creates the complete current destination schema and its own new migration ledger. Do not copy the old migration ledger over it or manually mark migrations complete on the source.

Changes are PHP/Blade/translations and documentation; bundled frontend sources are unchanged. The existing 18-entry `public/build.zip` is byte-checked against extracted assets and manifest references. SHA-256: `F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7`. Preserve the hosting-specific `public/.htaccess`. Do not restore old build folders over a verified candidate.

## Overdue reminder activation

Default is **off** until the approved release/database is installed and the owner activates scheduling. The owner approved daily in-app reminders from one day overdue. No worker was launched by this development work.

1. Check application date/timezone. The daily run is 08:00 in the configured application timezone; do not silently change historical date/time policy.
2. Read-only preview: `php artisan seera:overdue-reminders`. It reports the eligible invoice count and does not create notifications.
3. At the approved activation, set `SEERA_OVERDUE_REMINDERS_ENABLED=true` in the private environment, then clear the relevant cached configuration using the established deployment procedure.
4. Configure exactly one hosting scheduler entry to call `php artisan schedule:run` each minute with the approved PHP 8.3 binary. No queue worker is required for this database-only reminder flow.
5. Preview first, then an explicit `php artisan seera:overdue-reminders --send` may be used for the approved smoke check. Same user/invoice/day is deduplicated, including a retry or competing process.
6. Verify only active authorized Finance/Super Admin recipients receive it, project/site scope is respected, and a settled invoice disappears from the current notification list. Stopping the feature prevents new notices; existing accessible history is retained.

## Supplier recoverable mapping

Finance creates/chooses its own dedicated advance asset; no account or posting rule is seeded automatically. Use Automatic Posting Rules:

- Source module: `Supplier Advance`; trigger: `Payment Recorded`.
- Debit: active dedicated non-VAT advance/recoverable asset, without a required cost centre. Cash/bank/receivables/input-VAT/inventory controls and their descendants are refused.
- Automatic posting: enabled; extra approval required: disabled. The explicit action itself requires Accounts Payable **view + approve + process** rights.
- The actual cash/bank account comes from the payment form and is restricted by Supplier Cash/Bank/Both. The configured credit field is not an alternate bank override.

Use Supplier → Edit / Manage → Supplier advances. Check a small authorized training/staging example: original payment, partial application to the same supplier/project/site bill, cash return, reversals and balances. Applications debit the AP account stored in the bill's original journal, not a later changed supplier mapping. Supplier View stays read-only.

This covers agreed recoverable payments, including salary/repair paid on behalf. A new taxable back-charge is not silently zero-rated and must follow the separately approved tax-document treatment. Existing VAT rounding, bill VAT, payroll, bank history and posted journals are not rewritten.

## Account/master cleanup is a separate approval

Run `php artisan seera:entry-audit --json` read-only and save the result privately. It includes exact IDs, displayed Employee codes, actual linked Employee IDs/codes, duplicate-code conflicts, counts and missing cost-centre references. It contains names/emails; do not upload it to a public guide or commit it.

The owner must approve the exact retained client/Super Admin and real-user IDs, then identify old accounts for deactivation. Existing deactivation controls retain history. Do not infer demo status from names, dates or a reused displayed Employee code. Confirm Project/Location relationships and the current user's actual record scope before changing setup or adding records.

## Acceptance evidence still required

- Full backup plus successful isolated restore and the final consistent snapshot with writers stopped.
- Source/copy counts, user/role/link checks, opening/posted GL, AP, AR, VAT, payroll and stock reconciliation.
- Exact private disputed IBAN comparison; a failing check digit is not bypassed.
- Real-user/project scenario, approved cleanup list, client workflow and Arabic acceptance.
- Physical-device GPS/offline testing and external integrations where contracted.
- Production ZATCA remains disabled; its onboarding/SDK/company/Finance decisions remain separate.

No production deployment, database switch, migration, seed, reset, deactivation, queue or scheduler launch was performed by this work.
