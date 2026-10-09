# Deployment — GPS Attendance + Geofence Runtime, Phase 1 (online mobile web), 9 October 2026
Feature branch `feature/seera-connected-workspaces-2026-09-23`. Companion to [production-deployment-guide.md](production-deployment-guide.md).

Brings the server from the Batch D HEAD (`bd3090f`) to the GPS attendance HEAD. This release has
**one additive migration** and **a changed asset bundle**.

| Item | Value |
| --- | --- |
| Migration | `2026_10_09_000001_add_gps_evidence_to_attendance_records` — adds 12 nullable columns to `attendance_records` (check-in / check-out latitude, longitude, accuracy, distance, geofence status, recorded-at). Re-runnable (guards on `hasColumn`); existing rows untouched; verified on MySQL 8 (fresh and additive path) and SQLite. |
| Seeder | None. |
| Asset bundle | `public/build.zip`, 189,897 bytes, 18 files, root `manifest.json` + `fonts-manifest.json` + `assets/`; new `assets/app-CS691W_a.js` (mobile attendance script). SHA-256 `25B77A8DEAC96BDB4BFE5646AA6BE800B30F12FA984CCC93DB01FF2901850A4E`. |
| New route / screen | `GET /admin/hr/attendance/mobile` (HR-ATT-004) plus three JSON POST endpoints under it (locate, check-in, check-out). Gated by Attendance → mobile, Mobile Access and a linked active employee with a site. |
| HTTPS | Required in production: browsers only release device location to secure origins. The site is already served over HTTPS. |

```bash
cd ~/seera
PHP=/opt/cpanel/ea-php83/root/usr/bin/php

$PHP artisan down
git fetch origin
git checkout feature/seera-connected-workspaces-2026-09-23
git pull --ff-only
$PHP artisan migrate --force          # expects exactly: 2026_10_09_000001_add_gps_evidence_to_attendance_records
unzip -t public/build.zip | tail -1   # "No errors detected"
sha256sum public/build.zip            # 25B77A8D…850A4E
rm -rf public/build && mkdir -p public/build
unzip -oq public/build.zip -d public/build
test -f public/build/manifest.json && echo "assets ok"
test -f docs/user-guide/index.html && echo "user guide ok"
$PHP artisan optimize
$PHP artisan up
```

Post-release checks (read-only unless stated):

1. Sign in as a user with Mobile Access and Attendance → mobile whose login is linked to an
   active employee with a site. Open Attendance → **Mobile Attendance** on a phone browser: the
   page shows employee, project, site, server time and *Not checked in yet*.
2. Tap **Check in** and allow location: the page shows site, allowed radius, accuracy, distance
   and Inside / Outside. (Confirming records real attendance: do this only with a training
   employee or during the agreed acceptance window.)
3. As HR, open Attendance: the row shows *GPS / Mobile* with the geofence state and distance;
   Edit shows the Location evidence block read-only.
4. A user without Mobile Access gets the explanation on the page and 422 / 403 on the endpoints.
5. `https://seera.tech-brit.co.uk/user-guide/` shows version 1.10; WF-020 and HR-ATT-004 are present.

Site configuration before employees use it: each site needs coordinates, a radius and the two
flags (*Geofence enabled*, *Attendance inside only*) reviewed on MST-SITE-004. A site without
coordinates blocks inside-only check-ins by design.

Rollback: `git checkout bd3090f`, extract that commit's `public/build.zip` the same way, then
`$PHP artisan optimize`. The migration can stay (nullable columns are harmless) or be reversed
with `php artisan migrate:rollback --step=1` only if no GPS rows have been recorded yet.
