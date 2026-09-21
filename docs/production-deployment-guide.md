# Seera Production Deployment Guide

This guide applies to the hardened release after commit `8f32d728`. The production server shown in the handoff is still at `8f32d728`; do not begin until the new release has been committed and pushed to `origin/main`.

The current project declares PHP `^8.3` and Laravel `^13.8` in `composer.json`.

## 0. Server binaries (read first)

On the cPanel host the plain `php` command is PHP 8.2 and `composer` is not on the
PATH, so every artisan or composer command in this guide fails with "Your Composer
dependencies require a PHP version >= 8.3.0" or "composer: command not found" unless
the full paths are used. Start every SSH session with:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
COMPOSER="$PHP /opt/cpanel/composer/bin/composer"
$PHP -v            # must print PHP 8.3.x
$COMPOSER --version
```

If `/opt/cpanel/composer/bin/composer` does not exist, find it with
`ls /opt/cpanel/composer/bin/` or `which composer`, and use that path instead.
All commands below assume `$PHP` and `$COMPOSER` are set.

## 1. Choose the database path

### Path A - normal upgrade (preserve data)

Use this for every release on a database that has ever been used. Run only:

```bash
$PHP artisan migrate --force
```

The migrations are additive. Seeders are only needed when a release says so in its
own section (sections 9 and 10 below).

### Path B - brand-new, empty database only

Only for the very first setup on a database that contains no tables. It uses the same
`migrate --force` (on an empty database it creates everything) followed by the
bootstrap seeder:

```bash
$PHP artisan migrate --force
$PHP artisan db:seed --class=ProductionBootstrapSeeder --force
```

**`migrate:fresh` is not used anywhere in this guide.** It drops every table and has
already erased the production database twice by accident. If a wipe is ever truly
intended, take a `mysqldump` first and type the command deliberately, never from
shell history.

`ProductionBootstrapSeeder` creates only: the Administration department and the
permission catalogue, the Super Admin role and the bootstrap administrator from
`.env`, a minimal company profile, the standard chart of accounts with zero balances
plus posting rules and the current VAT period, the leave types, the organisation-chart
departments, roles, designations and staff accounts, and the Marketing permissions.
It does not create demo employees, projects, suppliers, customers, payroll, journals,
invoices, stock, or activity history.

Never run `$PHP artisan db:seed` without a `--class=` in production. The default
`DatabaseSeeder` intentionally creates demo users and transactions.

## 2. Pre-deployment checks and backups

From `~/seera`:

```bash
git status --short
git fetch origin
git log --oneline --decorate -5 origin/main
$PHP -v
$COMPOSER --version
```

Stop if the worktree is not clean or `origin/main` does not contain the intended release commit.

Back up the database and uploaded files before changing code. Replace the placeholders with the production database values:

```bash
mkdir -p ~/seera-backups
mysqldump -u DB_USERNAME -p DB_DATABASE > ~/seera-backups/seera-before-release.sql
tar -czf ~/seera-backups/seera-storage-before-release.tar.gz storage/app
cp .env ~/seera-backups/seera-env-before-release
```

Keep these backups outside the Git checkout and restrict access to them.

## 3. Production environment

Preserve the existing `APP_KEY`; changing it invalidates encrypted data and sessions. Configure at least:

```dotenv
APP_NAME="Seera Construction ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_from_address
MAIL_FROM_NAME="${APP_NAME}"
```

For a clean production bootstrap, also set temporary unique values:

```dotenv
SEERA_COMPANY_NAME="Your Legal Company Name"
SEERA_ADMIN_NAME="Production Administrator"
SEERA_ADMIN_EMAIL=owner@your-domain.example
SEERA_ADMIN_USERNAME=owner
SEERA_ADMIN_PASSWORD="use-a-unique-password-of-at-least-16-characters"
```

The current release has no scheduled tasks or dispatched queue jobs. `QUEUE_CONNECTION=sync` is therefore suitable for this shared-hosting deployment. Add a supervised queue worker and scheduler cron only when later features introduce them.

The domain document root must point to `~/seera/public`, never to `~/seera`.

## 4. Deploy the release

Enable maintenance mode, update code, and install locked production dependencies:

```bash
cd ~/seera
$PHP artisan down --retry=60
git pull --ff-only origin main
$COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Confirm the exact release:

