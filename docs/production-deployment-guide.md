# Seera Production Deployment Guide

This guide applies to the hardened release after commit `8f32d728`. The production server shown in the handoff is still at `8f32d728`; do not begin until the new release has been committed and pushed to `origin/main`.

The current project declares PHP `^8.3` and Laravel `^13.8` in `composer.json`.

## 1. Choose the database path

### Path A - preserve real production data

Use this whenever any production data must survive. Run only:

```bash
php artisan migrate --force
```

Do not run any seeder. The migrations are additive. The employee-document migration moves files referenced by existing employee-document rows from public storage to private storage.

### Path B - erase the current test database and start production clean

Use this only because the current server database has been confirmed to contain test data. This permanently deletes every current table and row:

```bash
php artisan migrate:fresh --force
php artisan db:seed --class=ProductionSeeder --force
```

`ProductionSeeder` creates only:

- the Administration department;
- the complete permission catalog;
- the Super Admin role with all permissions;
- one production administrator supplied through `.env`;
- a minimal company profile.

It does not create demo employees, projects, suppliers, customers, payroll, journals, invoices, stock, or activity history.

Never run `php artisan db:seed` without `--class=ProductionSeeder` in production. The default `DatabaseSeeder` intentionally creates demo users and transactions.

## 2. Pre-deployment checks and backups

From `~/seera`:

```bash
git status --short
git fetch origin
git log --oneline --decorate -5 origin/main
php -v
composer --version
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
php artisan down --retry=60
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
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
php artisan storage:link
```

If `storage:link` reports that the link already exists, verify that `public/storage` points to `storage/app/public` and continue.

## 5. Initialize the database

For the confirmed test-only database, use Path B:

```bash
php artisan migrate:fresh --force
php artisan db:seed --class=ProductionSeeder --force
```

For a database containing any data that must survive, use Path A instead:

```bash
php artisan migrate --force
```

Verify migration state:

```bash
php artisan migrate:status
```

After the production administrator has been created, remove `SEERA_ADMIN_PASSWORD` from `.env`. Keep the other bootstrap values only if useful for documentation. The production seeder deliberately refuses to run without a 16-character bootstrap password.

## 6. Cache and release

```bash
php artisan optimize:clear
php artisan optimize
php artisan about
php artisan route:list
php artisan up
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
5. Run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` and `php artisan optimize`.
6. Run `php artisan up` only after the old application and restored database agree.

Do not attempt a code-only rollback after `migrate:fresh`; restoring the matching database and storage backup is mandatory.
