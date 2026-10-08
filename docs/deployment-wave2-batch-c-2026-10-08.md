# Deployment — Wave 2 Batch C (Customer Invoice document workspace), 8 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md) and the Batch B guide.

Brings the server from `8103bdb` (Batch B) to the Batch C HEAD. **No migration, no seeder,
no new route and no asset change**: `public/build.zip` is the Batch B archive
(188,884 bytes, 18 files, SHA-256
`B73DAD25B9372E90603DB5A0ABCDF2FD77C3D1AE7E56E393879B1601C2666AC0`), so no re-extraction is
needed. The user guide HTML (version 1.8) is served from the same `/user-guide/` route.

What changes on screen: the Customer Invoice View is a read-only finance document workspace
(header with Received / Outstanding / payment state, sections Invoice Information, Lines,
Customer, Project / Cost Center, VAT, Accounting, Receipts, Balance / Ageing, Local e-Invoice
Record, Activity); Edit Draft, Record Receipt and the receipt form return to the customer or
project page they were started from; Customer workspace invoice links carry that context.

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php

$PHP artisan down
git fetch origin
git checkout feature/seera-connected-workspaces-2026-09-23
git pull --ff-only
$PHP artisan migrate --force          # "Nothing to migrate" is expected
test -f public/build/manifest.json && echo "assets ok"   # unchanged; re-extract only if missing
test -f docs/user-guide/index.html && echo "user guide ok"
$PHP artisan optimize
$PHP artisan up
```

Post-release checks (read-only, signed in):

1. Finance → Accounts Receivable → View on an approved invoice: header shows Total, Received,
   Outstanding and the payment state; the section bar lists only the sections your role may
   read; Record Receipt appears only for open invoices with the process right.
2. Open the same invoice from Customers → Edit / Manage → Invoices → View: the page shows
   **Back to origin** and returns to the customer's Invoices tab.
3. Open a draft invoice: Edit Draft is offered only with the edit right; an approved invoice
   offers no Edit.
4. The Local e-Invoice Record section (ZATCA Invoicing view) says "local record, not verified
   live" and never "cleared by ZATCA".
5. `https://seera.tech-brit.co.uk/user-guide/` shows version 1.8.

Rollback: `git checkout 8103bdb`, then `$PHP artisan optimize`. No database or asset rollback.
