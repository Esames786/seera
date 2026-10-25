# Deployment — Customer Credit Resolution Phase 1 (apply credit to invoice + customer refund), 23 October 2026

Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [deployment-customer-notes-2026-10-22.md](deployment-customer-notes-2026-10-22.md) and [production-deployment-guide.md](production-deployment-guide.md).

Brings the server from the Customer Notes HEAD (`fef2152`) to the Credit Resolution HEAD. **One additive migration, no seeder, no permission rows, asset bundle unchanged.** Nothing connects to ZATCA; production stays disabled.

| Item | Value |
| --- | --- |
| Migration | `2026_10_23_000001_add_customer_credit_resolution` — creates `customer_credit_allocations` and `customer_credit_refunds` (explicit short constraint names). Guarded, re-runnable, verified on MySQL 8.0.30 by `tests/manual/customer-credit-concurrency.php`. No existing table changes. |
| Seeder | None. |
| Permissions | None added. Apply Credit = Accounts Receivable **process** (like receipts); Refund Customer and every reversal = Accounts Receivable **approve**; the Customer Credits register = view. Routes `admin.accounting.accounts-receivable.credits.*` are mapped explicitly in the permission middleware. |
| Asset bundle | **Unchanged**: `public/build.zip` 197,777 bytes, SHA-256 `436847840B7FC77904572B45ED3388752BA76CBC0A12290EFBE821A31BD18B7C`. No unzip step. |
| Configuration | None. Optional: an Automatic Posting Rule "Customer Credit Refund" / "Refund Recorded"; otherwise refund journals follow the Customer Receipt rule, else post directly. |
| New routes / screens | `/admin/accounting/accounts-receivable/credits` (FIN-AR-010 Customer Credits); POST actions under `/accounts-receivable/notes/{id}/credit/…` (allocate, refund, reverse). Sidebar: Accounting → Customer Credits. The note workspace gains "Credit Resolution"; the invoice workspace gains "Applied Customer Credits" and splits "Received (cash / bank)" from "Customer credit applied"; the customer workspace header shows Available customer credit and Net customer position. |
| Accounting impact | Allocation: none (AR sub-ledger only). Refund: one journal per refund, Dr 1200 Accounts Receivable / Cr the selected 1110 / 1120 account (source module "Customer Credit Refund"); reversal posts the opposite journal through the standard reversal. No VAT rows. No change to any existing journal, receipt, note or ZATCA artefact. |
| Behaviour change | `CustomerInvoice::refreshPaymentStatus()` now also subtracts active credit allocations received from other credit notes; `received_amount` stays cash only. The customer summary adds `available_credit` and `net_position`; the "View all" link of the Receipts & Balance panel points at the Customer Credits register. |

## Deploy, one command at a time

**Superseded release warning (26 October):** use the cumulative 25 October guide and its corrective-release evidence before deploying. The original release permitted refunds backed only by draft journals. The correction refuses and rolls back new review-mode refunds, requires a posted source-note journal, and blocks replay/reversal of inconsistent existing refunds. It does not change Finance posting rules or repair historical rows automatically. Existing affected data needs a scoped Finance reconciliation before any repair.

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
$PHP artisan optimize:clear
$PHP artisan migrate:status | grep -i pending                # expect 2026_10_23_000001_add_customer_credit_resolution
$PHP artisan migrate --force
$PHP artisan view:cache
$PHP artisan up
```

`composer.lock` unchanged; no Composer step; no bundle step. Never `composer update`, `db:seed`, `migrate:fresh`, `migrate:refresh` or a blind rollback.

## Verify

- Customer Credits register opens; a posted credit note with unapplied credit shows Credit Resolution on its page.
- Apply a small amount to another open invoice of the same customer: the invoice balance falls, no journal appears.
- Refund a small amount to the bank account: one journal Dr 1200 / Cr 1120, no VAT row; reverse it with a reason: the opposite journal appears and the credit is available again.

## Recovery

Additive migration: to back the code out, return to the previous commit and leave the tables in place. Allocation and refund rows are financial history; reverse them through the screens, never delete them.
