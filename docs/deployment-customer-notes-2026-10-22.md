# Deployment — Customer Credit / Debit Notes Phase 1 (AR / VAT / GL / ZATCA correction documents), 22 October 2026

Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md), [deployment-zatca-phase2-2026-10-17.md](deployment-zatca-phase2-2026-10-17.md) and [deployment-zatca-phase2-gate-2026-10-17.md](deployment-zatca-phase2-gate-2026-10-17.md).

Brings the server from the ZATCA gate HEAD (`1d8507b`) to the Customer Notes Phase 1 HEAD. **One additive migration, no seeder, no permission rows, asset bundle unchanged.** Deploying this release does **not** connect anything to ZATCA; production stays disabled.

| Item | Value |
| --- | --- |
| Migration | `2026_10_22_000001_add_customer_adjustment_notes` — creates `customer_adjustment_notes` and `customer_adjustment_note_lines`; adds `customer_adjustment_note_id` (nullable FK) and `document_type` (default `invoice`) to `zatca_invoice_records`. Guarded, re-runnable, verified on MySQL 8.0.30 by `tests/manual/customer-notes-concurrency.php`. Existing invoices, receipts, journals and ZATCA records are untouched (a note record keeps `customer_invoice_id` = the original invoice). |
| Seeder | None. |
| Permissions | None added. Notes reuse **Accounts Receivable** (view register / note, create draft, edit draft, approve = post) and **ZATCA Invoicing** (view / post / retry / export on the note's record). Routes live under `admin.accounting.accounts-receivable.notes.*`, so the existing route-suffix rules apply. |
| Asset bundle | **Unchanged**: `public/build.zip` 197,777 bytes, SHA-256 `436847840B7FC77904572B45ED3388752BA76CBC0A12290EFBE821A31BD18B7C`. No unzip step. |
| Configuration | None. |
| New routes / screens | `/admin/accounting/accounts-receivable/notes` (FIN-AR-006 register), `/notes/create?invoice=ID&type=credit|debit` (FIN-AR-007 draft form), `/notes/{id}` (FIN-AR-008 note workspace), `/notes/{id}/print` (FIN-AR-009 bilingual print view). Sidebar: Accounting → Credit / Debit Notes. The Customer Invoice workspace gains the "Adjustments / Notes" section; the Customer workspace gains the "Credit / Debit Notes" panel; the ZATCA register shows notes (381 / 383). |
| Accounting impact | Posting a note writes one journal (`source_module` Customer Credit Note / Customer Debit Note, event Note Posted; it follows a posting rule of its own if one is defined, otherwise the Customer Invoice rule) and one signed VAT correction row (negative for a credit note). The original invoice journal and VAT row are never changed. `AutomaticPostingRule` rows for the new modules are optional. |
| Behaviour change | Posted notes change the original invoice's outstanding amount: balance = invoice total + posted debit notes − credit applied − receipts. A credit note on a paid invoice records the excess as `unapplied_amount` on the note (customer credit awaiting Finance); no refund or allocation is automated. `refreshPaymentStatus()` now nets notes for every later receipt. AR cards, ageing and the customer summary follow the stored balance; the Project Cost Report nets posted notes in its "invoiced" figure. |

## 1. Back up and inspect

As in the previous guides (database, `.env`, `storage/app`, current commit), then `$PHP artisan migrate:status | grep -i pending`.

## 2. Deploy, one command at a time

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan down
git pull --ff-only origin feature/seera-connected-workspaces-2026-09-23
git log -1 --oneline
$PHP artisan optimize:clear
$PHP artisan migrate:status | grep -i pending                # expect 2026_10_22_000001_add_customer_adjustment_notes
$PHP artisan migrate --force
$PHP artisan migrate:status | grep -i pending                # expect nothing
$PHP artisan view:cache
$PHP artisan up
```

`composer.lock` unchanged; no Composer step; no bundle step. Never `composer update`, `db:seed`, `migrate:fresh`, `migrate:refresh` or a blind rollback.

## 3. Post-deploy configuration (owner / Finance)

1. Roles: confirm who may **create** and **approve** under Accounts Receivable; approve now also posts credit / debit notes.
2. Optional: define Automatic Posting Rules for "Customer Credit Note" / "Customer Debit Note" with trigger "Note Posted" if notes should follow a different review mode from invoices.
3. Finance policy still pending: VAT rounding (unchanged), and the handling of unapplied customer credit (refund or allocation is manual outside Seera in Phase 1).

## 4. Verify

- Open a posted invoice → Adjustments / Notes → Create Credit Note → save draft → Post Note: journal, VAT row, adjusted balance on the invoice, ZATCA record "Not generated" on the note.
- Only in sandbox / simulation with a test CSID: Submit to ZATCA on the note record; expect Cleared (standard) or Reported (simplified).

## Recovery

Additive migration: to back the code out, return to the previous commit and leave the tables in place. Never delete posted notes: their journals, VAT rows and ZATCA records are issued documents.
