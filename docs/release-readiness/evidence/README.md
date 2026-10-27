# Evidence retained from 26 October 2026

## 27 October closure evidence

The `defect-closure/` directory contains separate new screenshots/results; baseline failures below were not overwritten. See [closure report](../SEERA-DEFECT-CLOSURE-2026-10-27.md). The manual VAT reproducer now expects refusal; its former 1-test/7-assertion result remains historical. The original 34-test/392-assertion focused run is also retained as `vat-closure-focused.xml`.

- `affected-tests.xml`: 112 tests / 1,283 assertions, no failures/errors/skips.
- `browser-results.json`: 46 passed, no script errors or failed same-origin resources. Five RTL widths, all Employee/Customer tabs, a 320px master dialog, dirty guard/long content and three English pages.
- `javascript-tests.txt`: all 12 JavaScript tests passed, no skips.
- `full-laravel-tests.xml` and `full-laravel-console.txt`: full-suite evidence; use the completed report's totals, not an in-progress file.
- Screenshots contain only the synthetic local fixture. Private environment keys and the SQLite database remain outside the repository.

## Baseline review

- `critical-183-tests.xml`: fresh focused PHPUnit output, 183 tests / 2,049 assertions, zero errors/failures/skips. Test names/paths and durations are retained; data is synthetic.
- `browser-results.json`: 33 actual local-browser checks, 30 passed / 3 failed. No failed same-origin response or JavaScript exception. Browser service workers were blocked, so this does not prove offline behaviour.
- `mobile-ar_*.png`: reproduced horizontal overflow on three 390px Arabic pages; synthetic seed identities only.
- `dashboard-ar-desktop.png`: working Arabic/RTL shell with remaining English labels visible.

Separate targeted runs: connected procurement scenario 1 test / 36 assertions; R22-02 current-behaviour diagnostic 1 test / 7 assertions. The latter PASS demonstrates the open requirement, not acceptance of the desired restriction.

Reproduction commands (from repo root, with explicit `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty `DB_URL`, and `ZATCA_PRODUCTION_ENABLED=false`; refuse cached application config):

```text
php vendor/phpunit/phpunit/phpunit --filter "FinanceAcceptanceScenariosTest|GrnBillMatchingTest|SupplierBillApprovalRuntimeTest|PayrollAccountingTest|PayrollSettlementTest|FinanceScopeAuthorizationTest|VatPeriodLockTest|SiteExpenseTest|InventoryTest|ProjectBudgetReportTest|EquipmentCostTest"
php vendor/phpunit/phpunit/phpunit --filter test_uat_received_goods_runtime_approval_and_partial_full_payment_reconcile
php vendor/phpunit/phpunit/phpunit tests/manual/RequirementGapReviewTest.php
```

The broad selection will now include the added procurement test (184 instead of the original 183), because that test was added after the original suite had loaded its test list. Do not replace the recorded original count with the expected rerun count.

For the browser harness, first create a NEW isolated temporary fixture as described in the local rehearsal report; migrate/seed only its database, start its own localhost server and point the harness at its `process-env.json`. The harness refuses other base URLs/database paths. The temporary environment/key, database and full screenshots are outside Git. It is not a tool to run against production or a server sharing the application database.
