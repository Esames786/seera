# Recovery and next-release handoff — 26 October 2026

Current owner-evidenced production release: `aca78ee`; previous `4d77c77`. Four October migrations have already run. This review adds tests/evidence only and needs **no deployment, migration, seeder or rebuilt ZIP**.

For a future release, use [the cumulative deployment guide](../deployment-cumulative-2026-10-25.md) with its exact reviewed commit and matching archive checksum, not an old placeholder or assumed latest branch tip.

Before a future deployment: record current HEAD/migrations and nonsecret runtime settings; verify private database/storage/config backups and restore access; preserve APP_KEY, private XML and documents, hosting .htaccess, assets and offline receipt IDs. Pause writes/workers as needed for a consistent backup. Do not infer a verified restore from the mere presence of a backup file.

If a command fails: stop the block, retain maintenance mode while investigating, preserve the exact error and the latest transaction cutoff. Prefer a reviewed corrective roll-forward for an isolated code defect. Do not run demo seeders, migrate:fresh/refresh, blanket rollback, reset --hard, or restore over new transactions.

Before a code rollback: Finance and the release owner must assess whether the older code understands customer notes/refunds, offline idempotency receipts and new ZATCA identities already stored. Additive tables alone do not establish backwards compatibility. Select matching old assets; preserve source-controlled server configuration. Do not blindly return to `4d77c77` after using new financial features.

Database restore is a separate owner-authorised recovery action: isolate writes, retain the current database for reconciliation, test the backup in a disposable environment, quantify transactions since the backup and agree how they will be recovered. Never lose accepted ZATCA documents or paid salary/customer/supplier history to make a rollback appear clean.

This pass did not verify remote backup/restore, cPanel cron or worker supervision. Those remain operator checks. Production ZATCA activation is outside this runbook.