```bash
git rev-parse HEAD
git status --short
```

Deploy the committed production assets. Node is not required on the server because `public/build.zip` is part of the release:

```bash
mkdir -p public/build
unzip -oq public/build.zip -d public/build
```

Make Laravel's writable paths available to the web-server user without using world-writable permissions:

```bash
chmod -R ug+rwX storage bootstrap/cache
$PHP artisan storage:link
```

If `storage:link` reports that the link already exists, verify that `public/storage` points to `storage/app/public` and continue.

## 5. Initialize the database

Apply the migrations (this is the whole of Path A):

```bash
$PHP artisan migrate --force
$PHP artisan migrate:status
```

`migrate:status` must list every migration as "Ran". On an empty database (Path B)
continue with the bootstrap seeder in 5a; on a database that is already set up, run a
seeder only when the release section says so.

After the production administrator has been created, remove `SEERA_ADMIN_PASSWORD` from `.env`. Keep the other bootstrap values only if useful for documentation. The production seeder deliberately refuses to run without a 16-character bootstrap password; add the value back temporarily if the bootstrap ever has to be re-run.

### 5a. Organization chart login accounts

`ProductionSeeder` creates only the bootstrap administrator. To create the twelve
staff accounts from the company organization chart, run:

```bash
$PHP artisan db:seed --class=OrganizationHierarchySeeder --force
```

Or run everything with one command (it refuses to start while the login domain
is still the `seera.local` placeholder, and prints the resulting account list):

```bash
$PHP artisan db:seed --class=ProductionBootstrapSeeder --force
```

This also runs `ProductionChartOfAccountsSeeder`: the standard chart of accounts
(Cash, Bank, Receivables, Payables 2100, VAT, Revenue, Expenses) with zero
balances, the automatic posting rules, and an open VAT period for the current
quarter. Without it no bill or invoice can post. It never overwrites an account
that already exists, so it can be re-run at any time, also on its own:

```bash
$PHP artisan db:seed --class=ProductionChartOfAccountsSeeder --force
```

Set the login domain first, otherwise the accounts are created on the
`seera.local` placeholder:

```dotenv
SEERA_ORG_EMAIL_DOMAIN=your-company-domain.com
```

The seeder also creates the departments, roles, designations and role permissions
those accounts depend on, so it is safe to run against a database that has only
been through `ProductionSeeder`.

Every account is created with the shared default password `123456` and the
`must_change_password` flag set. On first sign-in the panel is locked to the
"Set Your Password" screen until the holder chooses their own; no other admin
route is reachable until then.

The seeder is idempotent and re-running it will **not** reset a password someone
has already changed. It also refuses to touch an account whose username is
already taken by somebody else, reporting a skip instead.

Because `123456` is a weak shared secret, treat the window between seeding and
first sign-in as sensitive: seed the accounts only when the staff are ready to
log in, and confirm afterwards that no account still shows `must_change_password`.

## 6. Cache and release

```bash
$PHP artisan optimize:clear
$PHP artisan optimize
$PHP artisan about
$PHP artisan route:list
$PHP artisan up
```

The deployment is not complete unless every command exits successfully.

## 7. Browser acceptance checks

1. Open the HTTPS login page and confirm no debug output appears.
2. Log in with `SEERA_ADMIN_EMAIL` and the one-time bootstrap password.
3. Immediately change the administrator password.
4. Complete Company Profile, then create real branches, departments, designations, projects, sites, and warehouses.
5. Create real users and assign the smallest required roles and project/site/warehouse scope.
6. Confirm a pending or inactive account cannot log in.
7. Confirm an Operator cannot open Users, Permission Matrix, Accounts Payable, Payroll, or Stock Adjustments.
8. Create one balanced test journal and verify Trial Balance.
9. Perform one controlled stock receipt and issue and verify Stock On Hand and Stock Ledger.
10. Confirm password-reset email reaches the configured mailbox.

