# Two-account clean-start verification — 11 November 2026

## Decision and evidence boundary

**GO for operator preparation and read-only destination inspection.** Actual destination preparation and activation remain conditional on the command's successful private verification report and live smoke checks. No production connection, migration, cleanup, seed, configuration change or deployment was performed during this verification.

Branch: `feature/seera-clean-start-two-accounts-2026-11-11`.

- `02f6da464552f5cdd0135fa7d30c8901b55dddbb`: new clean-start service/command and tests, based on existing November application candidate `f22e391`.
- `48ea709e926d50858c4daf2cde0a5bdf7b914da5`: same application code, updated client guide/validator and separate-destination-user test probe. This is the pinned operator application/guide release. Later documentation/evidence commits do not change its application code.

The owner's terminal output identified retained User IDs **1** and **15**. ID 1 is the active Super Admin. ID 15 is the active Finance Manager; no Super Admin elevation is authorized or performed. The owner then reported creating **`techbrit_seera_clean`**. Its actual empty state, new user creation and ALL PRIVILEGES still require the read-only operator check. DB creation in a screenshot alone does not prove those privileges.

## What is implemented

`seera:prepare-clean-start` is read-only by default. Execution prepares a **different empty MySQL database**, using current migrations, without a seeder. It keeps exactly two explicitly supplied existing users, their existing password hashes and access assignments, all setup/entry masters, master files/relationships, sequences and IDs. It excludes old business documents and runtime rows. The original DB, configuration, table engines and uploaded files are untouched; no automatic promotion occurs. This separate clean-start path does not require a SQL backup of the discarded business history and does not bypass or weaken the existing full-preservation copy command.

Existing nullable master references to excluded users are detached in the destination and reported. Required/explicit approval-user references are refused for review. Assigned equipment, unknown schema, bad master FKs, changed source data, production ZATCA history/settings, invalid Admin identities and nonempty targets are refused. Source and destination identity checks, destination lock, per-master hashes, retained login-set verification, empty business-table checks, fresh migration ledger and all-InnoDB/FK scans run before a success report.

The original database still contains the old accounts/history after preparation. After activation those rows are not in the new portal; this is not a physical purge of the old database. Runtime source tables must not be edited concurrently. Retained opening balances are not silently changed to zero. Role-based approvals may need replacement staff users, as requested by the client. No automatic approver reassignment or new background queue worker is introduced.

## Fresh verification results

| Check | Result | Evidence |
| --- | --- | --- |
| New command regression tests | **7 passed / 27 assertions** | Included in the complete run; direct focused run passed before it |
| Complete accelerated Laravel suite | **944 passed / 10,892 assertions**, zero failures | [Full output](evidence/clean-start-2026-11-11/laravel-full.txt), at `02f6da4` |
| JavaScript suite | **12 passed**, zero failures | [Output](evidence/clean-start-2026-11-11/javascript.txt) |
| Final positive disposable MySQL scenario | **17 checks passed** | [Output](evidence/clean-start-2026-11-11/positive.json); separate destination credentials, legacy MyISAM/partial schema, exact two users, hashes, files and roles |
| Six disposable negative MySQL scenarios | **24 checks passed** (4 each) | Orphan master, removed explicit approver, external source writer, unknown table, enabled production ZATCA, assigned asset; [evidence directory](evidence/clean-start-2026-11-11/) |
| Updated November guide tests | **5 passed / 99 assertions** | Focused `UserGuideTest` after the documentation update |
| Guide link/content validation | **8 pages / 615 links and anchors / 94 validated visual-example JPEGs / 13 issue rows** | `php scripts/validate-user-guide.php` |
| Deployment syntax validation | **3 Bash blocks + 2 embedded PHP guards** passed | Syntax only; no server-side execution |
| Single activation-script helper | **16 isolated fixture checks passed**, Bash and **4 embedded PHP blocks** linted | [Fixture evidence](evidence/clean-start-2026-11-11/activation-helper.json); password quoting/round-trip, atomic replacement, unchanged APP_KEY/settings/real development env, valid/missing/incomplete/wrong-user/ambiguous reports |
| Existing build package | **18 ZIP entries** readable; SHA-256 `F6451E04AA14549EA7EC629F27DE9BECB5E2FCF37D063DE9E63710A84B132BF7` | Unchanged application assets; rebuild unnecessary |
| Fresh production browser acceptance | **NOT RUN** | No authenticated production terminal/browser connector; operator actions remain necessary |
| New browser screenshot run | **NOT RUN** | No application UI implementation changed; existing screenshot evidence is retained, not presented as a fresh live check |

The MySQL runs used a newly initialized disposable datadir with server-identity guards. Application and production DB credentials were not used for those tests. The source received only read queries during the copy; negative cases confirm no source rows were removed. The additional destination-user test uses a generated synthetic password and checks that it never appears in the report.

During guide validation, the existing validator incorrectly treated repeated issue IDs in the screenshot index as duplicate primary issue rows. It now scopes the uniqueness check to the primary issue table. The guide itself still has all 13 rows and its screenshot index; no client data or routes changed.

## Delivery and remaining operator work

- [Exact clean-start deployment steps](../deployment-clean-start-2026-11-11.md): create/privilege the new user, A read-only preparation, B final stopped-writer copy, C private DB configuration/code/assets switch, D smoke/recovery boundary.
- [November client issues and screenshots](../user-guide/CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.md): R10-06 identifies the retained accounts and clearly says installation is pending; R10-07 says masters are retained, so specific old master removal remains separate.
- Existing production-reported HEAD remains `9cc5ea0` until new operator evidence shows otherwise. Do not infer live activation from these pushed commits.
- Production ZATCA stays disabled. Actual live photo/document upload, password login, code preview, Finance/HR access, preserved master files and ledger starting position still need the operator/client smoke checks.
- No unconditional release approval is claimed for the actual uninspected target DB, cPanel privileges or final activation.

## Owner execution update

The owner subsequently supplied a successful read-only preflight and then a terminal screenshot reporting **Clean destination prepared for operator review**, with source DB/config/files unchanged and **website NOT switched**. This confirms command completion, not live activation or fresh browser acceptance. A nonzero master opening balance of SAR 500 was shown and is deliberately preserved. The single activation helper will validate the actual private report at execution before attempting cutover; that report has not been read through a remote developer connector.
