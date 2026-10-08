# Deployment — Wave 2 Batch D (Employee-centred HR UX), 9 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md) and the Batch B / C guides.

Brings the server from the Batch C HEAD (`f7d036d`) to the Batch D HEAD. **No migration, no seeder,
no new route and no asset change**: `public/build.zip` is still the Batch B archive
(188,884 bytes, 18 files, SHA-256
`B73DAD25B9372E90603DB5A0ABCDF2FD77C3D1AE7E56E393879B1601C2666AC0`), so no re-extraction is
needed. The user guide HTML (version 1.9) is served from the same `/user-guide/` route.

What changes on screen: the Employee View is the employee-centred HR context page (header with
contract / IQAMA, attendance this month, leave balance, pending items; sections Overview,
Documents, Attendance, Leaves, Overtime, Salary Structures, Payroll, Activity, each by
permission, each with View register and Add / Edit links that return to the employee); the
Attendance, Leave and Overtime forms use Save / Save & close and accept an employee filter on
their registers; the Edit-workspace panels show shift, late / overtime, project / site, leave
type and payroll period columns.

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

1. Operations → Employees → View on an employee as an HR-only user: Attendance, Overtime,
   Salary Structures and Payroll sections and the basic salary are absent; as a user with
   Payroll view they appear with the stored figures.
2. Employee View → Attendance → Add attendance: the employee is preselected; Save & close
   returns to the employee's Attendance section.
3. Employee View → Leaves → View register: the Leaves list says "Showing records of … only"
   with Back to employee.
4. The IQAMA / contract alerts appear only for employees whose dates are past or within 60 days.
5. `https://seera.tech-brit.co.uk/user-guide/` shows version 1.9.

Rollback: `git checkout f7d036d`, then `$PHP artisan optimize`. No database or asset rollback.
