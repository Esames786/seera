# SEERA — faster Laravel verification plan (30 October 2026)

This is a **development-only** acceleration workstream. It is **not** a production release or permission to merge, deploy, migrate, seed, or enable production ZATCA.

Baseline branch: `feature/seera-project-reports-mvp-2026-10-29` at `5120f18` (full-suite final result/evidence commit was still pending when this document was prepared).
Acceleration branch: `feature/seera-test-speed-2026-10-30`.

## Confirmed repository facts

- PHP `^8.3`, Laravel `v13.18.0`, PHPUnit `12.5.30`.
- `composer.json` has **no** `brianium/paratest` dev dependency; `composer.lock` has no locked ParaTest package. Mentions of ParaTest under other packages' `suggest` fields do not install it.
- `phpunit.xml` specifies `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`. Each separate PHP worker gets an independent in-memory DB. The application uses user-access global scopes; verify workers do not share other mutable artifacts.
- Latest reported full-suite baseline before this sprint: 795 tests / 9,640 assertions in about 3,111 seconds on a dev machine (source: invoice-reopen-messages QA). The Project Reports full suite was not finished when its 5120f18 handoff arrived.
- The owner reports 12 logical cores / 32 GB RAM, a single-process suite, frequent `DatabaseSeeder` calls, and CLI OPcache disabled. These require measurement rather than assumed speedups.

## Step 1 — Install the missing dependency on an isolated development checkout

Once the current full run completes, save its exact counts and elapsed time. Use the acceleration branch, not production:

```powershell
git status --short
git switch feature/seera-test-speed-2026-10-30
composer require --dev brianium/paratest
composer validate --strict
composer show brianium/paratest
git diff -- composer.json composer.lock
```

Commit **both** `composer.json` and Composer-generated `composer.lock` on the acceleration branch after checking that the solver selected a release compatible with the locally installed PHP and the already locked PHPUnit 12.5.30. Do not manually fabricate a lockfile, use `--ignore-platform-reqs`, or unnecessarily upgrade Laravel/PHPUnit. If solver reports conflicts, report them and stop instead of changing the application's dependency graph without review.

Laravel 13's documented entry point is `php artisan test --parallel` after installing ParaTest.

## Step 2 — Benchmark without overlapping a running full suite

Use `scripts/test-speed.ps1` on Windows / Laragon. The script refuses a missing local `vendor`, a cached Laravel config (which could ignore the SQLite test overrides), and parallel mode without installed ParaTest. It prints the exit code and wall-clock seconds.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode focused -Filter ProjectReportsOverviewTest
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode parallel -Processes 4
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode parallel -Processes 8
```

If the serial baseline is no longer representative, once run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-speed.ps1 -Mode serial
```

Record test count, assertions, skipped/incomplete, failures, seconds, processes, PHP ini path, CPU/RAM saturation. Compare time **excluding machine sleep and other full-suite jobs**. Start with 4 workers; keep 8 only if it is measurably faster, stable, and memory-safe. A 2–3x wall-time reduction is a **target**, not a guaranteed result.

## Step 3 — Isolated CLI OPcache experiment

On the local *development* PHP configuration only, test `opcache.enable_cli=1`. Verify the effective setting in both the PHP executable that runs Artisan and the PHP worker executables it launches. Merely passing `php -d opcache.enable_cli=1 artisan test` may not carry that `-d` option into child processes; configure the actual Laragon test PHP `php.ini` or use an explicit isolated test INI, then verify `php -i`.

Measure 4 and/or 8 workers with OPcache on and off. Roll it back if benefits are absent or test behaviour changes. Do not commit edits to any production PHP INI or alter application OPcache policy.

## Step 4 — Parallel-isolation gate

Before accepting an 8-worker benchmark, verify:

- Every run has the same test inventory, passed assertions and zero unexpected skips. Preserve the 795-test baseline plus new Project Reports tests once the report is complete.
- SQLite tests remain in `:memory:` **per worker**; no common test database or production/staging connection is ever used.
- No race on shared filesystem paths: test fixtures, uploads, temp artifacts, cache files, session files, lock files, browser screenshots, ZIPs, `public/build.zip`, or files in `storage`.
- No tests depend on cross-class order, global constants, static test-class state, hardcoded document IDs or existing demo data across workers.
- No accidental production credentials are available in the test environment.
- Browser checks continue isolated from the full-suite run; do not run two heavy test suites concurrently on one workstation.

If a parallel race is found, isolate the test fixture using the ParaTest `TEST_TOKEN` or `UNIQUE_TEST_TOKEN`, and write a regression test before adopting this as the standard command.

## Step 5 — Seeder optimization only after measuring parallel mode

Many existing Feature tests call `$this->seed(DatabaseSeeder::class)` in `setUp()`; this is a major candidate, but changes to shared seed lifecycle carry authorization/financial-fixture risks. First profile the slowest tests and count relevant seeders. Then prototype in a separate branch: either a minimal test seeder per narrowly scoped class, or a deterministic baseline SQLite fixture **copied separately per worker**, with transaction rollback per test. Do not reuse a mutable SQLite file across workers; do not share an in-memory PDO connection between child processes. Require a green full suite, accounting reconciliation assertions and cross-worker repeat runs before replacing seeded fixtures.

## Proposed verification cadence

- Every feature branch: focused class(es) + affected regression filter + targeted browser harness.
- Each release integration branch: complete Laravel suite once, JavaScript suite and agreed browser/UAT matrix.
- For security/accounting/ZATCA core changes: run additional relevant tests before handoff even when unrelated to the current UI sprint.
- Never call tests green while they are still running; no "GO for integration" until the exact full-suite exit code is recorded.

## Delivery conditions

A speed change becomes ready for integration only after Composer lock verification, isolated repeatable 4/8-worker test runs, identical expected assertion counts and no races. Keep original client feature branches and production untouched throughout.
