# Phase 1 + Phase 2 QA Checklist

Seera Construction ERP — admin foundation stabilization (Phase 1: Users & Roles, Phase 2: Dashboard & Master Setup).

Default admin login: `admin@example.com` / `password`
Reset demo data: `php artisan migrate:fresh --seed`

## Authentication

- [x] Login with valid credentials redirects to `/admin/dashboard`
- [x] Login with invalid credentials shows an error and stays guest
- [x] Failed logins are recorded in activity logs
- [x] Logout ends the session and returns to `/login`
- [x] Guests hitting `/admin/*` are redirected to `/login`
- [x] Forgot password creates a reset token and sends the email (log driver writes to `storage/logs/laravel.log`)
- [x] Reset password with a valid token updates the password and allows login
- [x] Reset password with an invalid token shows an error

## Phase 1 screens

- [x] Dashboard renders metric cards, projects table, recent activity
- [x] Users listing: search, department/role/status filters, pagination
- [x] Add User / Edit User forms validate and persist (role, scope, employment info)
- [x] User Details shows profile, access summary, recent activity
- [x] Deactivate user keeps the record (no hard delete)
- [x] Roles listing: search, filters, system-role delete protection
- [x] Create Role / Edit Role persists info, scope, and permission matrix
- [x] Role Details shows permissions, users, connected workflows
- [x] Permission Matrix loads per role and saves changes
- [x] Role Hierarchy tree renders and role selection shows details
- [x] Assign Users to Role: dual-list assign/remove (single, all), save persists to `user_roles`
- [x] Temporary access fields (temporary flag, start/end dates, reason) persist on assignment
- [x] Approval Workflows: create with multiple steps, reorder by step number, edit, delete
- [x] Seeded "Site Expense Approval" workflow intact (Supervisor → PM → Finance)
- [x] Activity Logs listing with module/action/date filters

## Phase 2 screens

- [x] Company Profile loads seeded data and saves (VAT/ZATCA/fiscal fields)
- [x] Branches: listing / create / edit / details, delete guarded by dependents
- [x] Departments: listing / create / edit / details, delete guarded
- [x] Designations: listing / create / edit / details
- [x] Projects: listing / create / edit / details, delete guarded
- [x] Sites: listing / create / edit / details with geo-fence fields + map placeholder
- [x] Warehouses: listing / create / edit / details (branch / project / site levels)
- [x] Expense Categories: listing / create / edit / details
- [x] Suppliers: listing / create / edit / details
- [x] Customers: listing / create / edit / details with linked projects

## Cross-cutting

- [x] Sidebar active state highlights the current module
- [x] Future modules (HR, Accounting, Inventory, Equipment, ZATCA, Reports, Settings) show Coming Soon pages
- [x] Delete confirmations use the shared modal
- [x] Success flash messages appear after create/update/delete
- [x] Validation errors render on forms
- [x] Tables scroll horizontally on narrow screens; layout collapses below 1000px
- [x] CSS served from the Vite build (`public/build`), no broken asset paths
- [x] `public/design-reference/` untouched and not served as the app
- [x] Every write action (users, roles, masters, workflows, assignments) records an activity log

## Automated tests

- [x] `php artisan test` — full feature suite passing (screens, auth, CRUD, assignment, workflows, password reset)

## Manual review priorities

1. `/admin/roles/assign-users` — dual-list assignment and temporary access save
2. `/admin/roles/approval-workflows` — create/edit workflow with steps
3. `/forgot-password` → reset link in `storage/logs/laravel.log` → `/reset-password`
4. `/admin/roles/permission-matrix` — per-role save
5. `/admin/master/sites` — geo-fence create/edit