Delete or reverse the controlled accounting and stock acceptance records according to the organization's audit policy; do not delete posted financial history directly.

## 8. Rollback

If deployment fails before users resume work:

1. Keep maintenance mode enabled.
2. Restore the database from `seera-before-release.sql`.
3. Restore `storage/app` from its archive.
4. Return the checkout to the previously recorded release commit using the hosting provider's approved deployment method.
5. Run `$COMPOSER install --no-dev --prefer-dist --optimize-autoloader --no-interaction` and `$PHP artisan optimize`.
6. Run `$PHP artisan up` only after the old application and restored database agree.

Do not attempt a code-only rollback after `migrate:fresh`; restoring the matching database and storage backup is mandatory.

## 9. September 2026 client change round

The release implementing the client's 5 September 2026 feedback (see
`docs/client-change-register-2026-09-07-status.md`) is additive: two migrations
(`2026_09_07_*`) add purchase-order line fields, a `purchase_order_attachments`
table and `users.employee_classification`. Deploy with Path A:

```bash
$PHP artisan migrate --force
```

No seeder is required. Supplier quotation files are stored under
`storage/app/private/purchase-orders/quotations`; keep `storage/app` in the backup set.

The 9 September follow-up (CR-15 to CR-18) adds one more additive migration
(`2026_09_09_000001_*`) that creates the project-classification and payment-term
lists, inserts the four existing payment-term choices and links suppliers to the
Accounts Payable account. Again `$PHP artisan migrate --force` is enough. Site maps
load Leaflet from cdnjs and tiles from openstreetmap.org in the user's browser; the
server itself needs no outbound access or API key.

## 10. September 2026 media batch (NR-01 to NR-34)

The 14 September batch (see `docs/client-change-register-2026-09-16-status.md`)
adds two additive migrations (`2026_09_18_000001_*` supplier/customer/employee/leave
fields and lookup values; `2026_09_18_000002_*` the marketing tables) and two
idempotent seeders that an existing installation needs once:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
COMPOSER="$PHP /opt/cpanel/composer/bin/composer"

$PHP artisan down
git pull --ff-only
$COMPOSER install --no-dev --optimize-autoloader
$PHP artisan migrate --force
$PHP artisan db:seed --class=ProductionHrDefaultsSeeder --force   # leave types: Annual, Sick, Urgent, Unpaid
$PHP artisan db:seed --class=MarketingModuleSeeder --force        # Marketing permissions for Super Admin and Marketing Manager
$PHP artisan optimize
$PHP artisan up
```

Both seeders are also part of `ProductionBootstrapSeeder`, so a database that was
bootstrapped after this release needs nothing extra. If the database is empty
(for example after an accidental wipe), skip the two seeders above and run
`$PHP artisan db:seed --class=ProductionBootstrapSeeder --force` instead, with
`SEERA_ADMIN_PASSWORD` and `SEERA_ORG_EMAIL_DOMAIN` present in `.env`. No asset rebuild is required for this round. Leave attachments
are stored under `storage/app/private/leave-attachments`; keep `storage/app` in the
backup set. Optional `.env` keys: `SEERA_EMPLOYEE_CODE_SPONSORSHIP` (default `SP-`),
`SEERA_EMPLOYEE_CODE_FREELANCER` (default `FL-`) and
`SEERA_FINANCIAL_YEAR_START_MONTH` (default `1`) for the report quick ranges.

## 11. September 2026 client feedback (FR-01 to FR-06)

The 21 September feedback (see `docs/client-change-register-2026-09-21-status.md`)
adds one additive migration, `2026_09_21_000001_add_document_type_lookup_values`,
which seeds the six standard employee document types into `lookup_values` and
carries over any type already stored. No seeder and no asset rebuild are needed:

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php

$PHP artisan down
git pull --ff-only
$PHP artisan migrate --force
$PHP artisan optimize
$PHP artisan up
```

After deploying, saving an employee creates their first salary structure from the
pay on the employee form. Employees already in the system get theirs the first time
they are edited; there is no automatic backfill, because writing salary records for
every existing employee needs the client's approval.
